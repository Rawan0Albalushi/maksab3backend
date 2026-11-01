@extends('layouts.admin.app')

@section('title', translate('Restore database'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/db-backup.css') }}">
@endpush

@section('content')
    @php
        $service = app(\App\Services\System\DatabaseBackupService::class);
        $is_demo = getEnvMode() == 'demo';
        $type_tags = [
            'manual' => ['label' => translate('Manual'), 'class' => ''],
            'pre-restore' => ['label' => translate('Pre-restore'), 'class' => 'dbk-tag--auto'],
            'uploaded' => ['label' => translate('Uploaded'), 'class' => 'dbk-tag--upload'],
        ];
    @endphp

    <div class="content container-fluid tps dbk">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-restore"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('Restore database') }}</h1>
                    <p>{{ translate('Replace the live database with the contents of a backup.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#db-restore-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="tps-note tps-note--danger mb-3">
            <i class="tio-warning"></i>
            <div>
                <strong>{{ translate('Read this first') }}:</strong>
                {{ translate('A restore overwrites every table in the live database with the version in the backup. Orders, customers and settings created since that backup was taken are gone, and nothing on this page can bring them back.') }}
                {{ translate('Database') }}: {{ $stats['database'] }}
            </div>
        </div>

        <div class="dbk-stats">
            <div class="dbk-stat dbk-stat--warn">
                <span class="dbk-stat__icon"><i class="tio-table"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ $service->humanBytes($stats['db_bytes']) }}</span>
                    <span class="dbk-stat__label">{{ translate('Live database') }} &middot; {{ translate('Tables') }}: {{ $stats['tables'] }}</span>
                </span>
            </div>
            <div class="dbk-stat">
                <span class="dbk-stat__icon"><i class="tio-archive"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ number_format($stats['count']) }}</span>
                    <span class="dbk-stat__label">{{ translate('Backups you can restore from') }}</span>
                </span>
            </div>
            <div class="dbk-stat">
                <span class="dbk-stat__icon"><i class="tio-history"></i></span>
                <span>
                    <span class="dbk-stat__value">{{ $stats['latest'] ? $stats['latest']->diffForHumans(null, true) : '—' }}</span>
                    <span class="dbk-stat__label">{{ $stats['latest'] ? translate('Since the last backup') : translate('No backup taken yet') }}</span>
                </span>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7 mb-3">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <div class="tps-card__titles">
                            <div class="tps-card__title dbk-steptitle"><span class="dbk-step">1</span>{{ translate('Choose a backup') }}</div>
                            <div class="tps-card__subtitle">{{ translate('Only files in the vault can be restored. Upload one first if the copy you need is on your machine.') }}</div>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        @if (count($backups))
                            <div class="mb-3">
                                <input type="search" class="form-control" id="dbk-source-search" autocomplete="off"
                                       placeholder="{{ translate('Search a backup by name') }}"
                                       aria-label="{{ translate('Search a backup by name') }}">
                            </div>

                            <div class="dbk-sources">
                                @foreach ($backups as $backup)
                                    @php $tag = $type_tags[$backup['type']] ?? $type_tags['manual']; @endphp
                                    <label class="dbk-source" data-name="{{ strtolower($backup['name']) }}">
                                        <input type="radio" name="file" form="dbk-restore-form"
                                               value="{{ $backup['name'] }}"
                                               data-label="{{ $backup['name'] }}"
                                               data-structure="{{ $backup['structure_only'] ? 1 : 0 }}"
                                               {{ $selected === $backup['name'] ? 'checked' : '' }}>
                                        <span class="dbk-source__body">
                                            <span class="dbk-file">{{ $backup['name'] }}</span>
                                            <span class="dbk-meta">
                                                <span class="dbk-tag {{ $tag['class'] }}">{{ $tag['label'] }}</span>
                                                @if ($backup['structure_only'])
                                                    <span class="dbk-tag dbk-tag--structure">{{ translate('Structure only') }}</span>
                                                @endif
                                                {{ $backup['created_at']->format('d M Y, h:i a') }}
                                                @if ($backup['tables'] !== null)
                                                    · {{ translate('Tables') }}: {{ number_format($backup['tables']) }}
                                                @endif
                                            </span>
                                        </span>
                                        <span class="dbk-source__size">{{ $backup['size_human'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="dbk-empty" id="dbk-source-empty" hidden>
                                <i class="tio-search"></i>
                                <span>{{ translate('No backup matches your search.') }}</span>
                            </div>
                        @else
                            <div class="dbk-empty">
                                <i class="tio-archive"></i>
                                <span>{{ translate('There is nothing to restore from yet.') }}</span>
                                <a href="{{ route('admin.business-settings.database.backup') }}" class="btn btn--primary btn-sm mt-2">
                                    <i class="tio-add-circle"></i> {{ translate('Create a backup') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-3">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <div class="tps-card__titles">
                            <div class="tps-card__title">{{ translate('Upload a backup') }}</div>
                            <div class="tps-card__subtitle">{{ translate('Adds the file to the vault. It is checked and then selectable on the left.') }}</div>
                        </div>
                    </div>

                    <form action="{{ route('admin.business-settings.database.restore.upload') }}" method="post"
                          enctype="multipart/form-data" id="dbk-upload-form">
                        @csrf
                        <div class="tps-card__body">
                            <label class="dbk-drop" id="dbk-drop">
                                <i class="tio-upload-on-cloud"></i>
                                <span class="dbk-drop__title" id="dbk-drop-title">{{ translate('Drop a backup here, or click to browse') }}</span>
                                <span class="dbk-drop__hint">.sql, .sql.gz — {{ translate('Maximum size') }}: {{ ini_get('upload_max_filesize') }}</span>
                                <input type="file" name="backup_file" id="dbk-file" accept=".sql,.gz,application/sql,application/gzip">
                            </label>

                            <div class="tps-note tps-note--warn mt-3">
                                <i class="tio-info-outined"></i>
                                <div>{{ translate('Only upload a file you produced yourself. A SQL file runs with full rights over your database.') }}</div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <button type="{{ getDemoModeFormButton(type: 'button') }}" id="dbk-upload-submit" disabled
                                    class="btn btn--primary {{ getDemoModeFormButton(type: 'class') }}">
                                <i class="tio-upload"></i> {{ translate('Upload to vault') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.business-settings.database.restore.run') }}" method="post" id="dbk-restore-form">
            @csrf

            <div class="tps-card mb-3">
                <div class="tps-card__head">
                    <div class="tps-card__titles">
                        <div class="tps-card__title dbk-steptitle"><span class="dbk-step">2</span>{{ translate('Safety options') }}</div>
                        <div class="tps-card__subtitle">{{ translate('Leave these as they are unless you know why you are changing them.') }}</div>
                    </div>
                </div>
                <div class="tps-card__body">
                    <div class="dbk-options">
                        {{-- An unchecked box is absent from the POST, and this one defaults
                             to on, so the "off" state has to be posted explicitly. --}}
                        <input type="hidden" name="safety_backup" value="0">
                        <label class="dbk-opt">
                            <input type="checkbox" name="safety_backup" value="1" checked>
                            <span>
                                <span class="dbk-opt__title">{{ translate('Take a safety backup first') }}</span>
                                <span class="dbk-opt__desc">{{ translate('Dumps the current database before anything is overwritten, so a restore of the wrong file can be undone. Strongly recommended.') }}</span>
                            </span>
                        </label>

                        <label class="dbk-opt dbk-opt--danger">
                            <input type="checkbox" name="wipe" value="1">
                            <span>
                                <span class="dbk-opt__title">{{ translate('Drop every existing table first') }}</span>
                                <span class="dbk-opt__desc">{{ translate('Clears tables the backup does not mention. Only needed when restoring an older version whose schema had different tables.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="dbk-arm" id="dbk-arm">
                <h2 class="dbk-arm__title">
                    <span class="dbk-step">3</span><i class="tio-lock-outlined"></i> {{ translate('Confirm it is you') }}
                </h2>
                <p class="dbk-arm__desc" id="dbk-arm-target">{{ translate('Select a backup above to continue.') }}</p>

                <div class="dbk-arm__fields">
                    <div class="tps-field">
                        <label class="tps-field__label" for="dbk-confirmation">
                            {{ translate('Confirmation') }} <span class="tps-req">*</span>
                        </label>
                        <input type="text" class="form-control dbk-token @error('confirmation') is-invalid @enderror"
                               id="dbk-confirmation" name="confirmation" autocomplete="off" spellcheck="false"
                               placeholder="RESTORE">
                        <span class="tps-field__hint">{{ translate('Type this to unlock the button') }}: <code>RESTORE</code></span>
                        @error('confirmation')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="tps-field">
                        <label class="tps-field__label" for="dbk-password">
                            {{ translate('Your admin password') }} <span class="tps-req">*</span>
                        </label>
                        <div class="tps-input-wrap">
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="dbk-password" name="password" autocomplete="current-password">
                            <button type="button" class="tps-input-action tps-toggle-secret" data-target="#dbk-password"
                                    aria-label="{{ translate('Show value') }}">
                                <i class="tio-visible"></i>
                            </button>
                        </div>
                        <span class="tps-field__hint">{{ translate('Re-entered here so a session left open cannot be used to wipe the database.') }}</span>
                        @error('password')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="dbk-actionbar">
                <div>
                    <p class="dbk-actionbar__summary" id="dbk-summary">{{ translate('No backup selected.') }}</p>
                    <p class="dbk-actionbar__hint" id="dbk-hint">{{ translate('The restore starts as soon as you press the button. Do not close this tab.') }}</p>
                </div>
                <div class="dbk-actionbar__buttons">
                    <a href="{{ route('admin.business-settings.database.backup') }}" class="btn btn--reset">
                        <i class="tio-arrow-backward"></i> {{ translate('Back to backups') }}
                    </a>
                    <button type="{{ getDemoModeFormButton(type: 'button') }}" id="dbk-restore-submit" disabled
                            class="btn btn--danger {{ getDemoModeFormButton(type: 'class') }}">
                        <i class="tio-restore"></i> {{ translate('Restore this backup') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="db-restore-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Restoring a database') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Pick the backup to restore, or upload one and pick it from the list.') }}</li>
                        <li>{{ translate('The file is checked against its stored checksum. A backup that no longer matches is refused rather than half-restored.') }}</li>
                        <li>{{ translate('A safety backup of the current database is taken, so the wrong choice can still be walked back.') }}</li>
                        <li>{{ translate('Every table in the backup is dropped and rebuilt, then the rows are inserted. Foreign key checks are off while this runs and back on when it ends.') }}</li>
                        <li>{{ translate('Caches are cleared afterwards. If your admin account is not in the backup, you are signed out and will need an account that is.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--danger">
                        <i class="tio-warning"></i>
                        <div>{{ translate('Put the site into maintenance mode before restoring a busy platform. Orders placed while the restore runs are written into a database that is being replaced underneath them.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')

    <script>
        "use strict";

        $(function () {
            const $form = $('#dbk-restore-form');
            const $submit = $('#dbk-restore-submit');
            const $summary = $('#dbk-summary');
            const $target = $('#dbk-arm-target');
            const $confirmation = $('#dbk-confirmation');
            const $password = $('#dbk-password');
            const TOKEN = 'RESTORE';

            /* The button stays disabled until all three keys are present, so the
               only way to reach a restore by accident is to type the word for it. */
            function refresh() {
                const $file = $('input[name="file"]:checked');
                const chosen = $file.length > 0;
                const armed = chosen
                    && $confirmation.val().trim().toUpperCase() === TOKEN
                    && $password.val().length > 0;

                $submit.prop('disabled', !armed);

                if (!chosen) {
                    $summary.text("{{ translate('No backup selected.') }}");
                    $target.text("{{ translate('Select a backup above to continue.') }}");
                    return;
                }

                const name = $file.data('label');
                $summary.text("{{ translate('Restoring from') }}: " + name);
                $target.text("{{ translate('This replaces the live database with the selected backup. Confirm below to unlock the button.') }}");

                if (String($file.data('structure')) === '1') {
                    $summary.append(' — ' + "{{ translate('This backup holds no rows, so every table will end up empty.') }}");
                }
            }

            $(document).on('change', 'input[name="file"]', refresh);
            $confirmation.add($password).on('input', refresh);
            refresh();

            const $preselected = $('input[name="file"]:checked').closest('.dbk-source');
            if ($preselected.length) {
                const $list = $preselected.closest('.dbk-sources');
                $list.scrollTop($preselected[0].offsetTop - $list[0].offsetTop - 8);
            }

            $('#dbk-source-search').on('input', function () {
                const query = $(this).val().trim().toLowerCase();
                let shown = 0;

                $('.dbk-source').each(function () {
                    const match = query === '' || String($(this).data('name')).indexOf(query) !== -1;
                    $(this).toggleClass('is-hidden', !match);
                    if (match) shown++;
                });

                $('#dbk-source-empty').prop('hidden', shown !== 0);
            });

            /* Last stop before the point of no return. The typed token and the
               password are already in hand, so this is a summary, not a gate. */
            $form.on('submit', function (event) {
                if ($form.data('confirmed')) {
                    $submit.addClass('dbk-busy').prop('disabled', true).html(
                        '<i class="tio-refresh dbk-busy__spin"></i> {{ translate('Restoring') }}…'
                    );
                    return true;
                }

                event.preventDefault();

                const name = $('input[name="file"]:checked').data('label');
                const safety = $('input[name="safety_backup"]:checkbox').is(':checked');
                const wipe = $('input[name="wipe"]').is(':checked');

                Swal.fire({
                    title: "{{ translate('Restore the database?') }}",
                    imageUrl: "{{ asset('public/assets/admin/img/off-danger.png') }}",
                    imageWidth: 80,
                    imageHeight: 80,
                    imageAlt: 'Custom icon',
                    html: "<div style='text-align:start'>"
                        + "<p>" + "{{ translate('Everything in the live database is replaced with') }}" + " <b></b>.</p>"
                        + "<p>" + (safety
                            ? "{{ translate('A safety backup will be taken first.') }}"
                            : "{{ translate('No safety backup will be taken. This cannot be undone.') }}") + "</p>"
                        + (wipe ? "<p>" + "{{ translate('Every existing table will be dropped first.') }}" + "</p>" : "")
                        + "</div>",
                    showCancelButton: true,
                    cancelButtonColor: 'default',
                    confirmButtonColor: '#FC6A57',
                    cancelButtonText: "{{ translate('Cancel') }}",
                    confirmButtonText: "{{ translate('Yes, restore now') }}",
                    reverseButtons: true,
                    didOpen: function (popup) {
                        /* Set as text, never as markup: the name is a file on
                           disk and has no business being parsed as HTML. */
                        $(popup).find('b').text(name);
                    }
                }).then(function (result) {
                    if (result.value) {
                        $form.data('confirmed', true).trigger('submit');
                    }
                });

                return false;
            });

            const $file = $('#dbk-file');
            const $drop = $('#dbk-drop');

            $file.on('change', function () {
                const name = this.files.length ? this.files[0].name : '';
                $('#dbk-drop-title').text(name || "{{ translate('Drop a backup here, or click to browse') }}");
                $('#dbk-upload-submit').prop('disabled', !name);
            });

            $drop.on('dragover dragenter', function (event) {
                event.preventDefault();
                $drop.addClass('is-over');
            }).on('dragleave drop', function () {
                $drop.removeClass('is-over');
            }).on('drop', function (event) {
                event.preventDefault();
                const files = event.originalEvent.dataTransfer.files;
                if (files.length) {
                    $file[0].files = files;
                    $file.trigger('change');
                }
            });
        });
    </script>
@endpush
