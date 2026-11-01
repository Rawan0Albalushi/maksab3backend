@php($old = session()->has('address') ? session()->get('address') : null)

<div class="modal fade pos-modal pos-modal--wide" id="deliveryAddrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ translate('Delivery options') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="delivery_address_store">
                    @csrf

                    <div class="row g-2" id="delivery_address_fields">
                        <div class="col-md-6">
                            <label class="input-label" for="contact_person_name">
                                {{ translate('messages.Contact person name') }}
                                <span class="input-label-secondary text-danger">*</span>
                            </label>
                            <input id="contact_person_name" type="text" class="form-control"
                                   name="contact_person_name"
                                   value="{{ $old ? $old['contact_person_name'] : (isset($customer) && $customer ? $customer->f_name . ' ' . $customer->l_name : '') }}"
                                   placeholder="{{ translate('messages.Ex') }}: John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="contact_person_number">
                                {{ translate('Contact number') }}
                                <span class="input-label-secondary text-danger">*</span>
                            </label>
                            <input id="contact_person_number" type="tel" class="form-control"
                                   name="contact_person_number"
                                   value="{{ $old ? $old['contact_person_number'] : (isset($customer) && $customer ? $customer->phone : '') }}"
                                   placeholder="{{ translate('messages.Ex') }}: +3264124565">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="road">{{ translate('messages.Road') }}</label>
                            <input id="road" type="text" class="form-control" name="road"
                                   value="{{ $old ? $old['road'] : '' }}"
                                   placeholder="{{ translate('messages.Ex') }}: 4th">
                        </div>
                        <div class="col-md-3">
                            <label class="input-label" for="house">{{ translate('messages.House') }}</label>
                            <input id="house" type="text" class="form-control" name="house"
                                   value="{{ $old ? $old['house'] : '' }}"
                                   placeholder="{{ translate('messages.Ex') }}: 45/C">
                        </div>
                        <div class="col-md-3">
                            <label class="input-label" for="floor">{{ translate('messages.Floor') }}</label>
                            <input id="floor" type="text" class="form-control" name="floor"
                                   value="{{ $old ? $old['floor'] : '' }}"
                                   placeholder="{{ translate('messages.Ex') }}: 1A">
                        </div>
                    </div>

                    {{-- Hidden until the store's zone resolves to an area/zip-priced delivery
                         rule (fetched from the coverage endpoint once the map initialises). A
                         distance/flat-priced zone never shows this, and the map's pinned
                         location is still recorded on the address either way. --}}
                    <div class="pos-fieldset d-none" id="coverage_picker_wrap">
                        <h5 class="pos-fieldset-title">
                            <i class="tio-map"></i>{{ translate('Delivery coverage') }}
                        </h5>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="input-label" for="coverage_picker_select">
                                    <span id="coverage_picker_label_text">{{ translate('messages.Select Area') }}</span>
                                    <span class="input-label-secondary text-danger">*</span>
                                </label>
                                <select class="form-control" id="coverage_picker_select" name="">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="pos-fieldset">
                        <h5 class="pos-fieldset-title">
                            <i class="tio-poi"></i>{{ translate('messages.Pin location') }}
                        </h5>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="input-label" for="longitude">
                                    {{ translate('messages.longitude') }}
                                    <span class="input-label-secondary text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="longitude" name="longitude"
                                       value="{{ $old ? $old['longitude'] : '' }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="input-label" for="latitude">
                                    {{ translate('messages.latitude') }}
                                    <span class="input-label-secondary text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="latitude" name="latitude"
                                       value="{{ $old ? $old['latitude'] : '' }}" readonly>
                            </div>
                            <div class="col-12">
                                <label class="input-label" for="address">{{ translate('messages.Address') }}</label>
                                <textarea id="address" name="address" class="form-control" cols="30" rows="2"
                                          placeholder="{{ translate('messages.Ex') }}: address">{{ $old ? $old['address'] : '' }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                                    <div class="pos-map-note">
                                        <i class="tio-info-outined"></i>
                                        <span>{{ translate('Pin the address in the map to calculate delivery fee') }}</span>
                                    </div>
                                    <div class="pos-fee-pill">
                                        <input type="hidden" name="distance" id="distance">
                                        <span>{{ translate('Delivery fee') }}:</span>
                                        <input type="hidden" name="delivery_fee" id="delivery_fee"
                                               value="{{ $old ? $old['delivery_fee'] : '' }}">
                                        <strong>{{ $old ? $old['delivery_fee'] : 0 }} {{ \App\CentralLogics\Helpers::currency_symbol() }}</strong>
                                    </div>
                                </div>

                                <input id="pac-input" class="controls map-search__option form-control mb-2"
                                       title="{{ translate('Search your location') }}" type="text"
                                       placeholder="{{ translate('Search') }}">
                                <div class="pos-map" id="map"></div>
                            </div>
                        </div>
                    </div>

                    <div class="btn--container justify-content-end mt-3">
                        <button class="btn btn--primary delivery-Address-Store" type="button">
                            <i class="tio-save"></i> {{ translate('Update delivery address') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
