@extends('admin.layouts.app')

@section('title', 'ブランディング')
@section('subtitle', '管理画面のサイドバー上部に表示されるロゴです。')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">サイトロゴ</h3></div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.branding.update') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label" for="logo">新しいロゴをアップロード</label>
                        <input id="logo" type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif" required>
                        <div class="form-text">
                            PNG・JPG・WebP・GIF形式、最大2MBまで。サイドバーは暗い背景のため、背景が透明な白または明るい色のロゴ（PNG）がおすすめです。
                            サイト名の左に、最大40 × 40pxの円形で表示されます（正方形の画像がおすすめです）。
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> ロゴをアップロード</button>
                        </div>
                    </form>

                    @if($logo)
                        <hr class="my-4">
                        <form method="post" action="{{ url_to('admin.branding.delete') }}" onsubmit="return confirm('ロゴを削除しますか？削除後はサイト名が表示されます。')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> ロゴを削除</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">プレビュー</h3></div>
                <div class="card-body">
                    <div class="logo-preview logo-preview-dark">
                        <span class="logo-fallback">
                            @if($logo)
                                <img src="{{ media_url($logo) }}" alt="">
                            @else
                                <i class="bi bi-asterisk"></i>
                            @endif
                            {{ $siteName }}
                        </span>
                    </div>
                    <div class="form-text mb-3">サイドバー</div>

                    <div class="logo-preview logo-preview-light">
                        @if($logo)
                            <img src="{{ media_url($logo) }}" alt="{{ $siteName }}">
                        @else
                            <span class="text-secondary">ロゴはアップロードされていません</span>
                        @endif
                    </div>
                    <div class="form-text">明るい背景での表示（Webサイトなど）: <code>media_url(setting('site_logo'))</code></div>
                </div>
            </div>
        </div>
    </div>
@endsection
