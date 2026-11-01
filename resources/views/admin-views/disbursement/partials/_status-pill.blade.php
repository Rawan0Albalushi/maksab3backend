{{-- Status pill, shared by all six disbursement screens.

     @include('admin-views.disbursement.partials._status-pill', ['status' => $disbursement->status])

     The label map is literal, never `translate($model->status)` — a variable
     inside translate() writes whatever it is handed into the language file
     (playbook §8). `processing` is here because the delivery man and rider
     tabs list it; `check_status()` never sets it, but old rows may carry it.

     Styles: `disbursement.css` §1. --}}

@php
    $sdb_pill_labels = [
        'pending' => translate('messages.Pending'),
        'processing' => translate('messages.Processing'),
        'completed' => translate('messages.Completed'),
        'partially_completed' => translate('messages.Partially completed'),
        'canceled' => translate('messages.Canceled'),
    ];
    $sdb_pill_classes = [
        'pending' => 'sdb-pill--pending',
        'processing' => 'sdb-pill--partial',
        'completed' => 'sdb-pill--completed',
        'partially_completed' => 'sdb-pill--partial',
        'canceled' => 'sdb-pill--canceled',
    ];
@endphp

<span class="sdb-pill {{ $sdb_pill_classes[$status] ?? '' }}">{{ $sdb_pill_labels[$status] ?? $status }}</span>
