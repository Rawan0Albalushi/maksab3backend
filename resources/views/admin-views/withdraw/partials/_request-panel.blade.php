{{-- The slide-in request panel: an empty shell the page fills over ajax from
     `getWithdrawDetails`, which renders that area's own `_side_view` partial.

     @include('admin-views.withdraw.partials._request-panel')

     `.wdr` goes on the wrapper as well as on the page container — the panel is
     a sibling of `.content`, not a child, so the page's scope class does not
     reach it.

     The markup keeps every class the existing scripts bind to
     (`withdraw-info-sidebar`, `withdraw-info-hide`, `#data-view`); only the
     header is new. Styles: `withdraw.css` §5. --}}

<div class="withdraw-info-sidebar-wrap wdr">
    <div class="withdraw-info-sidebar-overlay"></div>
    <div class="withdraw-info-sidebar">
        <div class="wdr-panel__head">
            <span class="wdr-panel__title">{{ translate('Withdraw request') }}</span>
            <span class="circle bg-light withdraw-info-hide cursor-pointer" role="button"
                  aria-label="{{ translate('Close') }}">
                <i class="tio-clear"></i>
            </span>
        </div>

        <div id="data-view" class="offcanvas-inner"></div>
    </div>
</div>
