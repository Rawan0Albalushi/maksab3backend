{{-- Per-row actions on a disbursement details table: inspect, then release or
     cancel — or, once released, put it back to pending.

     @include('admin-views.disbursement.partials._row-actions', [
         'detail'       => $detail,
         'status_route' => 'admin.transactions.rider-disbursement.change-status',
     ])

     `action-btn-section` is what the bulk tray hides: while rows are ticked,
     the per-row release/cancel buttons step aside so the two apply to the
     selection instead. The inspect button stays. --}}

<div class="btn--container justify-content-center">
    <a class="btn btn-sm action-btn action-btn--view" href="javascript:;"
       data-toggle="modal" data-target="#payment-info-{{ $detail->id }}" title="{{ translate('View details') }}">
        <i class="tio-visible-outlined"></i>
    </a>
    @if($detail->status == 'completed')
        <a class="btn btn-sm btn--danger btn-outline-danger action-btn action-btn-section"
           href="{{ route($status_route, ['id' => $detail->id, 'status' => 'pending']) }}"
           data-toggle="tooltip" title="{{ translate('Reverse status back to pending') }}">
            <i class="tio-restore"></i>
        </a>
    @else
        @if($detail->status != 'canceled')
            <a class="btn btn-sm btn--danger btn-outline-danger action-btn action-btn-section"
               href="{{ route($status_route, ['id' => $detail->id, 'status' => 'canceled']) }}"
               data-toggle="tooltip" title="{{ translate('Cancel') }}">
                <i class="tio-clear"></i>
            </a>
        @endif
        <a class="btn btn-sm btn--primary btn-outline-primary action-btn action-btn-section"
           href="{{ route($status_route, ['id' => $detail->id, 'status' => 'completed']) }}"
           data-toggle="tooltip" title="{{ translate('Complete') }}">
            <i class="tio-done"></i>
        </a>
    @endif
</div>
