@extends('admin.layouts.app')

@section('title', lang('Admin.settings.title'))

@section('content')
    <form method="post" action="{{ url_to('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <div class="card card-primary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-globe me-1"></i> {{ lang('Admin.settings.site') }}</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="site_name">{{ lang('Admin.settings.site_name') }}</label>
                            <input id="site_name" type="text" name="site_name" class="form-control" required value="{{ old('site_name', $values['site_name']) }}">
                        </div>
                        <div>
                            <label class="form-label" for="site_tagline">{{ lang('Admin.settings.tagline') }}</label>
                            <input id="site_tagline" type="text" name="site_tagline" class="form-control" value="{{ old('site_tagline', $values['site_tagline']) }}">
                        </div>
                    </div>
                </div>

                <div class="card card-info card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-book me-1"></i> {{ lang('Admin.settings.display') }}</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="posts_per_page">{{ lang('Admin.settings.per_page') }}</label>
                            <input id="posts_per_page" type="number" name="posts_per_page" class="form-control" min="1" max="100"
                                   value="{{ old('posts_per_page', $values['posts_per_page']) }}">
                            <div class="form-text">{!! lang('Admin.settings.per_page_help', ["<code>setting('posts_per_page')</code>"]) !!}</div>
                        </div>
                        <div>
                            <label class="form-label" for="date_format">{{ lang('Admin.settings.date_format') }}</label>
                            <select id="date_format" name="date_format" class="form-select">
                                @foreach($dateFormats as $format)
                                    <option value="{{ $format }}" @selected(old('date_format', $values['date_format']) === $format)>{{ date($format) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{!! lang('Admin.settings.date_format_help', ['<code>format_date()</code>']) !!}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-warning card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-tools me-1"></i> {{ lang('Admin.settings.maintenance') }}</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <div class="form-check form-switch">
                            <input id="maintenance_mode" type="checkbox" name="maintenance_mode" value="1" class="form-check-input"
                                   @checked(old('maintenance_mode', $values['maintenance_mode']) === '1')>
                            <label class="form-check-label" for="maintenance_mode">{{ lang('Admin.settings.maintenance_mode') }}</label>
                        </div>
                        <div class="form-text">{{ lang('Admin.settings.maintenance_help') }}</div>
                    </div>
                </div>

                <div class="card card-success card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-search me-1"></i> {{ lang('Admin.settings.search') }}</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="search_noindex" value="0">
                        <div class="form-check form-switch">
                            <input id="search_noindex" type="checkbox" name="search_noindex" value="1" class="form-check-input"
                                   @checked(old('search_noindex', $values['search_noindex']) === '1')>
                            <label class="form-check-label" for="search_noindex">{{ lang('Admin.settings.noindex') }}</label>
                            <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip"
                               title="{{ lang('Admin.settings.noindex_tip') }}"></i>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ url_to('sitemap') }}" target="_blank" rel="noopener"><i class="bi bi-diagram-3"></i> sitemap.xml</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ url_to('robots') }}" target="_blank" rel="noopener"><i class="bi bi-robot"></i> robots.txt</a>
                        </div>
                    </div>
                </div>

                <div class="card card-primary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-inbox me-1"></i> {{ lang('Admin.settings.inquiries') }}</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="store_inquiries" value="0">
                        <div class="form-check form-switch">
                            <input id="store_inquiries" type="checkbox" name="store_inquiries" value="1" class="form-check-input"
                                   @checked(old('store_inquiries', $values['store_inquiries']) === '1')>
                            <label class="form-check-label" for="store_inquiries">{{ lang('Admin.settings.store_inquiries') }}</label>
                            <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip"
                               title="{{ lang('Admin.settings.store_tip') }}"></i>
                        </div>
                    </div>
                </div>

                <div class="card card-info card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-envelope me-1"></i> {{ lang('Admin.settings.mail') }}</h3></div>
                    <div class="card-body">
                        @if($mail['configured'])
                            <dl class="row mb-0 small">
                                <dt class="col-4">{{ lang('Admin.settings.mail_server') }}</dt><dd class="col-8 text-mono">{{ $mail['host'] }}:{{ $mail['port'] }}</dd>
                                <dt class="col-4">{{ lang('Admin.settings.mail_encryption') }}</dt><dd class="col-8">{{ $mail['encryption'] }}</dd>
                                <dt class="col-4">{{ lang('Admin.settings.mail_from') }}</dt><dd class="col-8">{{ $mail['from'] }}</dd>
                            </dl>
                        @else
                            <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle"></i> {{ lang('Admin.settings.mail_not_set') }}</p>
                        @endif
                        <div class="form-text">{!! lang('Admin.settings.mail_env_help', ['<code>.env</code>', '<code>email.*</code>']) !!}</div>
                        <button type="submit" form="test-email-form" class="btn btn-outline-info btn-sm mt-2">
                            <i class="bi bi-send"></i> {{ lang('Admin.settings.test_send', [$user->email ?: lang('Admin.settings.test_self')]) }}
                        </button>
                    </div>
                </div>

                <div class="card card-secondary card-outline mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-shield-lock me-1"></i> {{ lang('Admin.settings.activity') }}</h3></div>
                    <div class="card-body">
                        <label class="form-label" for="activity_retention_days">{{ lang('Admin.settings.retention_label') }}</label>
                        <input id="activity_retention_days" type="number" name="activity_retention_days" class="form-control" min="1" max="3650"
                               value="{{ old('activity_retention_days', $values['activity_retention_days']) }}">
                        <div class="form-text">{{ lang('Admin.settings.retention_help') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ lang('Admin.settings.save') }}</button>
    </form>

    {{-- Separate form so the test button doesn't submit the settings --}}
    <form id="test-email-form" method="post" action="{{ url_to('admin.settings.testEmail') }}">
        @csrf
    </form>
@endsection
