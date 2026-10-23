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

    {{-- Google Analytics (Settings → Analytics). Filled in by the script below from Analytics::data. --}}
    @if($gaConfigured)
        <div class="card mb-4 ga-card" id="ga-card" data-url="{{ url_to('admin.analytics.data') }}">
            <div class="card-header flex-wrap">
                <h3 class="card-title"><i class="bi bi-graph-up-arrow me-1"></i> {{ lang('Admin.dash.ga.title') }}
                    <span class="badge text-bg-warning ms-2 ga-sample-badge" hidden title="{{ lang('Admin.dash.ga.sample_tip') }}">{{ lang('Admin.dash.ga.sample') }}</span></h3>
                <div class="card-tools d-flex align-items-center gap-3">
                    <span class="ga-live small" hidden><span class="ga-dot" aria-hidden="true"></span> <span data-ga="active"></span></span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ lang('Admin.dash.ga.range') }}">
                        @foreach(\App\Libraries\GoogleAnalytics::RANGES as $d)
                            <button type="button" @class(['btn btn-outline-secondary', 'active' => $d === 28]) data-ga-days="{{ $d }}" aria-pressed="{{ $d === 28 ? 'true' : 'false' }}">{{ lang('Admin.dash.ga.days', [$d]) }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="ga-loading small text-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ lang('Admin.dash.ga.loading') }}</div>
                <div class="ga-error alert alert-warning mb-0" role="alert" hidden></div>
                <div class="ga-body" hidden>
                    <div class="row g-3 mb-4">
                        @foreach(['activeUsers' => 'users', 'sessions' => 'sessions', 'screenPageViews' => 'views', 'averageSessionDuration' => 'engagement'] as $metric => $label)
                            <div class="col-6 col-xl-3">
                                <div class="ga-kpi" data-ga-kpi="{{ $metric }}">
                                    <div class="ga-kpi-label">{{ lang('Admin.dash.ga.' . $label) }}</div>
                                    <div class="ga-kpi-value">—</div>
                                    <div class="ga-kpi-delta"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <h4 class="ga-sub">{{ lang('Admin.dash.ga.per_day') }}</h4>
                            <div class="ga-chart"><canvas id="ga-chart" role="img" aria-label="{{ lang('Admin.dash.ga.per_day') }}"></canvas></div>
                        </div>
                        <div class="col-lg-4">
                            <h4 class="ga-sub">{{ lang('Admin.dash.ga.devices') }}</h4>
                            <div class="ga-donut">
                                <canvas id="ga-devices-chart" role="img" aria-label="{{ lang('Admin.dash.ga.devices') }}"></canvas>
                                <div class="ga-donut-center" aria-hidden="true"><strong data-ga="devices-total"></strong><span>{{ lang('Admin.dash.ga.users') }}</span></div>
                            </div>
                            <table class="ga-table ga-legend" data-ga="devices"></table>
                        </div>
                        <div class="col-lg-7">
                            <h4 class="ga-sub">{{ lang('Admin.dash.ga.pages') }}</h4>
                            <table class="ga-table" data-ga="pages"></table>
                        </div>
                        <div class="col-lg-5">
                            <h4 class="ga-sub">{{ lang('Admin.dash.ga.sources') }}</h4>
                            <table class="ga-table" data-ga="sources"></table>
                        </div>
                    </div>
                    <div class="small text-secondary mt-3" data-ga="updated"></div>
                </div>
            </div>
        </div>
    @elseif($user->role === 'admin')
        <div class="card mb-4 ga-connect">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <i class="bi bi-graph-up-arrow fs-3 text-secondary" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ lang('Admin.dash.ga.connect_title') }}</div>
                    <div class="small text-secondary">{{ lang('Admin.dash.ga.connect_text') }}</div>
                </div>
                <a href="{{ url_to('admin.analytics') }}" class="btn btn-outline-primary btn-sm">{{ lang('Admin.dash.ga.connect_btn') }}</a>
            </div>
        </div>
    @endif

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
@if($gaConfigured)
<script>
// Google Analytics card: data from Analytics::data (cached on the server for an hour).
// Text from Google (page titles, paths) is always set with textContent, never as HTML.
(() => {
    const card = document.getElementById('ga-card');
    if (!card) return;

    const L   = @json(lang('Admin.dash.ga'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    const q   = sel => card.querySelector(sel);
    const num = n => new Intl.NumberFormat(document.documentElement.lang).format(Math.round(n));
    const dur = s => Math.floor(s / 60) + ':' + String(Math.round(s % 60)).padStart(2, '0');
    let chart = null;

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }

    function state(name, message) {
        q('.ga-loading').hidden = name !== 'loading';
        q('.ga-error').hidden = name !== 'error';
        q('.ga-body').hidden = name !== 'ready';
        if (name === 'error') q('.ga-error').textContent = message;
    }

    // Change vs the previous period: arrow + signed % in text, so it isn't color alone.
    function delta(box, cur, prev) {
        box.replaceChildren();
        box.className = 'ga-kpi-delta';
        if (!prev) { box.textContent = L.no_compare; return; }
        const pct = (cur - prev) / prev * 100;
        const up = pct >= 0;
        box.classList.add(Math.abs(pct) < 0.5 ? 'is-flat' : (up ? 'is-up' : 'is-down'));
        box.append(el('i', 'bi ' + (up ? 'bi-arrow-up-right' : 'bi-arrow-down-right')),
                   ' ' + (up ? '+' : '') + pct.toFixed(1) + '% ' + L.vs_prev);
    }

    // Fixed colour per device (colour follows the device, not its rank). Validated for colour blindness;
    // the legend table repeats name, number and share, so colour is never the only clue.
    const DEVICE_COLORS = { mobile: '#16A34A', desktop: '#2A78D6', tablet: '#EDA100', 'smart tv': '#6250D6' };
    let donut = null;
    const pct = (n, total) => total > 0 ? (n / total * 100).toFixed(1) + '%' : '—';

    // Table: rows of cells; a cell is text, or { text, sub, cls, bar } (bar = 0..1 share, drawn under the %).
    function table(container, head, rows) {
        container.replaceChildren();
        if (!rows.length) { const tr = el('tr'); const td = el('td', 'text-secondary', L.no_data); tr.append(td); container.append(tr); return; }
        const thead = el('thead'); const hr = el('tr');
        head.forEach(h => hr.append(el('th', h.cls || '', h.text)));
        thead.append(hr);
        const tbody = el('tbody');
        rows.forEach(cells => {
            const tr = el('tr');
            cells.forEach(c => {
                const cell = typeof c === 'object' ? c : { text: String(c) };
                const td = el('td', cell.cls || '');
                if (cell.swatch) { const sw = el('span', 'ga-swatch'); sw.style.background = cell.swatch; td.append(sw); }
                const main = el('span', 'ga-cell-main', cell.text);
                if (cell.title) main.title = cell.title;
                td.append(main);
                if (cell.sub) td.append(el('span', 'ga-cell-sub', cell.sub));
                if (cell.bar !== undefined) { const t = el('span', 'ga-share-track'); const f = el('span', 'ga-share-fill'); f.style.width = Math.max(2, cell.bar * 100) + '%'; t.append(f); td.append(t); }
                tr.append(td);
            });
            tbody.append(tr);
        });
        container.append(thead, tbody);
    }

    function devices(list) {
        const total = list.reduce((s, d) => s + d.users, 0);
        q('[data-ga=devices-total]').textContent = num(total);
        donut?.destroy();
        donut = new Chart(document.getElementById('ga-devices-chart'), {
            type: 'doughnut',
            data: { labels: list.map(d => L.device[d.name] ?? d.name),
                    datasets: [{ data: list.map(d => d.users), backgroundColor: list.map(d => DEVICE_COLORS[d.name] ?? '#8A9A92'),
                                 borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#051C12', padding: 10, cornerRadius: 10,
                    callbacks: { label: c => ' ' + c.label + ': ' + num(c.parsed) + ' (' + pct(c.parsed, total) + ')' } } } }
        });
        table(q('[data-ga=devices]'), [{ text: L.col_device }, { text: L.users, cls: 'num' }, { text: L.col_share, cls: 'num' }],
            list.map(d => [{ text: L.device[d.name] ?? d.name, swatch: DEVICE_COLORS[d.name] ?? '#8A9A92' },
                           { text: num(d.users), cls: 'num' }, { text: pct(d.users, total), cls: 'num' }]));
    }

    function render(r) {
        const fmt = { activeUsers: num, sessions: num, screenPageViews: num, averageSessionDuration: dur };
        card.querySelectorAll('[data-ga-kpi]').forEach(box => {
            const m = box.dataset.gaKpi;
            box.querySelector('.ga-kpi-value').textContent = fmt[m](r.current[m]);
            delta(box.querySelector('.ga-kpi-delta'), r.current[m], r.previous[m]);
        });

        const labels = r.daily.map(d => { const [, mo, da] = d.date.split('-'); return +mo + '/' + +da; });
        chart?.destroy();
        chart = new Chart(document.getElementById('ga-chart'), {
            type: 'line',
            data: { labels, datasets: [{ label: L.users, data: r.daily.map(d => d.users), borderColor: '#16A34A', borderWidth: 2,
                tension: .35, pointRadius: 0, pointHoverRadius: 5, pointHitRadius: 12, fill: true,
                backgroundColor: ctx => { const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height); g.addColorStop(0, 'rgba(22,163,74,.18)'); g.addColorStop(1, 'rgba(22,163,74,0)'); return g; } }] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#051C12', padding: 10, cornerRadius: 10,
                    callbacks: { label: c => ' ' + L.users + ': ' + num(c.parsed.y), afterLabel: c => ' ' + L.views + ': ' + num(r.daily[c.dataIndex].views) } } },
                scales: { x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                          y: { beginAtZero: true, ticks: { precision: 0, maxTicksLimit: 5 }, grid: { color: '#EEF2F0' }, border: { display: false } } } }
        });

        devices(r.devices);

        // Share of all page views / sessions in the period (not just of the rows shown)
        const views = r.current.screenPageViews, sessions = r.current.sessions;
        table(q('[data-ga=pages]'), [{ text: '#', cls: 'rank' }, { text: L.col_page }, { text: L.views, cls: 'num' }, { text: L.col_share, cls: 'share' }],
            r.pages.map((p, i) => [{ text: i + 1, cls: 'rank' },
                { text: p.title && p.title !== '(not set)' ? p.title : p.path, sub: p.path, title: p.path, cls: 'name' },
                { text: num(p.views), cls: 'num' }, { text: pct(p.views, views), bar: views ? p.views / views : 0, cls: 'share' }]));
        table(q('[data-ga=sources]'), [{ text: L.col_source }, { text: L.sessions, cls: 'num' }, { text: L.col_share, cls: 'share' }],
            r.sources.map(s => [{ text: L.channel[s.name] ?? s.name, cls: 'name' },
                { text: num(s.sessions), cls: 'num' }, { text: pct(s.sessions, sessions), bar: sessions ? s.sessions / sessions : 0, cls: 'share' }]));

        const live = q('.ga-live');
        live.hidden = r.active_now === null || r.active_now === undefined;
        q('[data-ga=active]').textContent = L.active_now.replace('{0}', num(r.active_now ?? 0));
        q('[data-ga=updated]').textContent = L.updated.replace('{0}', new Date(r.fetched).toLocaleString(document.documentElement.lang, { dateStyle: 'medium', timeStyle: 'short' }));
    }

    async function load(days) {
        state('loading');
        try {
            const res = await fetch(card.dataset.url + '?days=' + days, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (data.error) { state('error', data.error); return; }
            if (!data.report) { state('error', L.load_failed); return; }
            render(data.report);
            q('.ga-sample-badge').hidden = !data.sample;
            state('ready');
        } catch (e) {
            state('error', L.load_failed);
        }
    }

    card.querySelectorAll('[data-ga-days]').forEach(btn => btn.addEventListener('click', () => {
        card.querySelectorAll('[data-ga-days]').forEach(b => { b.classList.toggle('active', b === btn); b.setAttribute('aria-pressed', String(b === btn)); });
        load(+btn.dataset.gaDays);
    }));
    load(28);
})();
</script>
@endif
@endpush
