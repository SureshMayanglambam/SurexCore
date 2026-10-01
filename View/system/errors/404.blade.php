@extends('frontend.layout.default')

@section('title', 'ページが見つかりません | ' . $site->name)
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="page-header">
        <div class="container">
            <h1>ページが見つかりません</h1>
            <p>404 Not Found</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <p>お探しのページは移動または削除された可能性があります。URLをご確認ください。</p>
            <p><a class="btn btn-outline" href="{{ url('/') }}">トップページへ戻る</a></p>
        </div>
    </section>

@endsection
