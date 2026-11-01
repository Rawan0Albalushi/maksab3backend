@extends('layouts.admin.app')

@section('title',translate('Login URL setup'))

@push('css_or_js')
    <style>
        .lus-card {
            height: 100%;
            margin-bottom: 0;
        }

        .lus-card .card-header {
            gap: 12px;
            align-items: flex-start;
        }

        .lus-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            line-height: 1;
            background-color: rgba(var(--lus-accent), .12);
            color: rgb(var(--lus-accent));
        }

        .lus-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .lus-chip i {
            font-size: 13px;
            line-height: 1;
        }

        .lus-chip--active {
            background-color: rgba(var(--bs-success-rgb), .12);
            color: var(--success-dark);
        }

        .lus-chip--idle {
            background-color: rgba(var(--bs-warning-rgb), .18);
            color: var(--warning-dark);
        }

        .lus-card .input-group-text {
            font-family: "Roboto Mono", monospace;
            font-size: 12px;
            color: var(--text-light-gray);
            background-color: var(--section-bg);
            white-space: nowrap;
        }

        .lus-input {
            font-family: "Roboto Mono", monospace;
        }

        .lus-preview {
            background-color: var(--section-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: var(--bs-border-radius);
            padding: 10px 12px;
        }

        .lus-preview-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--text-light-gray);
            margin-bottom: 4px;
        }

        .lus-preview-value {
            font-family: "Roboto Mono", monospace;
            font-size: 12px;
            color: var(--text-title);
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .lus-icon-btn {
            width: 30px;
            height: 30px;
            min-width: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--bs-border-color);
            border-radius: 6px;
            background-color: #fff;
            color: var(--text-title);
            font-size: 14px;
            padding: 0;
            transition: all .2s ease-in-out;
        }

        .lus-icon-btn:hover,
        .lus-icon-btn:focus {
            border-color: rgb(var(--lus-accent));
            color: rgb(var(--lus-accent));
            text-decoration: none;
        }

        .lus-icon-btn.disabled,
        .lus-icon-btn:disabled {
            opacity: .45;
            pointer-events: none;
        }

        .lus-note {
            display: flex;
            gap: 8px;
            font-size: 12px;
            color: var(--text-title);
            background-color: rgba(var(--bs-warning-rgb), .12);
            border-radius: var(--bs-border-radius);
            padding: 10px 12px;
        }

        .lus-note i {
            color: var(--cus-warning-clr);
            font-size: 14px;
            line-height: 1.4;
        }

        .lus-hint {
            font-size: 11px;
            color: var(--text-light-gray);
        }

        .lus-error {
            font-size: 11px;
            font-weight: 500;
            color: var(--bs-danger);
        }

        .lus-dirty {
            font-size: 11px;
            font-weight: 600;
            color: var(--warning-dark);
        }

        .lus-card .card-footer {
            background-color: transparent;
            border-top: 1px solid var(--bs-border-color);
        }
    </style>
@endpush

@section('content')
    @php
        $lus_base_url = rtrim(url('/'), '/') . '/login/';

        $lus_panels = [
            [
                'type' => 'admin',
                'field' => 'admin_login_url',
                'title' => translate('Admin login page'),
                'subtitle' => translate('Entry point for system administrators'),
                'label' => translate('messages.Admin login url'),
                'placeholder' => translate('messages.Admin login url'),
                'tooltip' => translate('Add dynamic url to secure admin login access.'),
                'icon' => 'tio-security-on',
                'accent' => 'var(--bs-primary-rgb)',
            ],
            [
                'type' => 'admin_employee',
                'field' => 'admin_employee_login_url',
                'title' => translate('Admin employee login page'),
                'subtitle' => translate('Entry point for admin panel employees'),
                'label' => translate('messages.Admin employee login url'),
                'placeholder' => translate('messages.Admin employee login url'),
                'tooltip' => translate('Add dynamic url to secure admin employee login access.'),
                'icon' => 'tio-group-equal',
                'accent' => 'var(--bs-purple-rgb)',
            ],
            [
                'type' => 'store',
                'field' => 'store_login_url',
                'title' => translate('Store login page'),
                'subtitle' => translate('Entry point for store owners'),
                'label' => translate('messages.Store login url'),
                'placeholder' => translate('messages.Store login url'),
                'tooltip' => translate('Add dynamic url to secure store login access.'),
                'icon' => 'tio-shop',
                'accent' => 'var(--bs-secondary-rgb)',
            ],
            [
                'type' => 'store_employee',
                'field' => 'store_employee_login_url',
                'title' => translate('Store employee login page'),
                'subtitle' => translate('Entry point for store employees'),
                'label' => translate('messages.Store employee login url'),
                'placeholder' => translate('messages.Store employee login url'),
                'tooltip' => translate('Add dynamic url to secure store employee login access.'),
                'icon' => 'tio-users-switch',
                'accent' => 'var(--bs-success-rgb)',
            ],
        ];
    @endphp

    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/app.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Login Setup')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('How customers may sign in, from phone and email to social accounts and OTP.') }}</p>
        </div>

        <ul class="nav nav-tabs border-0 nav--tabs nav--pills mb-4">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.business-settings.login-settings.index') }}">{{translate('Customer Login')}}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('admin.business-settings.login_url_page') }}">{{translate('panel login page Url')}}</a>
            </li>
        </ul>

        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-20">
                    <h4 class="fs-16 mb-1 font-semibold d-block">{{ translate('Panel Login URL') }}</h4>
                    <p class="fs-12 mb-0">{{ translate('Give each panel its own secret login address so the default paths cannot be guessed.') }}</p>
                </div>
                <div class="lus-note">
                    <i class="tio-warning"></i>
                    <span>
                        {{ translate('messages.Saving a new URL breaks the old link immediately. Copy it before you sign out or you lose access.') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach ($lus_panels as $lus_panel)
                @php
                    $lus_saved = $data[$lus_panel['field']] ?? null;
                    $lus_value = old($lus_panel['field'], $lus_saved);
                @endphp
                <div class="col-xl-6">
                    <form action="{{route('admin.business-settings.login_url_update')}}" method="post"
                          class="card lus-card" style="--lus-accent: {{ $lus_panel['accent'] }};">
                        @csrf
                        <input type="hidden" name="type" value="{{ $lus_panel['type'] }}">

                        <div class="card-header d-flex flex-wrap">
                            <span class="lus-icon">
                                <i class="{{ $lus_panel['icon'] }}"></i>
                            </span>
                            <div class="flex-grow-1">
                                <h4 class="fs-16 mb-1 font-semibold d-block">{{ $lus_panel['title'] }}</h4>
                                <p class="fs-12 mb-0">{{ $lus_panel['subtitle'] }}</p>
                            </div>
                            @if ($lus_saved)
                                <span class="lus-chip lus-chip--active">
                                    <i class="tio-checkmark-circle"></i> {{ translate('Active') }}
                                </span>
                            @else
                                <span class="lus-chip lus-chip--idle">
                                    <i class="tio-warning"></i> {{ translate('Not set') }}
                                </span>
                            @endif
                        </div>

                        <div class="card-body">
                            <div class="form-group mb-2">
                                <label class="form-label" for="lus-input-{{ $lus_panel['type'] }}">
                                    {{ $lus_panel['label'] }}
                                    <span class="input-label-secondary text--title" data-toggle="tooltip"
                                          data-placement="right"
                                          data-original-title="{{ $lus_panel['tooltip'] }}">
                                        <i class="tio-info-outined"></i>
                                    </span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text h--45px">/login/</span>
                                    </div>
                                    <input type="text" id="lus-input-{{ $lus_panel['type'] }}"
                                           class="form-control h--45px lus-input {{ $errors->has($lus_panel['field']) ? 'is-invalid' : '' }}"
                                           name="{{ $lus_panel['field'] }}"
                                           value="{{ $lus_value }}"
                                           data-initial="{{ $lus_saved }}"
                                           placeholder="{{ $lus_panel['placeholder'] }}"
                                           pattern="[A-Za-z0-9_\-]+"
                                           maxlength="100"
                                           autocomplete="off" spellcheck="false" required
                                           aria-describedby="lus-hint-{{ $lus_panel['type'] }}">
                                </div>
                            </div>

                            @error($lus_panel['field'])
                                <p class="lus-error mb-2">{{ $message }}</p>
                            @enderror

                            <p class="lus-hint mb-3" id="lus-hint-{{ $lus_panel['type'] }}">
                                {{ translate('Letters, numbers, hyphens and underscores only. Must be unique.') }}
                            </p>

                            <div class="lus-preview">
                                <span class="lus-preview-label">{{ translate('Login link') }}</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="lus-preview-value flex-grow-1">{{ $lus_base_url }}{{ $lus_value }}</span>
                                    <button type="button" class="lus-icon-btn lus-copy" data-toggle="tooltip"
                                            title="{{ translate('Copy Link') }}" aria-label="{{ translate('Copy Link') }}"
                                            {{ $lus_value ? '' : 'disabled' }}>
                                        <i class="tio-copy"></i>
                                    </button>
                                    @php $lus_can_open = $lus_saved && $lus_value === $lus_saved; @endphp
                                    <a class="lus-icon-btn lus-open {{ $lus_can_open ? '' : 'disabled' }}"
                                       @if ($lus_can_open) href="{{ $lus_base_url . $lus_saved }}" @endif
                                       target="_blank" rel="noopener" data-toggle="tooltip"
                                       title="{{ translate('Open in new tab') }}" aria-label="{{ translate('Open in new tab') }}">
                                        <i class="tio-open-in-new"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="lus-dirty d-none">
                                <i class="tio-info-outined"></i> {{ translate('Unsaved changes') }}
                            </span>
                            <div class="btn--container justify-content-end ml-auto">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="{{getEnvMode()!='demo'?'submit':'button'}}" class="btn btn--primary call-demo">
                                    <i class="tio-save"></i> {{translate('messages.Save')}}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        (function () {
            const LUS_BASE = @json($lus_base_url);

            function lusSanitize(value) {
                return (value || '').replace(/[^A-Za-z0-9_-]/g, '');
            }

            function lusSync($card) {
                const $input = $card.find('.lus-input');
                const value = $input.val();
                const initial = ($input.data('initial') || '').toString();
                const isDirty = value !== initial;
                const canOpen = !isDirty && value !== '';

                $card.find('.lus-preview-value').text(LUS_BASE + value);
                $card.find('.lus-dirty').toggleClass('d-none', !isDirty);
                $card.find('.lus-copy').prop('disabled', value === '');

                const $open = $card.find('.lus-open');
                $open.toggleClass('disabled', !canOpen);
                if (canOpen) {
                    $open.attr('href', LUS_BASE + initial);
                } else {
                    $open.removeAttr('href');
                }
            }

            function lusCopy(text) {
                // The admin panel is often served over plain HTTP, where the async
                // clipboard API is unavailable or rejects. Always keep a fallback so
                // the button never fails silently.
                function lusCopyFallback() {
                    const $temp = $('<input>');
                    $('body').append($temp);
                    $temp.val(text).select();
                    document.execCommand('copy');
                    $temp.remove();
                    toastr.success('{{ translate('messages.Text copied') }}');
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        toastr.success('{{ translate('messages.Text copied') }}');
                    }).catch(lusCopyFallback);
                    return;
                }

                lusCopyFallback();
            }

            $(document).on('input', '.lus-input', function () {
                const clean = lusSanitize($(this).val());
                if (clean !== $(this).val()) {
                    $(this).val(clean);
                }
                lusSync($(this).closest('.lus-card'));
            });

            $(document).on('click', '.lus-copy', function () {
                lusCopy($(this).closest('.lus-card').find('.lus-preview-value').text().trim());
            });

            $(document).on('reset', '.lus-card', function () {
                const $card = $(this);
                window.setTimeout(function () {
                    lusSync($card);
                }, 0);
            });

            $('.lus-card').each(function () {
                lusSync($(this));
            });
        })();
    </script>
@endpush
