@extends('admin.layouts.app')

@section('title', lang('Admin.inquiries.title'))
@section('subtitle', lang('Admin.inquiries.received_at', [date('Y-m-d H:i', strtotime($item->created_at))]))

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.inquiries') }}"><i class="bi bi-arrow-left"></i> {{ lang('Admin.inquiries.back') }}</a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <dl class="row mb-0">
                        @foreach($item->fields as $field)
                            <dt class="col-sm-3 text-secondary fw-semibold">{{ $field['label'] }}</dt>
                            <dd class="col-sm-9" style="white-space: pre-wrap">{{ $field['value'] }}</dd>
                        @endforeach
                        <dt class="col-sm-3 text-secondary fw-semibold">{{ lang('Admin.inquiries.attachment') }}</dt>
                        <dd class="col-sm-9">
                            @if($item->attachment_file)
                                <a href="{{ url_to('admin.inquiries.attachment', $item->id) }}"><i class="bi bi-paperclip"></i> {{ $item->attachment_name }}</a>
                            @else
                                {{ lang('Admin.inquiries.none') }}
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-0">
                <div class="card-body d-grid gap-2">
                    @if($item->email)
                        <a class="btn btn-primary" href="mailto:{{ $item->email }}"><i class="bi bi-reply"></i> {{ lang('Admin.inquiries.reply') }}</a>
                    @endif
                    <form method="post" action="{{ url_to('admin.inquiries.unread', $item->id) }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-envelope"></i> {{ lang('Admin.inquiries.mark_unread') }}</button>
                    </form>
                    <form method="post" action="{{ url_to('admin.inquiries.delete', $item->id) }}" class="d-grid" onsubmit="return confirm('{{ lang('Admin.inquiries.confirm_del') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ lang('Admin.inquiries.delete') }}</button>
                    </form>
                </div>
                <div class="card-footer small text-secondary">
                    {{ lang('Admin.inquiries.form') }}：{{ $item->form }}<br>
                    {{ lang('Admin.inquiries.ip') }}：{{ $item->ip_address }}
                </div>
            </div>
        </div>
    </div>
@endsection
