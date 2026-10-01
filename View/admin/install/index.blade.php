@extends('admin.layouts.auth')

@section('title', 'インストール')
@section('box-class', 'login-box-wide')

@section('content')
    <p class="login-box-msg">ようこそ！以下の項目を入力して、サイトをセットアップしてください。</p>

    <div class="d-flex flex-wrap gap-2 mb-3 small">
        <span class="badge text-bg-success">PHP {{ $php }}</span>
        @foreach($writable as $path => $ok)
            <span class="badge text-bg-{{ $ok ? 'success' : 'danger' }}">{{ $path }} {{ $ok ? '書き込み可' : '書き込み不可' }}</span>
        @endforeach
    </div>

    @if($errors)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ url_to('install') }}">
        @csrf
        <h6 class="text-uppercase text-secondary mt-2">サイト</h6>
        <div class="mb-3">
            <label class="form-label" for="site_name">サイト名</label>
            <input id="site_name" type="text" name="site_name" class="form-control" value="{{ $old['site_name'] ?? '' }}" required>
        </div>

        <h6 class="text-uppercase text-secondary mt-4">管理者アカウント</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_name">名前</label>
                <input id="admin_name" type="text" name="admin_name" class="form-control" value="{{ $old['admin_name'] ?? '' }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_email">メールアドレス</label>
                <input id="admin_email" type="email" name="admin_email" class="form-control" value="{{ $old['admin_email'] ?? '' }}" required autocomplete="username">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_password">パスワード</label>
                <input id="admin_password" type="password" name="admin_password" class="form-control" minlength="10" required autocomplete="new-password">
                <div class="form-text">10文字以上で入力してください。</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="password_confirm">パスワード（確認）</label>
                <input id="password_confirm" type="password" name="password_confirm" class="form-control" minlength="10" required autocomplete="new-password">
            </div>
        </div>

        <h6 class="text-uppercase text-secondary mt-4">データベース <small class="text-lowercase">（サーバーのコントロールパネルで確認できます）</small></h6>
        <div class="row">
            <div class="col-8 mb-3">
                <label class="form-label" for="db_host">ホスト</label>
                <input id="db_host" type="text" name="db_host" class="form-control" value="{{ $old['db_host'] }}" required>
            </div>
            <div class="col-4 mb-3">
                <label class="form-label" for="db_port">ポート</label>
                <input id="db_port" type="number" name="db_port" class="form-control" value="{{ $old['db_port'] }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_name">データベース名</label>
                <input id="db_name" type="text" name="db_name" class="form-control" value="{{ $old['db_name'] ?? '' }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_prefix">テーブル接頭辞</label>
                <input id="db_prefix" type="text" name="db_prefix" class="form-control" value="{{ $old['db_prefix'] }}" pattern="[a-z0-9_]+">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_user">ユーザー名</label>
                <input id="db_user" type="text" name="db_user" class="form-control" value="{{ $old['db_user'] ?? '' }}" required autocomplete="off">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_pass">パスワード</label>
                <input id="db_pass" type="password" name="db_pass" class="form-control" autocomplete="off">
            </div>
        </div>

        <div class="d-grid mt-2">
            <button type="submit" class="btn btn-primary btn-lg">SurexCore をインストール</button>
        </div>
    </form>
@endsection
