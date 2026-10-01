{{-- Welcome page of the SurexCore starter. Replace this file with your site's top page. --}}
@extends('frontend.layout.default')

@section('title', 'Welcome to ' . $cms['name'])
@section('description', $cms['name'] . ' — a lightweight CMS for shared hosting, built on CodeIgniter 4 and Blade.')
@section('body_class', 'page-welcome')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
@endpush

@section('content')

    <section class="hero">
        <div class="container">
            <span class="hero__badge">v{{ $cms['version'] }} · Developer Preview</span>
            <h1 class="hero__title">Welcome to <span>{{ $cms['name'] }}</span></h1>
            <p class="hero__lead">
                {{ $cms['name'] }} is a lightweight, secure CMS for building websites on ordinary shared hosting.
                Build content types in the admin panel, and write the website in plain Blade — like Laravel, without the weight.
            </p>
            <div class="hero__actions">
                <a class="btn btn-primary" href="{{ $adminUrl }}"><i class="bi bi-box-arrow-in-right"></i> 管理画面へログイン</a>
                <a class="btn btn-outline" href="{{ url_to('news') }}"><i class="bi bi-newspaper"></i> Sample page</a>
            </div>
            <p class="hero__meta">Developed by {{ $cms['developer'] }}</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="section-title">What is {{ $cms['name'] }}?</h2>
            <p class="section-lead">A WordPress alternative for sites you build yourself: no plugins, no theme layer — just your code and a friendly admin panel for your clients.</p>

            <div class="features">
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-ui-checks-grid"></i></div>
                    <h3>Content types &amp; field builder</h3>
                    <p>Create content types and their fields in the admin, ACF-style: text, editor, images, files, choices, groups, repeaters and conditional logic.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-database"></i></div>
                    <h3>Real tables, like Laravel models</h3>
                    <p>Every content type gets its own database table. Query it with models: <code>where()</code>, <code>orderBy()</code>, <code>paginate()</code>.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-code-slash"></i></div>
                    <h3>Plain Blade frontend</h3>
                    <p>Write routes, controllers and Blade views yourself. Full control of the HTML, CSS and JavaScript.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-eye"></i></div>
                    <h3>Preview before publishing</h3>
                    <p>Editors see unsaved changes on the real frontend page, rendered by its own controller.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-images"></i></div>
                    <h3>Media library &amp; backups</h3>
                    <p>Reuse uploaded images, see where files are used, and download the database and uploads in one click.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-shield-check"></i></div>
                    <h3>Secure by default</h3>
                    <p>CSRF, login rate limiting, a hidden admin URL, roles (管理者 / Web管理者), an activity log and automatic migrations.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-envelope"></i></div>
                    <h3>Forms &amp; mail</h3>
                    <p>A contact form with 入力 → 確認 → 完了, Laravel-style validation and plain-text mail templates.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-search"></i></div>
                    <h3>SEO ready</h3>
                    <p>SEO fields on every entry, an automatic <code>sitemap.xml</code> and <code>robots.txt</code>, and a noindex switch for test sites.</p>
                </div>
                <div class="feature">
                    <div class="feature__icon"><i class="bi bi-hdd-network"></i></div>
                    <h3>Runs on shared hosting</h3>
                    <p>PHP 8.2+ and MySQL. Upload, run the installer, done — no SSH, Node or build step needed.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--alt">
        <div class="container">
            <h2 class="section-title">Start developing</h2>
            <p class="section-lead">Everything you need is in the project. The full guide is in <code>README.md</code>.</p>

            <ol class="steps">
                <li>
                    <div>
                        <h3>Log in to the admin panel</h3>
                        <p>Open <a href="{{ $adminUrl }}">{{ $adminUrl }}</a> with the account you created in the installer.
                            Change the admin URL with <code>cms.adminPath</code> in <code>.env</code>.</p>
                    </div>
                </li>
                <li>
                    <div>
                        <h3>Create a content type</h3>
                        <p>設定 → コンテンツタイプ. Try the sample: name <code>お知らせ</code>, slug <code>news</code>, fields <code>headline</code> (テキスト) and <code>body</code> (本文 / CKEditor).
                            Then open <a href="{{ url_to('news') }}">/news</a>.</p>
                    </div>
                </li>
                <li>
                    <div>
                        <h3>Add your pages</h3>
                        <p>Route in <code>routes/web.php</code> → controller in <code>App/Controller/</code> → model in <code>App/Model/</code> → view in <code>View/frontend/</code>.
                            The News sample shows all four.</p>
                    </div>
                </li>
                <li>
                    <div>
                        <h3>Replace this page</h3>
                        <p>This welcome page is <code>View/frontend/index.blade.php</code>; the design is <code>public/assets/frontend/css/style.css</code>.</p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <section class="section">
        <div class="container about">
            <div>
                <h2 class="section-title">About {{ $cms['name'] }}</h2>
                <p>{{ $cms['name'] }} is a developer preview shared for feedback. It is built on CodeIgniter 4 with BladeOne templates and an admin panel in Japanese.
                    Please report anything that feels wrong, is hard to use or is missing.</p>
            </div>
            <dl>
                <dt>Version</dt><dd>{{ $cms['version'] }}</dd>
                <dt>Framework</dt><dd>{{ $cms['framework'] }}</dd>
                <dt>PHP</dt><dd>{{ $cms['php'] }}</dd>
                <dt>Developed by</dt><dd>{{ $cms['developer'] }}</dd>
            </dl>
        </div>
    </section>

@endsection
