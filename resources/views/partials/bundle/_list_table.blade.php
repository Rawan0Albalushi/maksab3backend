@use('App\CentralLogics\Helpers')

<div class="table-responsive datatable-custom">
    <table class="font-size-sm table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
        <thead class="thead-light">
            <tr>
                <th>{{ translate('Bundle name') }}</th>
                @if ($showStoreColumn)
                    <th>{{ $ownerLabel }}</th>
                @endif
                <th>{{ translate('messages.Items') }}</th>
                <th>{{ translate('messages.Duration') }}</th>
                <th>{{ translate('Base price') }}</th>
                <th>{{ translate('messages.Discount') }}</th>
                <th>{{ translate('After discount') }}</th>
                <th>{{ translate('messages.Status') }}</th>
                <th class="text-center">{{ translate('messages.Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bundles as $key => $bundle)
                <tr>
                    <td>
                        <a href="javascript:" class="d-block text-body bundle-detail"
                            data-url="{{ route($routePrefix.'.view', $bundle->id) }}">
                            {{ Str::limit($bundle->name, 25, '...') }}
                        </a>
                        @if ($bundle->has_stale_pricing)
                            {{-- A bundle's line prices are frozen when it is saved, so it keeps
                                 selling at the old total after a member's price changes. Nothing
                                 said so before; re-saving the bundle re-prices it. --}}
                            <span class="badge badge-soft-warning mt-1"
                                title="{{ translate('messages.A product in this bundle has changed price since the bundle was saved. Re-save the bundle to price it again.') }}">
                                {{ translate('messages.Price changed') }}
                            </span>
                        @endif
                    </td>
                    @if ($showStoreColumn)
                        <td>{{ $bundle->store?->name ?? translate('messages.N/A') }}</td>
                    @endif
                    <td>
                        <a href="javascript:" class="text--primary text-underline bundle-items-popover"
                            data-target="#bundle-items-{{ $bundle->id }}">
                            {{ $bundle->items_count }} {{ translate('messages.Items') }}
                        </a>
                        <div class="d-none" id="bundle-items-{{ $bundle->id }}">
                            @include('partials.bundle._item_popover', ['items' => $bundle->items])
                        </div>
                    </td>
                    <td>
                        <span class="d-block">{{ $bundle->start_date ? Helpers::time_date_format($bundle->start_date) : translate('messages.N/A') }}</span>
                        <span class="d-block">{{ $bundle->end_date ? Helpers::time_date_format($bundle->end_date) : translate('messages.N/A') }}</span>
                    </td>
                    <td>{{ Helpers::format_currency($bundle->base_price) }}</td>
                    <td>{{ $bundle->discount_percentage + 0 }}%</td>
                    <td>{{ Helpers::format_currency($bundle->discounted_price) }}</td>
                    <td>
                        @include('partials.bundle._status_toggle', ['bundle' => $bundle, 'idPrefix' => 'bundleStatus'])
                    </td>
                    <td>
                        <div class="btn--container justify-content-center">
                            <a class="btn btn-sm action-btn action-btn--view bundle-detail" href="javascript:"
                                data-url="{{ route($routePrefix.'.view', $bundle->id) }}" title="{{ translate('View') }}">
                                <i class="tio-visible-outlined"></i>
                            </a>
                            <a class="btn btn-sm btn--primary btn-outline-primary action-btn bundle-edit-trigger"
                                href="javascript:" data-url="{{ route($routePrefix.'.edit', $bundle->id) }}"
                                data-title="{{ translate('Do you want to edit the items?') }}"
                                data-message="{{ translate("messages.If you change any items or their combination, customers who already added this bundle to their cart won't be able to place an order with it until they remove it and add it again.") }}"
                                title="{{ translate('Edit') }}">
                                <i class="tio-edit"></i>
                            </a>
                            <a class="btn btn-sm btn--danger btn-outline-danger action-btn bundle-delete-trigger"
                                href="javascript:" data-form="bundle-{{ $bundle->id }}"
                                data-title="{{ translate('Do you want to delete this bundle?') }}"
                                data-message="{{ translate("messages.Customers who already added this bundle to their cart won't be able to place an order with it, and it will no longer be visible to others.") }}"
                                title="{{ translate('Delete') }}">
                                <i class="tio-delete-outlined"></i>
                            </a>
                            <form action="{{ route($routePrefix.'.delete', $bundle->id) }}" method="post" id="bundle-{{ $bundle->id }}">
                                @csrf @method('delete')
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($bundles->total() !== 0)
    <div class="page-area px-4 pb-3">
        <div class="d-flex align-items-center justify-content-end">
            <div>{!! $bundles->links() !!}</div>
        </div>
    </div>
@else
    <div class="empty--data">
        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
        <h5>{{ translate('No data found') }}</h5>
    </div>
@endif
