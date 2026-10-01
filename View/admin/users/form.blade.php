@extends('admin.layouts.app')

@section('title', $item ? 'ユーザーを編集' : 'ユーザーを追加')

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.users') }}"><i class="bi bi-arrow-left"></i> 戻る</a>
@endsection

@section('content')
    <form method="post" action="{{ $item ? url_to('admin.users.update', $item->id) : url_to('admin.users.store') }}" class="row" autocomplete="off">
        @csrf
        @if($item) @method('PUT') @endif
        @php $role = old('role', $item->role ?? 'webadmin'); @endphp

        <div class="col-lg-8">
            <div class="card card-primary card-outline mb-4">
                <div class="card-header"><h3 class="card-title">アカウント</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="name">名前</label>
                        <input id="name" type="text" name="name" class="form-control" required value="{{ old('name', $item->name ?? '') }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="role">権限</label>
                            <select id="role" name="role" class="form-select">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="status">ステータス</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" @selected(old('status', $item->status ?? 'active') === 'active')>有効</option>
                                <option value="disabled" @selected(old('status', $item->status ?? 'active') === 'disabled')>無効（ログイン不可）</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="username">ログインID</label>
                            <input id="username" type="text" name="username" class="form-control" value="{{ old('username', $item->username ?? '') }}"
                                   pattern="[a-zA-Z0-9._\-]+" minlength="3" maxlength="60">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email">メールアドレス <span id="email-required" class="text-danger">*</span></label>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $item->email ?? '') }}">
                        </div>
                    </div>
                    <div class="form-text" id="role-help"></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">パスワード</h3></div>
                <div class="card-body">
                    @if($item)<p class="text-secondary small">変更しない場合は空欄のままにしてください。</p>@endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password">パスワード</label>
                            <input id="password" type="password" name="password" class="form-control" minlength="10" autocomplete="new-password" @required(!$item)>
                            <div class="form-text">10文字以上で入力してください。</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password_confirm">パスワード（確認）</label>
                            <input id="password_confirm" type="password" name="password_confirm" class="form-control" autocomplete="new-password" @required(!$item)>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $item ? 'ユーザーを更新' : 'ユーザーを作成' }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">権限について</h3></div>
                <div class="card-body small">
                    <p><span class="badge text-bg-danger">管理者</span> 設定（ユーザー・コンテンツタイプ・操作ログ）を含む、すべての機能を利用できます。<strong>メールアドレスは必須です。</strong></p>
                    <p class="mb-0"><span class="badge text-bg-info">Web管理者</span> コンテンツの管理のみ行えます。<strong>ログインIDまたはメールアドレス</strong>（設定されている方）でログインできます。</p>
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
            ? '管理者はメールアドレスが必須です。ログインIDは任意です。'
            : 'Web管理者はログインIDかメールアドレスのどちらか（または両方）が必要です。どちらでもログインできます。';
    }
    role.addEventListener('change', syncRole);
    syncRole();
</script>
@endpush
