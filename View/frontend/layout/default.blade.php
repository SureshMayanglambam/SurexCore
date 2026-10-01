{{--
    The HTML page around every frontend view. A page fills it like this:

    @extends('frontend.layout.default')
    @section('title', 'About | ' . $site->name)     <title>; omit on the top page (site name + tagline)
    @section('description', '…')                    meta description + og:description
    @section('robots', 'noindex, nofollow')         default: index, follow
    @section('og_image', media_url($item->photo))   optional share image
    @section('body_class', 'page-about')
    @push('styles') <link rel="stylesheet" href="…"> @endpush
    @push('scripts') <script src="…"></script> @endpush
    @section('content') … @endsection
--}}
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $site->name . ($site->tagline ? ' | ' . $site->tagline : ''))</title>
    <meta name="description" content="@yield('description', $site->tagline)">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', current_url())">

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $site->name }}">
    <meta property="og:title" content="@yield('title', $site->name)">
    <meta property="og:description" content="@yield('description', $site->tagline)">
    <meta property="og:url" content="{{ current_url() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif

    <link rel="stylesheet" href="{{ asset('assets/frontend/css/style.css') }}">
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @include('frontend.layout.header')

    <main>
        @yield('content')
    </main>

    @include('frontend.layout.footer')

    <script src="{{ asset('assets/frontend/js/main.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
