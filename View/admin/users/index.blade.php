@extends('admin.layouts.app')

@section('title', 'ユーザー')

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.users.create') }}"><i class="bi bi-person-plus"></i> ユーザーを追加</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>名前</th><th>ログインID</th><th>メールアドレス</th><th>権限</th><th>ステータス</th><th>最終ログイン</th><th class="text-end">操作</th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>
                            <a href="{{ url_to('admin.users.edit', $item->id) }}" class="fw-semibold">{{ $item->name }}</a>
                            @if($item->id === $user->id) <span class="badge text-bg-light">あなた</span> @endif
                        </td>
                        <td>{{ $item->username ?? '—' }}</td>
                        <td>{{ $item->email ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $item->role === 'admin' ? 'dark' : 'info' }}">{{ $roles[$item->role] ?? $item->role }}</span></td>
                        <td>
                            @if($item->status === 'active')
                                <span class="badge text-bg-success">有効</span>
                            @else
                                <span class="badge text-bg-secondary">無効</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $item->last_login_at ? date('Y-m-d H:i', strtotime($item->last_login_at)) : '未ログイン' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url_to('admin.users.edit', $item->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            @if($item->id !== $user->id)
                                <form method="post" action="{{ url_to('admin.users.delete', $item->id) }}" class="d-inline"
                                      onsubmit="return confirm('ユーザー「{{ $item->name }}」を削除しますか？（このユーザーが作成したコンテンツは残ります）')">
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
