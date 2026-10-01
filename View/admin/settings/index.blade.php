@extends('admin.layouts.app')

@section('title', '一般設定')

@section('content')
    <form method="post" action="{{ url_to('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <div class="card card-primary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-globe me-1"></i> サイト</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="site_name">サイト名</label>
                            <input id="site_name" type="text" name="site_name" class="form-control" required value="{{ old('site_name', $values['site_name']) }}">
                        </div>
                        <div>
                            <label class="form-label" for="site_tagline">キャッチフレーズ</label>
                            <input id="site_tagline" type="text" name="site_tagline" class="form-control" value="{{ old('site_tagline', $values['site_tagline']) }}">
                        </div>
                    </div>
                </div>

                <div class="card card-info card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-book me-1"></i> 表示</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="posts_per_page">1ページの表示件数</label>
                            <input id="posts_per_page" type="number" name="posts_per_page" class="form-control" min="1" max="100"
                                   value="{{ old('posts_per_page', $values['posts_per_page']) }}">
                            <div class="form-text">ページ分割された一覧で <code>setting('posts_per_page')</code> として使用します。</div>
                        </div>
                        <div>
                            <label class="form-label" for="date_format">日付の形式</label>
                            <select id="date_format" name="date_format" class="form-select">
                                @foreach($dateFormats as $format)
                                    <option value="{{ $format }}" @selected(old('date_format', $values['date_format']) === $format)>{{ date($format) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text"><code>format_date()</code> で使用します。</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-warning card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-tools me-1"></i> メンテナンス</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <div class="form-check form-switch">
                            <input id="maintenance_mode" type="checkbox" name="maintenance_mode" value="1" class="form-check-input"
                                   @checked(old('maintenance_mode', $values['maintenance_mode']) === '1')>
                            <label class="form-check-label" for="maintenance_mode">メンテナンスモード</label>
                        </div>
                        <div class="form-text">訪問者には「メンテナンス中」ページ（HTTP 503）が表示されます。ログイン中のユーザーは通常どおりサイトを閲覧できます。</div>
                    </div>
                </div>

                <div class="card card-success card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-search me-1"></i> 検索エンジン</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="search_noindex" value="0">
                        <div class="form-check form-switch">
                            <input id="search_noindex" type="checkbox" name="search_noindex" value="1" class="form-check-input"
                                   @checked(old('search_noindex', $values['search_noindex']) === '1')>
                            <label class="form-check-label" for="search_noindex">検索エンジンにインデックスさせない</label>
                            <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip"
                               title="テストサイトや公開前のサイト用。robots.txt で全ページをブロックし、各ページに noindex を送ります"></i>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ url_to('sitemap') }}" target="_blank" rel="noopener"><i class="bi bi-diagram-3"></i> sitemap.xml</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ url_to('robots') }}" target="_blank" rel="noopener"><i class="bi bi-robot"></i> robots.txt</a>
                        </div>
                    </div>
                </div>

                <div class="card card-info card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-envelope me-1"></i> メール（SMTP）</h3></div>
                    <div class="card-body">
                        @if($mail['configured'])
                            <dl class="row mb-0 small">
                                <dt class="col-4">サーバー</dt><dd class="col-8 text-mono">{{ $mail['host'] }}:{{ $mail['port'] }}</dd>
                                <dt class="col-4">暗号化</dt><dd class="col-8">{{ $mail['encryption'] }}</dd>
                                <dt class="col-4">送信元</dt><dd class="col-8">{{ $mail['from'] }}</dd>
                            </dl>
                        @else
                            <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle"></i> SMTPサーバーがまだ設定されていません。</p>
                        @endif
                        <div class="form-text">サーバー上の <code>.env</code> ファイル（<code>email.*</code>）で設定します。</div>
                        <button type="submit" form="test-email-form" class="btn btn-outline-info btn-sm mt-2">
                            <i class="bi bi-send"></i> {{ $user->email ?: '自分' }} 宛てにテストメールを送信
                        </button>
                    </div>
                </div>

                <div class="card card-secondary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-shield-lock me-1"></i> 操作ログ</h3></div>
                    <div class="card-body">
                        <label class="form-label" for="activity_retention_days">操作ログの保存期間（日）</label>
                        <input id="activity_retention_days" type="number" name="activity_retention_days" class="form-control" min="1" max="3650"
                               value="{{ old('activity_retention_days', $values['activity_retention_days']) }}">
                        <div class="form-text">保存期間を過ぎたログは自動的に削除されます。</div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> 設定を保存</button>
    </form>

    {{-- Separate form so the test button doesn't submit the settings --}}
    <form id="test-email-form" method="post" action="{{ url_to('admin.settings.testEmail') }}">
        @csrf
    </form>
@endsection
