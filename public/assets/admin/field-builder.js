/**
 * Field builder for Settings → Content Types (ACF-style).
 *
 * <div id="field-builder" data-fields='[...]' data-types='{...}'></div>
 * <input type="hidden" name="fields_json">   (filled on submit)
 * <select name="title_field">                 (kept in sync with top-level text fields)
 *
 * The server re-validates everything (App\Libraries\Fields::sanitizeDefinitions).
 */
(() => {
    const root = document.getElementById('field-builder');
    if (!root) return;

    const TYPES = JSON.parse(root.dataset.types);          // type => [label, category]
    const CONTAINERS = ['group', 'repeater'];
    const CHOICES = ['select', 'radio', 'checkbox'];
    const MAX_DEPTH = 3;
    const OPERATORS = JSON.parse(root.dataset.operators);  // operator => label
    // Built-in columns of the type table / of repeater row tables: field names can't use them.
    const RESERVED = JSON.parse(root.dataset.reserved);    // { entry: [...], row: [...] }
    const CONTENT_TYPES = JSON.parse(root.dataset.contentTypes || '{}');   // slug => name (for relation fields)

    const form = root.closest('form');
    const output = form.querySelector('[name="fields_json"]');
    const titleSelect = form.querySelector('[name="title_field"]');

    // ---------------------------------------------------------------- state

    const prepare = field => {
        field.key ??= newKey();
        field._open = false;
        field._autoName = !field.name;
        if (Array.isArray(field.choices)) {
            field.choices = field.choices.map(c => c.value === c.label ? c.value : `${c.value} : ${c.label}`).join('\n');
        }
        field.conditions ??= [];
        field.required_if ??= [];
        field.show_in_list ??= false;
        field.related_type ??= '';
        field.multiple ??= false;
        (field.sub_fields ??= []).forEach(prepare);
        return field;
    };

    const state = JSON.parse(root.dataset.fields || '[]').map(prepare);

    function newKey() {
        return 'f_' + Math.random().toString(36).slice(2, 12).padEnd(10, '0');
    }

    function newField() {
        return { key: newKey(), label: '', name: '', type: 'text', instructions: '', required: false,
                 conditions: [], required_if: [], sub_fields: [], _open: true, _autoName: true };
    }

    const slugify = text => text.toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1').slice(0, 64);

    // ---------------------------------------------------------------- DOM helpers

    function h(tag, attrs = {}, ...children) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === null || v === undefined || v === false) continue;
            if (k === 'class') el.className = v;
            else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
            else if (k === 'value') el.value = v;
            else if (k === 'checked') el.checked = !!v;
            else el.setAttribute(k, v === true ? '' : v);
        }
        children.flat().forEach(c => c != null && el.append(c instanceof Node ? c : document.createTextNode(c)));
        return el;
    }

    const icon = name => h('i', { class: 'bi bi-' + name });

    /** "?" icon whose explanation shows on hover (tooltips.js) */
    const tip = text => h('i', { class: 'bi bi-question-circle tip', tabindex: 0, 'data-bs-toggle': 'tooltip', title: text });

    function input(field, prop, { after, ...attrs } = {}) {
        return h('input', {
            class: 'form-control', value: field[prop] ?? '', ...attrs,
            oninput: e => { field[prop] = e.target.value; after?.(); }
        });
    }

    function labelled(text, control, help) {
        return h('div', {},
            h('label', { class: 'form-label' }, text, help ? ' ' : null, help ? tip(help) : null),
            control);
    }

    /** On/off switch with a short label and the explanation in a tooltip */
    function toggle(id, text, help, checked, onchange) {
        return h('div', { class: 'form-check form-switch mb-0' },
            h('input', { type: 'checkbox', class: 'form-check-input', id, checked, onchange }),
            h('label', { class: 'form-check-label', for: id }, text),
            help ? ' ' : null, help ? tip(help) : null);
    }

    const section = (...children) => h('div', { class: 'fb-section' }, ...children);

    // ---------------------------------------------------------------- rendering

    function renderList(list, depth, reserved = RESERVED.entry) {
        const wrap = h('div', { class: 'fb-list' });

        const draw = () => {
            wrap.replaceChildren(...[
                ...list.map((field, i) => renderField(field, list, i, depth, draw, reserved)),
                list.length === 0 ? h('div', { class: 'fb-empty text-secondary border rounded mb-3 text-center' },
                    icon('inboxes'), depth === 1 ? ' ' + window.sxt('no_fields_1') : ' ' + window.sxt('no_fields_2')) : null,
                h('button', {
                    type: 'button', class: 'btn btn-sm ' + (depth === 1 ? 'btn-primary' : 'btn-outline-primary'),
                    onclick: () => { list.push(newField()); draw(); changed(); }
                }, icon('plus-lg'), depth === 1 ? ' ' + window.sxt('add_field') : ' ' + window.sxt('add_subfield'))
            ].filter(Boolean));
        };

        draw();
        return wrap;
    }

    function renderField(field, list, index, depth, redrawList, reserved) {
        const typeLabel = () => (TYPES[field.type] || [field.type])[0];
        const head = {
            label: h('span', { class: 'fb-label' }, field.label || window.sxt('no_label')),
            name: h('code', { class: 'fb-name ms-2 small' }, field.name),
            type: h('span', { class: 'badge fb-type ms-2' }, typeLabel()),
            required: h('span', { class: 'text-danger ms-1' }),
            conditional: h('span', { class: 'badge text-bg-warning ms-2', 'data-bs-toggle': 'tooltip', title: window.sxt('cond_show') }, icon('eye')),
            listed: h('span', { class: 'badge text-bg-info ms-2', 'data-bs-toggle': 'tooltip', title: window.sxt('in_list') }, icon('table')),
        };
        const refreshHead = () => {
            head.label.textContent = field.label || window.sxt('no_label');
            head.name.textContent = field.name;
            head.type.textContent = typeLabel();
            head.required.textContent = field.required ? '*' : (field.required_if.length ? '*?' : '');
            head.required.title = field.required_if.length ? window.sxt('req_when_cond') : '';
            head.conditional.hidden = !field.conditions.length;
            head.listed.hidden = !(field.show_in_list && depth === 1 && !CONTAINERS.includes(field.type));
        };
        refreshHead();

        const body = h('div', { class: 'card-body fb-body', hidden: !field._open });
        const chevron = icon(field._open ? 'chevron-up' : 'chevron-down');
        const openClose = () => { field._open = !field._open; body.hidden = !field._open; chevron.className = 'bi bi-chevron-' + (field._open ? 'up' : 'down'); };
        const drawBody = () => body.replaceChildren(...renderSettings(field, list, depth, refreshHead, drawBody, reserved));
        drawBody();

        const move = delta => {
            const j = index + delta;
            if (j < 0 || j >= list.length) return;
            [list[index], list[j]] = [list[j], list[index]];
            redrawList(); changed();
        };

        return h('div', { class: 'card fb-field mb-2' + (depth > 1 ? ' shadow-none border' : '') },
            h('div', { class: 'card-header d-flex align-items-center gap-3' },
                h('span', { class: 'fb-num' }, index + 1),
                h('button', {
                    type: 'button', class: 'btn btn-link p-0 text-start text-decoration-none text-body flex-grow-1',
                    onclick: openClose
                }, head.label, head.required, head.name, head.type, head.conditional, head.listed),
                h('div', { class: 'fb-tools d-flex gap-1' },
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-bs-toggle': 'tooltip', title: window.sxt('move_up'),
                    disabled: index === 0, onclick: () => move(-1) }, icon('arrow-up')),
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-bs-toggle': 'tooltip', title: window.sxt('move_down'),
                    disabled: index === list.length - 1, onclick: () => move(1) }, icon('arrow-down')),
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', 'data-bs-toggle': 'tooltip', title: window.sxt('delete'),
                    onclick: () => {
                        if (!confirm(window.sxt('del_field_confirm', field.label || field.name || window.sxt('untitled')))) return;
                        list.splice(index, 1);
                        list.forEach(f => ['conditions', 'required_if'].forEach(p => {
                            f[p] = f[p].map(g => g.filter(r => r.field !== field.key)).filter(g => g.length);
                        }));
                        redrawList(); changed();
                    } }, icon('trash')),
                h('button', { type: 'button', class: 'btn btn-sm btn-link text-secondary', 'data-bs-toggle': 'tooltip', title: window.sxt('toggle_open'),
                    onclick: openClose }, chevron))
            ),
            body
        );
    }

    function renderSettings(field, list, depth, refreshHead, redrawBody, reserved) {
        // Fields a rule may look at: the others at this level, except groups/repeaters.
        const siblings = () => list.filter(f => f !== field && !CONTAINERS.includes(f.type));

        const typeSelect = h('select', {
            class: 'form-select',
            onchange: e => { field.type = e.target.value; refreshHead(); redrawBody(); changed(); }
        });
        const groups = {};
        for (const [type, [label, category]] of Object.entries(TYPES)) {
            if (CONTAINERS.includes(type) && depth >= MAX_DEPTH) continue;
            groups[category] ??= h('optgroup', { label: category });
            groups[category].append(h('option', { value: type, selected: field.type === type }, label));
        }
        typeSelect.append(...Object.values(groups));

        const nameWarning = h('div', { class: 'form-text text-danger' });
        const checkName = () => {
            nameWarning.textContent = reserved.includes(field.name)
                ? window.sxt('name_reserved', field.name)
                : '';
        };
        const nameInput = input(field, 'name', {
            class: 'form-control font-monospace', placeholder: window.sxt('name_ph'), pattern: '[a-z][a-z0-9_]*',
            after: () => { field._autoName = false; checkName(); refreshHead(); changed(); }
        });
        checkName();

        const rows = [
            h('div', { class: 'row g-3' },
                h('div', { class: 'col-md-4' }, labelled(window.sxt('label'), input(field, 'label', {
                    placeholder: window.sxt('label_ph'),
                    after: () => {
                        if (field._autoName) { field.name = slugify(field.label); nameInput.value = field.name; checkName(); }
                        refreshHead(); changed();
                    }
                }))),
                h('div', { class: 'col-md-4' }, labelled(window.sxt('field_name'), h('div', {}, nameInput, nameWarning), window.sxt('field_name_help'))),
                h('div', { class: 'col-md-4' }, labelled(window.sxt('field_type'), typeSelect))
            ),
            h('div', { class: 'row g-3 mt-0' },
                h('div', { class: 'col-md-8' }, labelled(window.sxt('instructions'), input(field, 'instructions'), window.sxt('instructions_help'))),
                h('div', { class: 'col-md-4' }, labelled(window.sxt('required'), h('select', {
                    class: 'form-select',
                    onchange: e => {
                        field.required = e.target.value === 'yes';
                        field.required_if = e.target.value === 'if' ? [[newRule()]] : [];
                        refreshHead(); redrawBody();
                    }
                },
                    h('option', { value: 'no', selected: !field.required && !field.required_if.length }, window.sxt('req_no')),
                    h('option', { value: 'yes', selected: field.required }, window.sxt('req_yes')),
                    h('option', { value: 'if', selected: !field.required && field.required_if.length > 0 }, window.sxt('req_if')))))
            ),
        ];

        if (field.required_if.length) {
            rows.push(renderRules(field, 'required_if', siblings, redrawBody, refreshHead, window.sxt('req_cond_title')));
        }

        const switches = h('div', { class: 'fb-switches' },
            // Top-level fields can be columns in the entry list.
            depth === 1 && !CONTAINERS.includes(field.type)
                ? toggle(field.key + '_list', window.sxt('in_list'), window.sxt('in_list_help'), field.show_in_list,
                    e => { field.show_in_list = e.target.checked; refreshHead(); })
                : null,
            toggle(field.key + '_cond', window.sxt('cond_show'), window.sxt('cond_show_help'), field.conditions.length > 0,
                e => { field.conditions = e.target.checked ? [[newRule()]] : []; refreshHead(); redrawBody(); }));

        const main = [h('div', {}, ...rows)];
        main.push(section(switches,
            field.conditions.length ? renderRules(field, 'conditions', siblings, redrawBody, refreshHead, window.sxt('show_cond_title')) : null));

        const options = [];

        const t = field.type;
        const col = (w, ...c) => h('div', { class: 'col-md-' + w }, ...c);

        if (['text', 'email', 'url', 'textarea', 'number'].includes(t)) {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(t === 'textarea' ? 4 : 6, labelled(window.sxt('placeholder'), input(field, 'placeholder'))),
                col(t === 'textarea' ? 6 : 6, labelled(window.sxt('default'), input(field, 'default', t === 'number' ? { type: 'number', step: 'any' } : {}))),
                t === 'textarea' ? col(2, labelled(window.sxt('rows'), input(field, 'rows', { type: 'number', min: 2, max: 30, value: field.rows ?? 4 }))) : null
            ));
        }
        if (t === 'number') {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(4, labelled(window.sxt('min'), input(field, 'min', { type: 'number', step: 'any' }))),
                col(4, labelled(window.sxt('max'), input(field, 'max', { type: 'number', step: 'any' }))),
                col(4, labelled(window.sxt('step'), input(field, 'step', { type: 'number', step: 'any', placeholder: '1' })))
            ));
        }
        if (CHOICES.includes(t)) {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(8, labelled(window.sxt('choices'), h('textarea', {
                    class: 'form-control font-monospace', rows: 4, placeholder: window.sxt('choices_ph'),
                    oninput: e => field.choices = e.target.value
                }, typeof field.choices === 'string' ? field.choices : ''), window.sxt('choices_help'))),
                col(4, labelled(window.sxt('default'), input(field, 'default', { placeholder: window.sxt('choice_default_ph') })))
            ));
        }
        if (t === 'toggle') {
            options.push(h('div', { class: 'form-check form-switch' },
                h('input', { type: 'checkbox', class: 'form-check-input', id: field.key + '_def', checked: !!field.default,
                    onchange: e => field.default = e.target.checked }),
                h('label', { class: 'form-check-label', for: field.key + '_def' }, window.sxt('toggle_on_default'))));
        }
        if (t === 'relation') {
            const typeSelect = h('select', { class: 'form-select', onchange: e => { field.related_type = e.target.value; } },
                h('option', { value: '' }, window.sxt('rel_select')),
                ...Object.entries(CONTENT_TYPES).map(([slug, name]) =>
                    h('option', { value: slug, selected: field.related_type === slug }, `${name}（${slug}）`)));
            options.push(h('div', { class: 'row g-3 align-items-end' + (options.length ? ' mt-0' : '') },
                col(6, labelled(window.sxt('rel_type'), typeSelect, window.sxt('rel_type_help'))),
                col(6, h('div', { class: 'pb-2' }, toggle(field.key + '_multiple', window.sxt('rel_multiple'), window.sxt('rel_multiple_help'),
                    !!field.multiple, e => { field.multiple = e.target.checked; })))
            ));
        }
        if (t === 'repeater') {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(4, labelled(window.sxt('min_rows'), input(field, 'min_rows', { type: 'number', min: 0, placeholder: '0' }))),
                col(4, labelled(window.sxt('max_rows'), input(field, 'max_rows', { type: 'number', min: 0, placeholder: window.sxt('max_rows_ph') }))),
                col(4, labelled(window.sxt('button_label'), input(field, 'button_label', { placeholder: window.sxt('add_row') })))
            ));
        }
        if (options.length) main.push(section(...options));
        if (CONTAINERS.includes(t)) {
            main.push(h('div', { class: 'fb-sub' },
                h('div', { class: 'small fw-semibold text-secondary mb-3' }, icon(t === 'group' ? 'collection' : 'list-ol'), ' ',
                    t === 'group' ? window.sxt('group_fields') : window.sxt('repeater_fields')),
                // Group fields share the parent table; repeater fields get their own row table.
                renderList(field.sub_fields, depth + 1, t === 'repeater' ? RESERVED.row : reserved)));
        }

        return main;
    }

    // ---------------------------------------------------------------- conditional rules

    const newRule = () => ({ field: '', operator: '==', value: '' });

    const choicesOf = f => (typeof f.choices === 'string' ? f.choices : '').split(/\r?\n/)
        .map(line => line.trim()).filter(Boolean)
        .map(line => { const [v, ...l] = line.split(':'); return { value: v.trim(), label: (l.join(':').trim() || v.trim()) }; });

    function operatorsFor(target) {
        switch (target?.type) {
            case 'number': return ['==', '!=', '>', '<', 'empty', 'not_empty'];
            case 'toggle': return ['=='];
            case 'select': case 'radio': case 'checkbox': return ['==', '!=', 'empty', 'not_empty'];
            case 'image': case 'file': case 'editor': case 'relation': return ['not_empty', 'empty'];
            case undefined: return ['=='];
            default: return ['==', '!=', 'contains', 'empty', 'not_empty'];
        }
    }

    /** OR-groups of AND-rules, e.g. field.conditions = [[rule, rule], [rule]] */
    function renderRules(field, prop, siblings, redraw, refreshHead, title) {
        const groups = field[prop];
        const box = h('div', { class: 'fb-rules border border-warning-subtle rounded bg-warning-subtle bg-opacity-25' },
            h('div', { class: 'small fw-semibold mb-3' }, title));

        groups.forEach((group, gi) => {
            if (gi > 0) box.append(h('div', { class: 'small fw-bold text-secondary text-uppercase my-1' }, window.sxt('cond_or')));
            const g = h('div', { class: 'd-flex flex-column gap-2' });
            group.forEach((rule, ri) => {
                if (ri > 0) g.append(h('div', { class: 'small text-secondary ms-1' }, window.sxt('cond_and')));
                g.append(renderRule(rule, () => {
                    group.splice(ri, 1);
                    if (!group.length) groups.splice(groups.indexOf(group), 1);
                    refreshHead(); redraw();
                }, siblings, redraw));
            });
            g.append(h('div', {}, h('button', { type: 'button', class: 'btn btn-sm btn-link p-0',
                onclick: () => { group.push(newRule()); redraw(); } }, window.sxt('add_and'))));
            box.append(g);
        });

        box.append(h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary mt-2',
            onclick: () => { groups.push([newRule()]); redraw(); } }, icon('plus'), ' ' + window.sxt('cond_or')));
        return box;
    }

    function renderRule(rule, remove, siblings, redraw) {
        const target = () => siblings().find(f => f.key === rule.field);

        const fieldSelect = h('select', { class: 'form-select',
            onchange: e => { rule.field = e.target.value; rule.operator = operatorsFor(target())[0]; rule.value = ''; redraw(); } });
        const fillFields = () => {
            const options = siblings().map(f => h('option', { value: f.key, selected: f.key === rule.field },
                `${f.label || window.sxt('no_label')}${f.name ? ' (' + f.name + ')' : ''}`));
            if (rule.field && !target()) options.unshift(h('option', { value: rule.field, selected: true }, window.sxt('field_missing')));
            fieldSelect.replaceChildren(h('option', { value: '' }, window.sxt('select_field')), ...options);
        };
        fillFields();
        fieldSelect.addEventListener('focus', fillFields);   // siblings may have been renamed/added meanwhile

        const t = target();
        const ops = operatorsFor(t);
        if (!ops.includes(rule.operator)) rule.operator = ops[0];

        const opSelect = h('select', { class: 'form-select',
            onchange: e => { rule.operator = e.target.value; redraw(); } },
            ...ops.map(op => h('option', { value: op, selected: op === rule.operator },
                t?.type === 'checkbox' ? { '==': window.sxt('checked'), '!=': window.sxt('unchecked') }[op] ?? OPERATORS[op] : OPERATORS[op])));

        let valueControl = null;
        if (!['empty', 'not_empty'].includes(rule.operator)) {
            if (t && ['select', 'radio', 'checkbox'].includes(t.type)) {
                valueControl = h('select', { class: 'form-select', onchange: e => rule.value = e.target.value },
                    h('option', { value: '' }, window.sxt('select_value')),
                    ...choicesOf(t).map(c => h('option', { value: c.value, selected: c.value === rule.value }, c.label)));
            } else if (t?.type === 'toggle') {
                if (!['0', '1'].includes(rule.value)) rule.value = '1';
                valueControl = h('select', { class: 'form-select', onchange: e => rule.value = e.target.value },
                    h('option', { value: '1', selected: rule.value === '1' }, window.sxt('on')),
                    h('option', { value: '0', selected: rule.value === '0' }, window.sxt('off')));
            } else {
                valueControl = input(rule, 'value', { type: t?.type === 'number' ? 'number' : 'text', step: 'any', placeholder: window.sxt('value') });
            }
        }

        return h('div', { class: 'd-flex gap-2 align-items-center fb-rule' },
            h('div', { style: 'flex: 2' }, fieldSelect),
            h('div', { style: 'flex: 1.4' }, opSelect),
            h('div', { style: 'flex: 2' }, valueControl),
            h('button', { type: 'button', class: 'btn btn-outline-danger', 'data-bs-toggle': 'tooltip', title: window.sxt('del_cond'), onclick: remove }, icon('x-lg')));
    }

    // ---------------------------------------------------------------- title field + submit

    function changed() {
        if (!titleSelect) return;
        const current = titleSelect.value || titleSelect.dataset.selected || '';
        const texts = state.filter(f => f.type === 'text' && f.name);

        titleSelect.replaceChildren(...(texts.length
            ? texts.map(f => h('option', { value: f.name, selected: f.name === current }, `${f.label || f.name} (${f.name})`))
            : [h('option', { value: '' }, window.sxt('no_text_field'))]));
        titleSelect.dataset.selected = titleSelect.value;
    }

    // Labels of fields that already have a database column, to warn before their data is dropped.
    const savedKeys = new Set(JSON.parse(root.dataset.savedKeys || '[]'));
    const savedLabels = {};
    (function remember(list) {
        list.forEach(f => { savedLabels[f.key] = f.label || f.name; remember(f.sub_fields || []); });
    })(state);

    form.addEventListener('submit', e => {
        const current = new Set();
        (function collect(list) { list.forEach(f => { current.add(f.key); collect(f.sub_fields || []); }); })(state);
        const removed = [...savedKeys].filter(k => !current.has(k)).map(k => savedLabels[k] || k);

        if (removed.length && !confirm(window.sxt('drop_fields_confirm', removed.join('\n• ')))) {
            e.preventDefault();
            return;
        }
        form.querySelector('[name="confirm_drop"]').value = removed.length ? '1' : '';
        output.value = JSON.stringify(state, (k, v) => k.startsWith('_') ? undefined : v);
    });

    root.replaceChildren(renderList(state, 1));
    changed();
})();
