@extends('admin.layouts.app')

@section('title', 'コンテンツタイプ')

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.types.create') }}"><i class="bi bi-plus-lg"></i> コンテンツタイプを追加</a>
@endsection

@section('content')
    <div class="callout callout-info mb-4">
        コンテンツタイプ（例：お知らせ、店舗）ごとに、専用のメニューと編集画面が作成されます。
        サイト側では、コントローラーで <code>entries('slug')</code> や <code>entry('slug', $slug)</code> を使って投稿を表示できます。
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th style="width:3rem"></th><th>名前</th><th>スラッグ</th><th>投稿数</th><th>表示順</th><th class="text-end">操作</th></tr></thead>
                <tbody>
                @forelse($types as $slug => $ct)
                    <tr>
                        <td><i class="bi bi-{{ $ct->icon }} icon-preview"></i></td>
                        <td>
                            <a href="{{ url_to('admin.types.edit', $ct->id) }}" class="fw-semibold">{{ $ct->name }}</a>
                            @if($ct->description)<div class="small text-secondary">{{ $ct->description }}</div>@endif
                        </td>
                        <td><code>{{ $slug }}</code></td>
                        <td><a href="{{ url_to('admin.entries', $slug) }}">{{ $counts[$slug] }}</a></td>
                        <td>{{ $ct->sort_order }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url_to('admin.types.edit', $ct->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url_to('admin.types.delete', $ct->id) }}" class="d-inline"
                                  onsubmit="return confirm('コンテンツタイプ「{{ $ct->name }}」を削除しますか？')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($counts[$slug] > 0)
                                        title="{{ $counts[$slug] > 0 ? '先に投稿を削除してください' : '削除' }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">コンテンツタイプはまだありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
