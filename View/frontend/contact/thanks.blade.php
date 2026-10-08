@extends('frontend.layout.default')

@section('title', 'お問い合わせ（送信完了） | ' . $site->name)
@section('robots', 'noindex, nofollow')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/frontend/css/contact.css') }}">
@endpush

@section('content')
<div class="sx-contact">

    <section class="page-header">
        <div class="container">
            <h1>送信完了</h1>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <p>お問い合わせいただき、ありがとうございました。<br>
                ご入力いただいたメールアドレスに確認メールをお送りしました。内容を確認のうえ、担当者よりご連絡いたします。</p>
            <p><a class="btn btn-outline" href="{{ url('/') }}">トップページへ戻る</a></p>
        </div>
    </section>

</div>
@endsection
