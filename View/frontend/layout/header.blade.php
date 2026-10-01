<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-logo" href="{{ url('/') }}">
            @if(setting('site_logo'))
                <img src="{{ media_url(setting('site_logo')) }}" alt="">
            @endif
            <span>{{ $site->name }}</span>
        </a>

        <button class="nav-toggle" type="button" aria-label="メニュー" aria-expanded="false" aria-controls="site-nav">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="site-nav">
            <a href="{{ url('/') }}" @class(['is-current' => url_is('/')])>Home</a>
            <a href="{{ url_to('news') }}" @class(['is-current' => url_is('news*')])>News</a>
            <a href="{{ url_to('contact') }}" @class(['is-current' => url_is('contact*')])>Contact</a>
        </nav>
    </div>
</header>
