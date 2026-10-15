@extends('admin.layouts.app')

@section('title', lang('Admin.security.title'))
@section('subtitle', lang('Admin.security.subtitle'))

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.security.blocked_title') }}</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>{{ lang('Admin.security.ip') }}</th><th>{{ lang('Admin.security.note') }}</th><th class="text-end">{{ lang('Admin.actions') }}</th></tr></thead>
                        <tbody>
                        @forelse($blocked as $ip => $note)
                            <tr>
                                <td class="text-nowrap"><code>{{ $ip }}</code></td>
                                <td class="small text-secondary">{{ $note }}</td>
                                <td class="text-end">
                                    <form method="post" action="{{ url_to('admin.security.unblock') }}" class="d-inline"
                                          onsubmit="return confirm({{ json_encode(lang('Admin.security.confirm_unblock', [$ip]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $ip }}">
                                        <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-unlock"></i> {{ lang('Admin.security.unblock') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">{{ lang('Admin.security.none_blocked') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-secondary">
                    {!! lang('Admin.security.blocked_help', ['<code>' . esc($files['blocked']) . '</code>']) !!}
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.security.allowed_title') }}</h3></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ lang('Admin.security.allowed_help') }}</p>
                    <p class="small mb-2">
                        {{ lang('Admin.security.your_ip') }} <code>{{ $myIp }}</code>
                        @if($myAllowed)
                            <span class="badge text-bg-success ms-1">{{ lang('Admin.security.on_allow_list') }}</span>
                        @endif
                    </p>
                    <form method="post" action="{{ url_to('admin.security.allowed') }}">
                        @csrf
                        @method('PUT')
                        @php
                            $text = '';
                            foreach ($allowed as $ip => $note) { $text .= $ip . ($note !== '' ? '  # ' . $note : '') . "\n"; }
                        @endphp
                        <textarea name="allowed" rows="6" class="form-control font-monospace small" placeholder="203.0.113.10  # Office">{{ old('allowed', $text) }}</textarea>
                        <div class="form-text">{!! lang('Admin.security.allowed_format', ['<code>' . esc($files['allowed']) . '</code>']) !!}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ lang('Admin.save') }}</button>
                            @unless($myAllowed)
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="const t=this.form.allowed; t.value=(t.value.trim()?t.value.trim()+'\n':'')+{{ json_encode($myIp . '  # ' . $user->name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }};">
                                    <i class="bi bi-plus-lg"></i> {{ lang('Admin.security.add_my_ip') }}
                                </button>
                            @endunless
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.security.rules_title') }}</h3></div>
                <div class="card-body small">
                    <ul class="mb-2 ps-3">
                        <li>{{ $cms->ipBlockAfter > 0 ? lang('Admin.security.rule_ip_block', [$cms->ipBlockAfter, $cms->loginLockMinutes]) : lang('Admin.security.rule_ip_block_off') }}</li>
                        <li>{{ $cms->loginAlertAfter > 0 ? lang('Admin.security.rule_alert', [$cms->loginAlertAfter]) : lang('Admin.security.rule_alert_off') }}</li>
                        <li>{{ $cms->loginLockAfter > 0 ? lang('Admin.security.rule_lock', [$cms->loginLockAfter, $cms->loginLockMinutes]) : lang('Admin.security.rule_lock_off') }}</li>
                    </ul>
                    <p class="text-secondary mb-0">{!! lang('Admin.security.rules_env', ['<code>cms.ipBlockAfter</code>, <code>cms.loginAlertAfter</code>, <code>cms.loginLockAfter</code>, <code>cms.loginLockMinutes</code>']) !!}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
