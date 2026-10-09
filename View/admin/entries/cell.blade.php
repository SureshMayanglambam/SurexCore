{{-- One list-table cell for a "Show in list" field. $field = definition, $item = entry --}}
@php
    $name  = $field['name'];
    $value = $item->field($name);
@endphp
@switch($field['type'])
    @case('image')
        @if($value)
            <img src="{{ media_url($value) }}" alt="" class="list-thumb rounded border">
        @else
            <span class="text-secondary">—</span>
        @endif
        @break

    @case('file')
        @if($value)
            <a href="{{ media_url($value) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark"></i> {{ basename($value) }}</a>
        @else
            <span class="text-secondary">—</span>
        @endif
        @break

    @case('select')
    @case('radio')
        {{ $item->label($name) ?: '—' }}
        @break

    @case('checkbox')
        @forelse($item->label($name) as $label)
            <span class="badge text-bg-light border">{{ $label }}</span>
        @empty
            <span class="text-secondary">—</span>
        @endforelse
        @break

    @case('relation')
        @php $titles = service('fields')->relationOptions($field['related_type']); @endphp
        @forelse((array) $value as $relatedId)
            <span class="badge text-bg-light border">{{ $titles[$relatedId] ?? '#' . $relatedId }}</span>
        @empty
            <span class="text-secondary">—</span>
        @endforelse
        @break

    @case('toggle')
        @if($value)
            <i class="bi bi-check-circle-fill text-success" title="{{ lang('Admin.yes') }}" aria-label="{{ lang('Admin.yes') }}"></i>
        @else
            <i class="bi bi-dash-circle text-secondary" title="{{ lang('Admin.no') }}" aria-label="{{ lang('Admin.no') }}"></i>
        @endif
        @break

    @case('number')
        {{ $value === null ? '—' : number_format($value, is_float($value) ? 2 : 0) }}
        @break

    @case('date')
        {{ $value ? date('Y-m-d', strtotime($value)) : '—' }}
        @break

    @case('editor')
    @case('textarea')
        {{ mb_strimwidth(trim(strip_tags((string) $value)), 0, 60, '…') ?: '—' }}
        @break

    @default
        {{ mb_strimwidth((string) $value, 0, 60, '…') ?: '—' }}
@endswitch
