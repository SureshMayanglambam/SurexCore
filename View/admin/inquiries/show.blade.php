@extends('admin.layouts.app')

@section('title', 'お問い合わせ')
@section('subtitle', date('Y-m-d H:i', strtotime($item->created_at)) . ' 受信')

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url_to('admin.inquiries') }}"><i class="bi bi-arrow-left"></i> 一覧へ戻る</a>
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
                        <dt class="col-sm-3 text-secondary fw-semibold">添付ファイル</dt>
                        <dd class="col-sm-9">
                            @if($item->attachment_file)
                                <a href="{{ url_to('admin.inquiries.attachment', $item->id) }}"><i class="bi bi-paperclip"></i> {{ $item->attachment_name }}</a>
                            @else
                                なし
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
                        <a class="btn btn-primary" href="mailto:{{ $item->email }}"><i class="bi bi-reply"></i> メールで返信</a>
                    @endif
                    <form method="post" action="{{ url_to('admin.inquiries.unread', $item->id) }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-envelope"></i> 未読に戻す</button>
                    </form>
                    <form method="post" action="{{ url_to('admin.inquiries.delete', $item->id) }}" class="d-grid" onsubmit="return confirm('このお問い合わせを削除しますか？')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> 削除</button>
                    </form>
                </div>
                <div class="card-footer small text-secondary">
                    フォーム：{{ $item->form }}<br>
                    IPアドレス：{{ $item->ip_address }}
                </div>
            </div>
        </div>
    </div>
@endsection
