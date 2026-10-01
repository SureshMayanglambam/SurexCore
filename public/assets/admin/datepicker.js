/**
 * Japanese calendar for every <input type="date"> and <input type="datetime-local"> in the admin
 * (flatpickr, self-hosted in assets/vendor/flatpickr). The submitted value keeps the same format
 * (Y-m-d or Y-m-d\TH:i); people see 2026年9月28日 (10:30).
 * Inputs added later (e.g. new repeater rows): window.wdDates.init(element).
 */
window.wdDates = (() => {
    function init(root = document) {
        if (!window.flatpickr) return;

        root.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(input => {
            if (input._flatpickr) return;

            const withTime = input.type === 'datetime-local';
            const fp = flatpickr(input, {
                locale: 'ja',
                dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
                altInput: true,
                altFormat: withTime ? 'Y年n月j日 H:i' : 'Y年n月j日',
                enableTime: withTime,
                time_24hr: true,
                disableMobile: true,
                // Other scripts (conditional logic) listen for normal change events.
                onChange: () => input.dispatchEvent(new Event('change', { bubbles: true })),
            });

            fp.altInput.placeholder = input.placeholder || (withTime ? '日時を選択' : '日付を選択');

            // × button to empty the field
            const wrap = document.createElement('span');
            wrap.className = 'wd-date';
            fp.altInput.before(wrap);
            wrap.append(fp.altInput);
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'wd-date-clear';
            clear.title = 'クリア';
            clear.innerHTML = '<i class="bi bi-x-lg"></i>';
            clear.addEventListener('click', () => { fp.clear(); input.dispatchEvent(new Event('change', { bubbles: true })); });
            wrap.append(clear);
        });
    }

    document.addEventListener('DOMContentLoaded', () => init());

    return { init };
})();
