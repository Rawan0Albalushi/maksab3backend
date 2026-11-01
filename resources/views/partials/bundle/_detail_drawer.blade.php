@use('App\CentralLogics\Helpers')

<div class="d-flex flex-column" style="height:100%">
    <div class="custom-offcanvas-header bundle-detail__head d-flex justify-content-between align-items-center px-3 py-3">
        <h2 class="mb-0 fs-18 text-title font-bold">
            {{ translate('Bundle item') }} #{{ $bundle->id }}
        </h2>
        @isset($closeUrl)
            <a href="{{ $closeUrl }}" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary fz-15px p-0"
                aria-label="{{ translate('Close') }}">&times;</a>
        @else
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="{{ translate('Close') }}">&times;</button>
        @endisset
    </div>

    <div class="custom-offcanvas-body p-20 flex-grow-1">
        <div class="bundle-detail__card">
            <div class="bundle-detail__summary d-flex gap-3">
                <img class="bundle-detail__thumb onerror-image"
                    data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                    src="{{ $bundle->image_full_url }}" alt="{{ $bundle->name }}">

                <div class="flex-grow-1" style="min-width:0">
                    <h4 class="bundle-detail__title">{{ $bundle->name }}</h4>

                    @foreach ($summaryRows as $label => $value)
                        <div class="bundle-detail__row d-flex align-items-start">
                            <span class="bundle-detail__label">{{ $label }}</span>
                            <span class="bundle-detail__colon">:</span>
                            <span class="bundle-detail__value">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bundle-detail__status d-flex align-items-center justify-content-between">
                <strong class="fs-14 text-title">{{ translate('Bundle activity status') }}</strong>
                @include('partials.bundle._status_toggle', ['bundle' => $bundle, 'idPrefix' => 'drawerBundleStatus'])
            </div>
        </div>

        <h5 class="bundle-detail__heading">{{ translate('messages.Items') }}</h5>

        <div class="bundle-detail__items">
            @foreach ($bundle->items as $line)
                <div class="bundle-item-row d-flex align-items-center gap-3">
                    <img class="bundle-item-row__thumb onerror-image"
                        data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                        src="{{ $line->item_image_full_url }}" alt="">

                    <div class="flex-grow-1" style="min-width:0">
                        <strong class="d-block bundle-item-row__name">{{ $line->item_name }}</strong>

                        @if ($lineMeta[$line->id] ?? false)
                            <span class="d-block bundle-item-row__meta">{{ $lineMeta[$line->id] }}</span>
                        @endif

                        @if ($addOnLines[$line->id] ?? false)
                            <span class="d-block bundle-item-row__meta">
                                {{ translate('messages.Add-on') }} : {{ implode(', ', $addOnLines[$line->id]) }}
                            </span>
                        @endif

                        <span class="d-block bundle-item-row__price">{{ Helpers::format_currency($line->unit_price) }}</span>
                    </div>

                    <span class="bundle-item-row__qty">{{ translate('QTY') }} {{ $line->quantity }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="custom-offcanvas-footer border-top p-3 d-flex align-items-center gap-2">
        <a href="javascript:" class="btn btn-soft-danger flex-grow-1 bundle-delete-trigger"
            data-form="bundle-drawer-{{ $bundle->id }}"
            data-title="{{ translate('Do you want to delete this bundle?') }}"
            data-message="{{ translate("messages.Customers who already added this bundle to their cart won't be able to place an order with it, and it will no longer be visible to others.") }}">
            {{ translate('Delete bundle') }}
        </a>
        <form action="{{ route($routePrefix.'.delete', $bundle->id) }}" method="post" id="bundle-drawer-{{ $bundle->id }}">
            @csrf @method('delete')
        </form>
        <a href="javascript:" class="btn btn--primary flex-grow-1 bundle-edit-trigger"
            data-url="{{ route($routePrefix.'.edit', $bundle->id) }}"
            data-title="{{ translate('Do you want to edit the items?') }}"
            data-message="{{ translate("messages.If you change any items or their combination, customers who already added this bundle to their cart won't be able to place an order with it until they remove it and add it again.") }}">
            {{ translate('Edit bundle') }}
        </a>
    </div>
</div>
