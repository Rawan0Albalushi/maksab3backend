@php($offer = $offer ?? null)
@php($quantity_locked = $quantity_locked ?? false)
{{-- getWebConfig rather than a direct BusinessSetting query: settings are cached and
     querying the table directly is banned in new code. Already an array, so no json_decode. --}}
@php($language = $language ?? getWebConfig('language'))
@php($selected_types = $offer?->order_types ?? ['delivery', 'take_away'])
{{-- Nothing before today may be picked, and an offer that already began keeps its own start as
     the floor so a running offer can still be edited. Mirrors the server's earliestAllowedDate(). --}}
@php($min_datetime = $offer?->start_date && $offer->start_date->isPast()
    ? $offer->start_date->format('Y-m-d\TH:i')
    : now()->format('Y-m-d\TH:i'))

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8 mb-3">
                <div class="bg-light rounded p-3 h-100">
                    @if($language)
                        <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{$lang}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang).'('.strtoupper($lang).')'}}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="lang_form" id="default-form">
                            <div class="form-group">
                                <label class="input-label" for="default_title">
                                    {{translate('Title')}} ({{ translate('Default') }}) <span class="text-danger">*</span>
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                          data-original-title="{{translate('messages.The offer name customers will see')}}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <input type="text" name="title[]" id="default_title" class="form-control h--45px" maxlength="100"
                                       data-counter="count_title_default" placeholder="{{ translate('Type title') }}"
                                       value="{{ $offer?->getRawOriginal('title') }}">
                                <small class="d-block text-right opacity-75">
                                    <span id="count_title_default">{{ strlen($offer?->getRawOriginal('title') ?? '') }}</span>/100
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            <div class="form-group mb-0">
                                <label class="input-label">
                                    {{translate('Description')}} ({{ translate('Default') }})
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                          data-original-title="{{translate('messages.A short description shown with the offer')}}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <textarea name="description[]" class="form-control" maxlength="150" rows="2"
                                          data-counter="count_desc_default"
                                          placeholder="{{ translate('messages.Type about the description') }}">{{ $offer?->getRawOriginal('description') }}</textarea>
                                <small class="d-block text-right opacity-75">
                                    <span id="count_desc_default">{{ strlen($offer?->getRawOriginal('description') ?? '') }}</span>/150
                                </small>
                            </div>
                        </div>

                        @foreach($language as $lang)
                            <?php
                                $translate = [];
                                if ($offer && count($offer['translations'])) {
                                    foreach ($offer['translations'] as $t) {
                                        if ($t->locale == $lang && $t->key == 'title') {
                                            $translate[$lang]['title'] = $t->value;
                                        }
                                        if ($t->locale == $lang && $t->key == 'description') {
                                            $translate[$lang]['description'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                            <div class="d-none lang_form" id="{{$lang}}-form">
                                <div class="form-group">
                                    <label class="input-label" for="{{$lang}}_title">
                                        {{translate('Title')}} ({{strtoupper($lang)}})
                                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                              data-original-title="{{translate('messages.The offer name customers will see')}}">
                                            <i class="tio-info text-gray1 fs-16"></i>
                                        </span>
                                    </label>
                                    <input type="text" name="title[]" id="{{$lang}}_title" class="form-control h--45px" maxlength="100"
                                           data-counter="count_title_{{$lang}}" placeholder="{{ translate('Type title') }}"
                                           value="{{ $translate[$lang]['title'] ?? '' }}">
                                    <small class="d-block text-right opacity-75">
                                        <span id="count_title_{{$lang}}">{{ strlen($translate[$lang]['title'] ?? '') }}</span>/100
                                    </small>
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{translate('Description')}} ({{strtoupper($lang)}})
                                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                              data-original-title="{{translate('messages.A short description shown with the offer')}}">
                                            <i class="tio-info text-gray1 fs-16"></i>
                                        </span>
                                    </label>
                                    <textarea name="description[]" class="form-control" maxlength="150" rows="2"
                                              data-counter="count_desc_{{$lang}}"
                                              placeholder="{{ translate('messages.Type about the description') }}">{{ $translate[$lang]['description'] ?? '' }}</textarea>
                                    <small class="d-block text-right opacity-75">
                                        <span id="count_desc_{{$lang}}">{{ strlen($translate[$lang]['description'] ?? '') }}</span>/150
                                    </small>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div id="default-form">
                            <div class="form-group">
                                <label class="input-label">{{translate('Title')}} <span class="text-danger">*</span></label>
                                <input type="text" name="title[]" class="form-control h--45px" maxlength="100"
                                       data-counter="count_title_default" placeholder="{{ translate('Type title') }}"
                                       value="{{ $offer?->getRawOriginal('title') }}">
                                <small class="d-block text-right opacity-75">
                                    <span id="count_title_default">{{ strlen($offer?->getRawOriginal('title') ?? '') }}</span>/100
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            <div class="form-group mb-0">
                                <label class="input-label">{{translate('Description')}}</label>
                                <textarea name="description[]" class="form-control" maxlength="150" rows="2"
                                          data-counter="count_desc_default"
                                          placeholder="{{ translate('messages.Type about the description') }}">{{ $offer?->getRawOriginal('description') }}</textarea>
                                <small class="d-block text-right opacity-75">
                                    <span id="count_desc_default">{{ strlen($offer?->getRawOriginal('description') ?? '') }}</span>/150
                                </small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="bg-light rounded p-3 h-100 text-center d-flex flex-column">
                    <h6 class="mb-1">{{translate('BOGO offer image')}}
                        <span class="text-danger">*</span>
                    </h6>
                    <p class="opacity-75 font-size-sm mb-3">{{translate('Upload your BOGO offer image')}}</p>

                    {{-- The same uploader the food form uses, so a picked file, its preview and
                         the size and format line all behave identically across the panel. Kept
                         required: an offer is created with an image and there is no route that
                         clears one, so the partial's remove button would promise a delete the
                         controller does not do. An existing image drops the required attribute,
                         which is what makes the image optional on update. --}}
                    @include('admin-views.partials._image-uploader', [
                        'id' => 'image-input',
                        'name' => 'image',
                        'ratio' => '3:1',
                        'isRequired' => true,
                        'existingImage' => $offer && $offer->image ? $offer->image_full_url : null,
                        // What Reset puts back: the image the offer is saved with.
                        'defaultImage' => $offer && $offer->image ? $offer->image_full_url : null,
                        'imageExtension' => IMAGE_EXTENSION,
                        'imageFormat' => IMAGE_FORMAT,
                        'maxSize' => MAX_FILE_SIZE,
                    ])
                </div>
            </div>
        </div>

        @if($quantity_locked)
            <div class="alert alert-soft-warning alert--note d-flex gap-2">
                <i class="tio-error"></i>
                <span>{{translate('Buy and get quantity cannot be changed once a store has joined this offer')}}</span>
            </div>
        @endif

        <div class="bg-light rounded p-3">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">
                            {{translate('Buy item quantity')}} <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.How many items the customer must buy to qualify')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <input type="number" min="1" name="buy_qty" class="form-control h--45px" placeholder="{{ translate('Ex') }}: 2"
                               value="{{ $offer?->buy_qty }}" required {{$quantity_locked ? 'readonly' : ''}}>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">
                            {{translate('Get item quantity')}} <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.How many items the customer receives free')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <input type="number" min="1" name="get_qty" class="form-control h--45px" placeholder="{{ translate('Ex') }}: 1"
                               value="{{ $offer?->get_qty }}" required {{$quantity_locked ? 'readonly' : ''}}>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">{{translate('Start date & time')}} <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="start_date" id="start_date" class="form-control h--45px"
                               placeholder="{{ translate('Select date & time') }}" min="{{ $min_datetime }}"
                               value="{{ $offer?->start_date?->format('Y-m-d\TH:i') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">{{translate('End date & time')}} <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="end_date" id="end_date" class="form-control h--45px"
                               placeholder="{{ translate('Select date & time') }}" min="{{ $min_datetime }}"
                               value="{{ $offer?->end_date?->format('Y-m-d\TH:i') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">
                            {{translate('Usage limit total')}}
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.Total times this offer can be used across all customers')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <input type="number" min="1" name="usage_limit_total" class="form-control h--45px"
                               placeholder="{{ translate('Ex') }}: 100" value="{{ $offer?->usage_limit_total }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="input-label">
                            {{translate('Usage limit per customer')}}
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.How many times one customer can use this offer')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <input type="number" min="1" name="usage_limit_per_customer" class="form-control h--45px"
                               placeholder="{{ translate('Ex') }}: 20" value="{{ $offer?->usage_limit_per_customer }}">
                    </div>
                </div>
            </div>

            {{-- Hidden while order types are switched off - see BogoOffer::ORDER_TYPES_ENABLED.
                 The field is left whole, not deleted: turning the constant back on brings the
                 picker back with whatever each offer already had ticked. --}}
            @if(\App\Models\BogoOffer::orderTypesEnabled())
                <div class="form-group mb-0">
                    <label class="input-label">{{translate('messages.Select Order Type')}}</label>
                    <div class="row border rounded mx-0 py-3">
                        {{-- dine_in is rendered but disabled: 6amMart has no dine-in concept --
                             PlaceOrderRequest validates in:take_away,delivery,parcel -- so it cannot be
                             honoured, while removing it entirely would make the screen unlike the design. --}}
                        @foreach(['delivery' => 'Home_Delivery', 'take_away' => 'Take_Away', 'dine_in' => 'Dine_In'] as $value => $label)
                            <div class="col-md-4">
                                <div class="form-check" @if($value === 'dine_in') data-toggle="tooltip"
                                     title="{{ translate('messages.Dine in is not supported on this platform') }}" @endif>
                                    <input class="form-check-input" type="checkbox"
                                           @if($value !== 'dine_in') name="order_types[]" @endif value="{{$value}}"
                                           id="order_type_{{$value}}" {{ $value === 'dine_in' ? 'disabled' : '' }}
                                           {{ in_array($value, $selected_types) ? 'checked' : '' }}>
                                    <label class="form-check-label {{ $value === 'dine_in' ? 'text-muted' : '' }}"
                                           for="order_type_{{$value}}">{{translate('messages.'.$label)}}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
