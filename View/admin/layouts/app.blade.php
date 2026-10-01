<!doctype html>
<html lang="ja" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | {{ $siteName }} 管理画面</title>
    <link rel="stylesheet" href="{{ base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/vendor/flatpickr/flatpickr.min.css') }}">
    {{-- Admin look & feel (inspired by the Spark Admin design) --}}
    <link rel="stylesheet" href="{{ base_url('assets/admin/theme.css') }}">
    <link rel="stylesheet" href="{{ base_url('assets/admin/admin.css') }}">
    @stack('head')
</head>
@php
    $initials = mb_strtoupper(mb_substr(trim($user->name), 0, 1));
@endphp
<body class="layout-fixed sidebar-expand-lg">
<div class="app-wrapper">

    {{-- Top bar --}}
    <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
            <ul class="navbar-nav align-items-center gap-2">
                <li class="nav-item">
                    <a class="btn-icon" data-lte-toggle="sidebar" href="#" role="button" aria-label="メニューの開閉"><i class="bi bi-layout-sidebar-inset"></i></a>
                </li>
                @if(!empty($contentTypes))
                    <li class="nav-item dropdown">
                        <button type="button" class="btn-create" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">新規作成</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li class="dropdown-header">新規投稿</li>
                            @foreach($contentTypes as $ct)
                                <li><a class="dropdown-item" href="{{ url_to('admin.entries.create', $ct->slug) }}"><i class="bi bi-{{ $ct->icon }}"></i> {{ $ct->singular }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            </ul>

            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item d-none d-md-block">
                    <a href="{{ site_url('/') }}" class="btn-icon" target="_blank" rel="noopener" title="サイトを表示"><i class="bi bi-box-arrow-up-right"></i></a>
                </li>
                <li class="nav-item d-none d-md-block">
                    <a class="btn-icon" href="#" data-lte-toggle="fullscreen" title="全画面表示">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="header-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">{{ $initials }}</span>
                        <span class="d-none d-md-inline">{{ $user->name }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <div class="fw-semibold text-body">{{ $user->name }}</div>
                            <div class="small">{{ $user->email ?: $user->username }}</div>
                            <span class="role-pill role-{{ $user->role }}">{{ $roles[$user->role] ?? $user->role }}</span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url_to('admin.profile') }}"><i class="bi bi-person"></i> マイプロフィール</a></li>
                        <li>
                            <form method="post" action="{{ url_to('admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> ログアウト</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    {{-- Sidebar --}}
    <aside class="app-sidebar" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ url_to('admin.dashboard') }}" class="brand-link" title="{{ $siteName }}">
                @if($siteLogo)
                    <img src="{{ media_url($siteLogo) }}" alt="" class="brand-logo">
                @else
                    <i class="bi bi-asterisk brand-icon"></i>
                @endif
                <span class="brand-text">{{ $siteName }}</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav>
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
                    <li class="nav-header">メニュー</li>
                    <li class="nav-item">
                        <a href="{{ url_to('admin.dashboard') }}" @class(['nav-link', 'active' => url_is(admin_path())])>
                            <i class="nav-icon bi bi-grid-fill"></i><p>ダッシュボード</p>
                        </a>
                    </li>

                    <li class="nav-header">コンテンツ</li>
                    @foreach($contentTypes as $ct)
                        <li class="nav-item">
                            <a href="{{ url_to('admin.entries', $ct->slug) }}" @class(['nav-link', 'active' => url_is(admin_path('content/' . $ct->slug)) || url_is(admin_path('content/' . $ct->slug . '/*'))])>
                                <i class="nav-icon bi bi-{{ $ct->icon }}"></i><p>{{ $ct->name }}</p>
                            </a>
                        </li>
                    @endforeach
                    @if(empty($contentTypes))
                        <li class="nav-item">
                            @if($isAdmin)
                                <a href="{{ url_to('admin.types.create') }}" class="nav-link">
                                    <i class="nav-icon bi bi-plus-circle"></i><p>コンテンツタイプを追加</p>
                                </a>
                            @else
                                <span class="nav-link"><i class="nav-icon bi bi-info-circle"></i><p>コンテンツはまだありません</p></span>
                            @endif
                        </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{ url_to('admin.media') }}" @class(['nav-link', 'active' => url_is(admin_path('media*'))])>
                            <i class="nav-icon bi bi-images"></i><p>メディア</p>
                        </a>
                    </li>

                    @if($isAdmin)
                        @php $inSettings = url_is(admin_path('settings*')) || url_is(admin_path('branding')); @endphp
                        <li class="nav-header">管理</li>
                        <li @class(['nav-item', 'menu-open' => $inSettings])>
                            <a href="#" @class(['nav-link', 'active' => $inSettings])>
                                <i class="nav-icon bi bi-gear-fill"></i>
                                <p>設定 <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.settings') }}" @class(['nav-link', 'active' => url_is(admin_path('settings'))])><p>一般設定</p></a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.branding') }}" @class(['nav-link', 'active' => url_is(admin_path('branding'))])><p>ブランディング</p></a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.users') }}" @class(['nav-link', 'active' => url_is(admin_path('settings/users*'))])><p>ユーザー</p></a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.types') }}" @class(['nav-link', 'active' => url_is(admin_path('settings/content-types*'))])><p>コンテンツタイプ</p></a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.activity') }}" @class(['nav-link', 'active' => url_is(admin_path('settings/activity-log*'))])><p>操作ログ</p></a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ url_to('admin.backup') }}" @class(['nav-link', 'active' => url_is(admin_path('settings/backup*'))])><p>バックアップ</p></a>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-header">設定</li>
                        <li class="nav-item">
                            <a href="{{ url_to('admin.branding') }}" @class(['nav-link', 'active' => url_is(admin_path('branding'))])>
                                <i class="nav-icon bi bi-palette-fill"></i><p>ブランディング</p>
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>

        <a href="{{ url_to('admin.profile') }}" class="sidebar-profile">
            <span class="avatar">{{ $initials }}</span>
            <span class="sidebar-profile-info">
                <span class="sidebar-profile-name">{{ $user->name }}</span>
                <span class="sidebar-profile-email">{{ $user->email ?: $user->username }}</span>
            </span>
        </a>
    </aside>

    {{-- Page --}}
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">@yield('title')</h1>
                        @hasSection('subtitle')
                            <p class="page-subtitle">@yield('subtitle')</p>
                        @endif
                    </div>
                    <div class="page-actions">@yield('actions')</div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                @include('admin.partials.alerts')
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="footer-left">
            <span class="footer-logo"><i class="bi bi-asterisk"></i> {{ \Config\Cms::NAME }} {{ $version }}</span>
            <span class="footer-separator">|</span>
            <span class="footer-copy">&copy; {{ date('Y') }} {{ $siteName }}</span>
        </div>
    </footer>
</div>

<script src="{{ base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ base_url('assets/vendor/adminlte/adminlte.min.js') }}"></script>
<script src="{{ base_url('assets/vendor/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ base_url('assets/vendor/flatpickr/ja.js') }}"></script>
<script src="{{ base_url('assets/admin/datepicker.js') }}"></script>
<script src="{{ base_url('assets/admin/tooltips.js') }}"></script>
@stack('scripts')
</body>
</html>
