<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- SEO --}}
    <title>@yield('title', $site->name)</title>

    <meta name="description" content="@yield('description', '')">
    <meta name="robots" content="@yield('robots', 'index, follow')">

    {{-- Canonical URL --}}
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph --}}
    <meta property="og:locale" content="ja_JP">
    <meta property="og:site_name" content="{{ $site->name }}">
    @if (url_is('/'))
        <meta property="og:type" content="website">
    @else
        <meta property="og:type" content="article">
    @endif
    <meta property="og:title" content="@yield('title', $site->name)">
    <meta property="og:description" content="@yield('description', '')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('assets/frontend/images/ogp.jpg'))">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $site->name)">
    <meta name="twitter:description" content="@yield('description', '')">
    <meta name="twitter:image" content="@yield('og_image', asset('assets/frontend/images/ogp.jpg'))">

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    {{--Font--}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">

    {{-- Shared CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/frontend/lib/wow/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/frontend/css/style.min.css') }}">

    {{-- Page-specific CSS --}}
    @stack('styles')

    {{-- Additional head content --}}
    @yield('head')
</head>

<body class="@yield('body_class')">

    {{-- Header --}}
    @include('frontend.layout.header')

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('frontend.layout.footer')

    {{-- Shared JS --}}
    <script src="{{ asset('assets/frontend/lib/jquery/jquery-3.7.1.min.js') }}" defer></script>
    <script src="{{ asset('assets/frontend/js/script.min.js') }}" defer></script>
    <script src="{{ asset('assets/frontend/lib/wow/wow.min.js') }}" defer></script>
    <script src="{{ asset('assets/frontend/lib/wow/init-wow.js') }}" defer></script>

    {{-- Page-specific JS --}}
    @stack('scripts')

</body>
</html>