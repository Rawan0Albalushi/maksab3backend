@extends('layouts.admin.app')

@section('title', translate('Mail Config'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $config = \App\Models\BusinessSetting::where(['key' => 'mail_config'])->first();
        $data = $config ? json_decode($config['value'], true) : null;
        $data = is_array($data) ? $data : null;
        $is_demo = getEnvMode() == 'demo';
        $is_active = (int) ($data['status'] ?? 0) === 1;

        $firebase_note = '';
        if (\App\CentralLogics\Helpers::get_business_settings('firebase_otp_verification', false) == 1) {
            $firebase_note = '<p class=text--danger>' . translate('NOTE: Currently Your FireBase OTP System is Active.Users won\'t get any OTP related mails.') . '</p>';
        }
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-email',
            'title' => translate('messages.Smtp mail setup'),
            'summary' => translate('Set the SMTP server the system uses for order updates, OTP codes and account emails.'),
            'helpTarget' => '#works-modal',
        ])

        @include('admin-views.business-settings.partials.mail-tabs', ['current' => 'config'])

        <div class="py-2"></div>

        {{-- Master switch. The id/form-id pair is what confirm-Status-Toggle submits. --}}
        <form action="{{ route('admin.business-settings.third-party.mail-config-status') }}" method="post"
              id="mail-config-disable_form">
            @csrf
            <div class="tps-switchbar mb-3">
                <div class="tps-switchbar__text">
                    <h6>{{ translate('Mail Service') }}</h6>
                    <p>
                        {{ $is_active
                            ? translate('The system is allowed to send emails through the SMTP settings below.')
                            : translate('By Turning OFF mail configuration, all your mailing services will be off.') }}
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                        {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                    </span>
                    <label class="toggle-switch toggle-switch-sm p-0 m-0">
                        <input id="mail-config-disable" type="checkbox"
                               data-id="mail-config-disable"
                               data-type="status"
                               data-image-on="{{ asset('/public/assets/admin/img/modal/mail-success.png') }}"
                               data-image-off="{{ asset('/public/assets/admin/img/modal/mail-warning.png') }}"
                               data-title-on="{{ translate('Important!') }}"
                               data-title-off="{{ translate('warning') }}"
                               data-text-on="<p>{{ translate('The system can send emails. Check your SMTP settings first to avoid delivery problems.') }}</p>
                               {{ $firebase_note }}"
                               data-text-off="<p>{{ translate('The system stops sending emails. Turn this off only temporarily — anything that relies on email will break.') }}</p>"
                               class="status toggle-switch-input dynamic-checkbox"
                               name="status" value="1" {{ $is_active ? 'checked' : '' }}>
                        <span class="toggle-switch-label text p-0">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </div>
            </div>
        </form>

        <form action="javascript:" method="post" id="mail-config-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="status" value="{{ $data['status'] ?? 0 }}">

            <div class="disable-on-turn-of {{ $is_active ? '' : 'inactive' }}">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="tps-card">
                            <div class="tps-card__head">
                                <span class="tps-card__brand"><i class="tio-globe"></i></span>
                                <div class="tps-card__titles">
                                    <h2 class="tps-card__title">{{ translate('SMTP Credentials') }}</h2>
                                    <p class="tps-card__subtitle">
                                        {{ translate('Your email provider or hosting panel supplies every value on this form.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="tps-card__body">
                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('Sender Identity') }}</h3>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="name" class="tps-field__label">
                                                    {{ translate('messages.Mailer name') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="name" type="text" class="form-control" name="name"
                                                       placeholder="{{ translate('messages.Ex') }}: John Doe"
                                                       value="{{ ! $is_demo ? $data['name'] ?? '' : '' }}" required>
                                                <small class="tps-field__hint">{{ translate('Shown as the sender name in the recipient inbox.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="email" class="tps-field__label">
                                                    {{ translate('messages.Email id') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="email" type="text" class="form-control" name="email"
                                                       placeholder="{{ translate('messages.Ex') }}: ex@yahoo.com"
                                                       value="{{ ! $is_demo ? $data['email_id'] ?? '' : '' }}" required>
                                                <small class="tps-field__hint">{{ translate('The address emails are sent from.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('Server') }}</h3>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="host" class="tps-field__label">
                                                    {{ translate('messages.host') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="host" type="text" class="form-control" name="host"
                                                       placeholder="{{ translate('messages.Ex') . ' : ' . 'mail.6am.one' }}"
                                                       value="{{ ! $is_demo ? $data['host'] ?? '' : '' }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="driver" class="tps-field__label">
                                                    {{ translate('messages.Driver') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="driver" type="text" class="form-control" name="driver"
                                                       placeholder="{{ translate('messages.Ex') . ' : ' . 'SMTP' }}"
                                                       value="{{ ! $is_demo ? $data['driver'] ?? '' : '' }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="port" class="tps-field__label">
                                                    {{ translate('messages.port') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="port" type="text" class="form-control" name="port"
                                                       placeholder="{{ translate('messages.Ex') . ' : 587' }}"
                                                       value="{{ ! $is_demo ? $data['port'] ?? '' : '' }}" required>
                                                <small class="tps-field__hint">587 for TLS, 465 for SSL.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="encryption" class="tps-field__label">
                                                    {{ translate('messages.encryption') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="encryption" type="text" class="form-control" name="encryption"
                                                       placeholder="{{ translate('messages.Ex') }}: tls"
                                                       value="{{ ! $is_demo ? $data['encryption'] ?? '' : '' }}" required>
                                                <small class="tps-field__hint">{{ translate('Must match the port you entered.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('Authentication') }}</h3>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="username" class="tps-field__label">
                                                    {{ translate('messages.username') }} <span class="tps-req">*</span>
                                                </label>
                                                <input id="username" type="text" class="form-control" name="username"
                                                       autocomplete="off" placeholder="{{ translate('messages.Ex') }}: ex@yahoo.com"
                                                       value="{{ ! $is_demo ? $data['username'] ?? '' : '' }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="password" class="tps-field__label">
                                                    {{ translate('messages.password') }} <span class="tps-req">*</span>
                                                </label>
                                                <div class="tps-input-wrap">
                                                    <input id="password" type="password" class="form-control" name="password"
                                                           autocomplete="new-password"
                                                           placeholder="{{ translate('messages.Ex') . ' : 5+ Characters' }}"
                                                           value="{{ ! $is_demo ? $data['password'] ?? '' : '' }}" required>
                                                    <button type="button" class="tps-input-action tps-toggle-secret"
                                                            data-target="#password" aria-label="{{ translate('Show value') }}">
                                                        <i class="tio-visible"></i>
                                                    </button>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('Many providers require an app password rather than your account password.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-card__foot">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="{{ ! $is_demo ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                                    <i class="tio-save"></i> {{ translate('messages.Save') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="tps-card">
                            <div class="tps-card__head">
                                <span class="tps-card__brand"><i class="tio-help-outlined"></i></span>
                                <div class="tps-card__titles">
                                    <h2 class="tps-card__title">{{ translate('Common Provider Settings') }}</h2>
                                    <p class="tps-card__subtitle">{{ translate('Use these as a starting point, then confirm with your provider.') }}</p>
                                </div>
                            </div>
                            <div class="tps-card__body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 fs-12">
                                        <thead>
                                            <tr class="text-uppercase" style="font-size: 0.6875rem;">
                                                <th class="border-top-0">{{ translate('Provider') }}</th>
                                                <th class="border-top-0">{{ translate('messages.host') }}</th>
                                                <th class="border-top-0">{{ translate('messages.port') }}</th>
                                                <th class="border-top-0">{{ translate('messages.encryption') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([
                                                ['Gmail', 'smtp.gmail.com', '587', 'tls'],
                                                ['Outlook', 'smtp.office365.com', '587', 'tls'],
                                                ['Yahoo', 'smtp.mail.yahoo.com', '465', 'ssl'],
                                                ['Zoho', 'smtp.zoho.com', '587', 'tls'],
                                                ['SendGrid', 'smtp.sendgrid.net', '587', 'tls'],
                                                ['Mailgun', 'smtp.mailgun.org', '587', 'tls'],
                                            ] as $provider)
                                                <tr>
                                                    <td class="font-weight-bold">{{ $provider[0] }}</td>
                                                    <td>{{ $provider[1] }}</td>
                                                    <td>{{ $provider[2] }}</td>
                                                    <td>{{ $provider[3] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="tps-note tps-note--info mt-3">
                                    <i class="tio-info"></i>
                                    <div>{{ translate('After saving, send a test mail to confirm the connection actually works.') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="sent-mail-modal">
        <div class="modal-dialog status-warning-modal modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <div class="text-center mb-20">
                        <img src="{{ asset('/public/assets/admin/img/sent-mail-box.png') }}" alt="" class="mb-20">
                        <h5 class="modal-title">{{ translate('Congratulations! Your SMTP mail has been setup successfully!') }}</h5>
                        <p class="txt">{{ translate('Send a test mail to check whether it works.') }}</p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <a href="{{ route('admin.business-settings.third-party.test') }}" class="btn btn--primary min-w-120">
                            <img src="{{ asset('/public/assets/admin/img/paper-plane.png') }}" alt=""> {{ translate('Send Test Mail') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="update-data-modal">
        <div class="modal-dialog status-warning-modal modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <div class="text-center mb-20">
                        <img src="{{ asset('/public/assets/admin/img/mail-config/save-data.png') }}" alt="" class="mb-20">
                        <h5 class="modal-title">{{ translate('Send a Test Mail to Your Email?') }}</h5>
                        <p class="txt">{{ translate('A test mail will be sent to your email to confirm it works.') }}</p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="submit" class="btn btn--primary min-w-120" data-dismiss="modal">
                            <i class="tio-send"></i> {{ translate('Send Test Mail') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin-views.business-settings.partials.mail-works-modal')
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')

    <script>
        "use strict";

        const disableMailConf = () => {
            if ($('#mail-config-disable').is(':checked')) {
                $('.disable-on-turn-of').removeClass('inactive')
            } else {
                $('.disable-on-turn-of').addClass('inactive')
            }
        }

        $('#mail-config-disable').on('change', function () {
            disableMailConf()
        })

        $('#mail-config-form').submit(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{ route('admin.business-settings.third-party.mail-config') }}",
                method: 'POST',
                data: $('#mail-config-form').serialize(),
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function () {
                    toastr.success('{{ translate('Updated successfully') }}');
                    $('#sent-mail-modal').modal('show');
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        })
    </script>
@endpush
