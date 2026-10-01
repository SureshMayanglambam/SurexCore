@extends('frontend.layout.default')

@section('title', 'お問い合わせ（確認） | ' . $site->name)
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="page-header">
        <div class="container">
            <h1>お問い合わせ（確認）</h1>
            <p>以下の内容でよろしければ「送信する」を押してください。</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            @if(session('error'))
                <p class="form-error">{{ session('error') }}</p>
            @endif

            <form class="form" method="post" action="{{ url_to('contact.send') }}">
                @csrf
                <dl class="confirm-list">
                    <dt>お名前</dt><dd>{{ $data['name'] }}</dd>
                    <dt>フリガナ</dt><dd>{{ $data['kana'] }}</dd>
                    <dt>メールアドレス</dt><dd>{{ $data['email'] }}</dd>
                    <dt>電話番号</dt><dd>{{ $data['tel'] }}</dd>
                    <dt>お問い合わせ内容</dt><dd>{{ $data['message'] }}</dd>
                </dl>

                <div class="form-actions">
                    {{-- 戻る: back to the form with the entered values --}}
                    <button type="submit" class="btn btn-outline" formaction="{{ url_to('contact.back') }}" formmethod="get" formnovalidate>戻る</button>
                    <button type="submit" class="btn btn-primary">送信する</button>
                </div>
            </form>
        </div>
    </section>

@endsection
