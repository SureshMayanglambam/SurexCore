{{--
    Renders one custom field input (recursive for group / repeater).
    $field  definition array (App\Libraries\Fields)
    $value  current value
    $name   input name, e.g. fields[price] or fields[faq][0][question]
--}}
@php
    $id    = 'fld_' . trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $name), '_');
    $type  = $field['type'];
    $label = $field['label'];
    $req   = !empty($field['required']);
    $reqIf = !empty($field['required_if']);
    $cond  = !empty($field['conditions']);
    $json  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
@endphp

{{-- data-conditions / data-required-if are evaluated by public/assets/admin/entry-form.js (and again on the server) --}}
<div class="mb-3 fld fld-type-{{ $type }}" data-key="{{ $field['key'] }}" data-type="{{ $type }}"
     @if($cond) data-conditions="{{ json_encode($field['conditions'], $json) }}" @endif
     @if($reqIf) data-required-if="{{ json_encode($field['required_if'], $json) }}" @endif>
    @if($type !== 'toggle')
        <label class="form-label fw-semibold" for="{{ $id }}">{{ $label }} @if($req)<span class="text-danger">*</span>@elseif($reqIf)<span class="text-danger fld-req-if" title="{{ lang('Admin.field.required_when') }}" hidden>*</span>@endif</label>
    @endif

    @switch($type)
        @case('text')
        @case('email')
        @case('url')
        @case('date')
        @case('number')
            <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" class="form-control"
                   value="{{ is_scalar($value) ? $value : '' }}"
                   placeholder="{{ $field['placeholder'] ?? '' }}"
                   @if($type === 'number')
                       @if(isset($field['min']) && $field['min'] !== null) min="{{ $field['min'] }}" @endif
                       @if(isset($field['max']) && $field['max'] !== null) max="{{ $field['max'] }}" @endif
                       step="{{ $field['step'] ?? 'any' }}"
                   @endif>
            @break

        @case('textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" class="form-control" rows="{{ $field['rows'] ?? 4 }}"
                      placeholder="{{ $field['placeholder'] ?? '' }}">{{ is_scalar($value) ? $value : '' }}</textarea>
            @break

        @case('editor')
            <textarea id="{{ $id }}" name="{{ $name }}" class="form-control" rows="10" data-editor>{{ is_scalar($value) ? $value : '' }}</textarea>
            @break

        @case('select')
            <select id="{{ $id }}" name="{{ $name }}" class="form-select">
                <option value="">{{ lang('Admin.field.select_prompt') }}</option>
                @foreach($field['choices'] as $choice)
                    <option value="{{ $choice['value'] }}" @selected((string) $value === $choice['value'])>{{ $choice['label'] }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            <div id="{{ $id }}">
                @foreach($field['choices'] as $i => $choice)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="{{ $name }}" id="{{ $id }}_{{ $i }}"
                               value="{{ $choice['value'] }}" @checked((string) $value === $choice['value'])>
                        <label class="form-check-label" for="{{ $id }}_{{ $i }}">{{ $choice['label'] }}</label>
                    </div>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <div id="{{ $id }}">
                @foreach($field['choices'] as $i => $choice)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="{{ $name }}[]" id="{{ $id }}_{{ $i }}"
                               value="{{ $choice['value'] }}" @checked(in_array($choice['value'], (array) $value, true))>
                        <label class="form-check-label" for="{{ $id }}_{{ $i }}">{{ $choice['label'] }}</label>
                    </div>
                @endforeach
            </div>
            @break

        @case('relation')
            @php $options = service('fields')->relationOptions($field['related_type']); $chosen = array_map('strval', (array) $value); @endphp
            @if(! $options)
                <div class="form-text">{{ lang('Admin.field.no_relation', [$field['related_type']]) }}</div>
            @elseif($field['multiple'])
                <div id="{{ $id }}" class="fld-relation border rounded p-2">
                    <input type="hidden" name="{{ $name }}[]" value="">
                    @foreach($options as $optionId => $optionTitle)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="{{ $name }}[]" id="{{ $id }}_{{ $optionId }}"
                                   value="{{ $optionId }}" @checked(in_array((string) $optionId, $chosen, true))>
                            <label class="form-check-label" for="{{ $id }}_{{ $optionId }}">{{ $optionTitle }}</label>
                        </div>
                    @endforeach
                </div>
            @else
                <select id="{{ $id }}" name="{{ $name }}" class="form-select">
                    <option value="">{{ lang('Admin.field.select_prompt') }}</option>
                    @foreach($options as $optionId => $optionTitle)
                        <option value="{{ $optionId }}" @selected(in_array((string) $optionId, $chosen, true))>{{ $optionTitle }}</option>
                    @endforeach
                </select>
            @endif
            @break

        @case('toggle')
            <input type="hidden" name="{{ $name }}" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked((bool) $value)>
                <label class="form-check-label fw-semibold" for="{{ $id }}">{{ $label }} @if($req)<span class="text-danger">*</span>@elseif($reqIf)<span class="text-danger fld-req-if" title="{{ lang('Admin.field.required_when') }}" hidden>*</span>@endif</label>
            </div>
            @break

        @case('image')
        @case('file')
            @php $path = is_string($value) ? $value : ''; @endphp
            <div class="fld-upload border rounded p-2 d-flex align-items-center gap-3" data-kind="{{ $type }}">
                <input type="hidden" name="{{ $name }}" value="{{ $path }}" class="fld-upload-value">
                @if($type === 'image')
                    <img class="fld-upload-preview rounded border" alt="" src="{{ $path ? base_url($path) : '' }}" @if(!$path) hidden @endif>
                @else
                    <a class="fld-upload-preview text-truncate" target="_blank" rel="noopener" href="{{ $path ? base_url($path) : '#' }}" @if(!$path) hidden @endif>
                        <i class="bi bi-file-earmark"></i> {{ basename($path) }}
                    </a>
                @endif
                <div class="ms-auto d-flex gap-2 align-items-center">
                    <span class="fld-upload-status small text-secondary"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary fld-media-pick">
                        <i class="bi bi-images"></i> {{ lang('Admin.field.media_select') }}
                    </button>
                    <label class="btn btn-sm btn-outline-primary mb-0">
                        <i class="bi bi-upload"></i> {{ $type === 'image' ? lang('Admin.field.upload_image') : lang('Admin.field.upload_file') }}
                        <input type="file" class="fld-upload-input" hidden
                               accept="{{ $type === 'image' ? 'image/jpeg,image/png,image/gif,image/webp' : '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv,.jpg,.png' }}">
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-danger fld-upload-remove" title="{{ lang('Admin.field.remove') }}" aria-label="{{ lang('Admin.field.remove') }}" @if(!$path) hidden @endif><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            @break

        @case('group')
            <div class="border rounded p-3 bg-body-tertiary">
                @foreach($field['sub_fields'] as $sub)
                    @include('admin.fields.field', [
                        'field' => $sub,
                        'value' => is_array($value) ? ($value[$sub['name']] ?? null) : null,
                        'name'  => $name . '[' . $sub['name'] . ']',
                    ])
                @endforeach
            </div>
            @break

        @case('repeater')
            @php
                $rows  = is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
                $token = '__ROW_' . $field['key'] . '__';
            @endphp
            <div class="fld-repeater" data-token="{{ $token }}" data-min="{{ $field['min_rows'] ?? 0 }}" data-max="{{ $field['max_rows'] ?? 0 }}">
                <div class="fld-rows">
                    @foreach($rows as $i => $row)
                        @include('admin.fields.row', ['field' => $field, 'row' => $row, 'rowName' => $name . '[' . $i . ']'])
                    @endforeach
                </div>
                <template class="fld-row-template">
                    @include('admin.fields.row', ['field' => $field, 'row' => service('fields')->defaults($field['sub_fields']), 'rowName' => $name . '[' . $token . ']'])
                </template>
                <button type="button" class="btn btn-sm btn-outline-primary fld-row-add"><i class="bi bi-plus-lg"></i> {{ $field['button_label'] ?? lang('Admin.field.add_row') }}</button>
            </div>
            @break
    @endswitch

    @if(!empty($field['instructions']))
        <div class="form-text">{{ $field['instructions'] }}</div>
    @endif
</div>
