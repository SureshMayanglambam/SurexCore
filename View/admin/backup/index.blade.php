@extends('admin.layouts.app')

@section('title', lang('Admin.backup.title'))
@section('subtitle', lang('Admin.backup.subtitle'))

@push('head')
    <meta name="csrf-token" content="{{ csrf_hash() }}">
@endpush

@php
    $fmtSize = static fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
@endphp

@section('content')
    {{-- 1 + 2: plain backups --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card bk-card h-100 mb-0">
                <div class="card-body">
                    <div class="bk-icon is-db"><i class="bi bi-database"></i></div>
                    <h3 class="bk-title">{{ lang('Admin.backup.db_title') }}</h3>
                    <p class="bk-text">{{ lang('Admin.backup.db_text') }}</p>
                    <form method="post" action="{{ url_to('admin.backup.download') }}" class="bk-download">
                        @csrf
                        <button type="submit" name="type" value="db" class="btn btn-primary"><i class="bi bi-download"></i> {{ lang('Admin.backup.db_btn') }}</button>
                    </form>
                    <div class="bk-meta">
                        <i class="bi bi-clock-history"></i>
                        {{ $lastBackup ? lang('Admin.backup.last', [$lastBackup]) : lang('Admin.backup.none_yet') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bk-card h-100 mb-0">
                <div class="card-body">
                    <div class="bk-icon is-uploads"><i class="bi bi-images"></i></div>
                    <h3 class="bk-title">{{ lang('Admin.backup.up_title') }}</h3>
                    <p class="bk-text">{{ lang('Admin.backup.up_text') }}</p>
                    <form method="post" action="{{ url_to('admin.backup.download') }}" class="bk-download">
                        @csrf
                        <button type="submit" name="type" value="uploads" class="btn btn-primary" @disabled(! $canZip)><i class="bi bi-download"></i> {{ lang('Admin.backup.up_btn') }}</button>
                    </form>
                    <div class="bk-meta">
                        @if($canZip)
                            <i class="bi bi-info-circle"></i> {{ lang('Admin.backup.up_meta') }}
                        @else
                            <span class="text-danger"><i class="bi bi-exclamation-circle"></i> {{ lang('Admin.backup.no_zip') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3: migration --}}
    <div class="card mig-card mb-4">
        <div class="card-header flex-wrap">
            <h3 class="card-title"><i class="bi bi-arrow-left-right me-1"></i> {{ lang('Admin.migration.title') }}</h3>
            <div class="card-tools small text-secondary">{{ lang('Admin.migration.subtitle') }}</div>
        </div>
        <div class="card-body">
            <ol class="mig-flow">
                <li><span>1</span>{{ lang('Admin.migration.flow1') }}</li>
                <li><span>2</span>{{ lang('Admin.migration.flow2') }}</li>
                <li><span>3</span>{{ lang('Admin.migration.flow3') }}</li>
            </ol>

            <div class="row g-4">
                {{-- Export --}}
                <div class="col-lg-6">
                    <div class="mig-pane">
                        <h4 class="mig-pane-title"><i class="bi bi-box-arrow-up"></i> {{ lang('Admin.migration.export') }}</h4>
                        <p class="mig-pane-text">{{ lang('Admin.migration.export_text') }}</p>
                        <form method="post" action="{{ url_to('admin.migration.export') }}" id="mig-export">
                            @csrf
                            <input type="hidden" name="uploads" value="0">
                            <input type="hidden" name="code" value="0">
                            <div class="form-check mb-2">
                                <input id="mig-uploads" type="checkbox" name="uploads" value="1" class="form-check-input" checked>
                                <label class="form-check-label" for="mig-uploads">{{ lang('Admin.migration.opt_uploads') }}</label>
                            </div>
                            <div class="form-check mb-3">
                                <input id="mig-code" type="checkbox" name="code" value="1" class="form-check-input" checked>
                                <label class="form-check-label" for="mig-code">{{ lang('Admin.migration.opt_code') }}
                                    <i class="bi bi-question-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ lang('Admin.migration.opt_code_tip') }}"></i></label>
                            </div>
                            @if($canEncrypt)
                                <label class="form-label small fw-semibold" for="mig-password"><i class="bi bi-lock"></i> {{ lang('Admin.migration.password') }}</label>
                                <input id="mig-password" type="password" name="password" class="form-control mb-1" autocomplete="new-password" placeholder="{{ lang('Admin.migration.password_ph') }}">
                                <div class="form-text mt-0 mb-3">{{ lang('Admin.migration.password_help') }}</div>
                            @endif
                            <button type="submit" class="btn btn-primary" @disabled(! $canZip)><i class="bi bi-box-arrow-up"></i> {{ lang('Admin.migration.export_btn') }}</button>
                            <span class="mig-busy small text-secondary ms-2" hidden><span class="spinner-border spinner-border-sm"></span> {{ lang('Admin.migration.exporting') }}</span>
                        </form>
                    </div>
                </div>

                {{-- Import --}}
                <div class="col-lg-6">
                    <div class="mig-pane">
                        <h4 class="mig-pane-title"><i class="bi bi-box-arrow-in-down"></i> {{ lang('Admin.migration.import') }}</h4>
                        <p class="mig-pane-text">{{ lang('Admin.migration.import_text') }}</p>
                        <label class="mig-drop" id="mig-drop">
                            <input type="file" accept=".zip,application/zip" hidden id="mig-file">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <b>{{ lang('Admin.migration.drop') }}</b>
                            <small>{{ lang('Admin.migration.drop_sub') }}</small>
                            <span class="mig-progress" hidden><span class="mig-progress-bar"></span></span>
                            <span class="mig-progress-text small" hidden></span>
                        </label>
                        <div class="mig-warn"><i class="bi bi-exclamation-triangle"></i> {{ lang('Admin.migration.import_warn') }}</div>
                    </div>
                </div>
            </div>

            {{-- Saved migration files --}}
            <h4 class="mig-list-title">{{ lang('Admin.migration.saved') }}</h4>
            @if($migrations === [])
                <p class="text-secondary small mb-0">{{ lang('Admin.migration.none') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table mig-table mb-0">
                        <thead><tr><th>{{ lang('Admin.migration.col_file') }}</th><th>{{ lang('Admin.migration.col_from') }}</th><th>{{ lang('Admin.migration.col_contents') }}</th><th class="text-end">{{ lang('Admin.migration.col_size') }}</th><th class="text-end">{{ lang('Admin.actions') }}</th></tr></thead>
                        <tbody>
                        @foreach($migrations as $m)
                            @php $mf = $m['manifest']; @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold text-break">@if(!empty($mf['encrypted']))<i class="bi bi-lock-fill text-success me-1" title="{{ lang('Admin.migration.encrypted') }}"></i>@endif{{ $m['name'] }}</div>
                                    <small class="text-secondary">{{ date('Y-m-d H:i', $m['time']) }}</small>
                                </td>
                                <td>
                                    @if($mf)
                                        <div>{{ $mf['site_name'] ?? '' }}</div>
                                        <small class="text-secondary text-break">{{ $mf['source_url'] ?? '' }} · SurexCore {{ $mf['version'] ?? '?' }}</small>
                                    @else
                                        <span class="text-danger small">{{ lang('Admin.migration.not_valid') }}</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($mf)
                                        <span class="mig-chip">{{ lang('Admin.migration.c_db') }}</span>
                                        @if(!empty($mf['includes']['uploads']))<span class="mig-chip">{{ lang('Admin.migration.c_uploads') }}</span>@endif
                                        @if(!empty($mf['includes']['code']))<span class="mig-chip">{{ lang('Admin.migration.c_code') }}</span>@endif
                                    @endif
                                </td>
                                <td class="text-end text-nowrap small">{{ $fmtSize($m['size']) }}</td>
                                <td class="text-end text-nowrap">
                                    @if($mf)
                                        <button type="button" class="btn btn-sm btn-success" data-import="{{ $m['name'] }}"
                                                data-from="{{ $mf['source_url'] ?? '' }}" data-site="{{ $mf['site_name'] ?? '' }}" data-version="{{ $mf['version'] ?? '' }}"
                                                data-encrypted="{{ !empty($mf['encrypted']) ? '1' : '0' }}"><i class="bi bi-box-arrow-in-down"></i> {{ lang('Admin.migration.import_btn') }}</button>
                                    @endif
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ url_to('admin.migration.file', $m['name']) }}" title="{{ lang('Admin.download') }}"><i class="bi bi-download"></i></a>
                                    <form method="post" action="{{ url_to('admin.migration.delete', $m['name']) }}" class="d-inline" onsubmit="return confirm({!! json_encode(lang('Admin.migration.confirm_delete'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ lang('Admin.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="small text-secondary mt-3 mb-0"><i class="bi bi-folder2"></i> {!! lang('Admin.migration.folder', ['<code>writable/migrations/</code>']) !!}</p>
        </div>
    </div>

    {{-- Deploy packages (program files) --}}
    <details class="card bk-deploy mb-0">
        <summary class="card-header"><h3 class="card-title"><i class="bi bi-cloud-arrow-up me-1"></i> {{ lang('Admin.backup.deploy_title') }}</h3><i class="bi bi-chevron-down ms-auto"></i></summary>
        <div class="card-body">
            <p class="text-secondary small mb-3">{!! lang('Admin.backup.deploy_desc', ['<code>_deploy/README.txt</code>']) !!}</p>
            <form method="post" action="{{ url_to('admin.backup.package') }}" class="row g-3 deploy-form">
                @csrf
                <div class="col-md-6 d-grid">
                    <button type="submit" name="type" value="site" class="btn btn-outline-primary text-start py-3">
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
            <p class="small text-danger mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> {{ lang('Admin.backup.deploy_warn') }}</p>
        </div>
    </details>

    {{-- Import dialog --}}
    <div class="modal fade" id="mig-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> {{ lang('Admin.migration.modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ lang('Admin.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mig-from">
                        <div><small>{{ lang('Admin.migration.col_from') }}</small><b data-m="site"></b><span data-m="from"></span></div>
                        <div><small>SurexCore</small><b data-m="version"></b></div>
                    </div>
                    <div class="mig-setup">
                        <div class="mb-3" data-m="password-row" hidden>
                            <label class="form-label small fw-semibold" for="mig-import-password"><i class="bi bi-lock"></i> {{ lang('Admin.migration.password') }}</label>
                            <input id="mig-import-password" type="password" class="form-control" autocomplete="off">
                        </div>
                        <div class="form-check mb-3">
                            <input id="mig-backup-first" type="checkbox" class="form-check-input" checked>
                            <label class="form-check-label" for="mig-backup-first">{{ lang('Admin.migration.backup_first') }}</label>
                        </div>
                        <div class="mig-warn mb-0"><i class="bi bi-exclamation-triangle"></i> <span>{{ lang('Admin.migration.modal_warn') }}</span></div>
                    </div>
                    <ol class="mig-steps" hidden></ol>
                    <div class="alert alert-danger mt-3 mb-0" data-m="error" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ lang('Admin.cancel') }}</button>
                    <button type="button" class="btn btn-success" data-m="start"><i class="bi bi-box-arrow-in-down"></i> {{ lang('Admin.migration.start') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div id="mig-config" hidden
         data-upload-url="{{ url_to('admin.migration.upload') }}"
         data-import-url="{{ url_to('admin.migration.import') }}"
         data-step-url="{{ url_to('admin.migration.step') }}"
         data-chunk="{{ $chunkSize }}"></div>
@endsection

@push('scripts')
<script src="{{ base_url('assets/admin/migration.js') }}"></script>
@endpush
