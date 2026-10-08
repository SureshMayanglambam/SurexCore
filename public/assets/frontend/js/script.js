/**
 * Site entry point — bundled to assets/js/script.min.js
 */
import { initSpImg } from './module/base.js';

function boot() {
    initSpImg();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
