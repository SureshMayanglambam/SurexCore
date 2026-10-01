{{--
    お問い合わせ: 入力 → 確認 → 完了 (App/Controller/Contact.php).
    Fields and validation rules are defined in the controller ($fields); mails in View/frontend/contact/mail/.
--}}
@extends('frontend.layout.default')

@section('title', 'お問い合わせ | ' . $site->name)
@section('description', $site->name . 'へのお問い合わせはこちらのフォームからお送りください。')

@section('content')

@php $errors = session('errors') ?? []; @endphp

    <section class="page-header">
        <div class="container">
            <h1>お問い合わせ</h1>
            <p>Contact</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <form class="form" method="post" action="{{ url_to('contact.confirm') }}" novalidate>
                @csrf

                <div class="form-row">
                    <label for="name">お名前<span class="req">必須</span></label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" @class(['is-invalid' => isset($errors['name'])])>
                    @error('name')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <label for="kana">フリガナ<span class="req">必須</span></label>
                    <input id="kana" type="text" name="kana" value="{{ old('kana') }}" @class(['is-invalid' => isset($errors['kana'])])>
                    @error('kana')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <label for="email">メールアドレス<span class="req">必須</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" @class(['is-invalid' => isset($errors['email'])])>
                    @error('email')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <label for="tel">電話番号<span class="req">必須</span></label>
                    <input id="tel" type="tel" name="tel" value="{{ old('tel') }}" autocomplete="tel" @class(['is-invalid' => isset($errors['tel'])])>
                    @error('tel')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <label for="message">お問い合わせ内容<span class="req">必須</span></label>
                    <textarea id="message" name="message" @class(['is-invalid' => isset($errors['message'])])>{{ old('message') }}</textarea>
                    @error('message')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <label>
                        <input type="checkbox" name="privacy" value="1" @checked(old('privacy'))>
                        個人情報の取り扱いに同意する
                    </label>
                    @error('privacy')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">入力内容を確認する</button>
                </div>
            </form>
        </div>
    </section>

@endsection
