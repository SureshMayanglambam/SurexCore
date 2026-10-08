<header class="header">

    <a class="header__home block" href="{{ url('/') }}">{{ $site->name }}</a>

    <nav class="header__nav">
        <ul class="header__menu menu">
            <li class="menu__item">
                <a class="menu__link" href="{{ url('/') }}">ホーム</a>
            </li>
            <li class="menu__item">
                <a class="menu__link" href="{{ url_to('news') }}">お知らせ</a>
            </li>
            <li class="menu__item">
                <a class="menu__link" href="{{ url_to('contact') }}">お問い合わせ</a>
            </li>
        </ul>
    </nav>

    <div class="hamburger">
        <span></span>
    </div>
</header>
