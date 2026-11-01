{{--
    Global search palette, shared by the admin and vendor panels.

    Rendered by layouts/{admin,vendor}/partials/_header.blade.php for both the
    v1 and v2 header chrome, so it must not depend on either one. The panel
    layout owns the AJAX wiring; markup and keyboard behaviour live here and in
    public/assets/admin/{css/global-search.css,js/global-search.js}.

    @param string $searchRoute  Panel-specific POST endpoint for the lookup.
--}}
<div class="modal fade removeSlideDown gsearch-modal" id="staticBackdrop" tabindex="-1"
     role="dialog" aria-label="{{ translate('Search by keyword') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered gsearch-dialog" role="document">
        <div class="modal-content gsearch border-0">

            <div class="gsearch-head">
                <span class="gsearch-head-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.2-3.2"></path>
                    </svg>
                </span>

                {{-- The action is only read by the AJAX handler; onsubmit stops a
                     native GET to this POST-only route even if JS fails to bind. --}}
                <form class="gsearch-form" id="searchForm" action="{{ $searchRoute }}" onsubmit="return false;">
                    @csrf
                    <input type="search" id="searchInput" name="search" maxlength="255" autocomplete="off"
                           class="form-control gsearch-input search-input"
                           placeholder="{{ translate('Search by keyword') }}"
                           aria-label="{{ translate('Search by keyword') }}"
                           role="combobox" aria-expanded="true" aria-autocomplete="list"
                           aria-controls="searchResults">
                </form>

                <button type="button" class="gsearch-clear" id="gsearchClear" hidden
                        aria-label="{{ translate('Clear') }}" title="{{ translate('Clear') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"></path>
                    </svg>
                </button>

                <button type="button" class="gsearch-esc" data-dismiss="modal">{{ translate('Esc') }}</button>
            </div>

            <div class="gsearch-progress" aria-hidden="true"></div>

            <div class="gsearch-body search-result" id="searchResults" role="listbox"
                 aria-live="polite" aria-label="{{ translate('Search result') }}"></div>

            <div class="gsearch-foot">
                <span class="gsearch-hint"><kbd>&uarr;</kbd><kbd>&darr;</kbd>{{ translate('Navigate') }}</span>
                <span class="gsearch-hint"><kbd>&crarr;</kbd>{{ translate('Open') }}</span>
                <span class="gsearch-hint"><kbd>Esc</kbd>{{ translate('Close') }}</span>
            </div>

        </div>
    </div>
</div>
