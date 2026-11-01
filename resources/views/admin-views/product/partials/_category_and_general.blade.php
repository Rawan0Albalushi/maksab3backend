<div class="col-lg-12">
    <div class="general_wrapper">
        <div class="outline-wrapper">
            <div class="card shadow--card-2 border-0 bg-animate">
                <div class="card-header border-0 pb-0">
                    <div class="mb-0">
                        <h3 class="text-dark mb-0">
                            {{ translate('General setup') }}
                        </h3>
                        <p class="fs-12 mb-0">
                            {{ translate('Here you can set up the foundational details required for product creation.') }}
                        </p>
                    </div>
                    @if (isset($openai_config) && data_get($openai_config, 'status') == 1)
                        <button type="button"
                            class="btn bg-white text-primary opacity-1 generate_btn_wrapper p-0 mb-2 general_setup_auto_fill"
                            id="general_setup_auto_fill"
                            data-route="{{ route('admin.product.general-setup-auto-fill') }}"
                            data-error="{{ translate('Please provide an item name and description so the AI can generate suitable data.') }}"
                            data-restaurant-id="" data-lang="en">
                            <div class="btn-svg-wrapper">
                                <img width="18" height="18" class=""
                                    src="{{ asset('public/assets/admin/img/svg/blink-right-small.svg') }}"
                                    alt="">
                            </div>
                            <span class="ai-text-animation d-none" role="status">
                                {{ translate('Just a second') }}
                            </span>
                            <span class="btn-text">{{ translate('Generate') }}</span>
                        </button>
                    @endif

                </div>
                <div class="card-body">
                    <div class="__bg-F8F9FC-card p-xxl-20 p-3">
                        <div class="row g-2">
                            @php($column = 4)
                            @if (Auth::guard('admin')->check())
                                <div class="col-sm-6 col-lg-4">
    
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label" for="store_id">{{ translate('messages.Store') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span><span class="input-label-secondary"></span></label>
                                        <select name="store_id" id="store_id"
                                            title="{{ translate('Select store') }}"
                                            {{ isset(request()->product_gellary) == false ? 'required' : '' }}
                                            data-placeholder="{{ translate('Select store') }}"
                                            class="js-data-example-ajax form-control">
                                            @if (isset($product->store) && request()->product_gellary != 1)
                                                <option value="{{ $product->store_id }}" selected="selected">
                                                    {{ $product->store->name }}</option>
                                            @endif
                                        </select>
                                    </div>
    
    
                                </div>
                                @php($column = 4)
                                <div class="col-sm-6 col-lg-{{ $column }}">
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlSelect1">{{ translate('Main category') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span></label>
                                        <select name="category_id" id="category_id"
                                            data-placeholder="{{ translate('Select category') }}"
                                            @if (!Auth::guard('admin')->check()) data-url="{{ url('/') }}/vendor-panel/item/get-categories?parent_id=" data-id="sub-categories" @endif
                                            class="form-control js-data-example-ajax get-request" required>
    
                                            @if (isset($category))
                                                <option selected value="{{ $category['id'] }}">{{ $category['name'] }}
                                                </option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            @else
                                <div class="col-sm-6 col-lg-4">
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlSelect1">{{ translate('Main category') }}<span
                                                class="input-label-secondary">*</span></label>
                                        <select name="category_id" id="category_id"
                                            class="form-control js-select2-custom get-request"
                                            data-url="{{ url('/') }}/vendor-panel/item/get-categories?parent_id="
                                            data-id="sub-categories">
                                            <option value="">{{ translate('Select') }}</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category['id'] }}"
                                                    {{ isset($product) && $category->id == $product_category[0]->id ? 'selected' : '' }}>
                                                    {{ $category['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
    
                            @endif
    
    
    
                            <div class="col-sm-6 col-lg-{{ $column }}">
    
    
                                <div class="form-group mb-0 error-wrapper">
                                    <label class="input-label"
                                        for="exampleFormControlSelect1">{{ translate('Main subcategory') }}<span
                                            class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Make sure you have selected a category first!') }}"><img
                                                src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                alt="{{ translate('messages.Make sure you have selected a category first!') }}"></span></label>
    
    
    
                                    <select name="sub_category_id"
                                        data-placeholder="{{ translate('Select subcategory') }}"
                                        class="js-data-example-ajax form-control" id="sub-categories">
                                        @if (isset($sub_category))
                                            <option value="{{ $sub_category['id'] }}">{{ $sub_category['name'] }}
                                            </option>
                                        @endif
                                    </select>
                                </div>
    
    
                            </div>

                            @if (\App\CentralLogics\Helpers::storeCategoryStatus())
                            @if (Auth::guard('admin')->check() || \App\CentralLogics\Helpers::hasAnyStoreCategory(isset($product) ? $product->store_id : null))
                            <div class="col-sm-6 col-lg-{{ $column }}" id="store_category_col"
                                style="{{ \App\CentralLogics\Helpers::hasAnyStoreCategory(isset($product) ? $product->store_id : null) ? '' : 'display: none;' }}">
                                <div class="form-group mb-0 error-wrapper">
                                    <label class="input-label" for="store_category_id">
                                        {{ translate('Store category') }}
                                        <span class="text-danger store-category-required-mark"
                                            style="{{ \App\CentralLogics\Helpers::hasAnyStoreCategory(isset($product) ? $product->store_id : null) ? '' : 'display: none;' }}">*</span>
                                    </label>
                                    <select name="store_category_id" id="store_category_id"
                                        data-placeholder="{{ translate('Select store category') }}"
                                        @if (Auth::guard('admin')->check())
                                            data-url="{{ route('admin.store-category.by-store') }}"
                                        @endif
                                        class="form-control js-select2-custom"
                                        {{ \App\CentralLogics\Helpers::hasAnyStoreCategory(isset($product) ? $product->store_id : null) ? 'required' : '' }}>
                                        <option value="">{{ translate('Select store category') }}</option>
                                        @foreach ($store_categories ?? [] as $sc)
                                            <option value="{{ $sc->id }}"
                                                {{ (isset($product) && $product->store_category_id == $sc->id) || old('store_category_id') == $sc->id ? 'selected' : '' }}>
                                                {{ $sc->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif
                            @endif


                            @if (Config::get('module.current_module_type') == 'food')
                                <div class="col-sm-6 col-lg-{{ $column }}" id="veg_input">
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.Item type') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span></label>
                                        <select name="veg" id="veg" class="form-control js-select2-custom"
                                            required>
                                            <option {{ isset($product) && $product->veg == 1 ? 'selected' : '' }}
                                                value="1">{{ translate('Veg') }}</option>
                                            <option {{ isset($product) && $product->veg == 0 ? 'selected' : '' }}
                                                value="0">{{ translate('Non veg') }}</option>
                                        </select>
                                    </div>
                                </div>
                            @endif
    
    
    
    
                            @if (Config::get('module.current_module_type') == 'pharmacy')

                                <div class="col-sm-6 col-lg-{{ $column }}" id="condition_input">

                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label"
                                            for="condition_id">{{ translate('Suitable for') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="condition_id" id="condition_id"
                                            data-placeholder="{{ translate('Select condition') }}"
                                            class="js-data-example-ajax form-control">

                                            @if (isset($product?->pharmacy_item_details?->common_condition_id))
                                                <option value="{{ $product->pharmacy_item_details->common_condition_id }}"
                                                    selected="selected">
                                                    {{ $product?->pharmacy_item_details?->common_condition?->name }}</option>
                                            @elseif(isset($temp_product) && $temp_product == 1 && $product->common_condition_id)
                                                <option value="{{ $product->common_condition_id }}" selected="selected">
                                                    {{ $product->common_condition?->name }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-{{ $column }} error-wrapper" id="manufacturer_wrapper">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize" for="manufacturer">
                                            {{ translate('messages.Manufacturer') }}
                                        </label>
                                        <input type="text" name="manufacturer" id="manufacturer" class="form-control"
                                            placeholder="{{ translate('messages.Enter manufacturer name') }}"
                                            value="{{ isset($product) ? ($product->pharmacy_item_details?->manufacturer ?? (isset($temp_product) && $temp_product == 1 ? $product->manufacturer : '')) : '' }}">
                                    </div>
                                </div>
                            @endif
    
                            @if (in_array(Config::get('module.current_module_type'), ['ecommerce', 'grocery']))

                                <div class="col-sm-6 col-lg-{{ $column }}" id="brand_input">
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label" for="brand_id">{{ translate('messages.Brand') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="brand_id" id="brand_id"
                                            data-placeholder="{{ translate('messages.Select brand') }}"
                                            class="js-data-example-ajax form-control">
                                            @if (isset($product?->ecommerce_item_details?->brand_id))
                                                <option value="{{ $product->ecommerce_item_details->brand_id }}"
                                                    selected="selected">
                                                    {{ $product?->ecommerce_item_details?->brand?->name }}</option>
                                            @elseif(isset($temp_product) && $temp_product == 1 && $product?->brand_id)
                                                <option value="{{ $product->brand_id }}" selected="selected">
                                                    {{ $product?->brand?->name }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            @endif
                            @if (Config::get('module.current_module_type') != 'food')
                                @php($is_pharmacy = Config::get('module.current_module_type') == 'pharmacy')

                                <div class="col-sm-6 col-lg-{{ $is_pharmacy ? 2 : $column }}" id="unit_input">
                                    <div class="form-group mb-0 error-wrapper">
                                        <label class="input-label text-capitalize"
                                            for="unit">{{ translate('Unit') }}</label>
                                        <select name="unit" id="unit"
                                            data-placeholder="{{ translate('messages.Select unit') }}"
                                            class="form-control js-select2-custom">
                                            @foreach (\App\CentralLogics\Helpers::cached_list(\App\Models\Unit::class) as $unit)
                                                <option value="{{ $unit->id }}"
                                                    {{ isset($product) && $unit->id == $product->unit_id ? 'selected' : '' }}>
                                                    {{ $unit->unit }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                @if ($is_pharmacy)
                                    <div class="col-sm-6 col-lg-2 error-wrapper" id="unit_value_wrapper">
                                        <div class="form-group mb-0">
                                            <label class="input-label text-capitalize" for="unit_value">
                                                {{ translate('Unit value') }}
                                            </label>
                                            <input type="text" name="unit_value" id="unit_value" class="form-control"
                                                placeholder="{{ translate('messages.Ex') }}: 10 pcs per strip"
                                                value="{{ isset($product) ? ($product->pharmacy_item_details?->unit_value ?? (isset($temp_product) && $temp_product == 1 ? $product->unit_value : '')) : '' }}">
                                        </div>
                                    </div>
                                @endif
                            @endif
    
    
    
    
                            @if (Config::get('module.current_module_type') == 'grocery' || Config::get('module.current_module_type') == 'food')
                                @if (isset($temp_product) && $temp_product == 1)
                                    @php($product_nutritions = \App\CentralLogics\Helpers::cached_list(\App\Models\Nutrition::class)->whereIn('id', json_decode($product?->nutrition_ids))->pluck('id'))
                                    @php($product_allergies = \App\CentralLogics\Helpers::cached_list(\App\Models\Allergy::class)->whereIn('id', json_decode($product?->allergy_ids))->pluck('id'))
                                @else
                                    @php($product_nutritions = isset($product) ? $product->nutritions->pluck('id') : null)
                                    @php($product_allergies = isset($product) ? $product->allergies->pluck('id') : null)
                                @endif
    
                                <div class="col-sm-6 col-lg-6 error-wrapper" id="nutrition">
                                    <label class="input-label" for="">
                                        {{ translate('Nutrition') }}
                                        <span class="input-label-secondary"
                                            title="{{ translate('Specify the necessary keywords relating to energy values for the item and type this content & press enter.') }}"
                                            data-toggle="tooltip">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                    </label>
                                    <select name="nutritions[]" id="nutritions_input"
                                        class="form-control multiple-select2"
                                        data-placeholder="{{ translate('messages.Type your content and press enter') }}"
                                        multiple>
                                        @php($nutritions = \App\CentralLogics\Helpers::cached_list(\App\Models\Nutrition::class) ?? [])
                                        @foreach ($nutritions as $nutrition)
                                            <option
                                                {{ $product_nutritions && $product_nutritions->contains($nutrition->id) ? 'selected' : '' }}
                                                value="{{ $nutrition->nutrition }}">{{ $nutrition->nutrition }}</option>
                                        @endforeach
                                    </select>
                                </div>
    
    
                                <div class="col-sm-6 col-lg-6 error-wrapper" id="allergy">
                                    <label class="input-label" for="">
                                        {{ translate('Allergen ingredients') }}
                                        <span class="input-label-secondary"
                                            title="{{ translate('List the item\'s allergens. Type each one and press enter.') }}"
                                            data-toggle="tooltip">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                    </label>
                                    <select name="allergies[]" class="form-control multiple-select2" id="allergy_input"
                                        data-placeholder="{{ translate('messages.Type your content and press enter') }}"
                                        multiple>
                                        @php($allergies = \App\CentralLogics\Helpers::cached_list(\App\Models\Allergy::class) ?? [])
    
                                        @foreach ($allergies as $allergy)
                                            <option
                                                {{ $product_allergies && $product_allergies->contains($allergy->id) ? 'selected' : '' }}
                                                value="{{ $allergy->allergy }}">{{ $allergy->allergy }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
    
    
    
                            @if (Config::get('module.current_module_type') == 'grocery' || Config::get('module.current_module_type') == 'food')
                                <div class="col-sm-6 col-lg-4 error-wrapper" id="halal">
                                    <div class="form-check mb-sm-2 pb-sm-1">
                                        <input class="form-check-input" name="is_halal" type="checkbox" value="1"
                                            id="is_halal"
                                            {{ isset($product) && $product->is_halal == 1 ? 'checked' : (isset($temp_product) && $temp_product == 1 && $product->is_halal == 1 ? 'checked' : '') }}>
                                        <label class="form-check-label" for="is_halal">
                                            {{ translate('Is it halal?') }}
                                        </label>
                                    </div>
                                </div>
                            @endif
                            @if (Config::get('module.current_module_type') == 'pharmacy')
                                <div class="col-sm-6 col-lg-4 error-wrapper" id="generic_name">
                                    <label class="input-label" for="sub-categories">
                                        {{ translate('Generic name') }}
                                        <span class="input-label-secondary"
                                            title="{{ translate('Specify the medicine\'s active ingredient that makes it work') }}"
                                            data-toggle="tooltip">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                    </label>
                                    <div class="dropdown suggestion_dropdown">
                                        <input type="text" id="generic_name_input"
                                            value="{{ (isset($temp_product) && $temp_product == 1 ? \App\Models\GenericName::where('id', json_decode($product?->generic_ids))->first()?->generic_name : isset($product)) ? $product?->generic->pluck('generic_name')->first() : '' }}"
                                            class="form-control" name="generic_name" autocomplete="off">
                                        @php($generic_names = \App\CentralLogics\Helpers::cached_list(\App\Models\GenericName::class) ?? [])
                                        @if (count($generic_names) > 0)
                                            <div class="dropdown-menu">
                                                @foreach ($generic_names ?? [] as $generic_name)
                                                    <div class="dropdown-item">{{ $generic_name->generic_name }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-12">
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="flex-fill error-wrapper" id="basic">
                                        <div class="form-check mb-sm-2 pb-sm-1">
                                            <input class="form-check-input" name="basic" type="checkbox" value="1"
                                                id="is_basic_medicine"
                                                {{ isset($product) && $product->pharmacy_item_details?->is_basic == 1 ? 'checked' : (isset($temp_product) && $temp_product == 1 && $product->basic == 1 ? 'checked' : '') }}>
                                            <label class="form-check-label" for="is_basic_medicine">
                                                {{ translate('Is basic medicine') }}
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex-fill error-wrapper" id="is_prescription_required">
                                        <div class="form-check mb-sm-2 pb-sm-1">
                                            <input class="form-check-input" name="is_prescription_required" type="checkbox"
                                                value="1" id="prescription_required"
                                                {{ isset($product) && $product->pharmacy_item_details?->is_prescription_required == 1 ? 'checked' : (isset($temp_product) && $temp_product == 1 && $product->is_prescription_required == 1 ? 'checked' : '') }}>
                                            <label class="form-check-label" for="prescription_required">
                                                {{ translate('messages.Is prescription required') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                
                            @endif
                            @if (Config::get('module.current_module_type') == 'grocery')
                                <div class="col-sm-6 col-lg-4 error-wrapper" id="organic">
                                    <div class="form-check mb-sm-2 pb-sm-1">
                                        <input class="form-check-input" name="organic" type="checkbox" value="1"
                                            id="is_organic"
                                            {{ isset($product) && $product->organic == 1 ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_organic">
                                            {{ translate('Is organic') }}
                                        </label>
                                    </div>
                                </div>
                            @endif
    
    
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@if (Config::get('module.current_module_type') == 'food')
    @if (Auth::guard('admin')->check())
        <div class="col-lg-6" id="addon_input">
            <div class="general_wrapper">
                <div class="outline-wrapper">
                    <div class="card shadow--card-2 border-0 bg-animate">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon mr-2">
                                    <i class="tio-dashboard-outlined"></i>
                                </span>
                                <span>{{ translate('Addon') }}</span>
                            </h5>
                        </div>
                        <div class="card-body error-wrapper">
                            <label class="input-label"
                                for="exampleFormControlSelect1">{{ translate('Select add-on') }}<span
                                    class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.The selected addon\'s will be displayed in this food details') }}"><img
                                        src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                        alt="{{ translate('messages.The selected addon\'s will be displayed in this food details') }}"></span></label>
                            <select name="addon_ids[]" class="form-control border js-select2-custom"
                                multiple="multiple" id="add_on">

                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="col-lg-6">
            <div class="general_wrapper">
                <div class="outline-wrapper">
                    <div class="card shadow--card-2 border-0 bg-animate">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon"><i class="tio-puzzle"></i></span>
                                <span>{{ translate('Addon') }}</span>
                            </h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row g-2">
                                <div class="col-12">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlSelect1">{{ translate('Addon') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="addon_ids[]" class="form-control js-select2-custom"
                                            id="add_on" multiple="multiple">
                                            @foreach (\App\CentralLogics\Helpers::store_addons(\App\CentralLogics\Helpers::get_store_id()) as $addon)
                                                <option value="{{ $addon['id'] }}"
                                                    {{ isset($product) && in_array($addon->id, json_decode($product['add_ons'], true)) ? 'selected' : '' }}>
                                                    {{ $addon['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="col-lg-6" id="time_input">
        <div class="general_wrapper">
            <div class="outline-wrapper">
                <div class="card shadow--card-2 border-0 bg-animate">
                    <div class="card-header">
                        <h5 class="card-title">
                            <span class="card-header-icon mr-2"><i class="tio-date-range"></i></span>
                            <span>{{ translate('Time schedule') }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="form-group mb-0 error-wrapper">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('messages.Available time starts') }}<span
                                            class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}">
                                            *
                                        </span></label>
                                    <input type="time" name="available_time_starts"
                                        value="{{ isset($product) ? $product?->available_time_starts : old('available_time_starts') }}"
                                        class="form-control" id="available_time_starts"
                                        placeholder="{{ translate('messages.Ex') }}: 10:30 am " required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group mb-0 error-wrapper">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('messages.Available time ends') }}<span
                                            class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}">
                                            *
                                        </span></label>
                                    <input type="time" name="available_time_ends" class="form-control"
                                        value="{{ isset($product) ? $product?->available_time_ends : old('available_time_ends') }}"
                                        id="available_time_ends" placeholder="5:45 pm" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif


<div class="col-lg-12">
    <div class="general_wrapper">
        <div class="outline-wrapper">
            <div class="card shadow--card-2 border-0 bg-animate">
                <div class="card-header pb-0 border-0">
                    <div class="mb-0">
                        <h3 class="text-dark mb-0">
                            {{ translate('Search tags') }}
                        </h3>
                        <p class="fs-12 mb-0">
                            {{ translate('Words customers may search the item by. Type one and press enter.') }}
                        </p>
                    </div>
                </div>
                <div class="card-body">

                    <div class="__bg-F8F9FC-card p-xxl-20 p-3">
                        @if (isset($temp_product) && $temp_product == 1)
                            <div class="form-group error-wrapper mb-0">
                                @php($tags = \App\CentralLogics\Helpers::tags_by_ids(json_decode($product?->tag_ids)))
                                <input type="text" class="form-control" id="tags" name="tags"
                                    placeholder="{{ translate('Search tags') }}"
                                    value="@foreach ($tags as $c) {{ $c->tag . ',' }} @endforeach"
                                    data-role="tagsinput">
                            </div>
                        @else
                            <div class="form-group error-wrapper mb-0">
                                <input type="text" class="form-control" id="tags" name="tags"
                                    placeholder="{{ translate('Search tags') }}"
                                    @if (isset($product)) value="@foreach ($product->tags as $c) {{ $c->tag . ',' }} @endforeach" @endif
                                    data-role="tagsinput">
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
