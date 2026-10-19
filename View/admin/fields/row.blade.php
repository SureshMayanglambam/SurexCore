{{-- One repeater row: $field (repeater definition), $row (values), $rowName (e.g. fields[faq][0]).
     Collapsible: the header shows the row number and its first filled-in text (entry-form.js). --}}
<div class="card fld-row mb-2 shadow-none">
    <div class="card-header fld-row-head py-1 d-flex align-items-center gap-1">
        <button type="button" class="fld-row-toggle flex-grow-1 d-flex align-items-center gap-2 text-start" aria-expanded="true" title="{{ lang('Admin.field.row_toggle') }}">
            <i class="bi bi-chevron-down fld-row-chevron" aria-hidden="true"></i>
            <span class="fld-row-number badge text-bg-light border"></span>
            <span class="small fw-semibold text-nowrap">{{ $field['label'] }}</span>
            <span class="fld-row-summary small text-secondary text-truncate"></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary fld-row-up" title="{{ lang('Admin.field.row_up') }}" aria-label="{{ lang('Admin.field.row_up') }}"><i class="bi bi-arrow-up"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary fld-row-down" title="{{ lang('Admin.field.row_down') }}" aria-label="{{ lang('Admin.field.row_down') }}"><i class="bi bi-arrow-down"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger fld-row-remove" title="{{ lang('Admin.field.row_remove') }}" aria-label="{{ lang('Admin.field.row_remove') }}"><i class="bi bi-trash"></i></button>
    </div>
    <div class="card-body fld-row-body pb-0">
        @foreach($field['sub_fields'] as $sub)
            @include('admin.fields.field', [
                'field' => $sub,
                'value' => $row[$sub['name']] ?? null,
                'name'  => $rowName . '[' . $sub['name'] . ']',
            ])
        @endforeach
    </div>
</div>
