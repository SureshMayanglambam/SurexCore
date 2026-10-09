@extends('admin.layouts.app')

@section('title', lang('Admin.backup.title'))
@section('subtitle', lang('Admin.backup.subtitle'))

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.backup.download') }}</h3></div>
                <div class="card-body">
                    <form method="post" action="{{ url_to('admin.backup.download') }}" id="backup-form">
                        @csrf
                        <div class="d-grid gap-3">
                            <button type="submit" name="type" value="full" class="btn btn-primary btn-lg text-start" @disabled(! $canZip)>
                                <i class="bi bi-file-earmark-zip me-2"></i> {{ lang('Admin.backup.db_uploads') }}
                                <span class="d-block small fw-normal opacity-75 mt-1">{{ lang('Admin.backup.db_uploads_sub') }}</span>
                            </button>
                            <button type="submit" name="type" value="db" class="btn btn-outline-secondary btn-lg text-start">
                                <i class="bi bi-database-down me-2"></i> {{ lang('Admin.backup.db_only') }}
                                <span class="d-block small fw-normal opacity-75 mt-1">{{ lang('Admin.backup.db_only_sub') }}</span>
                            </button>
                        </div>
                    </form>
                    <p class="small text-secondary mt-3 mb-0" id="backup-status">
                        @if($lastBackup)
                            {{ lang('Admin.backup.last', [$lastBackup]) }}
                        @else
                            {{ lang('Admin.backup.none_yet') }}
                        @endif
                        @unless($canZip)
                            <br><span class="text-danger">{{ lang('Admin.backup.no_zip') }}</span>
                        @endunless
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-header"><h3 class="card-title">{{ lang('Admin.backup.contents') }}</h3></div>
                <div class="card-body small">
                    <ul class="mb-3 ps-3">
                        <li>{{ lang('Admin.backup.c1') }}</li>
                        <li>{{ lang('Admin.backup.c2') }}</li>
                        <li>{{ lang('Admin.backup.c3') }}</li>
                    </ul>
                    <p class="fw-semibold mb-1">{{ lang('Admin.backup.restore_how') }}</p>
                    <ol class="mb-0 ps-3">
                        <li>{!! lang('Admin.backup.r1', ['<code>database.sql</code>']) !!}</li>
                        <li>{!! lang('Admin.backup.r2', ['<code>uploads</code>', '<code>public/uploads/</code>']) !!}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4 mb-0">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-cloud-arrow-up me-1"></i> {{ lang('Admin.backup.deploy_title') }}</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                {!! lang('Admin.backup.deploy_desc', ['<code>_deploy/README.txt</code>']) !!}
            </p>
            <form method="post" action="{{ url_to('admin.backup.package') }}" class="row g-3 deploy-form">
                @csrf
                <div class="col-md-6 d-grid">
                    <button type="submit" name="type" value="site" class="btn btn-primary text-start py-3">
                        <i class="bi bi-box-seam me-2"></i> {{ lang('Admin.backup.first') }}
                        <span class="d-block small fw-normal opacity-75 mt-1">{{ lang('Admin.backup.first_sub') }}</span>
                    </button>
                </div>
                <div class="col-md-6 d-grid">
                    <button type="submit" name="type" value="code" class="btn btn-outline-secondary text-start py-3">
                        <i class="bi bi-arrow-repeat me-2"></i> {{ lang('Admin.backup.update_btn') }}
                        <span class="d-block small fw-normal opacity-75 mt-1">{{ lang('Admin.backup.update_sub') }}</span>
                    </button>
                </div>
            </form>
            <p class="small text-danger mt-3 mb-0">
                <i class="bi bi-exclamation-triangle"></i> {{ lang('Admin.backup.deploy_warn') }}
            </p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // The download starts after the file is built: show that something is happening.
    document.querySelector('.deploy-form').addEventListener('submit', e => {
        e.target.querySelectorAll('button').forEach(b => b.classList.add('disabled'));
        setTimeout(() => e.target.querySelectorAll('button').forEach(b => b.classList.remove('disabled')), 8000);
    });
    document.getElementById('backup-form').addEventListener('submit', e => {
        const status = document.getElementById('backup-status');
        status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + @json(lang('Admin.backup.building'));
        setTimeout(() => e.target.querySelectorAll('button').forEach(b => b.disabled = true), 0);
        setTimeout(() => e.target.querySelectorAll('button').forEach(b => b.disabled = {{ $canZip ? 'false' : 'b.value === "full"' }}), 8000);
    });
</script>
@endpush
