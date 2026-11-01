@extends('layouts.admin.app')

@section('title', translate('Send Test Mail'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $mail_config = \App\CentralLogics\Helpers::get_business_settings('mail_config');
        $mail_config = is_array($mail_config) ? $mail_config : [];
        $is_active = (int) ($mail_config['status'] ?? 0) === 1;
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-email',
            'title' => translate('messages.Smtp mail setup'),
            'summary' => translate('Send a real message through your SMTP settings to confirm delivery works.'),
            'helpTarget' => '#works-modal',
        ])

        @include('admin-views.business-settings.partials.mail-tabs', ['current' => 'test'])

        <div class="py-2"></div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-telegram"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Send Test Mail') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('The message is sent using the SMTP credentials saved on the Mail Config tab.') }}
                            </p>
                        </div>
                        <div class="tps-card__aside">
                            <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                            </span>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        @unless ($is_active)
                            <div class="tps-note tps-note--warn mb-4">
                                <i class="tio-warning"></i>
                                <div>
                                    {{ translate('Mail service is switched off, so no test mail will be delivered. Turn it on first from the') }}
                                    <a href="{{ route('admin.business-settings.third-party.mail-config') }}">{{ translate('Mail Config') }}</a>
                                    {{ translate('tab.') }}
                                </div>
                            </div>
                        @endunless

                        <form action="javascript:">
                            <div class="row gx-3 gy-2 align-items-end">
                                <div class="col-md-8 col-sm-7">
                                    <div class="tps-field">
                                        <label for="test-email" class="tps-field__label">{{ translate('email') }}</label>
                                        <input type="email" id="test-email" class="form-control"
                                               placeholder="{{ translate('messages.Ex') }}: jhon@email.com">
                                        <small class="tps-field__hint">
                                            {{ translate('Use an inbox you can open right now so you can confirm the message arrived.') }}
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-5">
                                    <button type="button" class="btn btn--primary h--45px btn-block send-mail">
                                        <i class="tio-telegram"></i> {{ translate('Send mail') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-info-outined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Current Configuration') }}</h2>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <ul class="list-unstyled m-0 d-flex flex-column __gap-12px fs-12">
                            @foreach ([
                                [translate('messages.Mailer name'), $mail_config['name'] ?? null],
                                [translate('messages.Email id'), $mail_config['email_id'] ?? null],
                                [translate('messages.host'), $mail_config['host'] ?? null],
                                [translate('messages.port'), $mail_config['port'] ?? null],
                                [translate('messages.encryption'), $mail_config['encryption'] ?? null],
                            ] as [$label, $value])
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="text-muted">{{ $label }}</span>
                                    <span class="font-weight-bold text-right">{{ $value ?: '—' }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tps-note tps-note--muted mt-3">
                            <i class="tio-info-outined"></i>
                            <div>{{ translate('If the test fails, double check the port and encryption pair, then try again.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin-views.business-settings.partials.mail-works-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        function ValidateEmail(inputText) {
            let mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
            return !!inputText.match(mailformat);
        }

        $(document).on('click', '.send-mail', function () {
            @if (getEnvMode() == 'demo')
                toastr.info('{{ translate('Update option is disabled for demo!') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            @else
            if (ValidateEmail($('#test-email').val())) {
                Swal.fire({
                    title: '{{ translate('Are you sure?') }}',
                    text: "{{ translate('A test mail will be sent to your email') }}!",
                    showCancelButton: true,
                    confirmButtonColor: '#00868F',
                    cancelButtonColor: 'secondary',
                    confirmButtonText: '{{ translate('Yes') }}!'
                }).then((result) => {
                    if (result.value) {
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                            }
                        });
                        $.ajax({
                            url: "{{ route('admin.business-settings.third-party.mail.send') }}",
                            method: 'GET',
                            data: {
                                "email": $('#test-email').val()
                            },
                            beforeSend: function () {
                                $('#loading').show();
                            },
                            success: function (data) {
                                if (data.success === 2) {
                                    toastr.error('{{ translate('Email configuration error') }} !!');
                                } else if (data.success === 1) {
                                    toastr.success('{{ translate('Email configured perfectly!') }}!');
                                } else {
                                    toastr.info('{{ translate('Email status is not active') }}!');
                                }
                            },
                            complete: function () {
                                $('#loading').hide();
                            }
                        });
                    }
                })
            } else {
                toastr.error('{{ translate('Invalid email address') }} !!');
            }
            @endif
        });
    </script>
@endpush
