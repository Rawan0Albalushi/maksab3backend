@php
    $discount_amount = $item->discount > 0
        ? ($item->discount_type == 'percent' ? ($item->price * $item->discount / 100) : $item->discount)
        : 0;
    $final_price = max($item->price - $discount_amount, 0);
    $category_name = \App\CentralLogics\Helpers::get_category_name($item->category_ids);
    $sub_category_name = \App\CentralLogics\Helpers::get_sub_category_name($item->category_ids);
    $is_update = (bool) $item->is_live_update;
    $waiting_days = $item->updated_at ? $item->updated_at->diffInDays(now()) : 0;
    $view_url = route('admin.item.requested_item_view', ['id' => $item->id]);
@endphp

<tr>
    <td>
        <a class="itm-item" href="{{ $view_url }}" title="{{ $item->name }}">
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

    <td>
        @if($item->store)
            <a class="itm-store" href="{{ route('admin.store.view', [$item->store->id]) }}"
               title="{{ $item->store->name }}">{{ $item->store->name }}</a>
            @if($item->store->zone)
                <span class="itm-meta"><i class="tio-poi-outlined"></i> {{ $item->store->zone->name }}</span>
            @endif
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

    <td>
        @if($is_update)
            <a class="itm-kind itm-kind--update" href="{{ route('admin.item.view', [$item->item_id]) }}"
               title="{{ translate('messages.Edits to an item already in the catalogue. Open the published item.') }}">
                <i class="tio-edit"></i> {{ translate('messages.Update') }}
            </a>
        @else
            <span class="itm-kind itm-kind--new"
                  title="{{ translate('messages.An item that is not in the catalogue yet.') }}">
                <i class="tio-add-circle"></i> {{ translate('messages.New') }}
            </span>
        @endif
    </td>

    <td data-order="{{ $item->updated_at }}">
        <span class="itm-when{{ ! $item->is_rejected && $waiting_days >= 3 ? ' itm-when--stale' : '' }}">
            <span class="itm-when__day">{{ \App\CentralLogics\Helpers::date_format($item->updated_at) }}</span>
            <span class="itm-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($item->updated_at) }}">
                {{ $item->updated_at?->diffForHumans() }}
            </span>
        </span>
    </td>

    <td>
        @if($item->is_rejected)
            <span class="badge badge-soft-danger text-capitalize">{{ translate('messages.rejected') }}</span>
            @if($item->note)
                <span class="itm-note" title="{{ $item->note }}">
                    <i class="tio-comment-text-outlined"></i> {{ Str::limit($item->note, 30) }}
                </span>
            @endif
        @else
            <span class="badge badge-soft-warning text-capitalize">{{ translate('Pending') }}</span>
        @endif
    </td>

    <td>
        <div class="table-actions justify-content-center">
            <a class="btn action-pill action-pill--approve" href="javascript:"
               data-ajax-action="{{ route('admin.item.approved', ['id' => $item->id]) }}"
               data-ajax-method="get"
               data-ajax-confirm="{{ $is_update
                    ? translate('messages.These edits will replace the item that is live now.')
                    : translate('messages.You want to approve this product') }}"
               data-ajax-confirm-yes="{{ translate('Approve') }}"
               data-ajax-remove="closest:tr"
               data-ajax-refresh="[data-ajax-region]">
                <i class="tio-checkmark-circle-outlined"></i>
                <span>{{ translate('Approve') }}</span>
            </a>

            @if($item->is_rejected)
                <span class="action-pill action-pill--done"
                      title="{{ translate('messages.Already denied — approve to publish it anyway') }}">
                    <i class="tio-remove-circle-outlined"></i>
                    <span>{{ translate('Denied') }}</span>
                </span>
            @else
                <a class="btn action-pill action-pill--deny deny-request" href="javascript:"
                   data-url="{{ route('admin.item.deny', ['id' => $item->id]) }}"
                   data-message="{{ translate('messages.You want to deny this product') }}"
                   data-ajax-refresh="[data-ajax-region]">
                    <i class="tio-remove-circle-outlined"></i>
                    <span>{{ translate('Deny') }}</span>
                </a>
            @endif

            <span class="table-actions__sep" aria-hidden="true"></span>

            <a class="btn action-btn action-btn--view" href="{{ $view_url }}"
               title="{{ translate('View details') }}" aria-label="{{ translate('View details') }}">
                <i class="tio-visible"></i>
            </a>
            <a class="btn action-btn action-btn--edit" href="{{ route('admin.item.edit', [$item->id, 'temp_product' => true]) }}"
               title="{{ translate('Edit item') }}" aria-label="{{ translate('Edit item') }}">
                <i class="tio-edit"></i>
            </a>
            <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
               data-id="food-{{ $item->id }}"
               data-message="{{ translate('Want to delete this item?') }}"
               title="{{ translate('messages.Delete item') }}" aria-label="{{ translate('messages.Delete item') }}">
                <i class="tio-delete-outlined"></i>
            </a>
        </div>

        <form action="{{ route('admin.item.delete', [$item->id]) }}" method="post" id="food-{{ $item->id }}"
              data-ajax-form data-ajax-remove="closest:tr" data-ajax-refresh="[data-ajax-region]">
            @csrf @method('delete')
            <input type="hidden" value="1" name="temp_product">
        </form>
    </td>
</tr>
