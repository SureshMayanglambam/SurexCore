@extends('admin.layouts.app')

@section('title', $item ? lang('Admin.userform.edit') : lang('Admin.userform.add'))

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.users') }}"><i class="bi bi-arrow-left"></i> {{ lang('Admin.back') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ $item ? url_to('admin.users.update', $item->id) : url_to('admin.users.store') }}" class="row" autocomplete="off">
        @csrf
        @if($item) @method('PUT') @endif
        @php $role = old('role', $item->role ?? 'webadmin'); @endphp

        <div class="col-lg-8">
            <div class="card card-primary card-outline mb-4">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.userform.account') }}</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="name">{{ lang('Admin.userform.name') }}</label>
                        <input id="name" type="text" name="name" class="form-control" required value="{{ old('name', $item->name ?? '') }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="role">{{ lang('Admin.userform.role') }}</label>
                            <select id="role" name="role" class="form-select">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="status">{{ lang('Admin.userform.status') }}</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" @selected(old('status', $item->status ?? 'active') === 'active')>{{ lang('Admin.userform.active') }}</option>
                                <option value="disabled" @selected(old('status', $item->status ?? 'active') === 'disabled')>{{ lang('Admin.userform.disabled') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="username">{{ lang('Admin.userform.login_id') }}</label>
                            <input id="username" type="text" name="username" class="form-control" value="{{ old('username', $item->username ?? '') }}"
                                   pattern="[a-zA-Z0-9._\-]+" minlength="3" maxlength="60">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email">{{ lang('Admin.userform.email') }} <span id="email-required" class="text-danger">*</span></label>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $item->email ?? '') }}">
                        </div>
                    </div>
                    <div class="form-text" id="role-help"></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.userform.password') }}</h3></div>
                <div class="card-body">
                    @if($item)<p class="text-secondary small">{{ lang('Admin.userform.pw_blank') }}</p>@endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password">{{ lang('Admin.userform.password') }}</label>
                            <input id="password" type="password" name="password" class="form-control" minlength="10" autocomplete="new-password" @required(!$item)>
                            <div class="form-text">{{ lang('Admin.userform.pw_min') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password_confirm">{{ lang('Admin.userform.pw_confirm') }}</label>
                            <input id="password_confirm" type="password" name="password_confirm" class="form-control" autocomplete="new-password" @required(!$item)>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $item ? lang('Admin.userform.update') : lang('Admin.userform.create') }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.userform.about_roles') }}</h3></div>
                <div class="card-body small">
                    <p><span class="badge text-bg-danger">{{ lang('Admin.role_admin') }}</span> {!! lang('Admin.userform.admin_desc') !!}</p>
                    <p class="mb-0"><span class="badge text-bg-info">{{ lang('Admin.role_webadmin') }}</span> {!! lang('Admin.userform.webadmin_desc') !!}</p>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Show which login fields the selected role needs (the server enforces the same rules)
    const role = document.getElementById('role'), email = document.getElementById('email');
    const star = document.getElementById('email-required'), help = document.getElementById('role-help');
    function syncRole() {
        const admin = role.value === 'admin';
        email.required = admin;
        star.hidden = !admin;
        help.textContent = admin
            ? @json(lang('Admin.userform.help_admin'))
            : @json(lang('Admin.userform.help_webadmin'));
    }
    role.addEventListener('change', syncRole);
    syncRole();
</script>
@endpush
