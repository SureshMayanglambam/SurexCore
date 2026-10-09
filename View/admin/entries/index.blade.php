@extends('admin.layouts.app')

@section('title', $type->name)

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.entries.create', $type->slug) }}"><i class="bi bi-plus-lg"></i> {{ lang('Admin.entries.add_singular', [$type->singular]) }}</a>
@endsection

@section('content')
    {{-- 一覧 / ゴミ箱 --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item"><a @class(['nav-link py-1', 'active' => ! $trash]) href="{{ url_to('admin.entries', $type->slug) }}">{{ lang('Admin.entries.tab_list') }}</a></li>
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $trash]) href="{{ url_to('admin.entries', $type->slug) }}?trash=1">
                    <i class="bi bi-trash"></i> {{ lang('Admin.entries.tab_trash') }} @if($trashCount)<span class="badge text-bg-secondary ms-1">{{ $trashCount }}</span>@endif
                </a>
            </li>
        </ul>
        @if($trash && $trashCount)
            <form method="post" action="{{ url_to('admin.entries.emptyTrash', $type->slug) }}" class="ms-auto"
                  onsubmit="return confirm('{{ lang('Admin.entries.confirm_empty', [$trashCount]) }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> {{ lang('Admin.entries.empty_trash') }}</button>
            </form>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-end">
                @if($trash)<input type="hidden" name="trash" value="1">@endif
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-status">{{ lang('Admin.entries.f_status') }}</label>
                    <select id="f-status" name="status" class="form-select form-select-sm">
                        <option value="">{{ lang('Admin.all') }}</option>
                        <option value="published" @selected($status === 'published')>{{ lang('Admin.published') }}</option>
                        <option value="draft" @selected($status === 'draft')>{{ lang('Admin.draft') }}</option>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-q">{{ lang('Admin.entries.f_title') }}</label>
                    <input id="f-q" type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ lang('Admin.entries.search_title') }}">
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-from">{{ lang('Admin.entries.f_from') }}</label>
                    <input id="f-from" type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1" for="f-to">{{ lang('Admin.entries.f_to') }}</label>
                    <input id="f-to" type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ lang('Admin.filter') }}</button>
                    @if($filtered)
                        <a href="{{ url_to('admin.entries', $type->slug) }}" class="btn btn-sm btn-outline-secondary">{{ lang('Admin.reset') }}</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ lang('Admin.entries.th_title') }}</th>
                        @foreach($listFields as $field)
                            <th>{{ $field['label'] }}</th>
                        @endforeach
                        <th>{{ lang('Admin.entries.th_author') }}</th><th>{{ lang('Admin.entries.th_status') }}</th><th>{{ lang('Admin.entries.th_published') }}</th><th class="text-end">{{ lang('Admin.entries.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            @if($trash)
                                <span class="fw-semibold">{{ $item->title }}</span>
                                <div class="small text-secondary">{{ lang('Admin.entries.deleted_at', [date('Y-m-d H:i', strtotime($item->deleted_at))]) }}</div>
                            @else
                                <a href="{{ url_to('admin.entries.edit', $type->slug, $item->id) }}" class="fw-semibold">{{ $item->title }}</a>
                                <div class="small text-secondary">{{ $item->slug }}</div>
                            @endif
                        </td>
                        @foreach($listFields as $field)
                            <td>@include('admin.entries.cell', ['field' => $field, 'item' => $item])</td>
                        @endforeach
                        <td>{{ $item->author_name ?? '—' }}</td>
                        <td>
                            @include('admin.partials.status', ['status' => $item->status])
                            @if($item->status === 'published' && $item->published_at > date('Y-m-d H:i:s'))
                                <span class="badge text-bg-warning">{{ lang('Admin.entries.badge_scheduled') }}</span>
                            @elseif($item->status === 'published' && $item->published_until && $item->published_until <= date('Y-m-d H:i:s'))
                                <span class="badge text-bg-secondary">{{ lang('Admin.entries.badge_ended') }}</span>
                            @elseif($item->status === 'published' && $item->published_until)
                                <span class="badge text-bg-light border" title="{{ lang('Admin.entries.until_tip', [date('Y-m-d H:i', strtotime($item->published_until))]) }}">{{ lang('Admin.entries.badge_limited') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $item->published_at ? date('Y-m-d H:i', strtotime($item->published_at)) : '—' }}</td>
                        <td class="text-end text-nowrap">
                          @if($trash)
                            <form method="post" action="{{ url_to('admin.entries.restore', $type->slug, $item->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="{{ lang('Admin.entries.restore') }}" aria-label="{{ lang('Admin.entries.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                            <form method="post" action="{{ url_to('admin.entries.purge', $type->slug, $item->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ lang('Admin.entries.confirm_purge', [$item->title]) }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="{{ lang('Admin.entries.purge') }}" aria-label="{{ lang('Admin.entries.purge') }}"><i class="bi bi-trash3"></i></button>
                            </form>
                          @else
                            <a href="{{ url_to('admin.entries.edit', $type->slug, $item->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ lang('Admin.entries.edit') }}" aria-label="{{ lang('Admin.entries.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url_to('admin.entries.duplicate', $type->slug, $item->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="{{ lang('Admin.entries.duplicate_tip') }}" aria-label="{{ lang('Admin.entries.duplicate') }}"><i class="bi bi-copy"></i></button>
                            </form>
                            <form method="post" action="{{ url_to('admin.entries.delete', $type->slug, $item->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ lang('Admin.entries.confirm_trash', [$type->singular]) }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="{{ lang('Admin.entries.to_trash') }}" aria-label="{{ lang('Admin.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                          @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + count($listFields) }}" class="text-center text-secondary py-4">{{ $trash ? lang('Admin.entries.trash_empty') : lang('Admin.entries.none_yet', [$type->name]) }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">{!! $pager->links() !!}</div>
    </div>
@endsection
