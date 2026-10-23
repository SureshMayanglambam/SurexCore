@extends('admin.layouts.app')

@section('title', lang('Admin.analytics.title'))
@section('subtitle', lang('Admin.analytics.subtitle'))

@section('content')
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-graph-up me-1"></i> {{ lang('Admin.analytics.connection') }}</h3>
                    <div class="card-tools">
                        @if($sample)
                            <span class="badge text-bg-warning">{{ lang('Admin.analytics.sample_badge') }}</span>
                        @elseif($configured)
                            <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> {{ lang('Admin.analytics.connected') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ lang('Admin.analytics.not_connected') }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.analytics.update') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="ga4_property_id">{{ lang('Admin.analytics.property_id') }}</label>
                            <input id="ga4_property_id" name="ga4_property_id" class="form-control text-mono" inputmode="numeric"
                                   placeholder="123456789" value="{{ old('ga4_property_id', $propertyId) }}">
                            <div class="form-text">{{ lang('Admin.analytics.property_help') }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="key_file">{{ lang('Admin.analytics.key_file') }}</label>
                            @if($clientEmail)
                                <div class="small mb-2">
                                    {{ lang('Admin.analytics.key_loaded') }}<br>
                                    <code class="user-select-all">{{ $clientEmail }}</code>
                                </div>
                            @endif
                            <input id="key_file" type="file" name="key_file" class="form-control" accept=".json,application/json">
                            <div class="form-text">{{ $clientEmail ? lang('Admin.analytics.key_replace') : lang('Admin.analytics.key_help') }}</div>
                        </div>

                        <div class="mb-3 p-3 rounded border bg-body-tertiary">
                            <input type="hidden" name="ga4_sample" value="0">
                            <div class="form-check form-switch mb-1">
                                <input id="ga4_sample" type="checkbox" name="ga4_sample" value="1" class="form-check-input" @checked($sample)>
                                <label class="form-check-label fw-semibold" for="ga4_sample">{{ lang('Admin.analytics.sample') }}</label>
                            </div>
                            <div class="form-text mt-0">{{ lang('Admin.analytics.sample_help') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ lang('Admin.save') }}</button>
                    </form>

                    @if($configured || $clientEmail)
                        <hr class="my-4">
                        <div class="d-flex flex-wrap gap-2">
                            @if($configured)
                                <form method="post" action="{{ url_to('admin.analytics.test') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-plug"></i> {{ lang('Admin.analytics.test') }}</button>
                                </form>
                            @endif
                            @if($clientEmail)
                                <form method="post" action="{{ url_to('admin.analytics.removeKey') }}" onsubmit="return confirm({{ json_encode(lang('Admin.analytics.confirm_remove'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ lang('Admin.analytics.remove_key') }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <p class="small text-secondary">{{ lang('Admin.analytics.privacy') }}</p>
        </div>

        <div class="col-lg-6">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-list-ol me-1"></i> {{ lang('Admin.analytics.steps_title') }}</h3></div>
                <div class="card-body">
                    <ol class="ga-steps mb-0">
                        <li>{!! lang('Admin.analytics.step1') !!}</li>
                        <li>{!! lang('Admin.analytics.step2') !!}</li>
                        <li>{!! lang('Admin.analytics.step3') !!}</li>
                        <li>{!! lang('Admin.analytics.step4') !!}
                            @if($clientEmail)<div class="mt-1"><code class="user-select-all">{{ $clientEmail }}</code></div>@endif
                        </li>
                        <li>{!! lang('Admin.analytics.step5') !!}</li>
                        <li>{!! lang('Admin.analytics.step6') !!}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection
