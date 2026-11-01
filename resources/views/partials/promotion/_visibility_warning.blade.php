{{--
    The "approved, but nobody can see it" flag, shown at the end of the promotion's name.

    "Approved" is the admin's answer to the store, not the customer's view of the offer,
    and the two come apart the moment an item sells out, a category is switched off, the store
    closes for the day or the campaign's redemption cap fills. Naming the reason on the row
    answers "why is our offer not showing?" without opening a drawer per row.

    It sits against the name rather than in the action column because it describes the
    promotion, not something the vendor can do to it - and the action column is now a fixed
    view button and a menu, with no room for a third meaning.

    The icon carries the reasons and hands them to partials.promotion._visibility_modal on
    click - which the page including this must also include, once.

    $reasons - string[]; BOGO supplies them from customerVisibilityProblems(). Nothing is
               drawn when it is empty.
    $class   - the caller's own sizing class
--}}
@php($reasons = array_values(array_filter($reasons ?? [])))

@if($reasons)
    <a href="javascript:" class="btn btn--warning btn-outline-warning promo-visibility-warning {{ $class ?? 'promo-name-warning' }}"
       {{-- Read straight off the element by the modal, so opening it costs no request. --}}
       data-reasons="{{ json_encode($reasons) }}"
       aria-label="{{ translate('Offer not visible to customers') }}">
        <i class="tio-warning"></i>
    </a>
@endif
