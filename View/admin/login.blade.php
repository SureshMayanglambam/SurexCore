@extends('admin.layouts.auth')

@section('title', lang('Admin.login.submit'))

@section('content')
    <p class="login-box-msg text-center">{{ lang('Admin.login.prompt') }}</p>

    @include('admin.partials.alerts')

    <form method="post" action="{{ url_to('admin.login.attempt') }}">
        @csrf
        <div class="input-group mb-3">
            <div class="form-floating">
                <input id="login" type="text" name="login" class="form-control" value="{{ session('old_login') }}"
                       placeholder="" required autofocus autocomplete="username">
                <label for="login">{{ lang('Admin.login.login_id') }}</label>
            </div>
            <div class="input-group-text"><span class="bi bi-person"></span></div>
        </div>
        <div class="input-group mb-3">
            <div class="form-floating">
                <input id="password" type="password" name="password" class="form-control" placeholder="" required autocomplete="current-password">
                <label for="password">{{ lang('Admin.login.password') }}</label>
            </div>
            <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i> {{ lang('Admin.login.submit') }}</button>
        </div>
    </form>
@endsection
