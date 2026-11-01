@php
    $discount_amount = $item->discount > 0
        ? ($item->discount_type == 'percent' ? ($item->price * $item->discount / 100) : $item->discount)
        : 0;
    $final_price = max($item->price - $discount_amount, 0);
    $stock = max((int) $item->stock, 0);
    $category_name = \App\CentralLogics\Helpers::get_category_name($item->category_ids);
    $sub_category_name = \App\CentralLogics\Helpers::get_sub_category_name($item->category_ids);
@endphp

<tr>
    <td>
        <a class="itm-item" href="{{ route('admin.item.view', [$item->id]) }}" title="{{ $item->name }}">
            <img class="itm-item__thumb onerror-image"
                 src="{{ $item->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                 alt="{{ $item->name }}">
            <span class="itm-item__body">
                <span class="itm-item__name">{{ $item->name }}</span>
                <span class="itm-meta">
                    @if($has_veg)
                        <i class="itm-veg itm-veg--{{ $item->veg ? 'yes' : 'no' }}"
                           title="{{ $item->veg ? translate('Veg') : translate('Non veg') }}"></i>
                    @endif
                    <span class="itm-id">#{{ $item->id }}</span>
                </span>
            </span>
        </a>
    </td>

    <td>
        <span class="itm-tag" title="{{ $category_name }}">{{ $category_name }}</span>
        @if($sub_category_name && $sub_category_name !== 'NA')
            <span class="itm-meta">{{ $sub_category_name }}</span>
        @endif
    </td>

    @if($has_stock)
        <td>
            <span class="itm-stock {{ $stock === 0 ? 'itm-stock--out' : ($stock <= 10 ? 'itm-stock--low' : '') }}">
                <span class="itm-stock__value">{{ $stock }}</span>
                <span class="itm-stock__edit tio-add-circle update-quantity" role="button" tabindex="0"
                      data-toggle="modal" data-target="#update-quantity" data-id="{{ $item->id }}"
                      title="{{ translate('Update stock') }}"></span>
            </span>
        </td>
    @endif

    <td>
        @if($item->store)
            <a class="itm-store" href="{{ route('admin.store.view', $item->store->id) }}"
               title="{{ $item->store->name }}">{{ $item->store->name }}</a>
        @else
            <span class="itm-blank">{{ translate('messages.Store deleted') }}</span>
        @endif
    </td>

    <td class="col--numeric" data-order="{{ $final_price }}">
        <span class="itm-price">
            <span class="itm-price__now">{{ \App\CentralLogics\Helpers::format_currency($final_price) }}</span>
            @if($discount_amount > 0)
                <span class="itm-price__was">
                    <del>{{ \App\CentralLogics\Helpers::format_currency($item->price) }}</del>
                    <span class="itm-price__off">
                        -{{ $item->discount_type == 'percent'
                            ? rtrim(rtrim(number_format($item->discount, 2, '.', ''), '0'), '.').'%'
                            : \App\CentralLogics\Helpers::format_currency($discount_amount) }}
                    </span>
                </span>
            @endif
        </span>
    </td>

    @if($productWiseTax)
        <td>
            @forelse($item?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $rate => $tax)
                <span class="itm-tax">{{ $tax }} <b>({{ $rate }}%)</b></span>
            @empty
                <span class="itm-blank">{{ translate('messages.N/A') }}</span>
            @endforelse
        </td>
    @endif

    <td data-order="{{ $item->rating_count ? $item->avg_rating : -1 }}">
        @if($item->rating_count)
            <span class="itm-rating"
                  title="{{ translate('messages.Ratings') . ': ' . $item->rating_count }}">
                <i class="tio-star"></i>
                {{ number_format($item->avg_rating, 1) }}
                <span class="itm-rating__count">({{ $item->rating_count }})</span>
            </span>
        @else
            <span class="itm-blank">{{ translate('messages.Not rated yet') }}</span>
        @endif
    </td>

    <td class="col--numeric" data-order="{{ $item->order_count }}">
        <span class="{{ $item->order_count ? 'itm-count' : 'itm-blank' }}"
              title="{{ $item->order_count
                    ? translate('messages.Total orders') . ': ' . $item->order_count
                    : translate('messages.Never ordered yet') }}">
            {{ $item->order_count }}
        </span>
    </td>

    <td>
        <div class="status-toggle" data-status="{{ $item->status ? 1 : 0 }}">
            <label class="toggle-switch toggle-switch-sm" for="itemStatus{{ $item->id }}">
                <input type="checkbox" id="itemStatus{{ $item->id }}"
                       class="toggle-switch-input redirect-url"
                       data-url="{{ route('admin.item.status', [$item->id, $item->status ? 0 : 1]) }}"
                       aria-label="{{ translate('messages.Item status') }}"
                       @if($filters['status']) data-ajax-refresh="[data-ajax-region]" @endif
                       {{ $item->status ? 'checked' : '' }}>
                <span class="toggle-switch-label">
                    <span class="toggle-switch-indicator"></span>
                </span>
            </label>
            <span class="status-toggle__text" aria-live="polite">
                {{ $item->status ? translate('messages.Active') : translate('messages.Inactive') }}
            </span>
        </div>
    </td>

    <td>
        <div class="btn--container justify-content-center">
            <a class="btn action-btn action-btn--view" href="{{ route('admin.item.view', [$item->id]) }}"
               title="{{ translate('messages.View item') }}"><i class="tio-visible-outlined"></i></a>
            <a class="btn action-btn action-btn--edit" href="{{ route('admin.item.edit', [$item->id]) }}"
               title="{{ translate('Edit item') }}"><i class="tio-edit"></i></a>
            <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
               data-id="food-{{ $item->id }}"
               data-message="{{ $item->order_count
                    ? translate('messages.This item has been ordered before. Deleting it removes it from the catalogue. Continue?') . ' ' . translate('messages.Total orders') . ': ' . $item->order_count
                    : translate('Want to delete this item?') }}"
               title="{{ translate('messages.Delete item') }}"><i class="tio-delete-outlined"></i></a>
            <form action="{{ route('admin.item.delete', [$item->id]) }}" method="post" id="food-{{ $item->id }}"
                  data-ajax-form data-ajax-remove="closest:tr" data-ajax-refresh="[data-ajax-region]">
                @csrf @method('delete')
            </form>
        </div>
    </td>
</tr>
