/**
 * Settings → Backup → Migration: chunked upload of a migration file (works under any
 * upload_max_filesize) and the step-by-step import (View/admin/backup/index.blade.php).
 */
(() => {
    const config = document.getElementById('mig-config');
    if (!config) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const t = (key, ...args) => window.sxt(key, ...args);

    async function post(url, data) {
        const body = data instanceof FormData ? data : Object.entries(data).reduce((f, [k, v]) => (f.append(k, v), f), new FormData());
        const res = await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json().catch(() => ({ error: t('mig_session') }));
        if (!res.ok || json.error) throw new Error(json.error || t('mig_failed'));
        return json;
    }

    // ------------------------------------------------------------------ export: show that it's working
    document.getElementById('mig-export')?.addEventListener('submit', e => {
        const busy = e.target.querySelector('.mig-busy');
        busy.hidden = false;
        setTimeout(() => { busy.hidden = true; }, 15000);
    });

    // ------------------------------------------------------------------ upload (chunks, in order)
    const drop = document.getElementById('mig-drop');
    const input = document.getElementById('mig-file');
    const bar = drop.querySelector('.mig-progress');
    const fill = drop.querySelector('.mig-progress-bar');
    const label = drop.querySelector('.mig-progress-text');

    async function upload(file) {
        if (!/\.zip$/i.test(file.name)) { alert(t('mig_not_zip')); return; }
        const size = Math.max(256 * 1024, +config.dataset.chunk || 1024 * 1024);
        const total = Math.max(1, Math.ceil(file.size / size));
        const id = [...crypto.getRandomValues(new Uint8Array(16))].map(b => b.toString(16).padStart(2, '0')).join('');
        drop.classList.add('is-busy');
        bar.hidden = label.hidden = false;
        try {
            let result = null;
            for (let i = 0; i < total; i++) {
                const form = new FormData();
                form.append('upload_id', id);
                form.append('index', i);
                form.append('total', total);
                form.append('name', file.name);
                form.append('chunk', file.slice(i * size, (i + 1) * size), 'chunk');
                result = await post(config.dataset.uploadUrl, form);
                const pct = Math.round((i + 1) / total * 100);
                fill.style.width = pct + '%';
                label.textContent = t('mig_uploading', pct);
            }
            label.textContent = t('mig_uploaded');
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            label.textContent = err.message;
            drop.classList.remove('is-busy');
        }
    }

    input.addEventListener('change', () => input.files[0] && upload(input.files[0]));
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', e => e.dataTransfer.files[0] && upload(e.dataTransfer.files[0]));

    // ------------------------------------------------------------------ import (modal, step by step)
    const modalEl = document.getElementById('mig-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const m = name => modalEl.querySelector(`[data-m="${name}"]`);
    const steps = modalEl.querySelector('.mig-steps');
    let current = null;
    let running = false;

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-import]');
        if (!btn) return;
        current = btn.dataset;
        m('site').textContent = current.site || '—';
        m('from').textContent = current.from || '';
        m('version').textContent = current.version || '?';
        m('password-row').hidden = current.encrypted !== '1';
        modalEl.querySelector('#mig-import-password').value = '';
        modalEl.querySelector('.mig-setup').hidden = false;
        steps.hidden = true;
        steps.replaceChildren();
        m('error').hidden = true;
        m('start').hidden = false;
        m('start').disabled = false;
        modal.show();
    });

    // Don't let the dialog close in the middle of an import.
    modalEl.addEventListener('hide.bs.modal', e => { if (running) e.preventDefault(); });

    function addStep(key) {
        const li = document.createElement('li');
        li.dataset.step = key;
        li.innerHTML = '<span class="mig-step-icon"></span>';
        li.append(t('mig_step_' + key));
        steps.append(li);
        return li;
    }

    m('start').addEventListener('click', async () => {
        if (!current) return;
        running = true;
        m('start').disabled = true;
        m('error').hidden = true;
        modalEl.querySelector('.mig-setup').hidden = true;
        steps.hidden = false;
        steps.replaceChildren();
        modalEl.querySelectorAll('[data-bs-dismiss]').forEach(b => b.disabled = true);

        const first = addStep('check');
        first.className = 'is-running';
        try {
            const start = await post(config.dataset.importUrl, {
                file: current.import,
                password: modalEl.querySelector('#mig-import-password').value,
                backup: modalEl.querySelector('#mig-backup-first').checked ? '1' : '0',
            });
            first.className = 'is-done';

            const items = start.steps.map(addStep);
            for (let i = 0; i < start.steps.length; i++) {
                items[i].className = 'is-running';
                const res = await post(config.dataset.stepUrl, { job: start.job, step: start.steps[i] });
                items[i].className = 'is-done';
                if (res.redirect) {
                    const done = document.createElement('li');
                    done.className = 'is-done is-final';
                    done.innerHTML = '<span class="mig-step-icon"></span>';
                    done.append(t('mig_done'));
                    steps.append(done);
                    running = false;
                    setTimeout(() => { location.href = res.redirect; }, 1500);
                    return;
                }
            }
        } catch (err) {
            steps.querySelector('.is-running')?.classList.replace('is-running', 'is-failed');
            m('error').textContent = err.message;
            m('error').hidden = false;
            running = false;
            modalEl.querySelectorAll('[data-bs-dismiss]').forEach(b => b.disabled = false);
            m('start').hidden = true;
        }
    });
})();
