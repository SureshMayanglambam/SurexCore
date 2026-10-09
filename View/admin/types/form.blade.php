@extends('admin.layouts.app')

@section('title', $item ? lang('Admin.typeform.edit') : lang('Admin.typeform.add'))

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.types') }}"><i class="bi bi-arrow-left"></i> {{ lang('Admin.back') }}</a>
    <button type="submit" form="type-form" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $item ? lang('Admin.update') : lang('Admin.create') }}</button>
@endsection

@section('content')
    <form id="type-form" method="post" action="{{ $item ? url_to('admin.types.update', $item->id) : url_to('admin.types.store') }}" class="type-form">
        @csrf
        @if($item) @method('PUT') @endif

        <div class="card card-primary card-outline mb-4">
            <div class="card-header"><h3 class="card-title">{{ lang('Admin.typeform.basic') }}</h3></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="name">{{ lang('Admin.typeform.name') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.name_tip') }}"></i></label>
                        <input id="name" type="text" name="name" class="form-control" required placeholder="{{ lang('Admin.typeform.name_ph') }}"
                               value="{{ old('name', $item->name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="singular">{{ lang('Admin.typeform.singular') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.singular_tip') }}"></i></label>
                        <input id="singular" type="text" name="singular" class="form-control" placeholder="{{ lang('Admin.typeform.singular_ph') }}"
                               value="{{ old('singular', $item->singular ?? '') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="slug">{{ lang('Admin.typeform.slug') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.slug_tip') }}"></i></label>
                        <input id="slug" type="text" name="slug" class="form-control text-mono" required placeholder="news"
                               pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $item->slug ?? '') }}">
                        @if($item)
                            <div class="form-text text-danger"><i class="bi bi-exclamation-triangle"></i> {{ lang('Admin.typeform.slug_warn') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="icon">{{ lang('Admin.typeform.icon') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.icon_tip') }}"></i></label>
                        <div class="input-group">
                            <span class="input-group-text"><i id="icon-preview" class="bi bi-{{ old('icon', $item->icon ?? 'file-earmark-text') }}"></i></span>
                            <input id="icon" type="text" name="icon" class="form-control text-mono"
                                   value="{{ old('icon', $item->icon ?? 'file-earmark-text') }}">
                            <a class="btn btn-outline-secondary" href="https://icons.getbootstrap.com/" target="_blank" rel="noopener"
                               data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.icon_list') }}"><i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">{{ lang('Admin.typeform.description') }}</label>
                        <input id="description" type="text" name="description" class="form-control"
                               value="{{ old('description', $item->description ?? '') }}">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label" for="preview_view">{{ lang('Admin.typeform.preview_tpl') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.preview_tip') }}"></i></label>
                        <input id="preview_view" type="text" name="preview_view" class="form-control text-mono"
                               placeholder="frontend.{{ $item->slug ?? 'news' }}.detail"
                               value="{{ old('preview_view', $item->preview_view ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sort_order">{{ lang('Admin.typeform.sort') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.sort_tip') }}"></i></label>
                        <input id="sort_order" type="number" name="sort_order" class="form-control"
                               value="{{ old('sort_order', $item->sort_order ?? 0) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-success card-outline mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title">{{ lang('Admin.typeform.fields') }}</h3>
                <div class="card-tools ms-auto small text-secondary">
                    <code>{{ $item ? \App\Libraries\ContentSchema::tableName($item->slug) : lang('Admin.typeform.slug_token') }}</code>
                    <i class="bi bi-info-circle tip ms-1" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.table_tip') }}"></i>
                </div>
            </div>
            <div class="card-body">
                <div id="field-builder"
                     data-fields="{{ json_encode($fields, JSON_UNESCAPED_UNICODE) }}"
                     data-types="{{ json_encode($fieldTypes, JSON_UNESCAPED_UNICODE) }}"
                     data-operators="{{ json_encode($operators, JSON_UNESCAPED_UNICODE) }}"
                     data-saved-keys="{{ json_encode($savedKeys) }}"
                     data-reserved="{{ json_encode($reserved) }}"
                     data-content-types="{{ json_encode($contentTypeOptions, JSON_UNESCAPED_UNICODE) }}"></div>
                <input type="hidden" name="fields_json" value="">
                <input type="hidden" name="confirm_drop" value="">

                <div class="row mt-4 pt-4 border-top">
                    <div class="col-md-6">
                        <label class="form-label" for="title_field">{{ lang('Admin.typeform.title_field') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.typeform.title_field_tip') }}"></i></label>
                        <select id="title_field" name="title_field" class="form-select"
                                data-selected="{{ old('title_field', $item->title_field ?? '') }}"></select>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $item ? lang('Admin.typeform.update') : lang('Admin.typeform.create') }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ base_url('assets/admin/field-builder.js') }}"></script>
<script>
    // Live icon preview and slug suggestion from the name
    const icon = document.getElementById('icon'), preview = document.getElementById('icon-preview');
    icon.addEventListener('input', () => preview.className = 'bi bi-' + icon.value.trim());

    const name = document.getElementById('name'), slug = document.getElementById('slug');
    let slugEdited = slug.value !== '';
    slug.addEventListener('input', () => slugEdited = true);
    name.addEventListener('input', () => {
        if (!slugEdited) slug.value = name.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
</script>
@endpush
