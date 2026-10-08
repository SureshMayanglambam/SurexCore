const SP_QUERY = '(max-width: 768px)';

export function initSpImg() {
    const $images = $('img.spimg');
    if (!$images.length) return;

    const media = window.matchMedia(SP_QUERY);

    const swap = () => {
        const isSp = media.matches;
        $images.each(function () {
            const src = this.getAttribute('src');
            if (!src) return;

            const next = isSp
                ? src.replace(/_pc(\.[a-z0-9]+)$/i, '_sp$1')
                : src.replace(/_sp(\.[a-z0-9]+)$/i, '_pc$1');

            if (next !== src) this.setAttribute('src', next);
        });
    };

    swap();
    media.addEventListener('change', swap);
}
