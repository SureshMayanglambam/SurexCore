@extends('admin.layouts.app')

@section('title', lang('Admin.profile.title'))

@section('content')
    <form method="post" action="{{ url_to('admin.profile.update') }}" class="row">
        @csrf
        @method('PUT')

        <div class="col-lg-8">
            <div class="card card-primary card-outline mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="name">{{ lang('Admin.profile.name') }}</label>
                            <input id="name" type="text" name="name" class="form-control" required value="{{ old('name', $user->name) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ lang('Admin.profile.login_id') }}</label>
                            <input type="text" class="form-control" value="{{ $user->username ?? '—' }}" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">{{ lang('Admin.profile.email') }} @if($isAdmin)<span class="text-danger">*</span>@endif</label>
                        <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" @required($isAdmin)>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password">{{ lang('Admin.profile.new_password') }}</label>
                            <input id="password" type="password" name="password" class="form-control" minlength="10" autocomplete="new-password">
                            <div class="form-text">{{ lang('Admin.profile.leave_blank') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password_confirm">{{ lang('Admin.profile.new_password_c') }}</label>
                            <input id="password_confirm" type="password" name="password_confirm" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <label class="form-label" for="current_password">{{ lang('Admin.profile.current_password') }} <span class="text-danger">*</span></label>
                        <input id="current_password" type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        <div class="form-text">{{ lang('Admin.profile.current_needed') }}</div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ lang('Admin.profile.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
