<!doctype html>
<html lang="ja" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | {{ config('Cms')->appName }}</title>
    <link rel="stylesheet" href="{{ base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/admin/theme.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/admin/admin.css') }}">
</head>
<body class="login-page">
    <div class="login-box @yield('box-class')">
        <div class="card">
            <div class="card-header">
                <span class="login-brand"><i class="bi bi-asterisk"></i> {{ config('Cms')->appName }}</span>
            </div>
            <div class="card-body login-card-body">
                @yield('content')
            </div>
        </div>
    </div>
    <script src="{{ base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
