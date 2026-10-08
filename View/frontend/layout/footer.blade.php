<footer class="footer">
    <div class="container">
        <div class="footer__inner">
            <div class="footer__info">
                <a href="{{ url('/') }}" class="footer__info-logo">{{ $site->name }}</a>
            </div>

            <div class="footer__link">
                <div class="footer__link-inner">
                    <a href="{{ url('/') }}">ホーム</a>
                    <a href="{{ url_to('news') }}">お知らせ</a>
                    <a href="{{ url_to('contact') }}">お問い合わせ</a>
                </div>
            </div>
        </div>
        <p class="footer__copyright">&copy;{{ date('Y') }} {{ $site->name }}. All Rights Reserved.</p>
    </div>
</footer>
