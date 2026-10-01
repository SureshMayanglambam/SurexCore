@extends('admin.layouts.app')

@section('title', 'プレビュー')

@section('content')
    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3"><i class="bi bi-eye-slash me-1"></i> プレビュー用のテンプレートがありません</h2>
            <p class="mb-2">「{{ $type->name }}」のプレビューには、フロント側の詳細ページのテンプレートが必要です。</p>
            <ul class="mb-3">
                @if(str_starts_with($expected, '/'))
                    <li>ページ <code>{{ $expected }}</code> が <code>routes/web.php</code> にありません。ルートを追加するか、URL を確認してください</li>
                @else
                    <li><code>{{ $expected }}</code> を作成する（<code>$item</code> で投稿を表示）</li>
                @endif
                <li>または、設定 → コンテンツタイプ → 「{{ $type->name }}」の「プレビュー用テンプレート」を変更する
                    （詳細ページはテンプレート名、一覧ページは <code>/recruit</code> のような URL）</li>
            </ul>
            <p class="text-secondary small mb-0">このタブは閉じて構いません。入力内容は編集画面に残っています。</p>
        </div>
    </div>
@endsection
