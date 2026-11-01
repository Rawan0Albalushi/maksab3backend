{{-- The action cell of a withdraw queue row.

     @include('admin-views.withdraw.partials._row-action', [
         'withdraw_id'    => $wr->id,
         'payee_present'  => (bool) $wr->rider,
         'disabled_hint'  => translate('messages.This rider has been removed'),
     ])

     When the payee record is gone the cell used to be empty, which read as
     "this row has no actions" rather than "this one cannot be opened". It now
     shows the same view control, switched off, and says why on hover.

     It is a `<span>`, not a `<button disabled>`: a disabled button fires no
     mouse events in any browser, so the tooltip explaining the disabled state
     would never appear. `aria-disabled` carries the semantics instead, and
     `tabindex="0"` keeps it reachable — Bootstrap's default tooltip trigger is
     "hover focus", so keyboard users get the explanation too.

     Styles: `withdraw.css` §3b. --}}

@if($payee_present)
    <a href="javascript:;" data-id="{{ $withdraw_id }}"
       class="btn btn-sm action-btn action-btn--view withdraw-info-show"
       title="{{ translate('View details') }}">
        <i class="tio-visible-outlined"></i>
    </a>
@else
    <span class="btn btn-sm action-btn wdr-action-off"
          data-toggle="tooltip" title="{{ $disabled_hint }}"
          role="button" aria-disabled="true" tabindex="0">
        <i class="tio-visible-outlined"></i>
    </span>
@endif
