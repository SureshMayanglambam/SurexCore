@extends('admin.layouts.app')

@section('title', lang('Admin.users.title'))

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.users.create') }}"><i class="bi bi-person-plus"></i> {{ lang('Admin.users.add') }}</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>{{ lang('Admin.users.name') }}</th><th>{{ lang('Admin.users.login_id') }}</th><th>{{ lang('Admin.users.email') }}</th><th>{{ lang('Admin.users.role') }}</th><th>{{ lang('Admin.users.status') }}</th><th>{{ lang('Admin.users.last_login') }}</th><th class="text-end">{{ lang('Admin.users.actions') }}</th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>
                            <a href="{{ url_to('admin.users.edit', $item->id) }}" class="fw-semibold">{{ $item->name }}</a>
                            @if($item->id === $user->id) <span class="badge text-bg-light">{{ lang('Admin.you') }}</span> @endif
                        </td>
                        <td>{{ $item->username ?? '—' }}</td>
                        <td>{{ $item->email ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $item->role === 'admin' ? 'dark' : 'info' }}">{{ $roles[$item->role] ?? $item->role }}</span></td>
                        <td>
                            @if($item->status === 'active')
                                <span class="badge text-bg-success">{{ lang('Admin.users.active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ lang('Admin.users.inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $item->last_login_at ? date('Y-m-d H:i', strtotime($item->last_login_at)) : lang('Admin.never_logged_in') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url_to('admin.users.edit', $item->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            @if($item->id !== $user->id)
                                <form method="post" action="{{ url_to('admin.users.delete', $item->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ lang('Admin.users.confirm_del', [$item->name]) }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
