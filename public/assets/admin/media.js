/**
 * Media library (View/admin/media/index.blade.php) and the "メディアから選択" picker
 * used by image/file fields in entry forms: window.wdMediaPicker.open(kind, item => ...).
 */
(() => {
    const meta = name => document.querySelector(`meta[name="${name}"]`)?.content;
    const csrf = meta('csrf-token');

    const formatSize = bytes => bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB'
        : Math.max(1, Math.round(bytes / 1024)) + ' KB';

    const escapeHtml = text => String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    async function upload(file, kind) {
        const body = new FormData();
        body.append('upload', file);
        const response = await fetch(`${meta('upload-url')}?kind=${kind}`, {
            method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json().catch(() => ({ error: { message: 'セッションの有効期限が切れた可能性があります。ページを再読み込みしてください。' } }));
        if (!response.ok || data.error) throw new Error(data.error?.message || 'アップロードに失敗しました。');
        return data;
    }

    // ---------------------------------------------------------------- library page

    const drop = document.getElementById('media-drop');
    if (drop) {
        const input = document.getElementById('media-upload-input');
        const status = drop.querySelector('.media-drop-status');

        const uploadAll = async files => {
            const errors = [];
            let done = 0;
            for (const file of files) {
                status.textContent = `アップロード中… ${done + 1} / ${files.length}`;
                try { await upload(file, 'any'); done++; } catch (e) { errors.push(`${file.name}：${e.message}`); }
            }
            if (errors.length) {
                status.textContent = '';
                alert(errors.join('\n'));
            }
            if (done) location.href = location.pathname;   // newest first, back to page 1
        };

        input.addEventListener('change', () => input.files.length && uploadAll([...input.files]));
        ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
        drop.addEventListener('drop', e => e.dataTransfer.files.length && uploadAll([...e.dataTransfer.files]));

        // Details modal
        const modalEl = document.getElementById('media-modal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const m = name => modalEl.querySelector(`[data-m="${name}"]`);
        let usages = [];

        document.addEventListener('click', async e => {
            const tile = e.target.closest('.media-tile');
            if (!tile) return;
            const item = await (await fetch(meta('media-show-url').replace(/0$/, tile.dataset.id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).json();
            usages = item.usages;

            m('name').textContent = item.name;
            m('preview').innerHTML = item.is_image
                ? `<img src="${escapeHtml(item.url)}" alt="">`
                : `<div class="media-preview-file"><i class="bi bi-file-earmark-text"></i><span>${escapeHtml(item.path.split('.').pop().toUpperCase())}</span></div>`;
            m('mime').textContent = item.mime;
            m('size').textContent = formatSize(item.size) + (item.width ? `（${item.width} × ${item.height}px）` : '');
            m('date').textContent = item.date;
            m('usages').innerHTML = usages.length
                ? usages.map(u => `<span class="badge text-bg-warning me-1">${escapeHtml(u.label)}（${u.count}件）</span>`).join('')
                : '<span class="text-secondary">使用されていません</span>';
            m('url').value = item.url;
            m('open').href = item.url;
            m('delete').action = meta('media-show-url').replace(/0$/, item.id);
            modal.show();
        });

        m('delete').addEventListener('submit', e => {
            const where = usages.map(u => `・${u.label}（${u.count}件）`).join('\n');
            const message = usages.length
                ? `このファイルは次の場所で使用されています：\n${where}\n\n削除すると、そのページで画像・リンクが表示されなくなります。削除しますか？`
                : 'このファイルを削除しますか？この操作は元に戻せません。';
            if (!confirm(message)) e.preventDefault();
        });

        modalEl.querySelector('[data-copy]').addEventListener('click', async e => {
            await navigator.clipboard.writeText(m('url').value).catch(() => m('url').select());
            e.currentTarget.innerHTML = '<i class="bi bi-check-lg"></i> コピーしました';
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.querySelector('[data-copy]').innerHTML = '<i class="bi bi-clipboard"></i> コピー';
        });
    }

    // ---------------------------------------------------------------- picker (entry forms)

    let picker = null;

    function buildPicker() {
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header gap-3">
                        <h5 class="modal-title text-nowrap">メディアから選択</h5>
                        <input type="search" class="form-control form-control-sm" placeholder="ファイル名で検索" style="max-width: 260px">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <div class="media-grid"></div>
                        <p class="media-picker-empty text-center text-secondary py-5 mb-0" hidden>ファイルがありません。</p>
                        <div class="text-center mt-3"><button type="button" class="btn btn-sm btn-outline-secondary media-picker-more" hidden>さらに読み込む</button></div>
                    </div>
                </div>
            </div>`;
        document.body.append(el);

        const state = { kind: 'image', page: 1, q: '', onSelect: null, timer: null };
        const grid = el.querySelector('.media-grid');
        const more = el.querySelector('.media-picker-more');
        const empty = el.querySelector('.media-picker-empty');
        const search = el.querySelector('input[type=search]');

        async function load(reset) {
            if (reset) { state.page = 1; grid.replaceChildren(); }
            const params = new URLSearchParams({ kind: state.kind, q: state.q, page: state.page });
            const data = await (await fetch(`${meta('media-list-url')}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).json();

            data.items.forEach(item => {
                const tile = document.createElement('button');
                tile.type = 'button';
                tile.className = 'media-tile';
                tile.title = item.name;
                tile.innerHTML = `<span class="media-thumb">${item.is_image
                    ? `<img src="${escapeHtml(item.url)}" alt="" loading="lazy">`
                    : `<i class="bi bi-file-earmark-text"></i><span class="media-ext">${escapeHtml(item.path.split('.').pop().toUpperCase())}</span>`}</span>
                    <span class="media-name">${escapeHtml(item.name)}</span>`;
                tile.addEventListener('click', () => { state.onSelect?.(item); modal.hide(); });
                grid.append(tile);
            });
            more.hidden = !data.more;
            empty.hidden = grid.children.length > 0;
        }

        more.addEventListener('click', () => { state.page++; load(false); });
        search.addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => { state.q = search.value.trim(); load(true); }, 300);
        });

        const modal = bootstrap.Modal.getOrCreateInstance(el);
        return {
            open(kind, onSelect) {
                state.kind = kind === 'image' ? 'image' : 'file';
                state.onSelect = onSelect;
                state.q = '';
                search.value = '';
                load(true);
                modal.show();
            },
        };
    }

    window.wdMediaPicker = {
        open(kind, onSelect) {
            picker ??= buildPicker();
            picker.open(kind, onSelect);
        },
    };
})();
