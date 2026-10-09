@extends('admin.layouts.app')

@section('title', lang('Admin.preview.title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3"><i class="bi bi-eye-slash me-1"></i> {{ lang('Admin.preview.no_template') }}</h2>
            <p class="mb-2">{{ lang('Admin.preview.intro', [$type->name]) }}</p>
            <ul class="mb-3">
                @if(str_starts_with($expected, '/'))
                    <li>{!! lang('Admin.preview.route_missing', ['<code>'.esc($expected).'</code>', '<code>routes/web.php</code>']) !!}</li>
                @else
                    <li>{!! lang('Admin.preview.create_tpl', ['<code>'.esc($expected).'</code>', '<code>$item</code>']) !!}</li>
                @endif
                <li>{!! lang('Admin.preview.or_change', [esc($type->name), '<code>/recruit</code>']) !!}</li>
            </ul>
            <p class="text-secondary small mb-0">{{ lang('Admin.preview.close_ok') }}</p>
        </div>
    </div>
@endsection
