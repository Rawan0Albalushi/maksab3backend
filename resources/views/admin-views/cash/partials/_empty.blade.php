{{-- Empty state for a Cash Operations ledger.

     @include('admin-views.cash.partials._empty', [
         'empty_title' => translate('messages.No transaction found'),
         'empty_body'  => request()->filled('search')
             ? translate('messages.Nothing matches this search. Try another name or reference.')
             : translate('messages.Cash you collect appears here the moment it is recorded above.'),
     ])

     A ledger that is empty because a search is on wants different copy from
     one that is empty because nothing has been recorded — the first needs a
     way out, the second needs to point at the composer above it.

     `.empty--data` is the shared illustration block from style.css; the second
     line is styled in `cash.css` §5. --}}

<div class="empty--data">
    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
    <h5>{{ $empty_title }}</h5>
    <p>{{ $empty_body }}</p>
</div>
