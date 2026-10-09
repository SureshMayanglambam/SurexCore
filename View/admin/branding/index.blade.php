@extends('admin.layouts.app')

@section('title', lang('Admin.branding.title'))
@section('subtitle', lang('Admin.branding.subtitle'))

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.branding.site_logo') }}</h3></div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.branding.update') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label" for="logo">{{ lang('Admin.branding.upload_new') }}</label>
                        <input id="logo" type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif" required>
                        <div class="form-text">{{ lang('Admin.branding.help') }}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ lang('Admin.branding.upload') }}</button>
                        </div>
                    </form>

                    @if($logo)
                        <hr class="my-4">
                        <form method="post" action="{{ url_to('admin.branding.delete') }}" onsubmit="return confirm('{{ lang('Admin.branding.confirm_del') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ lang('Admin.branding.delete') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.branding.preview') }}</h3></div>
                <div class="card-body">
                    <div class="logo-preview logo-preview-dark">
                        <span class="logo-fallback">
                            @if($logo)
                                <img src="{{ media_url($logo) }}" alt="">
                            @else
                                <i class="bi bi-asterisk"></i>
                            @endif
                            {{ $siteName }}
                        </span>
                    </div>
                    <div class="form-text mb-3">{{ lang('Admin.branding.sidebar') }}</div>

                    <div class="logo-preview logo-preview-light">
                        @if($logo)
                            <img src="{{ media_url($logo) }}" alt="{{ $siteName }}">
                        @else
                            <span class="text-secondary">{{ lang('Admin.branding.no_logo') }}</span>
                        @endif
                    </div>
                    <div class="form-text">{{ lang('Admin.branding.light_bg') }} <code>media_url(setting('site_logo'))</code></div>
                </div>
            </div>
        </div>
    </div>
@endsection
