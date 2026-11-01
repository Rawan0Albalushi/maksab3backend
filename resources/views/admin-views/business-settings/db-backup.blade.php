@extends('layouts.admin.app')

@section('title', translate('Backup database'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/db-backup.css') }}">
@endpush

@section('content')
    @php
        $service = app(\App\Services\System\DatabaseBackupService::class);
        $latest = $stats['latest'] ?? null;
        $type_tags = [
            'manual' => ['label' => translate('Manual'), 'class' => ''],
            'pre-restore' => ['label' => translate('Pre-restore'), 'class' => 'dbk-tag--auto'],
            'uploaded' => ['label' => translate('Uploaded'), 'class' => 'dbk-tag--upload'],
        ];
    @endphp

    <div class="content container-fluid tps dbk">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-archive"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('Backup database') }}</h1>
                    <p>{{ translate('Take a complete, restorable copy of the platform database.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#db-backup-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="dbk-stats">
            <div class="dbk-stat dbk-stat--ok">
                <span class="dbk-stat__icon"><i class="tio-archive"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ number_format($stats['count']) }}</span>
                    <span class="dbk-stat__label">{{ translate('Backups in the vault') }}</span>
                </span>
            </div>
            <div class="dbk-stat">
                <span class="dbk-stat__icon"><i class="tio-folder-outlined"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ $service->humanBytes($stats['bytes']) }}</span>
                    <span class="dbk-stat__label">{{ translate('Vault size') }}</span>
                </span>
            </div>
            <div class="dbk-stat">
                <span class="dbk-stat__icon"><i class="tio-table"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ $service->humanBytes($stats['db_bytes']) }}</span>
                    <span class="dbk-stat__label">{{ $stats['database'] }} · {{ translate('Tables') }}: {{ $stats['tables'] }}</span>
                </span>
            </div>
            <div class="dbk-stat {{ $latest ? '' : 'dbk-stat--warn' }}">
                <span class="dbk-stat__icon"><i class="tio-history"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ $latest ? $latest->diffForHumans(null, true) : '—' }}</span>
                    <span class="dbk-stat__label">{{ $latest ? translate('Since the last backup') : translate('No backup taken yet') }}</span>
                </span>
            </div>
        </div>

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info-outined"></i>
            <div>
                {{ translate('Backups are written to storage/app/backups/database, which is outside the public folder. They are only reachable through this page.') }}
                {{ translate('Free disk space') }}: {{ $service->humanBytes($stats['free_bytes']) }}
            </div>
        </div>

        <div class="tps-card mb-3">
            <div class="tps-card__head">
                <div class="tps-card__titles">
                    <div class="tps-card__title">{{ translate('Create a new backup') }}</div>
                    <div class="tps-card__subtitle">{{ translate('Runs against the live database and writes one file into the vault.') }}</div>
                </div>
            </div>

            <form action="{{ route('admin.business-settings.database.backup.store') }}" method="post" id="dbk-create-form">
                @csrf
                <div class="tps-card__body">
                    <div class="dbk-options mb-3">
                        {{-- An unchecked box is absent from the POST, so the "off" state
                             has to be posted explicitly or gzip could never be turned off. --}}
                        <input type="hidden" name="compress" value="0">
                        <label class="dbk-opt">
                            <input type="checkbox" name="compress" value="1" checked>
                            <span>
                                <span class="dbk-opt__title">{{ translate('Compress with gzip') }}</span>
                                <span class="dbk-opt__desc">{{ translate('Writes a .sql.gz file, typically a fifth of the size. Leave it off only if your tools cannot read gzip.') }}</span>
                            </span>
                        </label>

                        <label class="dbk-opt">
                            <input type="radio" name="scope" value="full" checked>
                            <span>
                                <span class="dbk-opt__title">{{ translate('Structure and data') }}</span>
                                <span class="dbk-opt__desc">{{ translate('A full copy — this is the one you restore from.') }}</span>
                            </span>
                        </label>

                        <label class="dbk-opt">
                            <input type="radio" name="scope" value="structure">
                            <span>
                                <span class="dbk-opt__title">{{ translate('Structure only') }}</span>
                                <span class="dbk-opt__desc">{{ translate('Tables, views and triggers with no rows. Useful for setting up a fresh install, not for recovery.') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="tps-field">
                        <label class="tps-field__label" for="dbk-note">{{ translate('Note') }}</label>
                        <input type="text" class="form-control" id="dbk-note" name="note" maxlength="190"
                               placeholder="{{ translate('Ex') }}: {{ translate('Before the tax rate change') }}">
                        <span class="tps-field__hint">{{ translate('Optional. Shown in the list so you can tell one backup from another.') }}</span>
                    </div>
                </div>

                <div class="tps-card__foot">
                    <span class="tps-foot-note">{{ translate('Large databases can take a few minutes. Keep this tab open until it finishes.') }}</span>
                    <button type="{{ getDemoModeFormButton(type: 'button') }}" id="dbk-create-submit"
                            class="btn btn--primary {{ getDemoModeFormButton(type: 'class') }}">
                        <i class="tio-save"></i> {{ translate('Create backup') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="tps-card">
            <div class="tps-card__head">
                <div class="tps-card__titles">
                    <div class="tps-card__title">
                        {{ translate('Stored backups') }}
                        <span class="badge badge-soft-secondary ml-1" id="dbk-count">{{ count($backups) }}</span>
                    </div>
                    <div class="tps-card__subtitle">{{ translate('Newest first. Download a copy off this server — a backup sitting next to the database it protects is not a backup.') }}</div>
                </div>
                <div class="tps-card__aside">
                    <a href="{{ route('admin.business-settings.database.restore') }}" class="btn btn--reset">
                        <i class="tio-restore"></i> {{ translate('Go to restore') }}
                    </a>
                </div>
            </div>

            <div class="tps-card__body p-0">
                @if (count($backups))
                    <div class="table-responsive datatable-custom">
                        <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('Backup file') }}</th>
                                    <th>{{ translate('Contents') }}</th>
                                    <th class="col--numeric">{{ translate('Size') }}</th>
                                    <th>{{ translate('Created') }}</th>
                                    <th class="text-center">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $key => $backup)
                                    @php
                                        $tag = $type_tags[$backup['type']] ?? $type_tags['manual'];
                                    @endphp
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <span class="dbk-file">{{ $backup['name'] }}</span>
                                            <span class="dbk-meta">
                                                <span class="dbk-tag {{ $tag['class'] }}">{{ $tag['label'] }}</span>
                                                @if ($backup['structure_only'])
                                                    <span class="dbk-tag dbk-tag--structure">{{ translate('Structure only') }}</span>
                                                @endif
                                                @if ($backup['note'])
                                                    — {{ $backup['note'] }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            @if ($backup['tables'] !== null)
                                                <span class="dbk-meta">{{ translate('Tables') }}: {{ number_format($backup['tables']) }}</span>
                                                <span class="dbk-meta">{{ translate('Rows') }}: {{ number_format((int) $backup['rows']) }}</span>
                                            @else
                                                <span class="dbk-meta">{{ translate('Not generated here') }}</span>
                                            @endif
                                        </td>
                                        <td class="col--numeric dbk-num">{{ $backup['size_human'] }}</td>
                                        <td>
                                            <span class="dbk-meta">{{ $backup['created_at']->format('d M Y, h:i a') }}</span>
                                            <span class="dbk-meta">{{ $backup['author'] ?: translate('System') }}</span>
                                        </td>
                                        <td>
                                            <div class="btn--container justify-content-center gap-2">
                                                <a class="btn action-btn btn--primary btn-outline-primary {{ getDemoModeFormButton(type: 'class') }}"
                                                   href="{{ getEnvMode() == 'demo' ? 'javascript:' : route('admin.business-settings.database.backup.download', ['file' => $backup['name']]) }}"
                                                   title="{{ translate('Download') }}">
                                                    <i class="tio-download-to"></i>
                                                </a>
                                                <a class="btn action-btn btn--warning btn-outline-warning"
                                                   href="{{ route('admin.business-settings.database.restore', ['file' => $backup['name']]) }}"
                                                   title="{{ translate('Restore this backup') }}">
                                                    <i class="tio-restore"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--delete {{ getEnvMode() == 'demo' ? 'call-demo' : 'form-alert' }}"
                                                   href="javascript:" title="{{ translate('Delete') }}"
                                                   data-id="dbk-delete-{{ $key }}"
                                                   data-title="{{ translate('Delete this backup?') }}"
                                                   data-message="{{ translate('The file is removed from the server for good. Download it first if you may still need it.') }}">
                                                    <i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{ route('admin.business-settings.database.backup.destroy', ['file' => $backup['name']]) }}"
                                                      method="post" id="dbk-delete-{{ $key }}" class="d-none">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="dbk-empty">
                        <i class="tio-archive"></i>
                        <span>{{ translate('The vault is empty. Create your first backup above.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="db-backup-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Backing up the database') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('A backup copies every table, view and trigger, with the rows in them, into one SQL file.') }}</li>
                        <li>{{ translate('Queue jobs, cache and sessions are copied as empty tables — restoring yesterday\'s pending jobs would fire them against today\'s data.') }}</li>
                        <li>{{ translate('Each file is stored with a checksum. Restore refuses any backup that no longer matches it.') }}</li>
                        <li>{{ translate('Download the file and keep it somewhere else. A backup on the same server is lost with the server.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('Uploaded files are stored, not media. A backup does not include the images and documents in your storage folder — copy those separately.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $(function () {
            const $form = $('#dbk-create-form');
            const $submit = $('#dbk-create-submit');

            /* A dump on a live marketplace runs for minutes. Without this the page
               looks idle and the admin presses the button again, which the server
               lock refuses — an error message for a backup that is running fine. */
            $form.on('submit', function () {
                $submit.addClass('dbk-busy').prop('disabled', true).html(
                    '<i class="tio-refresh dbk-busy__spin"></i> {{ translate('Creating backup') }}…'
                );
            });
        });
    </script>
@endpush
