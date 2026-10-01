/**
 * Entry form behaviour for custom fields: repeater rows and image/file uploads.
 * Markup comes from View/admin/fields/field.blade.php and row.blade.php.
 */
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const uploadUrl = document.querySelector('meta[name="upload-url"]')?.content;

    // ---------------------------------------------------------------- conditional logic
    // Same rules as App\Libraries\Fields on the server: OR-groups of AND-rules on sibling fields.

    function fieldValue(wrapper) {
        if (wrapper.hidden) return null;                        // a hidden field counts as empty
        const q = selector => wrapper.querySelector(selector);
        switch (wrapper.dataset.type) {
            case 'checkbox': return [...wrapper.querySelectorAll('input[type=checkbox]:checked')].map(i => i.value);
            case 'radio':    return q('input[type=radio]:checked')?.value ?? '';
            case 'toggle':   return q('input[type=checkbox]').checked ? '1' : '0';
            case 'select':   return q('select').value;
            case 'image':
            case 'file':     return q('.fld-upload-value').value;
            case 'editor':   return window.wdEditors?.getData(q('textarea')) ?? q('textarea').value;
            default:         return (q('input, textarea')?.value ?? '').trim();
        }
    }

    function compare(operator, value, expected) {
        const empty = value === null || value === '' || (Array.isArray(value) && value.length === 0);
        const numeric = v => v !== null && v !== '' && !Array.isArray(v) && !isNaN(v);
        switch (operator) {
            case 'empty':     return empty;
            case 'not_empty': return !empty;
            case '==':        return Array.isArray(value) ? value.includes(expected) : String(value ?? '') === expected;
            case '!=':        return Array.isArray(value) ? !value.includes(expected) : String(value ?? '') !== expected;
            case 'contains':  return Array.isArray(value) ? value.includes(expected)
                                   : expected !== '' && String(value ?? '').toLowerCase().includes(expected.toLowerCase());
            case '>':         return numeric(value) && numeric(expected) && Number(value) > Number(expected);
            case '<':         return numeric(value) && numeric(expected) && Number(value) < Number(expected);
        }
        return false;
    }

    function rulesMatch(groups, wrapper) {
        return groups.some(group => group.every(rule => {
            const target = [...wrapper.parentElement.children]
                .find(el => el.classList.contains('fld') && el.dataset.key === rule.field);
            return compare(rule.operator, target ? fieldValue(target) : null, rule.value);
        }));
    }

    function evaluate() {
        const conditional = [...document.querySelectorAll('.fld[data-conditions]')];
        // Repeat until stable: a field may depend on a field that is itself conditional.
        for (let pass = 0; pass <= conditional.length; pass++) {
            let changed = false;
            conditional.forEach(w => {
                const show = rulesMatch(JSON.parse(w.dataset.conditions), w);
                if (w.hidden === show) { w.hidden = !show; changed = true; }
            });
            if (!changed) break;
        }
        document.querySelectorAll('.fld[data-required-if]').forEach(w => {
            const mark = w.querySelector('.fld-req-if');
            if (mark) mark.hidden = !rulesMatch(JSON.parse(w.dataset.requiredIf), w);
        });
        // Inputs of hidden fields are not submitted (the server saves them empty).
        document.querySelectorAll('.fld [name]').forEach(el => el.disabled = el.closest('.fld[hidden]') !== null);
    }

    let scheduled = false;
    const scheduleEvaluate = () => {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => { scheduled = false; evaluate(); });   // once per burst of events, before the next paint
    };
    document.addEventListener('input', e => e.target.closest('.fld') && scheduleEvaluate());
    document.addEventListener('change', e => e.target.closest('.fld') && scheduleEvaluate());
    document.addEventListener('fields:changed', scheduleEvaluate);

    // ---------------------------------------------------------------- repeaters

    // Row names use a unique index so rows can be added/removed/reordered freely;
    // the server re-numbers them in order when saving.
    let rowCounter = Date.now();

    function refreshRepeater(repeater) {
        const rows = repeater.querySelector(':scope > .fld-rows').children;
        const max = +repeater.dataset.max;
        [...rows].forEach((row, i) => {
            row.querySelector('.fld-row-number').textContent = i + 1;
            row.querySelector('.fld-row-up').disabled = i === 0;
            row.querySelector('.fld-row-down').disabled = i === rows.length - 1;
        });
        repeater.querySelector(':scope > .fld-row-add').disabled = max > 0 && rows.length >= max;
    }

    function addRow(repeater) {
        const template = repeater.querySelector(':scope > .fld-row-template');
        const token = repeater.dataset.token;
        const html = template.innerHTML.split(token).join(String(rowCounter++));
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const row = holder.firstElementChild;
        repeater.querySelector(':scope > .fld-rows').append(row);
        row.querySelectorAll('.fld-repeater').forEach(ensureMinRows);
        window.wdEditors?.init(row);
        window.wdDates?.init(row);
        refreshRepeater(repeater);
        scheduleEvaluate();
        row.querySelector('input:not([type=hidden]), textarea, select')?.focus();
    }

    // Start with the minimum number of rows.
    function ensureMinRows(repeater) {
        const missing = +repeater.dataset.min - repeater.querySelector(':scope > .fld-rows').children.length;
        for (let i = 0; i < missing; i++) addRow(repeater);
        refreshRepeater(repeater);
    }

    document.querySelectorAll('.fld-repeater').forEach(ensureMinRows);
    evaluate();

    document.addEventListener('click', async e => {
        const button = e.target.closest('.fld-row-add, .fld-row-remove, .fld-row-up, .fld-row-down');
        if (!button) return;

        if (button.classList.contains('fld-row-add')) {
            addRow(button.closest('.fld-repeater'));
            return;
        }

        const row = button.closest('.fld-row');
        const repeater = row.closest('.fld-repeater');

        if (button.classList.contains('fld-row-remove')) {
            const min = +repeater.dataset.min;
            if (min > 0 && row.parentElement.children.length <= min) {
                alert(`最低${min}行が必要です。`);
                return;
            }
            if (!confirm('この行を削除しますか？')) return;
            await window.wdEditors?.destroy(row);
            row.remove();
        } else if (button.classList.contains('fld-row-up') && row.previousElementSibling) {
            // Editors are re-created after a DOM move so they keep working.
            await window.wdEditors?.destroy(row);
            row.previousElementSibling.before(row);
            reinitEditors(row);
        } else if (button.classList.contains('fld-row-down') && row.nextElementSibling) {
            await window.wdEditors?.destroy(row);
            row.nextElementSibling.after(row);
            reinitEditors(row);
        }
        refreshRepeater(repeater);
        scheduleEvaluate();
    });

    function reinitEditors(row) {
        row.querySelectorAll('textarea[data-editor-ready]').forEach(t => {
            delete t.dataset.editorReady;
            t.nextElementSibling?.classList.contains('form-text') && t.nextElementSibling.remove();
        });
        window.wdEditors?.init(row);
    }

    // ---------------------------------------------------------------- uploads

    document.addEventListener('change', async e => {
        const fileInput = e.target.closest('.fld-upload-input');
        if (!fileInput || !fileInput.files.length) return;

        const box = fileInput.closest('.fld-upload');
        const status = box.querySelector('.fld-upload-status');
        const body = new FormData();
        body.append('upload', fileInput.files[0]);

        status.textContent = 'アップロード中…';
        status.classList.remove('text-danger');

        try {
            const response = await fetch(`${uploadUrl}?kind=${box.dataset.kind}`, {
                method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => ({ error: { message: 'セッションの有効期限が切れた可能性があります。ページを再読み込みして、もう一度ログインしてください。' } }));
            if (!response.ok || data.error) throw new Error(data.error?.message || 'アップロードに失敗しました。');

            setUpload(box, data);
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
            status.classList.add('text-danger');
        } finally {
            fileInput.value = '';
        }
    });

    /** Show a chosen/uploaded file in its field: data = { path, url, name } */
    function setUpload(box, data) {
        box.querySelector('.fld-upload-value').value = data.path;
        const preview = box.querySelector('.fld-upload-preview');
        if (box.dataset.kind === 'image') {
            preview.src = data.url;
        } else {
            preview.href = data.url;
            preview.lastChild.textContent = ' ' + data.name;
        }
        preview.hidden = false;
        box.querySelector('.fld-upload-remove').hidden = false;
        scheduleEvaluate();
    }

    // "メディアから選択": reuse a file from the media library (media.js)
    document.addEventListener('click', e => {
        const pick = e.target.closest('.fld-media-pick');
        if (!pick || !window.wdMediaPicker) return;
        const box = pick.closest('.fld-upload');
        window.wdMediaPicker.open(box.dataset.kind, item => setUpload(box, item));
    });

    document.addEventListener('click', e => {
        const remove = e.target.closest('.fld-upload-remove');
        if (!remove) return;
        const box = remove.closest('.fld-upload');
        box.querySelector('.fld-upload-value').value = '';
        box.querySelector('.fld-upload-preview').hidden = true;
        remove.hidden = true;
        scheduleEvaluate();
    });
})();
