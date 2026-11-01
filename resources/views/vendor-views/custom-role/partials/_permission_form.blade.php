@php($selectedModules = $selectedModules ?? [])
@php($permissionTotal = array_sum(array_map(
    fn ($group) => array_sum(array_map(fn ($card) => count($card['items']), $group['cards'])),
    $permissionGroups
)))

<div class="rp-card">
    <div class="rp-card__head rp-card__head--split">
        <div class="rp-card__titles">
            <h2 class="rp-card__title">{{ translate('messages.Set permission') }}</h2>
            <p class="rp-card__subtitle">{{ translate('Tick every section this role should be able to open in the panel.') }}</p>
        </div>
        <span class="rp-summary">
            <span>{{ translate('Permissions selected') }}:</span>
            <span class="rp-summary__count" data-rp-summary-count>0</span>
            <span>/ {{ $permissionTotal }}</span>
        </span>
    </div>

    <div class="rp-toolbar">
        <div class="rp-search" data-rp-search-box>
            <i class="tio-search rp-search__icon"></i>
            <input type="search" class="form-control" data-rp-search-input autocomplete="off"
                placeholder="{{ translate('Search permissions') }}" aria-label="{{ translate('Search permissions') }}">
            <button type="button" class="rp-search__clear" data-rp-search-clear aria-label="{{ translate('Clear search') }}">&times;</button>
        </div>
        <div class="rp-toolbar__spacer"></div>
        <button type="button" class="rp-linkbtn" data-rp-expand-all>{{ translate('Expand all') }}</button>
        <span class="rp-linkbtn__sep">|</span>
        <button type="button" class="rp-linkbtn" data-rp-collapse-all>{{ translate('Collapse all') }}</button>
        <label class="rp-check rp-check--master rp-check--global">
            <input type="checkbox" id="select-all" data-rp-select-all>
            <span class="rp-check__label">{{ translate('All management') }}</span>
        </label>
    </div>

    <div class="rp-card__body">
        <div data-rp-groups>
            @foreach ($permissionGroups as $group)
                @include('vendor-views.custom-role.partials._permission_group', ['group' => $group])
            @endforeach
        </div>
        <div class="rp-empty">
            <i class="tio-search"></i>
            <p>{{ translate('No permission matches your search.') }}</p>
        </div>
    </div>
</div>
