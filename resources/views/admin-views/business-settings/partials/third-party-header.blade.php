{{--
    Shared header for every admin/business-settings/third-party/* screen.

    @param string      $icon    tio icon class for the header badge
    @param string      $title   page title
    @param string|null $summary one line explaining what the page configures
    @param string|null $helpTarget  modal selector, renders the "How it Works" trigger
    @param string|null $helpLabel   overrides the trigger label
--}}
<div class="tps-head">
    <div class="tps-head__title">
        <span class="tps-head__icon">
            <i class="{{ $icon ?? 'tio-settings' }}"></i>
        </span>
        <span class="tps-head__text">
            <h1>{{ $title }}</h1>
            @if (! empty($summary))
                <p>{{ $summary }}</p>
            @endif
        </span>
    </div>

    @if (! empty($helpTarget))
        <button type="button" class="tps-help" data-toggle="modal" data-target="{{ $helpTarget }}">
            <i class="tio-help-outlined"></i>
            <span>{{ $helpLabel ?? translate('How it works') }}</span>
        </button>
    @endif
</div>

@include('admin-views.business-settings.partials.third-party-links')
