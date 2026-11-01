{{-- Empty state for a disbursement list or details table.

     @include('admin-views.disbursement.partials._empty', [
         'empty_title' => translate('messages.No payout found'),
         'empty_body'  => $is_filtered
             ? translate('messages.Nothing matches this filter. Try another rider or payment method.')
             : translate('messages.This run holds no rider payouts.'),
     ])

     A list that is empty because a filter is on wants different copy from one
     that is empty because nothing has run yet — the first needs a way out, the
     second needs to say when to expect something.

     `.empty--data` is the shared illustration block from style.css; the second
     line is styled in `disbursement.css` §8. --}}

<div class="empty--data">
    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
    <h5>{{ $empty_title }}</h5>
    <p>{{ $empty_body }}</p>
</div>
