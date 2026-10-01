@extends('admin.layouts.app')

@section('title', 'メディア')
@section('subtitle', 'アップロードした画像・ファイルの一覧です。投稿の画像・ファイル欄から再利用できます。')

@push('head')
    <meta name="csrf-token" content="{{ csrf_hash() }}">
    <meta name="upload-url" content="{{ url_to('admin.upload') }}">
    <meta name="media-show-url" content="{{ url_to('admin.media.show', 0) }}">
@endpush

@section('actions')
    <label class="btn btn-primary mb-0">
        <i class="bi bi-upload"></i> アップロード
        <input type="file" id="media-upload-input" multiple hidden
               accept="image/jpeg,image/png,image/gif,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv">
    </label>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="btn-group" role="group" aria-label="種類">
                    <a href="{{ url_to('admin.media') }}{{ $search !== '' ? '?q=' . urlencode($search) : '' }}" @class(['btn btn-sm', 'btn-primary' => $kind === null, 'btn-outline-secondary' => $kind !== null])>すべて</a>
                    <a href="{{ url_to('admin.media') }}?kind=image{{ $search !== '' ? '&q=' . urlencode($search) : '' }}" @class(['btn btn-sm', 'btn-primary' => $kind === 'image', 'btn-outline-secondary' => $kind !== 'image'])><i class="bi bi-image"></i> 画像</a>
                    <a href="{{ url_to('admin.media') }}?kind=file{{ $search !== '' ? '&q=' . urlencode($search) : '' }}" @class(['btn btn-sm', 'btn-primary' => $kind === 'file', 'btn-outline-secondary' => $kind !== 'file'])><i class="bi bi-file-earmark"></i> ファイル</a>
                </div>
                @if($kind)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm ms-md-auto" style="max-width: 260px" placeholder="ファイル名で検索">
                <span class="small text-secondary">全{{ $total }}件</span>
            </form>
        </div>
    </div>

    <div id="media-drop" class="media-drop mb-4">
        <i class="bi bi-cloud-arrow-up"></i>
        <span>ここにファイルをドラッグ＆ドロップしてアップロード</span>
        <span class="media-drop-status small"></span>
    </div>

    @if($items)
        <div class="media-grid mb-4">
            @foreach($items as $m)
                @php $isImage = str_starts_with($m->mime, 'image/'); @endphp
                <button type="button" class="media-tile" data-id="{{ $m->id }}" title="{{ $m->original_name }}">
                    <span class="media-thumb">
                        @if($isImage)
                            <img src="{{ base_url($m->path) }}" alt="" loading="lazy">
                        @else
                            <i class="bi bi-file-earmark-text"></i>
                            <span class="media-ext">{{ strtoupper(pathinfo($m->path, PATHINFO_EXTENSION)) }}</span>
                        @endif
                    </span>
                    <span class="media-name">{{ $m->original_name }}</span>
                </button>
            @endforeach
        </div>
        <div class="d-flex justify-content-center">{!! $pager->links() !!}</div>
    @else
        <div class="card"><div class="card-body text-center text-secondary py-5">
            <i class="bi bi-images fs-1 d-block mb-2"></i>
            {{ $search !== '' || $kind ? '条件に一致するファイルはありません。' : 'まだファイルはありません。「アップロード」から追加できます。' }}
        </div></div>
    @endif

    {{-- Details (filled by media.js) --}}
    <div class="modal fade" id="media-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-truncate" data-m="name"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6"><div class="media-preview" data-m="preview"></div></div>
                        <div class="col-md-6">
                            <dl class="media-meta mb-3">
                                <dt>種類</dt><dd data-m="mime"></dd>
                                <dt>サイズ</dt><dd data-m="size"></dd>
                                <dt>アップロード日</dt><dd data-m="date"></dd>
                                <dt>使用箇所</dt><dd data-m="usages"></dd>
                            </dl>
                            <label class="form-label small fw-semibold">URL</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control text-mono" data-m="url" readonly>
                                <button type="button" class="btn btn-outline-secondary" data-copy><i class="bi bi-clipboard"></i> コピー</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <form method="post" data-m="delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> 削除</button>
                    </form>
                    <a class="btn btn-outline-secondary" data-m="open" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> 新しいタブで開く</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ base_url('assets/admin/media.js') }}"></script>
@endpush
