{{-- Status tabs for a withdraw queue, with a count per tab.

     @include('admin-views.withdraw.partials._tabs', [
         'tab_route' => 'admin.transactions.delivery-man.withdraw_list',
         'status'    => $status,
         'summary'   => $summary,
     ])

     This replaces the `<select>` that POSTed to `status-filter` and reloaded:
     that stored the choice in one session key shared by all three queues, so
     filtering one list silently filtered the other two, and the URL never said
     which view you were looking at.

     The links carry whatever else is on the query string (the search box), so
     switching status does not drop the term the admin typed. Counts come from
     one grouped query in the controller. Styled by `admin-tabs.css`, which
     already knows `.nav-link .badge`. --}}

@php
    $wdr_tabs = [
        'all' => translate('messages.All'),
        'pending' => translate('messages.Pending'),
        'approved' => translate('messages.Approved'),
        'denied' => translate('messages.Denied'),
    ];
    $wdr_tab_approved = ['pending' => 0, 'approved' => 1, 'denied' => 2];

    $wdr_tab_query = array_filter(request()->except(['status', 'page']), function ($v) {
        return $v !== null && $v !== '';
    });
@endphp

<ul class="nav nav-tabs mb-4 border-0 pt-2">
    @foreach($wdr_tabs as $tab => $label)
        <li class="nav-item">
            <a class="nav-link {{ $status == $tab ? 'active' : '' }}"
               href="{{ route($tab_route, array_merge($wdr_tab_query, ['status' => $tab])) }}">
                {{ $label }}
                <span class="badge">
                    {{ $tab === 'all'
                        ? $summary->sum('requests')
                        : ($summary[$wdr_tab_approved[$tab]]->requests ?? 0) }}
                </span>
            </a>
        </li>
    @endforeach
</ul>
