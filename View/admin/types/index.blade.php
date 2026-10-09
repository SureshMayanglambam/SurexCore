@extends('admin.layouts.app')

@section('title', lang('Admin.types.title'))

@section('actions')
    <a class="btn btn-primary" href="{{ url_to('admin.types.create') }}"><i class="bi bi-plus-lg"></i> {{ lang('Admin.types.add') }}</a>
@endsection

@section('content')
    <div class="callout callout-info mb-4">
        {!! lang('Admin.types.callout', ['<code>entries(\'slug\')</code>', '<code>entry(\'slug\', $slug)</code>']) !!}
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th style="width:3rem"></th><th>{{ lang('Admin.types.name') }}</th><th>{{ lang('Admin.types.slug') }}</th><th>{{ lang('Admin.types.count') }}</th><th>{{ lang('Admin.types.sort') }}</th><th class="text-end">{{ lang('Admin.types.actions') }}</th></tr></thead>
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
                                  onsubmit="return confirm('{{ lang('Admin.types.confirm_del', [$ct->name]) }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($counts[$slug] > 0)
                                        title="{{ $counts[$slug] > 0 ? lang('Admin.types.del_first') : lang('Admin.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ lang('Admin.types.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
