{{-- Status tab strip for a disbursement list, with a count per tab.

     @include('admin-views.disbursement.partials._tabs', [
         'tab_route'     => 'admin.transactions.dm-disbursement.list',
         'tab_statuses'  => ['all', 'pending', 'processing', 'completed', 'partially_completed', 'canceled'],
         'status'        => $status,
         'batch_summary' => $batch_summary,
     ])

     Counts come from one grouped query in the controller, not a count() per
     tab. The links carry whatever else is on the query string (the search
     box), so switching status does not silently drop the term the admin
     typed. Styled by `admin-tabs.css`, which already knows `.nav-link .badge`.

     The store list omits `processing`; the delivery man and rider lists keep
     it because their screens always listed it. --}}

@php
    $sdb_tab_labels = [
        'all' => translate('messages.All'),
        'pending' => translate('messages.Pending'),
        'processing' => translate('messages.Processing'),
        'completed' => translate('messages.Completed'),
        'partially_completed' => translate('messages.Partially completed'),
        'canceled' => translate('messages.Canceled'),
    ];

    $sdb_tab_query = array_filter(request()->except(['status', 'page']), function ($v) {
        return $v !== null && $v !== '';
    });
@endphp

<ul class="nav nav-tabs mb-4 border-0 pt-2">
    @foreach($tab_statuses as $tab)
        <li class="nav-item">
            <a class="nav-link {{ $status == $tab ? 'active' : '' }}"
               href="{{ route($tab_route, array_merge($sdb_tab_query, ['status' => $tab])) }}">
                {{ $sdb_tab_labels[$tab] ?? $tab }}
                <span class="badge">{{ $tab === 'all' ? $batch_summary->sum() : ($batch_summary[$tab] ?? 0) }}</span>
            </a>
        </li>
    @endforeach
</ul>
