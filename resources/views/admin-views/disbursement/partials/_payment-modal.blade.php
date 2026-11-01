{{-- Payment details for one payout, as a modal.

     @include('admin-views.disbursement.partials._payment-modal', [
         'detail'         => $detail,
         'subject_label'  => translate('Deliveryman information'),
         'subject_name'   => trim($detail->delivery_man?->f_name . ' ' . $detail->delivery_man?->l_name),
         'subject_phone'  => $detail->delivery_man?->phone,
         'owner_name'     => null,          // vendors only
         'owner_email'    => null,
         'status_route'   => 'admin.transactions.dm-disbursement.change-status',
     ])

     Emit these AFTER the table, never inside a `<tr>` — that is where all
     three screens used to put them, and the parser foster-parents them out of
     the table anyway, leaving them stranded between the rows.

     `method_fields` keys are admin-authored field names from the withdrawal
     method, so `translate($field)` here is the variable-fed shape playbook §8
     acknowledges: it cannot be rewritten at the call site, and renaming it
     would drop whatever an admin has already translated.

     Styles: `disbursement.css` §7. --}}

@php
    $sdb_method_fields = json_decode($detail->withdraw_method?->method_fields ?? '[]', true) ?: [];
    $sdb_owner_name = trim($owner_name ?? '');
    $sdb_owner_email = $owner_email ?? null;
@endphp

<div class="modal fade sdb-modal" id="payment-info-{{ $detail->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="sdb-modal__title">{{ translate('Payment information') }}</h2>
                    <div class="sdb-modal__meta">
                        <span>{{ translate('Disbursement ID') }} <strong>#{{ $detail->disbursement_id }}</strong></span>
                        @include('admin-views.disbursement.partials._status-pill', ['status' => $detail->status])
                    </div>
                </div>
                <button type="button" class="payment-modal-close btn-close border-0 outline-0 bg-transparent ml-auto"
                        data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <i class="tio-clear"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="sdb-panel">
                    <h6 class="sdb-panel__title"><i class="tio-user-outlined"></i> {{ $subject_label }}</h6>
                    <dl class="sdb-dl">
                        <div>
                            <dt>{{ translate('Name') }}</dt>
                            <dd>{{ $subject_name }}</dd>
                        </div>
                        <div>
                            <dt>{{ translate('Contact') }}</dt>
                            <dd>{{ $subject_phone }}</dd>
                        </div>
                    </dl>
                </div>

                @if($sdb_owner_name !== '' || $sdb_owner_email)
                    <div class="sdb-panel">
                        <h6 class="sdb-panel__title"><i class="tio-user-big-outlined"></i> {{ translate('Owner information') }}</h6>
                        <dl class="sdb-dl">
                            <div>
                                <dt>{{ translate('Name') }}</dt>
                                <dd>{{ $sdb_owner_name }}</dd>
                            </div>
                            <div>
                                <dt>{{ translate('email') }}</dt>
                                <dd>{{ $sdb_owner_email }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <div class="sdb-panel">
                    <h6 class="sdb-panel__title"><i class="tio-credit-card"></i> {{ translate('Account information') }}</h6>
                    <dl class="sdb-dl">
                        <div>
                            <dt>{{ translate('Payment method') }}</dt>
                            <dd>{{ $detail->withdraw_method?->method_name ?? translate('messages.N/A') }}</dd>
                        </div>
                        <div>
                            <dt>{{ translate('Amount') }}</dt>
                            <dd class="sdb-dl__amount">{{ \App\CentralLogics\Helpers::format_currency($detail['disbursement_amount']) }}</dd>
                        </div>
                        @foreach($sdb_method_fields as $field => $value)
                            <div>
                                <dt>{{ translate($field) }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            @if($detail->status != 'completed')
                <div class="modal-footer justify-content-end">
                    @if($detail->status == 'pending')
                        <a href="{{ route($status_route, ['id' => $detail->id, 'status' => 'canceled']) }}" class="btn btn--reset">
                            <i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}
                        </a>
                    @endif
                    <a href="{{ route($status_route, ['id' => $detail->id, 'status' => 'completed']) }}" class="btn btn--primary">
                        <i class="tio-checkmark-circle-outlined"></i> {{ translate('Complete') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
