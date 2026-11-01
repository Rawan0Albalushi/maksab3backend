@use('App\Support\Settings\BusinessRules')
<div class="modal-header">
    <h4 class="modal-title text-break">{{ $product->name }}</h4>
    <button class="close call-when-done" type="button" data-dismiss="modal"
            aria-label="{{ translate('messages.Close') }}">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body">
    <div class="pos-pv-head">
        <div class="pos-pv-media">
            <img class="onerror-image"
                 src="{{ $product['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                 width="96" height="96" alt="{{ $product->name }}">
        </div>

        <div class="pos-pv-details">
            <div class="pos-pv-tags">
                @if (BusinessRules::vegNonVegEnabled() && config('module.' . $product->store->module->module_type)['veg_non_veg'])
                    <span class="badge badge-{{ $product->veg ? 'success' : 'danger' }}">
                        {{ $product->veg ? translate('Veg') : translate('Non veg') }}
                    </span>
                @endif
                @if (isset($stock))
                    @if ($stock == 0)
                        <span class="badge badge-danger">{{ translate('Out of stock') }}</span>
                    @else
                        <span class="badge badge-soft-secondary">
                            {{ translate('messages.In stock') }}: {{ $stock }}
                        </span>
                    @endif
                @endif
            </div>

            <div class="pos-pv-price">
                @if (isset($product->module_id) && $product->module->module_type == 'food')
                    <span class="pos-pv-price-now">{{ \App\CentralLogics\Helpers::get_food_price_range($product, true) }}</span>
                    @if ($product->discount > 0 || \App\CentralLogics\Helpers::get_store_discount($product->store))
                        <span class="pos-pv-price-was">{{ \App\CentralLogics\Helpers::get_food_price_range($product) }}</span>
                    @endif
                @else
                    <span class="pos-pv-price-now">{{ \App\CentralLogics\Helpers::get_price_range($product, true) }}</span>
                    @if ($product->discount > 0 || \App\CentralLogics\Helpers::get_store_discount($product->store))
                        <span class="pos-pv-price-was">{{ \App\CentralLogics\Helpers::get_price_range($product) }}</span>
                    @endif
                @endif
            </div>

            @if ($product->discount > 0)
                <p class="pos-pv-meta">
                    {{ translate('Discount') }}:
                    <strong id="set-discount-amount">{{ \App\CentralLogics\Helpers::get_product_discount($product) }}</strong>
                </p>
            @endif

            <a href="{{ route('admin.item.view', $product->id) }}" class="pos-pv-link" target="_blank">
                {{ translate('messages.View product details') }} <i class="tio-open-in-new"></i>
            </a>
        </div>
    </div>

    <div class="row pt-2">
        <div class="col-12">
            @if (filled(strip_tags($product->description)))
                <h5 class="pos-pv-section-title">{{ translate('messages.Description') }}</h5>
                <div class="pos-pv-text text-break">{!! $product->description !!}</div>
            @endif

            @if (in_array($product->module->module_type ,['food','grocery']))
                @if (count($product->nutritions) )
                    <h5 class="pos-pv-section-title">{{ translate('Nutrition details') }}</h5>
                    <div class="pos-pv-text text-break">
                        @foreach($product->nutritions as $nutrition)
                        {{$nutrition->nutrition}}{{ !$loop->last ? ',' : '.'}}
                        @endforeach
                    </div>
                @endif
                @if (count($product->allergies))
                    <h5 class="pos-pv-section-title">{{ translate('Allergen ingredients') }}</h5>
                    <div class="pos-pv-text text-break">
                        @foreach($product->allergies as $allergy)
                        {{$allergy->allergy}}{{ !$loop->last ? ',' : '.'}}
                        @endforeach
                    </div>
                @endif
            @endif

            @if (in_array($product->module->module_type ,['pharmacy']))
                @if ($product->generic->pluck('generic_name')->first())
                    <h5 class="pos-pv-section-title">{{ translate('Generic name') }}</h5>
                    <div class="pos-pv-text text-break">
                        {{ $product->generic->pluck('generic_name')->first() }}
                    </div>
                @endif
            @endif

            <form id="add-to-cart-form" class="mb-2">
                @csrf
                <input type="hidden" name="id" value="{{ $product->id }}">
                @if ($product->module->module_type == 'food')
                    @if ($product->food_variations)

                        @foreach (json_decode($product->food_variations) as $key => $choice)
                            @if (isset($choice->price) == false)
                                <h5 class="pos-pv-section-title">{{ $choice->name }} <small class="pos-pv-optional">
                                        ({{ $choice->required == 'on' ? translate('messages.Required.') : translate('Optional') }})
                                    </small>
                                </h5>
                                @if ($choice->min != 0 && $choice->max != 0)
                                    <small class="d-block mb-3">
                                        {{ translate('You need to select minimum') }}  {{ $choice->min }}
                                        {{ translate('To maximum') }}  {{ $choice->max }} {{ translate('Options') }}
                                    </small>
                                @endif

                                <div>
                                    <input type="hidden" name="variations[{{ $key }}][min]"
                                        value="{{ $choice->min }}">
                                    <input type="hidden" name="variations[{{ $key }}][max]"
                                        value="{{ $choice->max }}">
                                    <input type="hidden" name="variations[{{ $key }}][required]"
                                        value="{{ $choice->required }}">
                                    <input type="hidden" name="variations[{{ $key }}][name]"
                                        value="{{ $choice->name }}">
                                    @foreach ($choice->values as $k => $option)
                                        <div class="form-check form--check d-flex pr-5 mr-6">
                                            <input class="form-check-input"
                                                type="{{ $choice->type == 'multi' ? 'checkbox' : 'radio' }}"
                                                id="choice-option-{{ $key }}-{{ $k }}"
                                                name="variations[{{ $key }}][values][label][]"
                                                value="{{ $option->label }}" autocomplete="off">

                                            <label class="form-check-label"
                                                for="choice-option-{{ $key }}-{{ $k }}">{{ Str::limit($option->label, 20, '...') }}</label>
                                            <span
                                                class="ml-auto">{{ \App\CentralLogics\Helpers::format_currency($option->optionPrice) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    @endif
                @else

                    @foreach (json_decode($product->choice_options) as $choice)
                        <h5 class="pos-pv-section-title">{{ $choice->title }}</h5>
                        <div class="d-flex justify-content-left flex-wrap">
                            @foreach ($choice->options as $key => $option)
                                <input class="btn-check check-stock" type="radio" id="{{ $choice->name }}-{{ $option }}"
                                    name="{{ $choice->name }}" value="{{ $option }}" {{ isset($selected_item) && array_key_exists($choice->name, $selected_item) && trim($option) == $selected_item[$choice?->name] ? 'checked' : ($key == 0 ? 'checked' : '') }}
                                    autocomplete="off" required>
                                <label class="btn btn-sm check-label mx-1 choice-input text-break"
                                    for="{{ $choice->name }}-{{ $option }}">{{ Str::limit($option, 20, '...') }}</label>
                            @endforeach
                        </div>
                    @endforeach
                @endif

                @if ((isset($stock) && $stock > 0) || !isset($stock) )
                <div class="d-flex justify-content-between mt-3">
                    <div class="product-description-label mt-2 text-dark h3">{{ translate('messages.quantity') }}:
                    </div>
                    <div class="product-quantity d-flex align-items-center">
                        <div class="input-group input-group--style-2 pr-3 initial--19">
                            <span class="input-group-btn">
                                <button class="btn btn-number p--10 text-dark decrease-button-cart" type="button" data-type="minus"
                                    data-field="quantity" >
                                    <i class="tio-remove  font-weight-bold"></i>
                                </button>
                            </span>

                            <input type="text" name="quantity"
                                class="form-control text-center cart-qty-field" placeholder="1" readonly
                                value="1" min="1" max="{{   (isset($stock) && $stock > 0) ?   ($product?->maximum_cart_quantity ?  min($stock, $product?->maximum_cart_quantity) : $stock)   :  $product?->maximum_cart_quantity ??  '9999999999' }}">
                                <span class="input-group-btn">
                                    <button class="btn btn-number p--10 text-dark increase-button-cart" type="button" data-type="plus"
                                    data-field="quantity">
                                    <i class="tio-add  font-weight-bold"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                @endif
                @php($add_ons = json_decode($product->add_ons))
                @if (count($add_ons) > 0 && $add_ons[0])
                    <h5 class="pos-pv-section-title">{{ translate('Addon') }}</h5>

                    <div class="d-flex justify-content-left flex-wrap">
                        @foreach ($product_addons as $key => $add_on)
                            <div class="flex-column pb-2">
                                <input type="hidden" name="addon-price{{ $add_on->id }}"
                                    value="{{ $add_on->price }}">
                                <input class="btn-check addon-chek addon-quantity-input-toggle" type="checkbox" id="addon{{ $key }}"
                                    name="addon_id[]"
                                    value="{{ $add_on->id }}" autocomplete="off">
                                <label
                                    class="d-flex align-items-center btn btn-sm check-label mx-1 addon-input text-break"
                                    for="addon{{ $key }}">{{ Str::limit($add_on->name, 20, '...') }} <br>
                                    {{ \App\CentralLogics\Helpers::format_currency($add_on->price) }}</label>
                                <label class="input-group addon-quantity-input mx-1 shadow-lg border bg-white rounded px-1"
                                    for="addon{{ $key }}">
                                    <button class="btn btn-sm h-100 text-dark px-0 decrease-button" data-id="{{ $add_on->id }}" type="button"
                                       ><i
                                            class="tio-remove  font-weight-bold"></i></button>
                                    <input type="number" name="addon-quantity{{ $add_on->id }}" id="addon_quantity_input{{ $add_on->id }}"
                                        class="form-control text-center border-0 h-100" placeholder="1"
                                        value="1" min="1" max="9999999999" readonly>
                                    <button class="btn btn-sm h-100 text-dark px-0 increase-button" data-id="{{ $add_on->id }}" type="button"
                                        ><i
                                            class="tio-add  font-weight-bold"></i></button>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (isset($stock) && $stock > 0 || !isset($stock))

                <div class="row no-gutters d-none mt-2 text-dark" id="chosen_price_div">
                    <div class="col-2">
                        <div class="product-description-label">{{ translate('Total price') }}:</div>
                    </div>
                    <div class="col-10">
                        <div class="product-price">

                            <strong id="chosen_price"></strong>
                        </div>
                    </div>
                </div>

                @endif
                @if (isset($stock) && $stock > 0 || !isset($stock) )
                <div class="d-flex justify-content-center mt-2">
                    <button class="btn btn--primary add-To-Cart" type="button" class="h--45px">
                        <i class="tio-shopping-cart"></i>
                        {{ translate('messages.Add to cart') }}
                    </button>
                </div>
                @elseif(isset($stock) && $stock == 0 )
                <div class="d-flex justify-content-center mt-2">
                    <button class="btn btn-secondary" type="button" class="h--45px">
                        <i class="tio-shopping-cart"></i>
                        {{ translate('Stock out') }}
                    </button>
                </div>
                @else
                <div class="d-flex justify-content-center mt-2">
                    <button class="btn btn-secondary" type="button" class="h--45px">
                        <i class="tio-shopping-cart"></i>
                        {{ translate('messages.Add to cart') }}
                    </button>
                </div>
                @endif
            </form>
        </div>
    </div>
</div>
<script type="text/javascript">
    getVariantPrice();
    $('#add-to-cart-form input').on('change', function() {
        getVariantPrice();
    });
</script>
