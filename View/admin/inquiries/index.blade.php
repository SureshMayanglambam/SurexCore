@extends('admin.layouts.app')

@section('title', lang('Admin.inquiries.title'))
@section('subtitle', lang('Admin.inquiries.subtitle'))

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 1%"></th>
                        <th>{{ lang('Admin.inquiries.received') }}</th>
                        <th>{{ lang('Admin.inquiries.name') }}</th>
                        <th>{{ lang('Admin.inquiries.email') }}</th>
                        <th>{{ lang('Admin.inquiries.content') }}</th>
                        <th class="text-end">{{ lang('Admin.inquiries.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    @php
                        $last = end($item->fields) ?: ['value' => ''];
                    @endphp
                    <tr @class(['fw-semibold' => $item->read_at === null])>
                        <td>
                            @if($item->read_at === null)
                                <span class="badge text-bg-primary">{{ lang('Admin.inquiries.unread') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ date('Y-m-d H:i', strtotime($item->created_at)) }}</td>
                        <td>{{ $item->name ?: '—' }}</td>
                        <td>{{ $item->email ?: '—' }}</td>
                        <td class="text-truncate" style="max-width: 320px">
                            {{ mb_strimwidth(preg_replace('/\s+/u', ' ', (string) $last['value']), 0, 80, '…') }}
                            @if($item->attachment_file)
                                <i class="bi bi-paperclip text-secondary" title="{{ lang('Admin.inquiries.has_file') }}"></i>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url_to('admin.inquiries.show', $item->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ lang('Admin.inquiries.view') }}"><i class="bi bi-eye"></i></a>
                            <form method="post" action="{{ url_to('admin.inquiries.delete', $item->id) }}" class="d-inline" onsubmit="return confirm('{{ lang('Admin.inquiries.confirm_del') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="{{ lang('Admin.inquiries.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">{{ lang('Admin.inquiries.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($pager->getPageCount() > 1)
            <div class="card-footer">{!! $pager->links() !!}</div>
        @endif
    </div>
@endsection
