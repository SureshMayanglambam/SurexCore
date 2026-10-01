/**
 * Bootstrap tooltips for every [data-bs-toggle="tooltip"] in the admin, including
 * elements drawn later by scripts (field builder). Created on first hover/focus.
 */
(() => {
    const sel = '[data-bs-toggle="tooltip"]';

    ['mouseover', 'focusin'].forEach(type => document.addEventListener(type, e => {
        const el = e.target.closest?.(sel);
        if (el && !bootstrap.Tooltip.getInstance(el)) {
            bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' }).show();
        }
    }));

    // A click may redraw the element under the tooltip (move/delete buttons): don't leave it floating.
    document.addEventListener('click', () => {
        document.querySelectorAll(sel).forEach(el => bootstrap.Tooltip.getInstance(el)?.hide());
        document.querySelectorAll('body > .tooltip').forEach(t => t.remove());
    });
})();
