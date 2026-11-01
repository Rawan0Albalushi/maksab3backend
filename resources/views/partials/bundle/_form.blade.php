<div class="card mb-3">
    <div class="card-body">
        <h4 class="mb-1">{{ translate('Basic setup') }}</h4>
        <p class="mb-3 fs-12">
            {{ translate('messages.Enter the bundle name, upload a thumbnail, and set the display schedule') }}
        </p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="bg-light rounded p-3 mb-3">
                    @if ($language)
                        <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang).'('.strtoupper($lang).')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="{{ $language ? 'lang_form' : '' }}" id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="default_name">
                                {{ translate('Bundle name') }} @if ($language) ({{ translate('Default') }}) @endif
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name[]" id="default_name" class="form-control h--45px" maxlength="80"
                                data-counter="count_name_default" placeholder="{{ translate('messages.Type bundle name') }}"
                                value="{{ $bundle?->getRawOriginal('name') }}">
                            <small class="d-block text-right opacity-75">
                                <span id="count_name_default">{{ strlen($bundle?->getRawOriginal('name') ?? '') }}</span>/80
                            </small>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>

                    @foreach ($language ?? [] as $lang)
                        <div class="d-none lang_form" id="{{ $lang }}-form">
                            <div class="form-group mb-0">
                                <label class="input-label" for="{{ $lang }}_name">
                                    {{ translate('Bundle name') }} ({{ strtoupper($lang) }})
                                </label>
                                <input type="text" name="name[]" id="{{ $lang }}_name" class="form-control h--45px" maxlength="80"
                                    data-counter="count_name_{{ $lang }}" placeholder="{{ translate('messages.Type bundle name') }}"
                                    value="{{ $translations[$lang]['name'] ?? '' }}">
                                <small class="d-block text-right opacity-75">
                                    <span id="count_name_{{ $lang }}">{{ strlen($translations[$lang]['name'] ?? '') }}</span>/80
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                        </div>
                    @endforeach
                </div>

                <div class="bg-light rounded p-3">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group mb-sm-0">
                                <label class="input-label">
                                    {{ translate('Start date & time') }} <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" name="start_date" id="start_date" class="form-control h--45px"
                                    placeholder="{{ translate('messages.Select date') }}" min="{{ $minDateTime }}"
                                    value="{{ $bundle?->start_date?->format('Y-m-d\TH:i') }}" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group mb-0">
                                <label class="input-label">
                                    {{ translate('End date & time') }} <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" name="end_date" id="end_date" class="form-control h--45px"
                                    placeholder="{{ translate('messages.Select date') }}" min="{{ $minDateTime }}"
                                    value="{{ $bundle?->end_date?->format('Y-m-d\TH:i') }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="bg-light rounded p-3 h-100 d-flex flex-column">
                    <h4 class="mb-1">
                        {{ translate('messages.Thumbnail') }}
                        @if (! $bundle?->image)
                            <span class="text-danger">*</span>
                        @endif
                    </h4>
                    <p class="mb-0 fs-12">
                        {{ strtoupper(IMAGE_FORMAT) }} {{ translate('Image dimension') }} :
                        {{ translate('messages.Max') }} {{ MAX_FILE_SIZE }} {{ translate('messages.MB') }} (1:1)
                    </p>

                    <div class="flex-grow-1 d-flex align-items-center justify-content-center py-3">
                        <div style="width:112px">
                            @include('admin-views.partials._image-uploader', [
                                'id' => 'bundle-image-input',
                                'name' => 'image',
                                'ratio' => '1:1',
                                'isRequired' => true,
                                'textPosition' => 'none',
                                'existingImage' => $bundle && $bundle->image ? $bundle->image_full_url : null,
                                'defaultImage' => $bundle && $bundle->image ? $bundle->image_full_url : null,
                                'imageExtension' => IMAGE_EXTENSION,
                                'imageFormat' => IMAGE_FORMAT,
                                'maxSize' => MAX_FILE_SIZE,
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h4 class="mb-1">{{ translate('Bundle items & pricing') }}</h4>
        <p class="mb-3 fs-12">
            @if ($storeLocked)
                {{ translate('messages.Pick products — prices update live, and you can add a discount') }}
            @else
                {{ translate('messages.Pick a store and products — prices update live, and you can add a discount') }}
            @endif
        </p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="bg-light rounded p-3 h-100">
                    <div class="row">
                        {{-- col-12/col-lg-6 rather than col-sm-6: this pair already sits inside the
                             outer col-md-6, so a sm-width split doubles up on that halving and
                             clips the value (confirmed at 768px, a 58px-wide "0.00" in a 47px box)
                             before the outer column has ever gotten close to running out of room. --}}
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="input-label">
                                    {{ translate('Total base price') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                        data-original-title="{{ translate('messages.After select items, here shows the total price of selected items') }}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <input type="text" id="bundle_base_price" class="form-control h--45px"
                                    value="{{ number_format((float) ($bundle?->base_price ?? 0), 2, '.', '') }}" readonly>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <label class="input-label">
                                    {{ translate('messages.Discount') }} (%) <span class="text-danger">*</span>
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                        data-original-title="{{ translate('If give discount percentage on cost, here show the bundle new price') }}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <input type="number" name="discount_percentage" id="bundle_discount" class="form-control h--45px"
                                    min="0" max="{{ $maxDiscount }}" step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                    placeholder="{{ translate('Ex') }}: 5" value="{{ $bundle ? $bundle->discount_percentage + 0 : '' }}" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="input-label">
                                    {{ translate('Bundle price') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                        data-original-title="{{ translate('If give discount percentage or not, here show the bundle total price') }}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <input type="text" id="bundle_final_price" class="form-control h--45px"
                                    value="{{ number_format((float) ($bundle?->discounted_price ?? 0), 2, '.', '') }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="bg-light rounded p-3 h-100">
                    @if ($storeLocked)
                        <input type="hidden" name="store_id" id="bundle_store_id" value="{{ $store?->id }}">
                    @else
                        <div class="form-group">
                            <label class="input-label">
                                {{ $ownerLabel }} <span class="text-danger">*</span>
                                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                    data-original-title="{{ $isServiceModule
                                        ? translate('Pick the provider this bundle belongs to its services are what the search offers')
                                        : translate('Pick the store this bundle belongs to its menu is what the item search offers') }}">
                                    <i class="tio-info text-gray1 fs-16"></i>
                                </span>
                            </label>
                            <select name="store_id" id="bundle_store_id" class="form-control h--45px js-select2-custom" required
                                {{ $bundle ? 'disabled' : '' }}>
                                <option value="">{{ $isServiceModule ? translate('messages.Select provider') : translate('messages.Select store') }}</option>
                                @foreach ($stores as $storeOption)
                                    <option value="{{ $storeOption->id }}" {{ $bundle?->store_id === $storeOption->id ? 'selected' : '' }}>
                                        {{ $storeOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($bundle)
                                <input type="hidden" name="store_id" value="{{ $bundle->store_id }}">
                            @endif
                        </div>
                    @endif

                    <div class="form-group mb-0">
                        <label class="input-label">
                            {{ $isServiceModule ? translate('Select service') : translate('Select bundle item') }}
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                data-original-title="{{ $isServiceModule
                                    ? ($storeLocked ? translate('messages.Search and pick a service you offer') : translate('messages.Search and pick a service from this provider'))
                                    : ($storeLocked ? translate('messages.Search and pick an item from your menu') : translate('messages.Search and pick an item from this store')) }}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <select class="form-control h--45px bundle-item-picker" disabled>
                            <option value="">{{ $isServiceModule ? translate('Select service') : translate('messages.Select Item') }}</option>
                        </select>
                    </div>

                    @include('partials.bundle._item_rows')
                </div>
            </div>
        </div>

        <div class="info-notes-bg px-3 py-2 rounded fz-11 gap-2 align-items-center d-flex mt-3">
            <img src="{{ asset('public/assets/admin/img/info-idea.svg') }}" alt="">
            <span>
                @if ($storeLocked)
                    {{ translate('messages.Choose products to include in your bundle, and the total price updates in real-time. Enter a discount percentage to see the final price') }}
                @else
                    {{ $isServiceModule
                        ? translate('Select a provider to create your bundle. Choose services to include, and the total price updates in real-time. Enter a discount percentage to see the final price.')
                        : translate('messages.Select a store to create your bundle. Choose products to include, and the total price updates in real-time. Enter a discount percentage to see the final price') }}
                @endif
            </span>
        </div>
    </div>
</div>
