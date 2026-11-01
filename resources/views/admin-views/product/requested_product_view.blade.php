@extends('layouts.admin.app')

@section('title', translate('Item request'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-detail.css') }}">
@endpush

@section('content')
    @php
        $module_type = $product->module?->module_type;
        $has_stock = (bool) config('module.'.$module_type.'.stock');
        $has_veg = (bool) config('module.'.$module_type.'.veg_non_veg');
        $has_addon = (bool) config('module.'.$module_type.'.add_on');
        $has_time = (bool) config('module.'.$module_type.'.item_available_time');
        $has_organic = (bool) config('module.'.$module_type.'.organic');
        $has_nutrition = (bool) config('module.'.$module_type.'.nutrition');
        $has_allergy = (bool) config('module.'.$module_type.'.allergy');
        $has_generic = (bool) config('module.'.$module_type.'.generic_name');
        $languages = \App\CentralLogics\Helpers::decodeJsonToArray(
            \App\CentralLogics\Helpers::get_business_settings('language', false)
        );
        $discount_amount = \App\CentralLogics\Helpers::discount_calculate($product, $product->price);
        $final_price = max($product->price - $discount_amount, 0);
        $category_name = $product->category?->parent?->name ?? $product->category?->name;
        $sub_category_name = $product->category?->parent ? $product->category?->name : null;
    @endphp

    <div class="content container-fluid idt">
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-md-8 col-12">
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/package.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{ $product->getRawOriginal('name') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('What this store has asked to add or change, next to what is live now.') }}</p>
                    <div class="idt-pills">
                        @if($product->is_rejected)
                            <span class="idt-pill idt-pill--danger">
                                <i class="tio-remove-circle-outlined"></i> {{ translate('messages.rejected') }}
                            </span>
                        @else
                            <span class="idt-pill idt-pill--wait">
                                <i class="tio-hourglass-outlined"></i> {{ translate('messages.Awaiting decision') }}
                            </span>
                        @endif

                        @if($is_update)
                            @if($live_item)
                                <a class="idt-pill idt-pill--ok" href="{{ route('admin.item.view', [$live_item->id]) }}">
                                    <i class="tio-edit"></i> {{ translate('messages.Update to a live item') }}
                                </a>
                            @else
                                <span class="idt-pill idt-pill--ok">
                                    <i class="tio-edit"></i> {{ translate('messages.Update to a live item') }}
                                </span>
                            @endif
                        @else
                            <span class="idt-pill">
                                <i class="tio-add-circle"></i> {{ translate('messages.New item') }}
                            </span>
                        @endif

                        <span class="idt-pill idt-pill--muted">#{{ $product->id }}</span>
                        <span class="idt-pill idt-pill--muted">
                            <i class="tio-time"></i>
                            {{ translate('messages.Submitted') }} {{ $product->updated_at?->diffForHumans() }}
                        </span>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="idt-actions">
                        <a class="btn btn--reset" href="{{ route('admin.item.approval_list', ['module_id' => request('module_id')]) }}">
                            <i class="tio-arrow-backward"></i> {{ translate('messages.Back to requests') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-lg-8 col-12">
                <div class="card mb-3">
                    <div class="idt-hero">
                        <div>
                            @include('partials._product-media-slider', ['product' => $product])
                        </div>

                        <div class="idt-hero__body">
                            @if(count($languages))
                                <ul class="nav nav-tabs mb-3">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                    </li>
                                    @foreach($languages as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">
                                                {{ \App\CentralLogics\Helpers::get_language_name($lang).' ('.strtoupper($lang).')' }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="lang_form" id="default-form">
                                <h2 class="idt-hero__title">{{ $product->getRawOriginal('name') }}</h2>
                                <span class="idt-hero__label">{{ translate('messages.Description') }}</span>
                                <p class="idt-hero__desc">{{ strip_tags($product->getRawOriginal('description')) }}</p>
                            </div>

                            @foreach($languages as $lang)
                                @php
                                    $translated = collect($product->translations)->where('locale', $lang);
                                    $translated_name = $translated->firstWhere('key', 'name')?->value;
                                    $translated_description = $translated->firstWhere('key', 'description')?->value;
                                @endphp
                                <div class="d-none lang_form" id="{{ $lang }}-form">
                                    <h2 class="idt-hero__title">{{ $translated_name }}</h2>
                                    <span class="idt-hero__label">{{ translate('messages.Description') }}</span>
                                    <p class="idt-hero__desc">{{ strip_tags($translated_description) }}</p>
                                </div>
                            @endforeach

                            <div class="idt-price">
                                <span class="idt-price__now">{{ \App\CentralLogics\Helpers::format_currency($final_price) }}</span>
                                @if($discount_amount > 0)
                                    <span class="idt-price__was">
                                        <del>{{ \App\CentralLogics\Helpers::format_currency($product->price) }}</del>
                                    </span>
                                    <span class="idt-price__off">
                                        -{{ $product->discount_type == 'percent'
                                            ? rtrim(rtrim(number_format($product->discount, 2, '.', ''), '0'), '.').'%'
                                            : \App\CentralLogics\Helpers::format_currency($discount_amount) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if($is_update && $live_item)
                    @include('admin-views.product.partials._request-changes', ['changes' => $changes])
                @endif

                <div class="card mb-3">
                    <div class="idt-card__head">
                        <h2 class="idt-card__title">{{ translate('Item details') }}</h2>
                    </div>

                    <div class="idt-specs">
                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('messages.Category') }}</span>
                            <span class="idt-spec__value">{{ $category_name ?? translate('messages.uncategorize') }}</span>
                        </div>

                        @if($sub_category_name)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('Subcategory') }}</span>
                                <span class="idt-spec__value">{{ $sub_category_name }}</span>
                            </div>
                        @endif

                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('Unit price') }}</span>
                            <span class="idt-spec__value">{{ \App\CentralLogics\Helpers::format_currency($product->price) }}</span>
                        </div>

                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('Discount') }}</span>
                            <span class="idt-spec__value">
                                {{ $product->discount > 0
                                    ? ($product->discount_type == 'percent'
                                        ? rtrim(rtrim(number_format($product->discount, 2, '.', ''), '0'), '.').'%'
                                        : \App\CentralLogics\Helpers::format_currency($product->discount))
                                    : translate('messages.No discount') }}
                            </span>
                        </div>

                        @if($has_stock)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Total stock') }}</span>
                                <span class="idt-spec__value">{{ max((int) $product->stock, 0) }}</span>
                            </div>
                            @if($product->unit)
                                <div class="idt-spec">
                                    <span class="idt-spec__label">{{ translate('Unit') }}</span>
                                    <span class="idt-spec__value">{{ $product->unit->unit }}</span>
                                </div>
                            @endif
                        @endif

                        @if($has_veg)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Item type') }}</span>
                                <span class="idt-spec__value">{{ $product->veg ? translate('Veg') : translate('Non veg') }}</span>
                            </div>
                        @endif

                        @if($has_organic)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('Is organic') }}</span>
                                <span class="idt-spec__value">{{ $product->organic ? translate('messages.Yes') : translate('messages.No') }}</span>
                            </div>
                        @endif

                        @if($product->maximum_cart_quantity)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Maximum cart quantity') }}</span>
                                <span class="idt-spec__value">{{ $product->maximum_cart_quantity }}</span>
                            </div>
                        @endif

                        @if($has_time && $product->available_time_starts && $product->available_time_ends)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Available time') }}</span>
                                <span class="idt-spec__value">
                                    {{ date(config('timeformat'), strtotime($product->available_time_starts)) }}
                                    - {{ date(config('timeformat'), strtotime($product->available_time_ends)) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                @include('admin-views.product.partials._request-options', [
                    'product' => $product,
                    'module_type' => $module_type,
                    'has_addon' => $has_addon,
                    'has_nutrition' => $has_nutrition,
                    'has_allergy' => $has_allergy,
                    'has_generic' => $has_generic,
                ])
            </div>

            <div class="col-lg-4 col-12">
                <div class="idt-side">
                    <div class="card mb-3 idt-decide">
                        <div class="card-body">
                            <h2 class="idt-card__title">{{ translate('messages.Your decision') }}</h2>
                            <p class="idt-decide__hint">
                                {{ $is_update
                                    ? translate('messages.Approving replaces the published item with what this store submitted.')
                                    : translate('messages.Approving publishes this item to the storefront.') }}
                            </p>

                            @if($product->is_rejected && $product->note)
                                <div class="idt-note">
                                    <span class="idt-note__label">{{ translate('messages.Reason given') }}</span>
                                    <p>{{ $product->note }}</p>
                                </div>
                            @endif

                            <div class="idt-decide__actions">
                                <a class="btn btn--primary request_alert" href="javascript:"
                                   data-url="{{ route('admin.item.approved', ['id' => $product->id]) }}"
                                   data-message="{{ $is_update
                                        ? translate('messages.These edits will replace the item that is live now.')
                                        : translate('messages.You want to approve this product') }}">
                                    <i class="tio-checkmark-circle-outlined"></i> {{ translate('Approve') }}
                                </a>

                                @if($product->is_rejected == 0)
                                    <a class="btn btn--danger canceled-status" href="javascript:"
                                       data-url="{{ route('admin.item.deny', ['id' => $product->id]) }}"
                                       data-message="{{ translate('messages.You want to deny this product') }}">
                                        <i class="tio-clear-circle-outlined"></i> {{ translate('messages.Reject') }}
                                    </a>
                                @endif

                                <a class="btn btn-outline-primary" href="{{ route('admin.item.edit', [$product->id, 'temp_product' => true]) }}">
                                    <i class="tio-edit"></i> {{ translate('Edit & approve') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="idt-card__head">
                            <h2 class="idt-card__title">{{ translate('messages.Submission') }}</h2>
                        </div>
                        <div class="card-body">
                            @if($product->store)
                                <a class="idt-store" href="{{ route('admin.store.view', [$product->store->id]) }}">
                                    <img class="idt-store__logo onerror-image"
                                         src="{{ $product->store->logo_full_url }}"
                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                         alt="{{ $product->store->name }}">
                                    <span class="idt-store__body">
                                        <span class="idt-store__name">{{ $product->store->name }}</span>
                                        @if($product->store->zone)
                                            <span class="idt-store__meta">
                                                <i class="tio-poi-outlined"></i> {{ $product->store->zone->name }}
                                            </span>
                                        @endif
                                    </span>
                                </a>
                            @else
                                <p class="idt-blank">{{ translate('messages.Store deleted') }}</p>
                            @endif

                            <div class="idt-facts">
                                <div class="idt-fact">
                                    <span class="idt-fact__label">{{ translate('messages.Request') }}</span>
                                    <span class="idt-fact__value">
                                        {{ $is_update ? translate('messages.Update to a live item') : translate('messages.New item') }}
                                    </span>
                                </div>
                                <div class="idt-fact">
                                    <span class="idt-fact__label">{{ translate('messages.Submitted') }}</span>
                                    <span class="idt-fact__value" title="{{ \App\CentralLogics\Helpers::time_date_format($product->updated_at) }}">
                                        {{ \App\CentralLogics\Helpers::date_format($product->updated_at) }}
                                    </span>
                                </div>
                                <div class="idt-fact">
                                    <span class="idt-fact__label">{{ translate('messages.Module') }}</span>
                                    <span class="idt-fact__value">{{ $product->module?->module_name }}</span>
                                </div>
                                @if($live_item)
                                    <div class="idt-fact">
                                        <span class="idt-fact__label">{{ translate('messages.Published item') }}</span>
                                        <a class="idt-fact__value" href="{{ route('admin.item.view', [$live_item->id]) }}">
                                            #{{ $live_item->id }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
