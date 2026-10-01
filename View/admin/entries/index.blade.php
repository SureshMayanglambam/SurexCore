@extends('admin.layouts.app')

@section('title', $type->name)

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.entries.create', $type->slug) }}"><i class="bi bi-plus-lg"></i> {{ $type->singular }}を追加</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-status">ステータス</label>
                    <select id="f-status" name="status" class="form-select form-select-sm">
                        <option value="">すべて</option>
                        <option value="published" @selected($status === 'published')>公開</option>
                        <option value="draft" @selected($status === 'draft')>下書き</option>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-q">タイトル</label>
                    <input id="f-q" type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="タイトルで検索">
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-from">公開日（開始）</label>
                    <input id="f-from" type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-to">公開日（終了）</label>
                    <input id="f-to" type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> 絞り込み</button>
                    @if($filtered)
                        <a href="{{ url_to('admin.entries', $type->slug) }}" class="btn btn-sm btn-outline-secondary">リセット</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>タイトル</th>
                        @foreach($listFields as $field)
                            <th>{{ $field['label'] }}</th>
                        @endforeach
                        <th>作成者</th><th>ステータス</th><th>公開日時</th><th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <a href="{{ url_to('admin.entries.edit', $type->slug, $item->id) }}" class="fw-semibold">{{ $item->title }}</a>
                            <div class="small text-secondary">{{ $item->slug }}</div>
                        </td>
                        @foreach($listFields as $field)
                            <td>@include('admin.entries.cell', ['field' => $field, 'item' => $item])</td>
                        @endforeach
                        <td>{{ $item->author_name ?? '—' }}</td>
                        <td>
                            @include('admin.partials.status', ['status' => $item->status])
                            @if($item->status === 'published' && $item->published_at > date('Y-m-d H:i:s'))
                                <span class="badge text-bg-warning">予約投稿</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $item->published_at ? date('Y-m-d H:i', strtotime($item->published_at)) : '—' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url_to('admin.entries.edit', $type->slug, $item->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="編集" aria-label="編集"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url_to('admin.entries.duplicate', $type->slug, $item->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="複製（下書きとして作成）" aria-label="複製"><i class="bi bi-copy"></i></button>
                            </form>
                            <form method="post" action="{{ url_to('admin.entries.delete', $type->slug, $item->id) }}" class="d-inline"
                                  onsubmit="return confirm('この{{ $type->singular }}を削除しますか？')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="削除" aria-label="削除"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + count($listFields) }}" class="text-center text-secondary py-4">{{ $type->name }}はまだありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">{!! $pager->links() !!}</div>
    </div>
@endsection
