@extends('admin.layouts.app')

@section('title', lang('Admin.activity.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-sm-auto">
                    <select name="user" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">{{ lang('Admin.activity.all_users') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected($userId === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-auto">
                    <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">{{ lang('Admin.activity.all_actions') }}</option>
                        @foreach($actions as $value => $label)
                            <option value="{{ $value }}" @selected($action === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col text-sm-end small text-secondary">
                    {!! lang('Admin.activity.retention', [$retention]) !!}
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ lang('Admin.activity.datetime') }}</th><th>{{ lang('Admin.activity.user') }}</th><th>{{ lang('Admin.activity.action') }}</th><th>{{ lang('Admin.activity.detail') }}</th><th>{{ lang('Admin.activity.ip') }}</th></tr></thead>
                <tbody>
                @forelse($items as $log)
                    @php
                        $group = strstr($log->action, '.', true) ?: $log->action;
                        $color = ['auth' => 'primary', 'entry' => 'success', 'type' => 'warning', 'user' => 'info', 'settings' => 'secondary', 'system' => 'dark', 'media' => 'info'][$group] ?? 'light';
                        if ($log->action === 'auth.failed') { $color = 'danger'; }
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ date('Y-m-d H:i:s', strtotime($log->created_at)) }}</td>
                        <td>{{ $log->user_name ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $color }}">{{ $log->action }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="text-mono" title="{{ $log->user_agent }}">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">{{ lang('Admin.activity.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">{!! $pager->links() !!}</div>
    </div>
@endsection
