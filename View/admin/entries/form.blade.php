@extends('admin.layouts.app')

@section('title', $item ? lang('Admin.entries.edit_title', [$type->singular]) : lang('Admin.entries.add_title', [$type->singular]))

@push('head')
    <meta name="csrf-token" content="{{ csrf_hash() }}">
    <meta name="upload-url" content="{{ url_to('admin.upload') }}">
    <meta name="media-list-url" content="{{ url_to('admin.media.list') }}">
@endpush

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.entries', $type->slug) }}"><i class="bi bi-arrow-left"></i> {{ lang('Admin.entries.back_to_list', [$type->name]) }}</a>
    @if($item)
        {{-- Copies the saved entry (not unsaved changes in this form) --}}
        <form method="post" action="{{ url_to('admin.entries.duplicate', $type->slug, $item->id) }}" class="d-inline"
              onsubmit="return confirm('{!! lang('Admin.entries.confirm_dup') !!}')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-copy"></i> {{ lang('Admin.entries.duplicate') }}</button>
        </form>
    @endif
@endsection

@section('content')
    <form method="post" action="{{ $item ? url_to('admin.entries.update', $type->slug, $item->id) : url_to('admin.entries.store', $type->slug) }}">
        @csrf
        @if($item) @method('PUT') @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card card-primary card-outline mb-4">
                    <div class="card-body">
                        @forelse($type->fields as $field)
                            @include('admin.fields.field', [
                                'field' => $field,
                                'value' => $values[$field['name']] ?? null,
                                'name'  => 'fields[' . $field['name'] . ']',
                            ])
                        @empty
                            <p class="text-secondary mb-0">
                                {{ lang('Admin.entries.no_fields') }}
                                @if($isAdmin)
                                    {!! lang('Admin.entries.add_fields', ['<a href="'.url_to('admin.types.edit', $type->id).'">'.esc(lang('Admin.entries.add_fields_link')).'</a>']) !!}
                                @else
                                    {{ lang('Admin.entries.ask_admin') }}
                                @endif
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">SEO</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="meta_title">{{ lang('Admin.entries.meta_title') }}</label>
                            <input id="meta_title" type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $item->meta_title ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label" for="meta_description">{{ lang('Admin.entries.meta_desc') }}</label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="2">{{ old('meta_description', $item->meta_description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-success card-outline mb-4">
                    <div class="card-header"><h3 class="card-title">{{ lang('Admin.entries.publish') }}</h3></div>
                    <div class="card-body">
                        @php $currentStatus = old('status', $item->status ?? 'draft'); @endphp
                        <div class="mb-3">
                            <label class="form-label" for="status">{{ lang('Admin.entries.status') }}</label>
                            <select id="status" name="status" class="form-select">
                                <option value="draft" @selected($currentStatus === 'draft')>{{ lang('Admin.draft') }}</option>
                                <option value="published" @selected($currentStatus === 'published')>{{ lang('Admin.published') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="slug">{{ lang('Admin.entries.slug_url') }}</label>
                            <input id="slug" type="text" name="slug" class="form-control" value="{{ old('slug', $item->slug ?? '') }}"
                                   placeholder="{{ lang('Admin.entries.slug_ph') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="published_at">{{ lang('Admin.entries.pub_at') }}</label>
                            <input id="published_at" type="datetime-local" name="published_at" class="form-control"
                                   value="{{ old('published_at', !empty($item->published_at) ? date('Y-m-d\TH:i', strtotime($item->published_at)) : '') }}">
                            <div class="form-text">{{ lang('Admin.entries.pub_at_help') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="published_until">{{ lang('Admin.entries.pub_until') }} <span class="text-secondary small">{{ lang('Admin.entries.pub_until_opt') }}</span></label>
                            <input id="published_until" type="datetime-local" name="published_until" class="form-control"
                                   value="{{ old('published_until', !empty($item->published_until) ? date('Y-m-d\TH:i', strtotime($item->published_until)) : '') }}">
                            <div class="form-text">{{ lang('Admin.entries.pub_until_help') }}</div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> {{ $item ? lang('Admin.update') : lang('Admin.save') }}</button>
                            {{-- Opens the frontend page with the current (unsaved) content in a new tab --}}
                            <button type="submit" class="btn btn-outline-secondary" formtarget="wd-preview" formnovalidate
                                    formaction="{{ $item ? url_to('admin.entries.preview.edit', $type->slug, $item->id) : url_to('admin.entries.preview', $type->slug) }}">
                                <i class="bi bi-eye"></i> {{ lang('Admin.entries.preview') }}
                            </button>
                        </div>
                    </div>
                    @if($item)
                        <div class="card-footer small text-secondary">
                            {{ lang('Admin.entries.created') }}: {{ date('Y-m-d H:i', strtotime($item->created_at)) }}<br>
                            {{ lang('Admin.entries.updated') }}: {{ date('Y-m-d H:i', strtotime($item->updated_at)) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @include('admin.partials.ckeditor')
    @push('scripts')
        <script src="{{ base_url('assets/admin/media.js') }}"></script>
        <script src="{{ base_url('assets/admin/entry-form.js') }}"></script>
    @endpush
@endsection
