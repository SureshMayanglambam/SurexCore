@extends('admin.layouts.app')

@php
    $greeting = $hour < 11 ? lang('Admin.dash.greet_morning') : ($hour < 18 ? lang('Admin.dash.greet_afternoon') : lang('Admin.dash.greet_evening'));
    $palette  = ['#072F1F', '#B4F105', '#F97316', '#22C55E', '#0EA5E9', '#6C7E75'];
    $diff     = $totals['thisMonth'] - $totals['lastMonth'];
    $firstType = array_key_first($types);
    $actionIcons = [
        'auth' => 'box-arrow-in-right', 'entry' => 'pencil-square', 'type' => 'collection', 'user' => 'person',
        'settings' => 'sliders', 'system' => 'cpu', 'media' => 'image',
    ];
@endphp

@section('title', lang('Admin.dash.title'))
@section('subtitle', lang('Admin.dash.subtitle', [$greeting, $user->name, $siteName]))

@section('actions')
    <span class="date-pill"><i class="bi bi-calendar3"></i> {{ ja_date() }}</span>
@endsection

@section('content')

    {{-- Row 1: summary + KPIs --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card card-hero h-100 mb-0">
                <div class="card-body">
                    <span class="hero-badge"><span class="dot"></span> {{ lang('Admin.dash.this_month') }}</span>
                    <p class="hero-date">{{ date(lang('Admin.dash.hero_date_fmt')) }}</p>
                    <h2 class="hero-title">
                        @if($totals['thisMonth'] > 0)
                            {{ lang('Admin.dash.published_n', [$totals['thisMonth']]) }}
                        @elseif($types)
                            {{ lang('Admin.dash.none_this_month') }}
                        @else
                            {{ lang('Admin.dash.create_type_first') }}
                        @endif
                    </h2>
                    @if(count($types) === 1)
                        <a class="hero-link" href="{{ url_to('admin.entries.create', $firstType) }}">{{ lang('Admin.dash.new_post') }} <i class="bi bi-arrow-right"></i></a>
                    @elseif(count($types) > 1)
                        {{-- Several content types: choose which one to write --}}
                        <div class="dropdown hero-dropdown">
                            <button type="button" class="hero-link" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ lang('Admin.dash.new_post') }} <i class="bi bi-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li class="dropdown-header">{{ lang('Admin.dash.choose_type') }}</li>
                                @foreach($types as $slug => $t)
                                    <li><a class="dropdown-item" href="{{ url_to('admin.entries.create', $slug) }}"><i class="bi bi-{{ $t['type']->icon }}"></i> {{ $t['type']->singular }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif($isAdmin)
                        <a class="hero-link" href="{{ url_to('admin.types.create') }}">{{ lang('Admin.dash.add_type') }} <i class="bi bi-arrow-right"></i></a>
                    @endif
                    <span class="hero-mark-clip" aria-hidden="true"><i class="bi bi-asterisk hero-mark"></i></span>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card card-stat h-100 mb-0">
                <div class="card-body">
                    <div class="stat-label">{{ lang('Admin.dash.published_posts') }}</div>
                    <div class="stat-value">{{ number_format($totals['published']) }}</div>
                    <span @class(['trend', 'trend-up' => $diff > 0, 'trend-down' => $diff < 0])>
                        <i class="bi bi-arrow-{{ $diff < 0 ? 'down-right' : 'up-right' }}"></i>
                        {{ $diff >= 0 ? '+' : '' }}{{ $diff }}{{ lang('Admin.dash.vs_last_month') }}
                    </span>
                    <div class="sparkline"><canvas id="spark-published" aria-label="{{ lang('Admin.dash.aria_monthly') }}" role="img"></canvas></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="card card-stat h-100 mb-0">
                <div class="card-body">
                    <div class="stat-label">{{ lang('Admin.dash.pending') }}</div>
                    <div class="stat-value">{{ number_format($totals['draft'] + $totals['scheduled']) }}</div>
                    <div class="stat-split">
                        <span><i class="bi bi-pencil"></i> {{ lang('Admin.dash.drafts_n', [$totals['draft']]) }}</span>
                        <span><i class="bi bi-clock"></i> {{ lang('Admin.dash.scheduled_n', [$totals['scheduled']]) }}</span>
                        @isset($people)
                            <span><i class="bi bi-people"></i> {{ lang('Admin.dash.users_n', [$people['total']]) }}</span>
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: charts --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card h-100 mb-0">
                <div class="card-header">
                    <h3 class="card-title">{{ lang('Admin.dash.trend_title') }}</h3>
                    <div class="card-tools small text-secondary">{{ lang('Admin.dash.last_months', [count($months)]) }}</div>
                </div>
                <div class="card-body">
                    @if($types)
                        <div class="chart-legend">
                            @foreach($types as $slug => $t)
                                <span><i style="background: {{ $palette[$loop->index % count($palette)] }}"></i> {{ $t['type']->name }}</span>
                            @endforeach
                        </div>
                        <div class="chart-box"><canvas id="chart-activity" aria-label="{{ lang('Admin.dash.aria_monthly_posts') }}" role="img"></canvas></div>
                    @else
                        <p class="empty-state">{{ lang('Admin.dash.no_types') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.dash.by_type') }}</h3></div>
                <div class="card-body d-flex flex-column">
                    @if($totals['published'] + $totals['draft'] + $totals['scheduled'] > 0)
                        <div class="donut-box">
                            <canvas id="chart-types" aria-label="{{ lang('Admin.dash.aria_by_type') }}" role="img"></canvas>
                            <div class="donut-center"><small>{{ lang('Admin.dash.total') }}</small><strong>{{ number_format($totals['published'] + $totals['draft'] + $totals['scheduled']) }}</strong></div>
                        </div>
                        <ul class="type-list mt-auto">
                            @foreach($types as $slug => $t)
                                @php $all = $t['stats']['published'] + $t['stats']['draft'] + $t['stats']['scheduled']; @endphp
                                <li>
                                    <i style="background: {{ $palette[$loop->index % count($palette)] }}"></i>
                                    <a href="{{ url_to('admin.entries', $slug) }}">{{ $t['type']->name }}</a>
                                    <span>{{ $all }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="empty-state">{{ lang('Admin.dash.no_posts') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: recent entries + activity / content types --}}
    <div class="row g-4 mb-4 align-items-start">
        <div class="{{ $isAdmin ? 'col-xl-7' : 'col-xl-8' }}">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.dash.recent_posts') }}</h3></div>
                <div class="card-body">
                    @forelse($recent as $row)
                        @php $item = $row['entry']; $ct = $row['type']; @endphp
                        <a class="list-row" href="{{ url_to('admin.entries.edit', $ct->slug, $item->id) }}">
                            <span class="list-icon"><i class="bi bi-{{ $ct->icon }}"></i></span>
                            <span class="list-main">
                                <span class="list-title">{{ $item->title }}</span>
                                <span class="list-meta">{{ $ct->name }} · {{ time_ago($item->updated_at) }}@if($item->author_name) · {{ $item->author_name }}@endif</span>
                            </span>
                            @if($item->status === 'published' && $item->published_at > date('Y-m-d H:i:s'))
                                <span class="badge text-bg-warning">{{ lang('Admin.dash.scheduled_badge') }}</span>
                            @else
                                @include('admin.partials.status', ['status' => $item->status])
                            @endif
                        </a>
                    @empty
                        <p class="empty-state">{{ lang('Admin.dash.nothing_yet') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="{{ $isAdmin ? 'col-xl-5' : 'col-xl-4' }}">
            @if($isAdmin)
                <div class="card mb-0">
                    <div class="card-header">
                        <h3 class="card-title">{{ lang('Admin.dash.recent_activity') }}</h3>
                        <div class="card-tools"><a href="{{ url_to('admin.activity') }}" class="small fw-semibold">{{ lang('Admin.dash.view_all') }}</a></div>
                    </div>
                    <div class="card-body">
                        <ul class="timeline-list">
                            @forelse($activity as $log)
                                @php
                                    $group = strstr($log->action, '.', true) ?: $log->action;
                                    $icon  = $log->action === 'auth.failed' ? 'shield-exclamation' : ($actionIcons[$group] ?? 'dot');
                                @endphp
                                <li @class(['is-danger' => $log->action === 'auth.failed'])>
                                    <span class="timeline-icon"><i class="bi bi-{{ $icon }}"></i></span>
                                    <span class="timeline-text">
                                        <strong>{{ $log->user_name ?? lang('Admin.dash.system_user') }}</strong> {{ $log->description }}
                                        <small>{{ time_ago($log->created_at) }}</small>
                                    </span>
                                </li>
                            @empty
                                <li class="empty-state">{{ lang('Admin.dash.no_activity') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @else
                <div class="card mb-0">
                    <div class="card-header"><h3 class="card-title">{{ lang('Admin.dash.your_content') }}</h3></div>
                    <div class="card-body">
                        @foreach($types as $slug => $t)
                            <a class="list-row" href="{{ url_to('admin.entries', $slug) }}">
                                <span class="list-icon"><i class="bi bi-{{ $t['type']->icon }}"></i></span>
                                <span class="list-main">
                                    <span class="list-title">{{ $t['type']->name }}</span>
                                    <span class="list-meta">{{ lang('Admin.dash.pub_draft_n', [$t['stats']['published'], $t['stats']['draft']]) }}</span>
                                </span>
                                <i class="bi bi-chevron-right text-secondary"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Row 4 (Admin): content types + system --}}
    @if($isAdmin)
        <div class="row g-4 mb-4 align-items-start">
            <div class="col-xl-7">
                <div class="card mb-0">
                    <div class="card-header">
                        <h3 class="card-title">{{ lang('Admin.dash.content_types') }}</h3>
                        <div class="card-tools"><a href="{{ url_to('admin.types') }}" class="small fw-semibold">{{ lang('Admin.dash.manage') }}</a></div>
                    </div>
                    <div class="card-body">
                        @forelse($types as $slug => $t)
                            @php $s = $t['stats']; @endphp
                            <div class="progress-row">
                                <div class="progress-head">
                                    <a href="{{ url_to('admin.entries', $slug) }}"><i class="bi bi-{{ $t['type']->icon }}"></i> {{ $t['type']->name }}</a>
                                    <span>{{ lang('Admin.dash.pub_draft_sched', [$s['published'], $s['draft'], $s['scheduled']]) }}</span>
                                </div>
                                <div class="progress-track">
                                    <span class="bar-published" style="width: {{ round($s['published'] / $maxEntries * 100, 1) }}%"></span>
                                    <span class="bar-scheduled" style="width: {{ round($s['scheduled'] / $maxEntries * 100, 1) }}%"></span>
                                    <span class="bar-draft" style="width: {{ round($s['draft'] / $maxEntries * 100, 1) }}%"></span>
                                </div>
                            </div>
                        @empty
                            <p class="empty-state">{{ lang('Admin.dash.no_types_create') }}<a href="{{ url_to('admin.types.create') }}">{{ lang('Admin.dash.create_one') }}</a></p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card mb-0">
                    <div class="card-header"><h3 class="card-title">{{ lang('Admin.dash.system_status') }}</h3></div>
                    <div class="card-body">
                        <ul class="status-list">
                            @foreach($system as [$label, $value, $ok])
                                <li>
                                    <span>{{ $label }}</span>
                                    <strong>
                                        <span @class(['status-dot', 'is-ok' => $ok === true, 'is-bad' => $ok === false, 'is-warn' => $ok === null])></span>
                                        {{ $value }}
                                    </strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @php
                    $size = static fn (int $bytes) => $bytes >= 1073741824 ? round($bytes / 1073741824, 2) . ' GB'
                        : ($bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB');
                @endphp
                <div class="card mb-0 mt-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ lang('Admin.dash.disk_usage') }}</h3>
                        <div class="card-tools small text-secondary">
                            {{ lang('Admin.dash.disk_asof', [$disk['checked_at']]) }} ·
                            <a href="{{ url_to('admin.dashboard') }}?refresh_disk=1" class="fw-semibold">{{ lang('Admin.dash.recalculate') }}</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-baseline gap-2 mb-3">
                            <span class="fs-3 fw-bold">{{ $size($disk['total']) }}</span>
                            <span class="small text-secondary">{{ lang('Admin.dash.disk_total') }}</span>
                        </div>
                        <ul class="status-list">
                            <li>
                                <span>{{ lang('Admin.dash.disk_uploads') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.dash.disk_uploads_tip') }}"></i></span>
                                <strong>{{ $size($disk['uploads']) }}</strong>
                            </li>
                            <li>
                                <span>{{ lang('Admin.dash.disk_database') }}</span>
                                <strong>{{ $size($disk['database']) }}</strong>
                            </li>
                            <li>
                                <span>{{ lang('Admin.dash.disk_writable') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.dash.disk_writable_tip') }}"></i></span>
                                <strong>{{ $size($disk['writable']) }}</strong>
                            </li>
                            <li>
                                <span>{{ lang('Admin.dash.disk_program') }} <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.dash.disk_program_tip') }}"></i></span>
                                <strong>{{ $size($disk['program']) }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
<script src="{{ base_url('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(() => {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.weight = 500;
    Chart.defaults.color = '#6C7E75';

    const months  = @json(array_values($months));
    const palette = @json($palette);
    const series  = @json(array_map(fn ($t) => ['name' => $t['type']->name, 'monthly' => array_values($t['stats']['monthly']), 'total' => $t['stats']['published'] + $t['stats']['draft'] + $t['stats']['scheduled']], array_values($types)));

    // Sparkline: all types together
    const spark = document.getElementById('spark-published');
    if (spark) {
        const sums = months.map((_, i) => series.reduce((s, t) => s + t.monthly[i], 0));
        new Chart(spark, {
            type: 'line',
            data: { labels: months, datasets: [{ data: sums, borderColor: '#22C55E', borderWidth: 2, tension: .45, pointRadius: 0, fill: true,
                backgroundColor: ctx => { const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60); g.addColorStop(0, 'rgba(34,197,94,.25)'); g.addColorStop(1, 'rgba(34,197,94,0)'); return g; } }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
        });
    }

    // Publishing activity: stacked bars per type
    const activity = document.getElementById('chart-activity');
    if (activity) {
        new Chart(activity, {
            type: 'bar',
            data: { labels: months, datasets: series.map((t, i) => ({
                label: t.name, data: t.monthly, backgroundColor: palette[i % palette.length],
                borderRadius: 6, borderSkipped: false, maxBarThickness: 34 })) },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#051C12', padding: 10, cornerRadius: 10 } },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#EEF2F0' }, border: { display: false } } } }
        });
    }

    // Content by type: doughnut
    const donut = document.getElementById('chart-types');
    if (donut) {
        new Chart(donut, {
            type: 'doughnut',
            data: { labels: series.map(t => t.name), datasets: [{ data: series.map(t => t.total),
                backgroundColor: series.map((_, i) => palette[i % palette.length]), borderWidth: 4, borderColor: '#fff', hoverOffset: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false } } }
        });
    }
})();
</script>
@endpush
