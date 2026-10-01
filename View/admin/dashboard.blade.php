@extends('admin.layouts.app')

@php
    $greeting = $hour < 11 ? 'おはようございます' : ($hour < 18 ? 'こんにちは' : 'こんばんは');
    $palette  = ['#072F1F', '#B4F105', '#F97316', '#22C55E', '#0EA5E9', '#6C7E75'];
    $diff     = $totals['thisMonth'] - $totals['lastMonth'];
    $firstType = array_key_first($types);
    $actionIcons = [
        'auth' => 'box-arrow-in-right', 'entry' => 'pencil-square', 'type' => 'collection', 'user' => 'person',
        'settings' => 'sliders', 'system' => 'cpu', 'media' => 'image',
    ];
@endphp

@section('title', 'ダッシュボード')
@section('subtitle', $greeting . '、' . $user->name . 'さん。' . $siteName . ' の最新の状況です。')

@section('actions')
    <span class="date-pill"><i class="bi bi-calendar3"></i> {{ ja_date() }}</span>
@endsection

@section('content')

    {{-- Row 1: summary + KPIs --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card card-hero h-100 mb-0">
                <div class="card-body">
                    <span class="hero-badge"><span class="dot"></span> 今月</span>
                    <p class="hero-date">{{ date('Y年n月') }}</p>
                    <h2 class="hero-title">
                        @if($totals['thisMonth'] > 0)
                            今月は {{ $totals['thisMonth'] }} 件の投稿を公開しました
                        @elseif($types)
                            今月はまだ公開された投稿がありません
                        @else
                            まずはコンテンツタイプを作成しましょう
                        @endif
                    </h2>
                    @if(count($types) === 1)
                        <a class="hero-link" href="{{ url_to('admin.entries.create', $firstType) }}">新しい投稿を作成 <i class="bi bi-arrow-right"></i></a>
                    @elseif(count($types) > 1)
                        {{-- Several content types: choose which one to write --}}
                        <div class="dropdown hero-dropdown">
                            <button type="button" class="hero-link" data-bs-toggle="dropdown" aria-expanded="false">
                                新しい投稿を作成 <i class="bi bi-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li class="dropdown-header">コンテンツタイプを選択</li>
                                @foreach($types as $slug => $t)
                                    <li><a class="dropdown-item" href="{{ url_to('admin.entries.create', $slug) }}"><i class="bi bi-{{ $t['type']->icon }}"></i> {{ $t['type']->singular }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif($isAdmin)
                        <a class="hero-link" href="{{ url_to('admin.types.create') }}">コンテンツタイプを追加 <i class="bi bi-arrow-right"></i></a>
                    @endif
                    <span class="hero-mark-clip" aria-hidden="true"><i class="bi bi-asterisk hero-mark"></i></span>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card card-stat h-100 mb-0">
                <div class="card-body">
                    <div class="stat-label">公開中の投稿</div>
                    <div class="stat-value">{{ number_format($totals['published']) }}</div>
                    <span @class(['trend', 'trend-up' => $diff > 0, 'trend-down' => $diff < 0])>
                        <i class="bi bi-arrow-{{ $diff < 0 ? 'down-right' : 'up-right' }}"></i>
                        {{ $diff >= 0 ? '+' : '' }}{{ $diff }}（前月比）
                    </span>
                    <div class="sparkline"><canvas id="spark-published" aria-label="月別の公開数" role="img"></canvas></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="card card-stat h-100 mb-0">
                <div class="card-body">
                    <div class="stat-label">公開待ち</div>
                    <div class="stat-value">{{ number_format($totals['draft'] + $totals['scheduled']) }}</div>
                    <div class="stat-split">
                        <span><i class="bi bi-pencil"></i> 下書き {{ $totals['draft'] }} 件</span>
                        <span><i class="bi bi-clock"></i> 予約投稿 {{ $totals['scheduled'] }} 件</span>
                        @isset($people)
                            <span><i class="bi bi-people"></i> ユーザー {{ $people['total'] }} 人</span>
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
                    <h3 class="card-title">公開数の推移</h3>
                    <div class="card-tools small text-secondary">過去{{ count($months) }}か月</div>
                </div>
                <div class="card-body">
                    @if($types)
                        <div class="chart-legend">
                            @foreach($types as $slug => $t)
                                <span><i style="background: {{ $palette[$loop->index % count($palette)] }}"></i> {{ $t['type']->name }}</span>
                            @endforeach
                        </div>
                        <div class="chart-box"><canvas id="chart-activity" aria-label="月別の公開投稿数" role="img"></canvas></div>
                    @else
                        <p class="empty-state">コンテンツタイプはまだありません。</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h3 class="card-title">タイプ別の投稿数</h3></div>
                <div class="card-body d-flex flex-column">
                    @if($totals['published'] + $totals['draft'] + $totals['scheduled'] > 0)
                        <div class="donut-box">
                            <canvas id="chart-types" aria-label="コンテンツタイプ別の投稿数" role="img"></canvas>
                            <div class="donut-center"><small>合計</small><strong>{{ number_format($totals['published'] + $totals['draft'] + $totals['scheduled']) }}</strong></div>
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
                        <p class="empty-state">投稿はまだありません。</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: recent entries + activity / content types --}}
    <div class="row g-4 mb-4 align-items-start">
        <div class="{{ $isAdmin ? 'col-xl-7' : 'col-xl-8' }}">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">最近更新された投稿</h3></div>
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
                                <span class="badge text-bg-warning">予約投稿</span>
                            @else
                                @include('admin.partials.status', ['status' => $item->status])
                            @endif
                        </a>
                    @empty
                        <p class="empty-state">まだ何もありません。</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="{{ $isAdmin ? 'col-xl-5' : 'col-xl-4' }}">
            @if($isAdmin)
                <div class="card mb-0">
                    <div class="card-header">
                        <h3 class="card-title">最近の操作</h3>
                        <div class="card-tools"><a href="{{ url_to('admin.activity') }}" class="small fw-semibold">すべて表示</a></div>
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
                                        <strong>{{ $log->user_name ?? 'システム' }}</strong> {{ $log->description }}
                                        <small>{{ time_ago($log->created_at) }}</small>
                                    </span>
                                </li>
                            @empty
                                <li class="empty-state">操作履歴はまだありません。</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @else
                <div class="card mb-0">
                    <div class="card-header"><h3 class="card-title">あなたのコンテンツ</h3></div>
                    <div class="card-body">
                        @foreach($types as $slug => $t)
                            <a class="list-row" href="{{ url_to('admin.entries', $slug) }}">
                                <span class="list-icon"><i class="bi bi-{{ $t['type']->icon }}"></i></span>
                                <span class="list-main">
                                    <span class="list-title">{{ $t['type']->name }}</span>
                                    <span class="list-meta">公開 {{ $t['stats']['published'] }} 件 · 下書き {{ $t['stats']['draft'] }} 件</span>
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
                        <h3 class="card-title">コンテンツタイプ</h3>
                        <div class="card-tools"><a href="{{ url_to('admin.types') }}" class="small fw-semibold">管理</a></div>
                    </div>
                    <div class="card-body">
                        @forelse($types as $slug => $t)
                            @php $s = $t['stats']; @endphp
                            <div class="progress-row">
                                <div class="progress-head">
                                    <a href="{{ url_to('admin.entries', $slug) }}"><i class="bi bi-{{ $t['type']->icon }}"></i> {{ $t['type']->name }}</a>
                                    <span>公開 {{ $s['published'] }} · 下書き {{ $s['draft'] }} · 予約 {{ $s['scheduled'] }}</span>
                                </div>
                                <div class="progress-track">
                                    <span class="bar-published" style="width: {{ round($s['published'] / $maxEntries * 100, 1) }}%"></span>
                                    <span class="bar-scheduled" style="width: {{ round($s['scheduled'] / $maxEntries * 100, 1) }}%"></span>
                                    <span class="bar-draft" style="width: {{ round($s['draft'] / $maxEntries * 100, 1) }}%"></span>
                                </div>
                            </div>
                        @empty
                            <p class="empty-state">コンテンツタイプはまだありません。<a href="{{ url_to('admin.types.create') }}">作成する</a></p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card mb-0">
                    <div class="card-header"><h3 class="card-title">システム状態</h3></div>
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
                        <h3 class="card-title">ディスク使用量</h3>
                        <div class="card-tools small text-secondary">
                            {{ $disk['checked_at'] }} 時点 ·
                            <a href="{{ url_to('admin.dashboard') }}?refresh_disk=1" class="fw-semibold">再計算</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-baseline gap-2 mb-3">
                            <span class="fs-3 fw-bold">{{ $size($disk['total']) }}</span>
                            <span class="small text-secondary">このサイトの合計（ファイル＋データベース）</span>
                        </div>
                        <ul class="status-list">
                            <li>
                                <span>アップロード <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="管理画面からアップロードした画像・ファイル（public/uploads）。投稿が増えると増えます"></i></span>
                                <strong>{{ $size($disk['uploads']) }}</strong>
                            </li>
                            <li>
                                <span>データベース</span>
                                <strong>{{ $size($disk['database']) }}</strong>
                            </li>
                            <li>
                                <span>ログ・キャッシュ <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="writable フォルダ（ログ・キャッシュ・セッション）。古いログは削除できます"></i></span>
                                <strong>{{ $size($disk['writable']) }}</strong>
                            </li>
                            <li>
                                <span>プログラム・テーマ <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="CMS本体・テンプレート・CSS／JS・ライブラリ（vendor）"></i></span>
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
