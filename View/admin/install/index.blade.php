@extends('admin.layouts.auth')

@section('title', 'Install')
@section('box-class', 'login-box-wide')

@section('content')
    <p class="login-box-msg">Welcome! Fill in the details below to set up your site.</p>

    <div class="d-flex flex-wrap gap-2 mb-3 small">
        <span class="badge text-bg-success">PHP {{ $php }}</span>
        @foreach($writable as $path => $ok)
            <span class="badge text-bg-{{ $ok ? 'success' : 'danger' }}">{{ $path }} {{ $ok ? 'writable' : 'not writable' }}</span>
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
        <h6 class="text-uppercase text-secondary mt-2">Site</h6>
        <div class="mb-3">
            <label class="form-label" for="site_name">Site name</label>
            <input id="site_name" type="text" name="site_name" class="form-control" value="{{ $old['site_name'] ?? '' }}" required>
        </div>

        <h6 class="text-uppercase text-secondary mt-4">Administrator account</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_name">Name</label>
                <input id="admin_name" type="text" name="admin_name" class="form-control" value="{{ $old['admin_name'] ?? '' }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_email">Email address</label>
                <input id="admin_email" type="email" name="admin_email" class="form-control" value="{{ $old['admin_email'] ?? '' }}" required autocomplete="username">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="admin_password">Password</label>
                <input id="admin_password" type="password" name="admin_password" class="form-control" minlength="10" required autocomplete="new-password">
                <div class="form-text">Use at least 10 characters.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="password_confirm">Password (confirm)</label>
                <input id="password_confirm" type="password" name="password_confirm" class="form-control" minlength="10" required autocomplete="new-password">
            </div>
        </div>

        <h6 class="text-uppercase text-secondary mt-4">Database <small class="text-lowercase">(you can find these in your server control panel)</small></h6>
        <div class="row">
            <div class="col-8 mb-3">
                <label class="form-label" for="db_host">Host</label>
                <input id="db_host" type="text" name="db_host" class="form-control" value="{{ $old['db_host'] }}" required>
            </div>
            <div class="col-4 mb-3">
                <label class="form-label" for="db_port">Port</label>
                <input id="db_port" type="number" name="db_port" class="form-control" value="{{ $old['db_port'] }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_name">Database name</label>
                <input id="db_name" type="text" name="db_name" class="form-control" value="{{ $old['db_name'] ?? '' }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_prefix">Table prefix</label>
                <input id="db_prefix" type="text" name="db_prefix" class="form-control" value="{{ $old['db_prefix'] }}" pattern="[a-z0-9_]+">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_user">Username</label>
                <input id="db_user" type="text" name="db_user" class="form-control" value="{{ $old['db_user'] ?? '' }}" required autocomplete="off">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="db_pass">Password</label>
                <input id="db_pass" type="password" name="db_pass" class="form-control" autocomplete="off">
            </div>
        </div>

        <div class="d-grid mt-2">
            <button type="submit" class="btn btn-primary btn-lg">Install SurexCore</button>
        </div>
    </form>
@endsection
