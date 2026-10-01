@extends('admin.layouts.app')

@section('title', 'バックアップ')
@section('subtitle', 'このサイトのデータをダウンロードして保存します。')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">バックアップをダウンロード</h3></div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.backup.download') }}" id="backup-form">
                        @csrf
                        <div class="d-grid gap-3">
                            <button type="submit" name="type" value="full" class="btn btn-primary btn-lg text-start" @disabled(! $canZip)>
                                <i class="bi bi-file-earmark-zip me-2"></i> データベース＋アップロード（.zip）
                                <span class="d-block small fw-normal opacity-75 mt-1">投稿・設定・ユーザーと、アップロードした画像・ファイルすべて</span>
                            </button>
                            <button type="submit" name="type" value="db" class="btn btn-outline-secondary btn-lg text-start">
                                <i class="bi bi-database-down me-2"></i> データベースのみ（.sql）
                                <span class="d-block small fw-normal opacity-75 mt-1">投稿・設定・ユーザー（画像・ファイルは含みません）</span>
                            </button>
                        </div>
                    </form>
                    <p class="small text-secondary mt-3 mb-0" id="backup-status">
                        @if($lastBackup)
                            前回のバックアップ：{{ $lastBackup }}
                        @else
                            まだバックアップを作成していません。
                        @endif
                        @unless($canZip)
                            <br><span class="text-danger">サーバーで ZIP 機能が使えないため、データベースのみ保存できます。</span>
                        @endunless
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">内容と復元方法</h3></div>
                <div class="card-body small">
                    <ul class="mb-3 ps-3">
                        <li>操作ログはバックアップに含まれません（テーブル構造のみ）</li>
                        <li>プログラム・テンプレート（App / View など）は含まれません</li>
                        <li>ファイルはダウンロード後、サーバーから自動で削除されます</li>
                    </ul>
                    <p class="fw-semibold mb-1">復元するには</p>
                    <ol class="mb-0 ps-3">
                        <li>phpMyAdmin で <code>database.sql</code>（または .sql ファイル）をインポート</li>
                        <li>zip 内の <code>uploads</code> フォルダを <code>public/uploads/</code> に戻す</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // The download starts after the file is built: show that something is happening.
    document.getElementById('backup-form').addEventListener('submit', e => {
        const status = document.getElementById('backup-status');
        status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> バックアップを作成しています。ダウンロードが始まるまでお待ちください…';
        setTimeout(() => e.target.querySelectorAll('button').forEach(b => b.disabled = true), 0);
        setTimeout(() => e.target.querySelectorAll('button').forEach(b => b.disabled = {{ $canZip ? 'false' : 'b.value === "full"' }}), 8000);
    });
</script>
@endpush
