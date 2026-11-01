@php
    $locales = $translated_locales[$category->id] ?? [];
    $items_count = (int) ($category->items_count ?? 0);
    $store_label = \App\CentralLogics\Helpers::moduleStoreLabel();
@endphp

<tr>
    <td>
        <div class="stc-cat">
            <img class="stc-cat__thumb onerror-image" src="{{ $category['image_full_url'] }}"
                 data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.jpg') }}"
                 alt="{{ $category['name'] }}">
            <div class="stc-cat__body">
                <span class="stc-cat__name" title="{{ $category['name'] }}">{{ $category['name'] }}</span>
                <span class="stc-meta"><span class="stc-id">#{{ $category->id }}</span></span>
            </div>
        </div>
    </td>

    <td>
        @if($category->store)
            <a class="stc-store" href="{{ route('admin.store.view', [$category->store->id]) }}"
               title="{{ $category->store->name }}">{{ $category->store->name }}</a>
            @if($category->store->zone)
                <span class="stc-zone"><i class="tio-poi-outlined"></i> {{ $category->store->zone->name }}</span>
            @endif
        @else
            <span class="stc-blank">{{ translate('messages.Store deleted') }}</span>
        @endif
    </td>

    <td class="col--numeric" data-order="{{ $items_count }}">
        @if($items_count && $items_linkable && $category->store_id)
            <a class="stc-count"
               href="{{ route('admin.item.list', array_filter([
                    'module_id' => request('module_id'),
                    'store_id' => $category->store_id,
                    'store_category_id' => $category->id,
               ])) }}"
               title="{{ translate('messages.See the items filed under this category') }}">{{ $items_count }}</a>
        @else
            <span class="stc-count{{ $items_count ? '' : ' stc-count--empty' }}"
                  title="{{ $items_count
                        ? translate('messages.Items') . ': ' . $items_count
                        : translate('messages.No item filed under this category yet') }}">{{ $items_count }}</span>
        @endif
    </td>

    <td>
        @if(count($locales))
            <span class="cell-chips">
                @foreach($locales as $locale)
                    <span class="cell-chip" title="{{ \App\CentralLogics\Helpers::get_language_name($locale) }}">{{ strtoupper($locale) }}</span>
                @endforeach
            </span>
        @else
            <span class="stc-blank">{{ translate('messages.Default only') }}</span>
        @endif
    </td>

    <td>
        <form action="{{ route('admin.store-category.priority', $category->id) }}" class="priority-form">
            <select name="priority" aria-label="{{ translate('messages.Priority') }}"
                    @if($filters['priority'] !== null) data-ajax-refresh="[data-ajax-region]" @endif
                    class="form-control form--control-select priority-select mx-auto {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                <option value="0" {{ $category->priority == 0 ? 'selected' : '' }}>{{ translate('messages.Normal') }}</option>
                <option value="1" {{ $category->priority == 1 ? 'selected' : '' }}>{{ translate('messages.medium') }}</option>
                <option value="2" {{ $category->priority == 2 ? 'selected' : '' }}>{{ translate('messages.High') }}</option>
            </select>
        </form>
    </td>

    <td>
        <div class="status-toggle" data-status="{{ $category->status ? 1 : 0 }}">
            <label class="toggle-switch toggle-switch-sm" for="storeCategoryStatus{{ $category->id }}">
                <input type="checkbox" id="storeCategoryStatus{{ $category->id }}"
                       class="toggle-switch-input redirect-url"
                       data-url="{{ route('admin.store-category.status', ['id' => $category->id, 'status' => $category->status ? 0 : 1]) }}"
                       aria-label="{{ $store_label . ' ' . translate('messages.Category') . ' ' . translate('messages.Status') }}"
                       @if($filters['status'] !== null) data-ajax-refresh="[data-ajax-region]" @endif
                       {{ $category->status ? 'checked' : '' }}>
                <span class="toggle-switch-label">
                    <span class="toggle-switch-indicator"></span>
                </span>
            </label>
            <span class="status-toggle__text" aria-live="polite">
                {{ $category->status ? translate('messages.Active') : translate('messages.Inactive') }}
            </span>
        </div>
    </td>

    <td data-order="{{ $category->created_at }}">
        <span class="stc-date">
            <span class="stc-date__day">{{ \App\CentralLogics\Helpers::date_format($category->created_at) }}</span>
            <span class="stc-date__time" title="{{ \App\CentralLogics\Helpers::time_date_format($category->created_at) }}">
                {{ \App\CentralLogics\Helpers::time_format($category->created_at) }}
            </span>
        </span>
    </td>

    <td>
        <div class="btn--container justify-content-center">
            <a class="btn action-btn action-btn--edit offcanvas-trigger data-info-show" href="javascript:void(0)"
               data-id="{{ $category['id'] }}"
               data-url="{{ route('admin.store-category.edit', [$category['id']]) }}"
               data-target="#offcanvas__storeCategoryBtn"
               title="{{ translate('Edit') }}">
                <i class="tio-edit"></i>
            </a>
            <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
               data-id="store-category-{{ $category['id'] }}"
               data-message="{{ $items_count
                    ? translate('messages.This category holds items. Deleting it leaves them without a category. Continue?') . ' ' . translate('messages.Items') . ': ' . $items_count
                    : translate('messages.Want to delete this?') . ' ' . $store_label . ' ' . translate('messages.Category') }}"
               title="{{ translate('messages.Delete') }}">
                <i class="tio-delete-outlined"></i>
            </a>
            <form action="{{ route('admin.store-category.delete') }}" method="post" id="store-category-{{ $category['id'] }}"
                  data-ajax-form data-ajax-remove="closest:tr"
                  data-ajax-refresh="[data-ajax-region]">
                @csrf @method('delete')
                <input type="hidden" name="id" value="{{ $category['id'] }}">
            </form>
        </div>
    </td>
</tr>
