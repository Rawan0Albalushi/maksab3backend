{{--
    Shared bulk import screen. Same idea as partials/bulk/_export: the dropzone,
    the mode picker, the file checks and the confirm dialog live once.

    Required: title, subtitle, icon, action
    Optional: action_update (second route for the update mode), modes (false for a
              single mode page), button_field (emit the hidden `button` the older
              controllers read), file_field, templates, columns, tips, summary,
              count_label, count_hint, export_url/_title/_desc, help_title,
              help_steps, extra (view to include in the card body), after (view
              rendered under the card), note_import, note_update, show_module
--}}
@php
    $bulk = array_merge([
        'icon' => asset('public/assets/admin/img/outline/category.svg'),
        'action_update' => null,
        'modes' => true,
        'button_field' => true,
        'file_field' => 'products_file',
        'templates' => [],
        'columns' => [],
        'tips' => [],
        'summary' => null,
        'count_label' => translate('Records in this module'),
        'count_hint' => null,
        'aside_title' => translate('How the file must look'),
        'aside_subtitle' => translate('Every one of these columns has to be present.'),
        'export_url' => null,
        'export_title' => null,
        'export_desc' => null,
        'help_title' => translate('Importing from a spreadsheet'),
        'help_steps' => [],
        'extra' => null,
        'after' => null,
        'note_import' => translate('The Id column is ignored — new ids are assigned automatically.'),
        'note_update' => translate('Your file must keep its Id column. Matching rows are overwritten and cannot be restored.'),
        'mode_add_title' => translate('Add new records'),
        'mode_add_desc' => translate('Every row becomes a new record.'),
        'mode_update_title' => translate('Update existing records'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'show_module' => true,
    ], $bulk ?? []);

    $bim_module_name = \Illuminate\Support\Facades\Config::get('module.current_module_name');
    $bim_module_icon = \Illuminate\Support\Facades\Config::get('module.current_module_icon');

    $bim_summary = $bulk['summary'];
    $bim_min_id = $bim_summary['min_id'] ?? null;
    $bim_max_id = $bim_summary['max_id'] ?? null;
    $bim_mode = old('upload_type', 'import');
    $bim_file = $bulk['file_field'];
@endphp

<div class="content container-fluid tps btk">
    <div class="tps-head">
        <div class="tps-head__title">
            <span class="tps-head__icon"><img src="{{ $bulk['icon'] }}" alt=""></span>
            <span class="tps-head__text">
                <h1>{{ $bulk['title'] }}</h1>
                <p>{{ $bulk['subtitle'] }}</p>
            </span>
        </div>

        <div class="btk-head-actions">
            @if ($bulk['show_module'] && $bim_module_name)
                <span class="btk-module" title="{{ translate('Rows are imported into the module you are working in.') }}">
                    @if ($bim_module_icon)
                        <img src="{{ $bim_module_icon }}" alt="">
                    @else
                        <i class="tio-apps"></i>
                    @endif
                    <small>{{ translate('messages.Module') }}:</small> {{ $bim_module_name }}
                </span>
            @endif

            @if (count($bulk['help_steps']))
                <button type="button" class="tps-help" data-toggle="modal" data-target="#btk-help-modal">
                    <i class="tio-help-outlined"></i>
                    <span>{{ translate('How it works') }}</span>
                </button>
            @endif
        </div>
    </div>

    <div class="btk-stats">
        @if ($bim_summary)
            <div class="btk-stat">
                <span class="btk-stat__icon"><i class="tio-layers"></i></span>
                <span>
                    <span class="btk-stat__label">{{ $bulk['count_label'] }}</span>
                    <span class="btk-stat__value">{{ $bim_summary['total'] ?? 0 }}</span>
                    @if ($bulk['count_hint'])
                        <span class="btk-stat__hint">{{ $bulk['count_hint'] }}</span>
                    @endif
                </span>
            </div>

            <div class="btk-stat">
                <span class="btk-stat__icon"><i class="tio-hashtag"></i></span>
                <span>
                    <span class="btk-stat__label">{{ translate('Existing ID range') }}</span>
                    <span class="btk-stat__value">{{ $bim_min_id ? $bim_min_id . ' – ' . $bim_max_id : '—' }}</span>
                    <span class="btk-stat__hint">{{ translate('An update matches rows on the Id column.') }}</span>
                </span>
            </div>
        @endif

        <div class="btk-stat">
            <span class="btk-stat__icon"><i class="tio-document-text-outlined"></i></span>
            <span>
                <span class="btk-stat__label">{{ translate('Accepted file') }}</span>
                <span class="btk-stat__value">.xlsx / .csv</span>
                <span class="btk-stat__hint">{{ translate('Maximum size') }}: {{ MAX_FILE_SIZE }} MB</span>
            </span>
        </div>
    </div>

    <div class="row g-3">
        <div class="{{ count($bulk['columns']) || count($bulk['tips']) ? 'col-lg-8' : 'col-12' }}">
            {{-- novalidate: the file checks below own the messages, and the server
                 re-runs them on the upload. --}}
            <form id="btk-form" action="{{ $bulk['action'] }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                @if ($bulk['button_field'])
                    <input type="hidden" name="button" id="btn_value" value="{{ $bim_mode }}">
                @endif

                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-upload-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Upload spreadsheet') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ $bulk['modes'] ? translate('Tell us what the file should do, then choose it.') : translate('Choose the filled-in file and upload it.') }}
                            </p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        @if ($bulk['modes'])
                            <div class="tps-group">
                                <p class="tps-group__label">1. {{ translate('What should this file do?') }}</p>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="tps-choice">
                                            <input type="radio" name="upload_type" value="import" class="btk-mode" {{ $bim_mode === 'update' ? '' : 'checked' }}>
                                            <span class="tps-choice__box">
                                                <span class="tps-choice__mark"></span>
                                                <span>
                                                    <span class="btk-choice-head">
                                                        <i class="tio-add-circle-outlined"></i>
                                                        <span class="tps-choice__title">{{ $bulk['mode_add_title'] }}</span>
                                                    </span>
                                                    <span class="tps-choice__desc">{{ $bulk['mode_add_desc'] }}</span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="tps-choice">
                                            <input type="radio" name="upload_type" value="update" class="btk-mode" {{ $bim_mode === 'update' ? 'checked' : '' }}>
                                            <span class="tps-choice__box">
                                                <span class="tps-choice__mark"></span>
                                                <span>
                                                    <span class="btk-choice-head">
                                                        <i class="tio-edit"></i>
                                                        <span class="tps-choice__title">{{ $bulk['mode_update_title'] }}</span>
                                                    </span>
                                                    <span class="tps-choice__desc">{{ $bulk['mode_update_desc'] }}</span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tps-note tps-note--info mt-3 btk-mode-note" data-mode="import">
                                    <i class="tio-info"></i>
                                    <div>{{ $bulk['note_import'] }}</div>
                                </div>

                                <div class="tps-note tps-note--warn mt-3 btk-mode-note" data-mode="update">
                                    <i class="tio-info-outined"></i>
                                    <div>{{ $bulk['note_update'] }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="tps-group">
                            <p class="tps-group__label">
                                {{ $bulk['modes'] ? '2. ' : '' }}{{ translate('Choose your file') }}
                            </p>

                            @foreach ($bulk['templates'] as $template)
                                <div class="btk-template mb-3">
                                    <span class="btk-template__icon"><i class="tio-file-text-outlined"></i></span>
                                    <span class="btk-template__text">
                                        <strong>{{ $template['label'] }}</strong>
                                        <span>{{ $template['hint'] }}</span>
                                    </span>
                                    <a href="{{ $template['url'] }}" download class="btn btn--primary btn-outline-primary">
                                        <i class="tio-download-to mr-1"></i>{{ translate('Download') }}
                                    </a>
                                </div>
                            @endforeach

                            <div class="tps-field {{ $errors->has($bim_file) ? 'btk-field--invalid' : '' }}" data-field="{{ $bim_file }}">
                                <label class="btk-drop" id="btk-drop">
                                    <span class="btk-drop__icon"><i class="tio-upload-on-cloud"></i></span>
                                    <span class="btk-drop__title">
                                        {{ translate('Drag your file here or') }} <span>{{ translate('browse') }}</span>
                                    </span>
                                    <span class="btk-drop__hint">
                                        {{ translate('Supported formats') . ': .xlsx, .csv' }} {{ translate('Maximum size') }}: {{ MAX_FILE_SIZE }} MB
                                    </span>
                                    <input type="file" name="{{ $bim_file }}" id="btk-file-input" accept=".xlsx,.csv">
                                </label>

                                <div class="btk-file" id="btk-file">
                                    <i class="tio-file-outlined btk-file__icon"></i>
                                    <span class="btk-file__meta">
                                        <span class="btk-file__name" id="btk-file-name"></span>
                                        <span class="btk-file__size" id="btk-file-size"></span>
                                    </span>
                                    <button type="button" class="btk-file__remove" id="btk-file-remove" aria-label="{{ translate('Remove file') }}">
                                        <i class="tio-clear"></i>
                                    </button>
                                </div>

                                <small class="btk-error">{{ $errors->first($bim_file) }}</small>
                            </div>
                        </div>

                        @if ($bulk['extra'])
                            @include($bulk['extra'])
                        @endif
                    </div>

                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ translate('Nothing is saved until you press upload.') }}</span>
                        <button type="reset" class="btn btn--reset" id="btk-reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary" id="btk-submit">
                            <i class="tio-upload-outlined mr-1"></i>{{ translate('messages.Upload') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @if (count($bulk['columns']) || count($bulk['tips']))
            <div class="col-lg-4">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-document-text-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ $bulk['aside_title'] }}</h2>
                            <p class="tps-card__subtitle">{{ $bulk['aside_subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        @if (count($bulk['columns']))
                            <ul class="btk-cols">
                                @foreach ($bulk['columns'] as $column)
                                    <li class="btk-chip">{{ $column }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if (count($bulk['tips']))
                            <ul class="btk-tips">
                                @foreach ($bulk['tips'] as $tip)
                                    <li>{!! $tip !!}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="tps-note tps-note--muted mt-3">
                            <i class="tio-info-outined"></i>
                            <div>{{ translate('If a single row is wrong, nothing is imported. Fix the file and upload it again.') }}</div>
                        </div>
                    </div>

                    @if ($bulk['export_url'])
                        <div class="tps-card__body pt-0">
                            <a href="{{ $bulk['export_url'] }}" class="btk-crosslink">
                                <i class="tio-download-to"></i>
                                <span>
                                    <strong>{{ $bulk['export_title'] }}</strong>
                                    <span>{{ $bulk['export_desc'] }}</span>
                                </span>
                                <i class="tio-chevron-right btk-crosslink__go"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($bulk['after'])
        @include($bulk['after'])
    @endif
</div>

@if (count($bulk['help_steps']))
    <div class="modal fade" id="btk-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $bulk['help_title'] }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        @foreach ($bulk['help_steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Upload images through the gallery first, then put their paths in the file.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@push('script_2')
    @php
        $btk_modes = [
            'import' => [
                'action' => $bulk['action'],
                'title' => translate('Add these rows?'),
                'text' => translate('Every row in the file is added as a new record.'),
            ],
            'update' => [
                'action' => $bulk['action_update'] ?: $bulk['action'],
                'title' => translate('Update these rows?'),
                'text' => translate('Rows matching an existing ID are overwritten. This cannot be undone.'),
            ],
        ];

        $btk_text = [
            'required' => translate('Choose a file to upload.'),
            'wrong_type' => translate('Supported formats') . ': .xlsx, .csv',
            'too_big' => translate('The file is too large.') . ' ' . translate('Maximum size') . ': ' . MAX_FILE_SIZE . ' MB',
            'uploading' => translate('Uploading'),
            'upload' => translate('messages.Upload'),
            'yes' => translate('messages.Yes'),
            'no' => translate('messages.No'),
        ];
    @endphp

    <script>
        "use strict";

        (function () {
            const $form = $('#btk-form');
            const $input = $('#btk-file-input');
            const $submit = $('#btk-submit');
            const $fileRow = $('#btk-file');

            const modes = @json($btk_modes);
            const text = @json($btk_text);
            const hasModes = {{ $bulk['modes'] ? 'true' : 'false' }};
            const maxBytes = {{ MAX_FILE_SIZE }} * 1024 * 1024;
            const allowed = ['xlsx', 'csv'];

            let confirmed = false;

            function currentMode() {
                return $('.btk-mode:checked').val() === 'update' ? 'update' : 'import';
            }

            function showError(message) {
                $form.find('[data-field]').addClass('btk-field--invalid').find('.btk-error').text(message);
                $input.attr('aria-invalid', 'true');
            }

            function clearError() {
                $form.find('[data-field]').removeClass('btk-field--invalid');
                $input.removeAttr('aria-invalid');
            }

            function readableSize(bytes) {
                return bytes < 1024 * 1024
                    ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
                    : (bytes / 1024 / 1024).toFixed(1) + ' MB';
            }

            function fileProblem(file) {
                if (allowed.indexOf(file.name.split('.').pop().toLowerCase()) === -1) {
                    return text.wrong_type;
                }

                return file.size > maxBytes ? text.too_big : null;
            }

            function renderFile() {
                const file = $input[0].files[0];

                $fileRow.toggleClass('is-visible', !!file);

                if (!file) {
                    clearError();
                    return;
                }

                $('#btk-file-name').text(file.name);
                $('#btk-file-size').text(readableSize(file.size));

                const problem = fileProblem(file);
                problem ? showError(problem) : clearError();
            }

            /*
             * Both mechanisms are kept: older controllers read the hidden `button`
             * field, newer ones have a separate update route.
             */
            function syncMode() {
                if (!hasModes) {
                    return;
                }

                const mode = currentMode();

                $('.btk-mode-note').each(function () {
                    $(this).toggle($(this).data('mode') === mode);
                });

                $form.attr('action', modes[mode].action);
                $('#btn_value').val(mode);
            }

            $(document).on('change', '.btk-mode', syncMode);
            $input.on('change', renderFile);

            /* The input covers the dropzone, so the browser handles the drop itself. */
            $('#btk-drop').on('dragenter dragover', function (event) {
                event.preventDefault();
                $(this).addClass('is-dragover');
            }).on('dragleave drop', function () {
                $(this).removeClass('is-dragover');
            });

            $('#btk-file-remove').on('click', function () {
                $input.val('');
                renderFile();
            });

            $form.on('submit', function (event) {
                if (confirmed) {
                    return;
                }

                event.preventDefault();

                const file = $input[0].files[0];

                if (!file) {
                    showError(text.required);
                    $input.trigger('focus');
                    return;
                }

                const problem = fileProblem(file);

                if (problem) {
                    showError(problem);
                    return;
                }

                const mode = modes[currentMode()];

                Swal.fire({
                    title: mode.title,
                    text: mode.text,
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: 'default',
                    confirmButtonColor: '#FC6A57',
                    cancelButtonText: text.no,
                    confirmButtonText: text.yes,
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.value) {
                        return;
                    }

                    confirmed = true;
                    $submit.addClass('is-busy')
                        .html('<span class="spinner-border spinner-border-sm mr-1"></span>' + text.uploading);
                    $form[0].submit();
                });
            });

            $('#btk-reset').on('click', function () {
                setTimeout(function () {
                    renderFile();
                    syncMode();
                }, 0);
            });

            syncMode();
            renderFile();
        })();
    </script>
@endpush
