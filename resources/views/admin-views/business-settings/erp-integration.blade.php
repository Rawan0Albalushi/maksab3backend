@extends('layouts.admin.app')

@section('title', translate('ERP integration'))

@push('css_or_js')
    {{-- third-party-setup.css holds the .tps-head and .tps-nav rules the shared
         header and the tab strip are written against, and every one of them is
         scoped under `.tps` — so the wrapper class below is part of loading it.
         Without both, the tab strip renders as a row of bare links. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/erp-integration.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps">
        {{-- The shared header, not a hand-rolled .page-header: this is one of the
             eleven admin/business-settings/third-party/* screens and it carries
             the same tab strip as the other ten. --}}
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-plug',
            'title' => translate('ERP integration'),
            'summary' => translate('Connect an outside ERP so orders, stock and invoices stay in step with your own system.'),
        ])

        {{-- Generate Token --}}
        <div class="card border-0 mb-3">
            <div class="card-header card-header-shadow d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1">{{ translate('messages.Generate token') }}</h5>
                    <p class="fs-12 text-muted mb-0">{{ translate('ERP token description') }}</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="header-action-link" id="copyBaseUrlBtn" onclick="copyBaseUrl()">
                        <i class="tio-link"></i> <span id="copyBaseUrlText">{{ translate('Copy base URL') }}</span>
                    </button>
                    <span class="header-actions-divider"></span>
                    <button type="button" class="header-action-link" data-toggle="modal" data-target="#setupGuideModal">
                        <i class="tio-info-outined"></i> {{ translate('ERP setup guide button') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.business-settings.third-party.integration.store') }}" method="POST">
                    @csrf
                    <div class="bg--secondary rounded p-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">{{ translate('messages.Token name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    placeholder="{{ translate('Eg ERP production') }}" required maxlength="100">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">{{ translate('Webhook URL') }} <span class="text-danger">*</span></label>
                                <input type="url" name="webhook_url" class="form-control"
                                    placeholder="https://erp.example.com/api/v1/integration/webhook" required maxlength="500">
                                <span class="fs-12 text-muted mt-1 d-block">{{ translate('Webhook URL hint') }}</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}" class="btn btn--primary btn-block call-demo">
                                    {{ translate('messages.Generate token') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                @if(session('new_credentials'))
                    <div class="credentials-banner mt-3">
                        <div class="credentials-banner__title">
                            <i class="tio-checkmark-circle"></i>
                            <span>{{ translate('messages.new_token_created') }}</span>
                        </div>
                        <p class="credentials-banner__subtitle">{{ translate('messages.copy_token_warning') }}</p>

                        <div class="credential-group">
                            <div class="credential-group__label">{{ translate('API key') }}</div>
                            <div class="credential-group__field">
                                <input type="text" class="credential-field" id="newApiKey" value="{{ session('new_credentials.api_key') }}" readonly>
                                <button type="button" class="credential-copy-btn" onclick="copyFieldInline(this, 'newApiKey')">
                                    <i class="tio-copy"></i> {{ translate('messages.copy') }}
                                </button>
                            </div>
                        </div>

                        <div class="credential-group">
                            <div class="credential-group__label">{{ translate('API secret') }}</div>
                            <div class="credential-group__field">
                                <input type="password" class="credential-field" id="newApiSecret" value="{{ session('new_credentials.api_secret') }}" readonly>
                                <div class="d-flex" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);gap:4px;">
                                    <button type="button" class="credential-copy-btn" style="position:static;transform:none;" onclick="toggleSecretVisibility()">
                                        <i class="tio-hidden-outlined" id="secretToggleIcon"></i>
                                    </button>
                                    <button type="button" class="credential-copy-btn" style="position:static;transform:none;" onclick="copyFieldInline(this, 'newApiSecret')">
                                        <i class="tio-copy"></i> {{ translate('messages.copy') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="credential-group">
                            <div class="credential-group__label">{{ translate('messages.base_url') }}</div>
                            <div class="credential-group__field">
                                <input type="text" class="credential-field" id="newBaseUrl" value="{{ url('/') }}" readonly>
                                <button type="button" class="credential-copy-btn" onclick="copyFieldInline(this, 'newBaseUrl')">
                                    <i class="tio-copy"></i> {{ translate('messages.copy') }}
                                </button>
                            </div>
                            <span class="fs-12 text-muted mt-1 d-block">{{ translate('messages.base_url_hint') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Token List --}}
        <div class="card border-0">
            <div class="card-header card-header-shadow d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ translate('API tokens') }}</h5>
                <span class="badge badge-soft-primary fs-12 px-2 py-1">{{ $tokens->count() }} {{ translate('messages.Tokens count') }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('SL') }}</th>
                                <th class="border-0">{{ translate('Name') }}</th>
                                <th class="border-0">{{ translate('Webhook URL') }}</th>
                                <th class="border-0">{{ translate('Status') }}</th>
                                <th class="border-0">{{ translate('messages.Last used') }}</th>
                                <th class="border-0">{{ translate('Created at') }}</th>
                                <th class="border-0 text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tokens as $token)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="fw-semibold text-dark">{{ $token->name }}</td>
                                    <td style="min-width:240px;">
                                        <div id="webhook-display-{{ $token->id }}">
                                            <div class="d-flex align-items-center gap-2">
                                                @if($token->webhook_url)
                                                    <span class="text-truncate fs-12" style="max-width: 220px;" title="{{ $token->webhook_url }}">{{ $token->webhook_url }}</span>
                                                @else
                                                    <span class="text-muted fs-12">{{ translate('messages.not_set') }}</span>
                                                @endif
                                                <a href="javascript:" class="text-muted" style="opacity:.6;transition:opacity .15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.6" onclick="document.getElementById('webhook-display-{{ $token->id }}').classList.add('d-none'); document.getElementById('webhook-edit-{{ $token->id }}').classList.remove('d-none');">
                                                    <i class="tio-edit"></i>
                                                </a>
                                            </div>
                                            @if($token->webhook_last_dispatched_at)
                                                <small class="text-muted fs-12">{{ translate('messages.last_dispatched') }}: {{ $token->webhook_last_dispatched_at->diffForHumans() }}</small>
                                            @endif
                                        </div>
                                        <div id="webhook-edit-{{ $token->id }}" class="d-none">
                                            <form action="{{ route('admin.business-settings.third-party.integration.update-webhook', $token->id) }}"
                                                method="POST" class="d-flex gap-1">
                                                @csrf
                                                <input type="url" name="webhook_url" class="form-control form-control-sm"
                                                    value="{{ $token->webhook_url }}"
                                                    placeholder="https://erp.example.com/.../webhook" required maxlength="500">
                                                <button type="submit" class="btn btn-sm btn--primary" style="white-space:nowrap;">
                                                    <i class="tio-save"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn--reset"
                                                    onclick="document.getElementById('webhook-edit-{{ $token->id }}').classList.add('d-none'); document.getElementById('webhook-display-{{ $token->id }}').classList.remove('d-none');">
                                                    <i class="tio-clear"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        @if($token->is_active)
                                            <span class="badge badge-soft-success px-2 py-1">{{ translate('Active') }}</span>
                                        @else
                                            <span class="badge badge-soft-danger px-2 py-1">{{ translate('messages.revoked') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-muted fs-12">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : translate('messages.never') }}</td>
                                    <td class="text-muted fs-12">{{ $token->created_at->format('M d, Y H:i') }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                        @if($token->is_active)
                                            <a class="btn action-btn btn--warning btn-outline-warning new-dynamic-submit-model" title="{{ translate('messages.revoke') }}"
                                                data-id="revoke-token-{{ $token->id }}"
                                                data-type="delete"
                                                data-title="{{ translate('messages.revoke_token_title') }}"
                                                data-text="<p>{{ translate('messages.revoke_token_confirm') }}</p>"
                                                data-image="{{ asset('public/assets/admin/img/modal/info-warning.png') }}">
                                                <i class="tio-block"></i>
                                            </a>
                                            <form id="revoke-token-{{ $token->id }}_form"
                                                action="{{ route('admin.business-settings.third-party.integration.revoke', $token->id) }}"
                                                method="POST">
                                                @csrf
                                            </form>
                                        @endif
                                        <a class="btn action-btn action-btn--delete new-dynamic-submit-model" title="{{ translate('Delete') }}"
                                            data-id="delete-token-{{ $token->id }}"
                                            data-type="delete"
                                            data-title="{{ translate('messages.delete_token_title') }}"
                                            data-text="<p>{{ translate('messages.delete_token_confirm') }}</p>"
                                            data-image="{{ asset('public/assets/admin/img/modal/delete-icon.png') }}">
                                            <i class="tio-delete-outlined"></i>
                                        </a>
                                        <form id="delete-token-{{ $token->id }}_form"
                                            action="{{ route('admin.business-settings.third-party.integration.destroy', $token->id) }}"
                                            method="POST">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="tio-documents-outlined" style="font-size:40px;color:#d1d5db;"></i>
                                            <span class="text-muted mt-2 fs-14">{{ translate('messages.No tokens yet') }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Setup Guide Modal --}}
    <div class="modal fade" id="setupGuideModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="tio-info-outined mr-2" style="color:var(--primary-clr);"></i>
                        {{ translate('ERP setup guide title') }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        @foreach([
                            ['num' => '1', 'title' => translate('messages.Name the token'), 'desc' => translate('messages.Give it a name you will recognise later, such as the system or environment it belongs to.')],
                            ['num' => '2', 'title' => translate('messages.Add your webhook URL'), 'desc' => translate('messages.The HTTPS endpoint on your side that should receive order, stock and invoice events.')],
                            ['num' => '3', 'title' => translate('messages.Store the secret'), 'desc' => translate('messages.The API secret is shown once, at generation. Copy it into your ERP before leaving this page.')],
                        ] as $step)
                            <div class="col-md-4">
                                <div class="bg--secondary rounded p-3 h-100">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="d-flex align-items-center justify-content-center mr-2" style="width:24px;height:24px;border-radius:50%;background:var(--primary-clr);color:#fff;font-size:12px;font-weight:600;">{{ $step['num'] }}</span>
                                        <strong class="fs-14">{{ $step['title'] }}</strong>
                                    </div>
                                    <p class="text-muted fs-12 mb-0">{{ $step['desc'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded overflow-hidden border mt-3">
                        <img src="{{ asset('public/assets/admin/img/erp_integration.gif') }}"
                            alt="{{ translate('ERP setup guide title') }}"
                            class="w-100 d-block">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        function copyFieldInline(btn, id) {
            var input = document.getElementById(id);
            var originalType = input.type;
            input.type = 'text';
            navigator.clipboard.writeText(input.value);
            input.type = originalType;
            btn.classList.add('copied');
            var icon = btn.querySelector('i');
            var origClass = icon.className;
            icon.className = 'tio-checkmark-circle';
            setTimeout(function() {
                btn.classList.remove('copied');
                icon.className = origClass;
            }, 1500);
        }

        function copyBaseUrl() {
            navigator.clipboard.writeText(@json(url('/')));
            var textEl = document.getElementById('copyBaseUrlText');
            var original = textEl.textContent;
            textEl.textContent = '{{ translate('messages.copied') }}';
            setTimeout(function() { textEl.textContent = original; }, 2000);
        }

        function toggleSecretVisibility() {
            var input = document.getElementById('newApiSecret');
            var icon = document.getElementById('secretToggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'tio-visible-outlined';
            } else {
                input.type = 'password';
                icon.className = 'tio-hidden-outlined';
            }
        }
    </script>
@endpush
