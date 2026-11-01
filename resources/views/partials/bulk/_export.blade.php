{{--
    Shared bulk export screen. Every export page in admin, vendor and the modules
    renders through here, so the scope picker, the range validation and the busy
    state exist once. The caller passes a $bulk array; only the entity specific
    strings live on the page, which keeps every translate() key greppable there.

    Required: title, subtitle, icon, action, file_name
    Optional: summary, count_label, count_hint, columns, tips, aside_title,
              aside_subtitle, import_url, import_title, import_desc, help_title,
              help_steps, show_module, preview_all
--}}
@php
    $bulk = array_merge([
        'icon' => asset('public/assets/admin/img/outline/category.svg'),
        'summary' => null,
        'count_label' => translate('Records in this module'),
        'count_hint' => null,
        'columns' => [],
        'tips' => [],
        'aside_title' => translate('What the file contains'),
        'aside_subtitle' => translate('One row per record, in these columns.'),
        'import_url' => null,
        'import_title' => null,
        'import_desc' => null,
        'help_title' => translate('Exporting your data'),
        'help_steps' => [],
        'show_module' => true,
        'preview_all' => translate('Everything will be exported.'),
    ], $bulk ?? []);

    $bex_module_name = \Illuminate\Support\Facades\Config::get('module.current_module_name');
    $bex_module_icon = \Illuminate\Support\Facades\Config::get('module.current_module_icon');

    $bex_summary = $bulk['summary'];
    $bex_total = $bex_summary['total'] ?? null;
    $bex_min_id = $bex_summary['min_id'] ?? null;
    $bex_max_id = $bex_summary['max_id'] ?? null;
    $bex_first = ($bex_summary['first_created_at'] ?? null) ? \Carbon\Carbon::parse($bex_summary['first_created_at']) : null;
    $bex_last = ($bex_summary['last_created_at'] ?? null) ? \Carbon\Carbon::parse($bex_summary['last_created_at']) : null;
    $bex_has_data = $bex_summary === null || $bex_total > 0;
    $bex_type = old('type', 'all');
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
            @if ($bulk['show_module'] && $bex_module_name)
                <span class="btk-module" title="{{ translate('The export only covers the module you are working in.') }}">
                    @if ($bex_module_icon)
                        <img src="{{ $bex_module_icon }}" alt="">
                    @else
                        <i class="tio-apps"></i>
                    @endif
                    <small>{{ translate('messages.Module') }}:</small> {{ $bex_module_name }}
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

    @if ($bex_summary)
        <div class="btk-stats">
            <div class="btk-stat">
                <span class="btk-stat__icon"><i class="tio-layers"></i></span>
                <span>
                    <span class="btk-stat__label">{{ $bulk['count_label'] }}</span>
                    <span class="btk-stat__value">{{ $bex_total }}</span>
                    @if ($bulk['count_hint'])
                        <span class="btk-stat__hint">{{ $bulk['count_hint'] }}</span>
                    @endif
                </span>
            </div>

            <div class="btk-stat">
                <span class="btk-stat__icon"><i class="tio-hashtag"></i></span>
                <span>
                    <span class="btk-stat__label">{{ translate('Available ID range') }}</span>
                    <span class="btk-stat__value">{{ $bex_has_data && $bex_min_id ? $bex_min_id . ' – ' . $bex_max_id : '—' }}</span>
                    <span class="btk-stat__hint">{{ translate('Use these bounds when exporting by ID.') }}</span>
                </span>
            </div>

            <div class="btk-stat">
                <span class="btk-stat__icon"><i class="tio-calendar-month"></i></span>
                <span>
                    <span class="btk-stat__label">{{ translate('Created between') }}</span>
                    <span class="btk-stat__value">
                        {{ $bex_first ? $bex_first->format('d M Y') . ' – ' . $bex_last->format('d M Y') : '—' }}
                    </span>
                    <span class="btk-stat__hint">{{ translate('The date filter matches the creation date.') }}</span>
                </span>
            </div>
        </div>
    @endif

    @if (!$bex_has_data)
        <div class="tps-note tps-note--warn mb-3">
            <i class="tio-info-outined"></i>
            <div>{{ translate('There is nothing to export yet.') }}</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="{{ count($bulk['columns']) || count($bulk['tips']) ? 'col-lg-8' : 'col-12' }}">
            {{-- novalidate: the range rules need both fields to speak with one voice,
                 and a native bubble would pre-empt them. The server still validates. --}}
            <form id="btk-form" action="{{ $bulk['action'] }}" method="POST" novalidate>
                @csrf

                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-download-to"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Export scope') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('Choose which rows go into the file.') }}</p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="tps-choice">
                                    <input type="radio" name="type" value="all" class="btk-type" {{ $bex_type === 'all' ? 'checked' : '' }}>
                                    <span class="tps-choice__box">
                                        <span class="tps-choice__mark"></span>
                                        <span>
                                            <span class="btk-choice-head">
                                                <i class="tio-layers"></i>
                                                <span class="tps-choice__title">{{ translate('Everything') }}</span>
                                            </span>
                                            <span class="tps-choice__desc">{{ translate('No filter — the whole list.') }}</span>
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="tps-choice">
                                    <input type="radio" name="type" value="date_wise" class="btk-type" {{ $bex_type === 'date_wise' ? 'checked' : '' }}>
                                    <span class="tps-choice__box">
                                        <span class="tps-choice__mark"></span>
                                        <span>
                                            <span class="btk-choice-head">
                                                <i class="tio-calendar-month"></i>
                                                <span class="tps-choice__title">{{ translate('By date range') }}</span>
                                            </span>
                                            <span class="tps-choice__desc">{{ translate('Only rows created between two dates.') }}</span>
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="tps-choice">
                                    <input type="radio" name="type" value="id_wise" class="btk-type" {{ $bex_type === 'id_wise' ? 'checked' : '' }}>
                                    <span class="tps-choice__box">
                                        <span class="tps-choice__mark"></span>
                                        <span>
                                            <span class="btk-choice-head">
                                                <i class="tio-hashtag"></i>
                                                <span class="tps-choice__title">{{ translate('By ID range') }}</span>
                                            </span>
                                            <span class="tps-choice__desc">{{ translate('Only rows whose ID falls in a range.') }}</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="btk-panel" id="btk-panel-date_wise">
                            <div class="btk-panel__head">
                                <h3 class="btk-panel__title">{{ translate('Date range') }}</h3>
                                @if ($bex_first)
                                    <button type="button" class="btk-panel__fill" data-fill="date_wise">{{ translate('Use full range') }}</button>
                                @endif
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="tps-field {{ $errors->has('from_date') ? 'btk-field--invalid' : '' }}" data-field="from_date">
                                        <label class="tps-field__label" for="from_date">
                                            {{ translate('messages.From date') }} <span class="tps-req">*</span>
                                        </label>
                                        <input type="date" id="from_date" name="from_date" class="form-control"
                                               value="{{ old('from_date') }}" max="{{ date('Y-m-d') }}">
                                        <small class="btk-error">{{ $errors->first('from_date') }}</small>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="tps-field {{ $errors->has('to_date') ? 'btk-field--invalid' : '' }}" data-field="to_date">
                                        <label class="tps-field__label" for="to_date">
                                            {{ translate('messages.To date') }} <span class="tps-req">*</span>
                                        </label>
                                        <input type="date" id="to_date" name="to_date" class="form-control"
                                               value="{{ old('to_date') }}" max="{{ date('Y-m-d') }}">
                                        <small class="btk-error">{{ $errors->first('to_date') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="btk-panel" id="btk-panel-id_wise">
                            <div class="btk-panel__head">
                                <h3 class="btk-panel__title">{{ translate('ID range') }}</h3>
                                @if ($bex_min_id)
                                    <button type="button" class="btk-panel__fill" data-fill="id_wise">{{ translate('Use full range') }}</button>
                                @endif
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="tps-field {{ $errors->has('start_id') ? 'btk-field--invalid' : '' }}" data-field="start_id">
                                        <label class="tps-field__label" for="start_id">
                                            {{ translate('Start ID') }} <span class="tps-req">*</span>
                                        </label>
                                        <input type="number" id="start_id" name="start_id" class="form-control" min="1"
                                               value="{{ old('start_id') }}" placeholder="{{ $bex_min_id ?: 1 }}">
                                        <small class="btk-error">{{ $errors->first('start_id') }}</small>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="tps-field {{ $errors->has('end_id') ? 'btk-field--invalid' : '' }}" data-field="end_id">
                                        <label class="tps-field__label" for="end_id">
                                            {{ translate('End ID') }} <span class="tps-req">*</span>
                                        </label>
                                        <input type="number" id="end_id" name="end_id" class="form-control" min="1"
                                               value="{{ old('end_id') }}" placeholder="{{ $bex_max_id ?: 1 }}">
                                        <small class="btk-error">{{ $errors->first('end_id') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tps-note tps-note--info mt-3">
                            <i class="tio-info"></i>
                            <div id="btk-preview">{{ $bulk['preview_all'] }}</div>
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <span class="tps-foot-note">
                            {{ translate('File name') }}: {{ $bulk['file_name'] }}
                        </span>
                        <button type="reset" class="btn btn--reset" id="btk-reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary" id="btk-submit" {{ $bex_has_data ? '' : 'disabled' }}>
                            <i class="tio-download-to mr-1"></i>{{ translate('messages.Export') }}
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
                    </div>

                    @if ($bulk['import_url'])
                        <div class="tps-card__body pt-0">
                            <a href="{{ $bulk['import_url'] }}" class="btk-crosslink">
                                <i class="tio-upload-outlined"></i>
                                <span>
                                    <strong>{{ $bulk['import_title'] }}</strong>
                                    <span>{{ $bulk['import_desc'] }}</span>
                                </span>
                                <i class="tio-chevron-right btk-crosslink__go"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
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
                    <div class="tps-note tps-note--muted">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('The export only covers the module you are working in.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@push('script_2')
    @php
        $btk_bounds = [
            'min_id' => $bex_min_id,
            'max_id' => $bex_max_id,
            'from_date' => $bex_first?->format('Y-m-d'),
            'to_date' => $bex_last?->format('Y-m-d'),
        ];

        $btk_text = [
            'all' => $bulk['preview_all'],
            'date' => translate('Exporting rows by date range'),
            'date_empty' => translate('Pick a start and an end date.'),
            'id' => translate('Exporting rows by ID range'),
            'id_empty' => translate('Pick a start and an end ID.'),
            'date_order' => translate('The end date cannot be earlier than the start date.'),
            'id_order' => translate('The end ID cannot be smaller than the start ID.'),
            'required' => translate('This field is required.'),
            'preparing' => translate('Preparing file'),
            'export' => translate('messages.Export'),
        ];
    @endphp

    <script>
        "use strict";

        (function () {
            const $form = $('#btk-form');
            const $submit = $('#btk-submit');
            const $preview = $('#btk-preview');

            const bounds = @json($btk_bounds);

            const text = @json($btk_text);

            function currentType() {
                return $('.btk-type:checked').val() || 'all';
            }

            function fieldsFor(type) {
                return type === 'date_wise' ? ['from_date', 'to_date'] : (type === 'id_wise' ? ['start_id', 'end_id'] : []);
            }

            function markInvalid(name, message) {
                const $field = $form.find('[data-field="' + name + '"]');
                $field.addClass('btk-field--invalid').find('.btk-error').text(message);
                $field.find('.form-control').attr('aria-invalid', 'true');
            }

            function clearErrors() {
                $form.find('.btk-field--invalid').removeClass('btk-field--invalid');
                $form.find('[aria-invalid]').removeAttr('aria-invalid');
            }

            function renderPreview() {
                const type = currentType();

                if (type === 'date_wise') {
                    const from = $('#from_date').val();
                    const to = $('#to_date').val();
                    $preview.text(from && to ? text.date + ': ' + from + ' – ' + to : text.date_empty);
                    return;
                }

                if (type === 'id_wise') {
                    const start = $('#start_id').val();
                    const end = $('#end_id').val();
                    $preview.text(start && end ? text.id + ': ' + start + ' – ' + end : text.id_empty);
                    return;
                }

                $preview.text(text.all);
            }

            /*
             * required follows the visible panel. The form is novalidate, so it is
             * there for assistive tech rather than for the browser bubble — the
             * checks below own the messages, and the server enforces required_if.
             */
            function syncPanels(animate) {
                const type = currentType();

                $('.btk-panel').each(function () {
                    const show = this.id === 'btk-panel-' + type;
                    animate ? $(this)[show ? 'slideDown' : 'slideUp'](200) : $(this).toggle(show);
                });

                $form.find('#from_date, #to_date, #start_id, #end_id').prop('required', false);
                fieldsFor(type).forEach(function (name) {
                    $form.find('[name="' + name + '"]').prop('required', true);
                });

                clearErrors();
                renderPreview();
            }

            $(document).on('change', '.btk-type', function () {
                syncPanels(true);
            });

            $(document).on('input change', '#from_date, #to_date, #start_id, #end_id', function () {
                $(this).closest('.tps-field').removeClass('btk-field--invalid');
                renderPreview();
            });

            $(document).on('click', '.btk-panel__fill', function () {
                if ($(this).data('fill') === 'date_wise') {
                    $('#from_date').val(bounds.from_date);
                    $('#to_date').val(bounds.to_date);
                } else {
                    $('#start_id').val(bounds.min_id);
                    $('#end_id').val(bounds.max_id);
                }

                clearErrors();
                renderPreview();
            });

            $form.on('submit', function (event) {
                const type = currentType();
                let valid = true;

                clearErrors();

                fieldsFor(type).forEach(function (name) {
                    if (!$form.find('[name="' + name + '"]').val()) {
                        markInvalid(name, text.required);
                        valid = false;
                    }
                });

                if (valid && type === 'date_wise' && $('#to_date').val() < $('#from_date').val()) {
                    markInvalid('to_date', text.date_order);
                    valid = false;
                }

                if (valid && type === 'id_wise' && Number($('#end_id').val()) < Number($('#start_id').val())) {
                    markInvalid('end_id', text.id_order);
                    valid = false;
                }

                if (!valid) {
                    event.preventDefault();
                    $form.find('.btk-field--invalid .form-control').first().trigger('focus');
                    return;
                }

                /*
                 * The response is a file, so the page never navigates: the busy
                 * state has to time itself out rather than wait for a load event.
                 */
                $submit.addClass('is-busy').html('<span class="spinner-border spinner-border-sm mr-1"></span>' + text.preparing);
                setTimeout(function () {
                    $submit.removeClass('is-busy').html('<i class="tio-download-to mr-1"></i>' + text.export);
                }, 4000);
            });

            $('#btk-reset').on('click', function () {
                setTimeout(function () {
                    syncPanels(true);
                }, 0);
            });

            syncPanels(false);
        })();
    </script>
@endpush
