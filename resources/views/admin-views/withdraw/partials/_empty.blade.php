{{-- Empty state for a withdraw queue.

     @include('admin-views.withdraw.partials._empty', [
         'empty_title' => translate('messages.No withdraw request found'),
         'empty_body'  => $is_filtered
             ? translate('messages.Nothing matches this filter. Try another status or clear the search.')
             : translate('messages.Requests appear here as soon as someone asks to withdraw their earnings.'),
     ])

     A queue that is empty because a filter is on wants different copy from one
     that is empty because nobody has asked for money yet.

     `.empty--data` is the shared illustration block from style.css; the second
     line is styled in `withdraw.css` §4. --}}

<div class="empty--data">
    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
    <h5>{{ $empty_title }}</h5>
    <p>{{ $empty_body }}</p>
</div>
