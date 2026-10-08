@extends('admin.layouts.app')

@section('title', $type->singular . ($item ? 'を編集' : 'を追加'))

@push('head')
    <meta name="csrf-token" content="{{ csrf_hash() }}">
    <meta name="upload-url" content="{{ url_to('admin.upload') }}">
    <meta name="media-list-url" content="{{ url_to('admin.media.list') }}">
@endpush

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.entries', $type->slug) }}"><i class="bi bi-arrow-left"></i> {{ $type->name }}一覧へ戻る</a>
    @if($item)
        {{-- Copies the saved entry (not unsaved changes in this form) --}}
        <form method="post" action="{{ url_to('admin.entries.duplicate', $type->slug, $item->id) }}" class="d-inline"
              onsubmit="return confirm('保存済みの内容を複製して、下書きとして作成します。\n（このページの未保存の変更は複製されません）')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-copy"></i> 複製</button>
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
                                このコンテンツタイプにはまだフィールドがありません。
                                @if($isAdmin)
                                    設定 → コンテンツタイプから<a href="{{ url_to('admin.types.edit', $type->id) }}">フィールドを追加</a>してください。
                                @else
                                    管理者にフィールドの追加を依頼してください。
                                @endif
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">SEO</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="meta_title">メタタイトル</label>
                            <input id="meta_title" type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $item->meta_title ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label" for="meta_description">メタディスクリプション</label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="2">{{ old('meta_description', $item->meta_description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-success card-outline mb-4">
                    <div class="card-header"><h3 class="card-title">公開設定</h3></div>
                    <div class="card-body">
                        @php $currentStatus = old('status', $item->status ?? 'draft'); @endphp
                        <div class="mb-3">
                            <label class="form-label" for="status">ステータス</label>
                            <select id="status" name="status" class="form-select">
                                <option value="draft" @selected($currentStatus === 'draft')>下書き</option>
                                <option value="published" @selected($currentStatus === 'published')>公開</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="slug">スラッグ（URL）</label>
                            <input id="slug" type="text" name="slug" class="form-control" value="{{ old('slug', $item->slug ?? '') }}"
                                   placeholder="タイトルから自動生成されます">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="published_at">公開日時</label>
                            <input id="published_at" type="datetime-local" name="published_at" class="form-control"
                                   value="{{ old('published_at', !empty($item->published_at) ? date('Y-m-d\TH:i', strtotime($item->published_at)) : '') }}">
                            <div class="form-text">空欄の場合はすぐに公開されます。未来の日時を指定すると予約投稿になります。</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="published_until">公開終了日時 <span class="text-secondary small">（任意）</span></label>
                            <input id="published_until" type="datetime-local" name="published_until" class="form-control"
                                   value="{{ old('published_until', !empty($item->published_until) ? date('Y-m-d\TH:i', strtotime($item->published_until)) : '') }}">
                            <div class="form-text">空欄の場合は無期限で公開されます。設定すると、この日時から非公開になります。</div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> {{ $item ? '更新' : '保存' }}</button>
                            {{-- Opens the frontend page with the current (unsaved) content in a new tab --}}
                            <button type="submit" class="btn btn-outline-secondary" formtarget="wd-preview" formnovalidate
                                    formaction="{{ $item ? url_to('admin.entries.preview.edit', $type->slug, $item->id) : url_to('admin.entries.preview', $type->slug) }}">
                                <i class="bi bi-eye"></i> プレビュー
                            </button>
                        </div>
                    </div>
                    @if($item)
                        <div class="card-footer small text-secondary">
                            作成日時: {{ date('Y-m-d H:i', strtotime($item->created_at)) }}<br>
                            更新日時: {{ date('Y-m-d H:i', strtotime($item->updated_at)) }}
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
