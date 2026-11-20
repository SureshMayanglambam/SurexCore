@extends('admin.layouts.app')

@section('title', lang('Admin.analytics.title'))
@section('subtitle', lang('Admin.analytics.subtitle'))

@php
    $hasProperty = $propertyId !== '';
    $hasKey      = $clientEmail !== null;
    // Third checkpoint: what the dashboard shows right now
    $dashState = $sample ? 'sample' : ($configured ? 'on' : 'off');
    $steps = [
        ['icon' => 'bi-hash',          'where' => 'GA'],
        ['icon' => 'bi-cloud-check',   'where' => 'Cloud'],
        ['icon' => 'bi-key',           'where' => 'Cloud'],
        ['icon' => 'bi-person-plus',   'where' => 'GA'],
        ['icon' => 'bi-upload',        'where' => 'here'],
        ['icon' => 'bi-plug',          'where' => 'here'],
    ];
    $done = [$hasProperty, false, $hasKey, false, $hasProperty && $hasKey, false];
@endphp

@section('content')
    {{-- Status at a glance --}}
    <div class="ga-status card mb-4">
        <div class="card-body">
            <div class="ga-status-head">
                <div>
                    <div class="ga-status-label">{{ lang('Admin.analytics.status_title') }}</div>
                    <div class="ga-status-state">
                        @if($sample)
                            <span class="ga-pill is-sample"><i class="bi bi-magic"></i> {{ lang('Admin.analytics.sample_badge') }}</span>
                        @elseif($configured)
                            <span class="ga-pill is-on"><i class="bi bi-check-circle-fill"></i> {{ lang('Admin.analytics.connected') }}</span>
                        @else
                            <span class="ga-pill is-off"><i class="bi bi-circle"></i> {{ lang('Admin.analytics.not_connected') }}</span>
                        @endif
                    </div>
                </div>
                @if($configured)
                    <form method="post" action="{{ url_to('admin.analytics.test') }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="bi bi-plug"></i> {{ lang('Admin.analytics.test') }}</button>
                    </form>
                @endif
            </div>
            <ol class="ga-checks">
                <li @class(['is-done' => $hasProperty])>
                    <span class="ga-check-dot"><i class="bi {{ $hasProperty ? 'bi-check-lg' : 'bi-hash' }}"></i></span>
                    <span><b>{{ lang('Admin.analytics.chk_property') }}</b><small>{{ $hasProperty ? $propertyId : lang('Admin.analytics.state_missing') }}</small></span>
                </li>
                <li @class(['is-done' => $hasKey])>
                    <span class="ga-check-dot"><i class="bi {{ $hasKey ? 'bi-check-lg' : 'bi-key' }}"></i></span>
                    <span><b>{{ lang('Admin.analytics.chk_key') }}</b><small>{{ $hasKey ? lang('Admin.analytics.state_set') : lang('Admin.analytics.state_missing') }}</small></span>
                </li>
                <li @class(['is-done' => $dashState !== 'off', 'is-sample' => $dashState === 'sample'])>
                    <span class="ga-check-dot"><i class="bi {{ $dashState === 'off' ? 'bi-speedometer2' : ($dashState === 'sample' ? 'bi-magic' : 'bi-check-lg') }}"></i></span>
                    <span><b>{{ lang('Admin.analytics.chk_dashboard') }}</b><small>{{ lang('Admin.analytics.state_' . $dashState) }}</small></span>
                </li>
            </ol>
        </div>
    </div>

    <div class="row g-4">
        {{-- Settings --}}
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-sliders me-1"></i> {{ lang('Admin.analytics.form_title') }}</h3></div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.analytics.update') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="ga4_property_id">
                                <span class="ga-step-no">1</span> {{ lang('Admin.analytics.property_id') }}
                                <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.analytics.property_help') }}"></i>
                            </label>
                            <input id="ga4_property_id" name="ga4_property_id" class="form-control form-control-lg text-mono" inputmode="numeric"
                                   placeholder="123456789" value="{{ old('ga4_property_id', $propertyId) }}">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="key_file">
                                <span class="ga-step-no">2</span> {{ lang('Admin.analytics.key_file') }}
                                <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ $hasKey ? lang('Admin.analytics.key_replace') : lang('Admin.analytics.key_help') }}"></i>
                            </label>
                            @if($hasKey)
                                <div class="ga-key-loaded">
                                    <i class="bi bi-file-earmark-lock2"></i>
                                    <div class="min-w-0">
                                        <small>{{ lang('Admin.analytics.key_loaded') }}</small>
                                        <code class="d-block text-truncate">{{ $clientEmail }}</code>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-copy="{{ $clientEmail }}"><i class="bi bi-clipboard"></i> {{ lang('Admin.analytics.copy') }}</button>
                                </div>
                            @endif
                            <input id="key_file" type="file" name="key_file" class="form-control" accept=".json,application/json">
                        </div>

                        <label class="ga-sample-option">
                            <input type="hidden" name="ga4_sample" value="0">
                            <span class="form-check form-switch mb-0">
                                <input id="ga4_sample" type="checkbox" name="ga4_sample" value="1" class="form-check-input" @checked($sample)>
                            </span>
                            <span>
                                <b><i class="bi bi-magic"></i> {{ lang('Admin.analytics.sample') }}</b>
                                <small>{{ lang('Admin.analytics.sample_help') }}</small>
                            </span>
                        </label>

                        <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-check-lg"></i> {{ lang('Admin.save') }}</button>
                    </form>

                    @if($hasKey)
                        <form method="post" action="{{ url_to('admin.analytics.removeKey') }}" class="mt-3" onsubmit="return confirm({{ json_encode(lang('Admin.analytics.confirm_remove'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i> {{ lang('Admin.analytics.remove_key') }}</button>
                        </form>
                    @endif

                    <div class="ga-privacy">
                        <i class="bi bi-shield-lock"></i>
                        <span>{{ lang('Admin.analytics.privacy') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Setup guide --}}
        <div class="col-xl-7">
            <div class="card mb-0">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-signpost-split me-1"></i> {{ lang('Admin.analytics.steps_title') }}</h3>
                    <div class="card-tools small text-secondary"><i class="bi bi-clock"></i> {{ lang('Admin.analytics.estimate') }}</div>
                </div>
                <div class="card-body">
                    <ol class="ga-timeline">
                        @foreach($steps as $i => $step)
                            @php $n = $i + 1; @endphp
                            <li @class(['is-done' => $done[$i]])>
                                <span class="ga-tl-dot">{{ $done[$i] ? '✓' : $n }}</span>
                                <div class="ga-tl-body">
                                    <div class="ga-tl-head">
                                        <i class="bi {{ $step['icon'] }}"></i>
                                        <b>{{ lang("Admin.analytics.s{$n}_title") }}</b>
                                        <span @class(['ga-where', 'is-' . strtolower($step['where'])])>{{ lang("Admin.analytics.s{$n}_where") }}</span>
                                    </div>
                                    <p>{!! lang("Admin.analytics.s{$n}_text") !!}</p>
                                    @if($n === 4 && $hasKey)
                                        <div class="ga-copy-row">
                                            <code class="text-truncate">{{ $clientEmail }}</code>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $clientEmail }}"><i class="bi bi-clipboard"></i> {{ lang('Admin.analytics.copy') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// "Copy" buttons for the service account e-mail
document.addEventListener('click', async e => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    try { await navigator.clipboard.writeText(btn.dataset.copy); } catch (err) { return; }
    const html = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> ' + {!! json_encode(lang('Admin.analytics.copied'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    setTimeout(() => { btn.innerHTML = html; }, 1500);
});
</script>
@endpush
