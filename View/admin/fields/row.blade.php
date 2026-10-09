{{-- One repeater row: $field (repeater definition), $row (values), $rowName (e.g. fields[faq][0]) --}}
<div class="card fld-row mb-2 shadow-none border">
    <div class="card-header py-1 d-flex align-items-center gap-1">
        <span class="fld-row-number badge text-bg-light border"></span>
        <span class="small text-secondary flex-grow-1">{{ $field['label'] }}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary fld-row-up" title="{{ lang('Admin.field.row_up') }}" aria-label="{{ lang('Admin.field.row_up') }}"><i class="bi bi-arrow-up"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary fld-row-down" title="{{ lang('Admin.field.row_down') }}" aria-label="{{ lang('Admin.field.row_down') }}"><i class="bi bi-arrow-down"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger fld-row-remove" title="{{ lang('Admin.field.row_remove') }}" aria-label="{{ lang('Admin.field.row_remove') }}"><i class="bi bi-trash"></i></button>
    </div>
    <div class="card-body pb-0">
        @foreach($field['sub_fields'] as $sub)
            @include('admin.fields.field', [
                'field' => $sub,
                'value' => $row[$sub['name']] ?? null,
                'name'  => $rowName . '[' . $sub['name'] . ']',
            ])
        @endforeach
    </div>
</div>
