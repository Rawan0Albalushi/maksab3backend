{{-- "How it Works" walkthrough, shared by the Mail Config and Send Test Mail screens. --}}
<div class="modal fade" id="works-modal">
    <div class="modal-dialog status-warning-modal modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="single-item-slider owl-carousel">
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{ asset('/public/assets/admin/img/mail-config/slide-1.png') }}" alt="" class="mb-20">
                                <h5 class="modal-title">{{ translate('Find SMTP Server Details') }}</h5>
                            </div>
                            <ul>
                                <li>{{ translate('Get the SMTP hostname, port, username and password from your email provider.') }}</li>
                                <li>{{ translate("Note: If you're not sure where to find these details, check the email provider's documentation or support resources for guidance.") }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{ asset('/public/assets/admin/img/mail-config/slide-2.png') }}" alt="" class="mb-20">
                                <h5 class="modal-title">{{ translate('Configure SMTP Settings') }}</h5>
                            </div>
                            <ul>
                                <li>{{ translate('Go to the SMTP mail setup page in the admin panel.') }}</li>
                                <li>{{ translate('Enter the obtained SMTP server details, including the hostname, port, username, and password.') }}</li>
                                <li>{{ translate('Choose the appropriate encryption method (e.g., SSL, TLS) if required. Save the settings.') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mb-20">
                            <div class="text-center">
                                <img src="{{ asset('/public/assets/admin/img/mail-config/slide-3.png') }}" alt="" class="mb-20">
                                <h5 class="modal-title">{{ translate('Test SMTP Connection') }}</h5>
                            </div>
                            <ul>
                                <li>{{ translate('Click on the "Send Test Mail" button to verify the SMTP connection.') }}</li>
                                <li>{{ translate('If successful, you will see a confirmation message indicating that the connection is working fine.') }}</li>
                                <li>{{ translate('If not, double-check your SMTP settings and try again.') }}</li>
                                <li>{{ translate("Note: If you're unsure about the SMTP settings, contact your email service provider or IT administrator for assistance.") }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="item">
                        <div class="mw-353px mb-20 mx-auto">
                            <div class="text-center">
                                <img src="{{ asset('/public/assets/admin/img/mail-config/slide-4.png') }}" alt="" class="mb-20">
                                <h5 class="modal-title">{{ translate('Enable Mail Configuration') }}</h5>
                            </div>
                            <ul class="px-3">
                                <li>{{ translate('Once the SMTP test passes, switch mail configuration on.') }}</li>
                                <li>{{ translate('This will allow the system to send emails using the configured SMTP settings.') }}</li>
                            </ul>
                            <div class="btn-wrap">
                                <button type="button" class="btn btn--primary w-100" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center">
                    <div class="slide-counter"></div>
                </div>
            </div>
        </div>
    </div>
</div>
