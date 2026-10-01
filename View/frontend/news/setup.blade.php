{{-- Shown by the News sample until the "news" content type exists. Delete it together with the sample. --}}
@extends('frontend.layout.default')

@section('title', 'お知らせ（サンプル） | ' . $site->name)
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="page-header">
        <div class="container">
            <h1>お知らせ（サンプル）</h1>
            <p>Sample: route → controller → model → view</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="notice">
                <p><strong>このページを表示するには、コンテンツタイプ「お知らせ」を作成してください。</strong></p>
                <ol>
                    <li>管理画面 → 設定 → コンテンツタイプ → 追加</li>
                    <li>名前 <code>お知らせ</code>、スラッグ <code>news</code></li>
                    <li>フィールド：<code>headline</code>（テキスト）、<code>body</code>（本文 / CKEditor）</li>
                    <li>「タイトルに使うフィールド」に <code>headline</code> を選んで保存</li>
                    <li>お知らせを公開すると、ここに一覧が表示されます</li>
                </ol>
                <p>Files: <code>routes/web.php</code>, <code>App/Controller/News.php</code>, <code>App/Model/NewsModel.php</code>, <code>View/frontend/news/</code></p>
            </div>
        </div>
    </section>

@endsection
