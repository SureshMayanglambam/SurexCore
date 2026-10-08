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
                    icon('inboxes'), depth === 1 ? ' フィールドはまだありません' : ' サブフィールドはまだありません') : null,
                h('button', {
                    type: 'button', class: 'btn btn-sm ' + (depth === 1 ? 'btn-primary' : 'btn-outline-primary'),
                    onclick: () => { list.push(newField()); draw(); changed(); }
                }, icon('plus-lg'), depth === 1 ? ' フィールドを追加' : ' サブフィールドを追加')
            ].filter(Boolean));
        };

        draw();
        return wrap;
    }

    function renderField(field, list, index, depth, redrawList, reserved) {
        const typeLabel = () => (TYPES[field.type] || [field.type])[0];
        const head = {
            label: h('span', { class: 'fb-label' }, field.label || '（ラベルなし）'),
            name: h('code', { class: 'fb-name ms-2 small' }, field.name),
            type: h('span', { class: 'badge fb-type ms-2' }, typeLabel()),
            required: h('span', { class: 'text-danger ms-1' }),
            conditional: h('span', { class: 'badge text-bg-warning ms-2', 'data-bs-toggle': 'tooltip', title: '条件付き表示' }, icon('eye')),
            listed: h('span', { class: 'badge text-bg-info ms-2', 'data-bs-toggle': 'tooltip', title: '一覧に表示' }, icon('table')),
        };
        const refreshHead = () => {
            head.label.textContent = field.label || '（ラベルなし）';
            head.name.textContent = field.name;
            head.type.textContent = typeLabel();
            head.required.textContent = field.required ? '*' : (field.required_if.length ? '*?' : '');
            head.required.title = field.required_if.length ? '条件に一致したときだけ必須になります' : '';
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
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-bs-toggle': 'tooltip', title: '上へ移動',
                    disabled: index === 0, onclick: () => move(-1) }, icon('arrow-up')),
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-bs-toggle': 'tooltip', title: '下へ移動',
                    disabled: index === list.length - 1, onclick: () => move(1) }, icon('arrow-down')),
                h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', 'data-bs-toggle': 'tooltip', title: '削除',
                    onclick: () => {
                        if (!confirm(`フィールド「${field.label || field.name || '無題'}」を削除しますか？\n保存すると、データベースのカラムと保存済みの値も削除されます。`)) return;
                        list.splice(index, 1);
                        list.forEach(f => ['conditions', 'required_if'].forEach(p => {
                            f[p] = f[p].map(g => g.filter(r => r.field !== field.key)).filter(g => g.length);
                        }));
                        redrawList(); changed();
                    } }, icon('trash')),
                h('button', { type: 'button', class: 'btn btn-sm btn-link text-secondary', 'data-bs-toggle': 'tooltip', title: '開く／閉じる',
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
                ? `「${field.name}」は使用できません（例：${field.name}_text）`
                : '';
        };
        const nameInput = input(field, 'name', {
            class: 'form-control font-monospace', placeholder: '例：post_type_name', pattern: '[a-z][a-z0-9_]*',
            after: () => { field._autoName = false; checkName(); refreshHead(); changed(); }
        });
        checkName();

        const rows = [
            h('div', { class: 'row g-3' },
                h('div', { class: 'col-md-4' }, labelled('ラベル', input(field, 'label', {
                    placeholder: '例：価格',
                    after: () => {
                        if (field._autoName) { field.name = slugify(field.label); nameInput.value = field.name; checkName(); }
                        refreshHead(); changed();
                    }
                }))),
                h('div', { class: 'col-md-4' }, labelled('フィールド名', h('div', {}, nameInput, nameWarning), 'カラム名／コードで使う名前（半角英小文字・数字・_）')),
                h('div', { class: 'col-md-4' }, labelled('フィールドタイプ', typeSelect))
            ),
            h('div', { class: 'row g-3 mt-0' },
                h('div', { class: 'col-md-8' }, labelled('説明', input(field, 'instructions'), '投稿画面でフィールドの下に表示されます')),
                h('div', { class: 'col-md-4' }, labelled('必須', h('select', {
                    class: 'form-select',
                    onchange: e => {
                        field.required = e.target.value === 'yes';
                        field.required_if = e.target.value === 'if' ? [[newRule()]] : [];
                        refreshHead(); redrawBody();
                    }
                },
                    h('option', { value: 'no', selected: !field.required && !field.required_if.length }, 'いいえ'),
                    h('option', { value: 'yes', selected: field.required }, 'はい'),
                    h('option', { value: 'if', selected: !field.required && field.required_if.length > 0 }, '条件付き'))))
            ),
        ];

        if (field.required_if.length) {
            rows.push(renderRules(field, 'required_if', siblings, redrawBody, refreshHead, '必須にする条件'));
        }

        const switches = h('div', { class: 'fb-switches' },
            // Top-level fields can be columns in the entry list.
            depth === 1 && !CONTAINERS.includes(field.type)
                ? toggle(field.key + '_list', '一覧に表示', '投稿一覧に列として表示します', field.show_in_list,
                    e => { field.show_in_list = e.target.checked; refreshHead(); })
                : null,
            toggle(field.key + '_cond', '条件付き表示', '条件に一致したときだけ、このフィールドを表示します', field.conditions.length > 0,
                e => { field.conditions = e.target.checked ? [[newRule()]] : []; refreshHead(); redrawBody(); }));

        const main = [h('div', {}, ...rows)];
        main.push(section(switches,
            field.conditions.length ? renderRules(field, 'conditions', siblings, redrawBody, refreshHead, '表示する条件') : null));

        const options = [];

        const t = field.type;
        const col = (w, ...c) => h('div', { class: 'col-md-' + w }, ...c);

        if (['text', 'email', 'url', 'textarea', 'number'].includes(t)) {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(t === 'textarea' ? 4 : 6, labelled('プレースホルダー', input(field, 'placeholder'))),
                col(t === 'textarea' ? 6 : 6, labelled('初期値', input(field, 'default', t === 'number' ? { type: 'number', step: 'any' } : {}))),
                t === 'textarea' ? col(2, labelled('行数', input(field, 'rows', { type: 'number', min: 2, max: 30, value: field.rows ?? 4 }))) : null
            ));
        }
        if (t === 'number') {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(4, labelled('最小値', input(field, 'min', { type: 'number', step: 'any' }))),
                col(4, labelled('最大値', input(field, 'max', { type: 'number', step: 'any' }))),
                col(4, labelled('刻み幅', input(field, 'step', { type: 'number', step: 'any', placeholder: '1' })))
            ));
        }
        if (CHOICES.includes(t)) {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(8, labelled('選択肢', h('textarea', {
                    class: 'form-control font-monospace', rows: 4, placeholder: 'red : 赤\nblue : 青',
                    oninput: e => field.choices = e.target.value
                }, typeof field.choices === 'string' ? field.choices : ''), '1行に1つ。「値 : ラベル」または「値」のみ')),
                col(4, labelled('初期値', input(field, 'default', { placeholder: '選択肢の値を入力' })))
            ));
        }
        if (t === 'toggle') {
            options.push(h('div', { class: 'form-check form-switch' },
                h('input', { type: 'checkbox', class: 'form-check-input', id: field.key + '_def', checked: !!field.default,
                    onchange: e => field.default = e.target.checked }),
                h('label', { class: 'form-check-label', for: field.key + '_def' }, '初期状態でオン')));
        }
        if (t === 'relation') {
            const typeSelect = h('select', { class: 'form-select', onchange: e => { field.related_type = e.target.value; } },
                h('option', { value: '' }, '— コンテンツタイプを選択 —'),
                ...Object.entries(CONTENT_TYPES).map(([slug, name]) =>
                    h('option', { value: slug, selected: field.related_type === slug }, `${name}（${slug}）`)));
            options.push(h('div', { class: 'row g-3 align-items-end' + (options.length ? ' mt-0' : '') },
                col(6, labelled('関連付けるコンテンツタイプ', typeSelect, 'このコンテンツタイプの投稿から選択できます')),
                col(6, h('div', { class: 'pb-2' }, toggle(field.key + '_multiple', '複数選択できる', 'オフ：1件だけ選択（セレクト）／オン：複数選択（チェックボックス）',
                    !!field.multiple, e => { field.multiple = e.target.checked; })))
            ));
        }
        if (t === 'repeater') {
            options.push(h('div', { class: 'row g-3' + (options.length ? ' mt-0' : '') },
                col(4, labelled('最小行数', input(field, 'min_rows', { type: 'number', min: 0, placeholder: '0' }))),
                col(4, labelled('最大行数', input(field, 'max_rows', { type: 'number', min: 0, placeholder: '0 = 無制限' }))),
                col(4, labelled('ボタンのラベル', input(field, 'button_label', { placeholder: '行を追加' })))
            ));
        }
        if (options.length) main.push(section(...options));
        if (CONTAINERS.includes(t)) {
            main.push(h('div', { class: 'fb-sub' },
                h('div', { class: 'small fw-semibold text-secondary mb-3' }, icon(t === 'group' ? 'collection' : 'list-ol'), ' ',
                    t === 'group' ? 'このグループ内のフィールド' : '各行のフィールド'),
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
            if (gi > 0) box.append(h('div', { class: 'small fw-bold text-secondary text-uppercase my-1' }, 'または'));
            const g = h('div', { class: 'd-flex flex-column gap-2' });
            group.forEach((rule, ri) => {
                if (ri > 0) g.append(h('div', { class: 'small text-secondary ms-1' }, 'かつ'));
                g.append(renderRule(rule, () => {
                    group.splice(ri, 1);
                    if (!group.length) groups.splice(groups.indexOf(group), 1);
                    refreshHead(); redraw();
                }, siblings, redraw));
            });
            g.append(h('div', {}, h('button', { type: 'button', class: 'btn btn-sm btn-link p-0',
                onclick: () => { group.push(newRule()); redraw(); } }, '+ かつ')));
            box.append(g);
        });

        box.append(h('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary mt-2',
            onclick: () => { groups.push([newRule()]); redraw(); } }, icon('plus'), ' または'));
        return box;
    }

    function renderRule(rule, remove, siblings, redraw) {
        const target = () => siblings().find(f => f.key === rule.field);

        const fieldSelect = h('select', { class: 'form-select',
            onchange: e => { rule.field = e.target.value; rule.operator = operatorsFor(target())[0]; rule.value = ''; redraw(); } });
        const fillFields = () => {
            const options = siblings().map(f => h('option', { value: f.key, selected: f.key === rule.field },
                `${f.label || '（ラベルなし）'}${f.name ? ' (' + f.name + ')' : ''}`));
            if (rule.field && !target()) options.unshift(h('option', { value: rule.field, selected: true }, '（存在しないフィールド）'));
            fieldSelect.replaceChildren(h('option', { value: '' }, '— フィールドを選択 —'), ...options);
        };
        fillFields();
        fieldSelect.addEventListener('focus', fillFields);   // siblings may have been renamed/added meanwhile

        const t = target();
        const ops = operatorsFor(t);
        if (!ops.includes(rule.operator)) rule.operator = ops[0];

        const opSelect = h('select', { class: 'form-select',
            onchange: e => { rule.operator = e.target.value; redraw(); } },
            ...ops.map(op => h('option', { value: op, selected: op === rule.operator },
                t?.type === 'checkbox' ? { '==': 'チェックあり', '!=': 'チェックなし' }[op] ?? OPERATORS[op] : OPERATORS[op])));

        let valueControl = null;
        if (!['empty', 'not_empty'].includes(rule.operator)) {
            if (t && ['select', 'radio', 'checkbox'].includes(t.type)) {
                valueControl = h('select', { class: 'form-select', onchange: e => rule.value = e.target.value },
                    h('option', { value: '' }, '— 値を選択 —'),
                    ...choicesOf(t).map(c => h('option', { value: c.value, selected: c.value === rule.value }, c.label)));
            } else if (t?.type === 'toggle') {
                if (!['0', '1'].includes(rule.value)) rule.value = '1';
                valueControl = h('select', { class: 'form-select', onchange: e => rule.value = e.target.value },
                    h('option', { value: '1', selected: rule.value === '1' }, 'オン'),
                    h('option', { value: '0', selected: rule.value === '0' }, 'オフ'));
            } else {
                valueControl = input(rule, 'value', { type: t?.type === 'number' ? 'number' : 'text', step: 'any', placeholder: '値' });
            }
        }

        return h('div', { class: 'd-flex gap-2 align-items-center fb-rule' },
            h('div', { style: 'flex: 2' }, fieldSelect),
            h('div', { style: 'flex: 1.4' }, opSelect),
            h('div', { style: 'flex: 2' }, valueControl),
            h('button', { type: 'button', class: 'btn btn-outline-danger', 'data-bs-toggle': 'tooltip', title: '条件を削除', onclick: remove }, icon('x-lg')));
    }

    // ---------------------------------------------------------------- title field + submit

    function changed() {
        if (!titleSelect) return;
        const current = titleSelect.value || titleSelect.dataset.selected || '';
        const texts = state.filter(f => f.type === 'text' && f.name);

        titleSelect.replaceChildren(...(texts.length
            ? texts.map(f => h('option', { value: f.name, selected: f.name === current }, `${f.label || f.name} (${f.name})`))
            : [h('option', { value: '' }, 'テキストフィールドがありません（投稿には連番のタイトルが付きます）')]));
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

        if (removed.length && !confirm(`次のフィールドを、すべての投稿に保存されたデータとともに削除します：\n\n• ${removed.join('\n• ')}\n\nこの操作は元に戻せません。続行しますか？`)) {
            e.preventDefault();
            return;
        }
        form.querySelector('[name="confirm_drop"]').value = removed.length ? '1' : '';
        output.value = JSON.stringify(state, (k, v) => k.startsWith('_') ? undefined : v);
    });

    root.replaceChildren(renderList(state, 1));
    changed();
})();
