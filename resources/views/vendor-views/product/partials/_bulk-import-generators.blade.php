{{--
    The two value generators that sit under the item import form. Their ids are
    what the page script binds to, so keep them as they are.
--}}
        <form action="javascript:" method="post" id="item_form" enctype="multipart/form-data">
            <div id="food_variation_section" style="display: none">
                <div class="card mt-2 rest-part">
                    <div class="card-header">
                        <h5 class="card-title">
                            <span>{{ translate('messages.Food variations generator') }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <div id="add_new_option">
                                </div>
                                <br>
                                <div class="mt-2">
                                    <a class="btn btn-outline-success"
                                        id="add_new_option_button"><i class="tio-add-circle"></i> {{ translate('Add new variation') }}</a>
                                </div> <br><br>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mb-3">
                            <button type="submit" class="btn btn--primary"><i class="tio-magic-wand"></i> {{translate('Generate')}}</button>
                        </div>
                        <textarea name="" id="food_variation_outpot" class="form-control" rows="5" readonly></textarea>
                    </div>
                </div>
            </div>
        </form>
<br>
        <form action="javascript:" method="post" id="item_form_2" enctype="multipart/form-data">
            <div id="attribute_section" style="display: none">
                <h4 class="mb-3">{{translate('Generate variation')}}</h4>
                <div class="card card mt-2 rest-part">
                    <div class="card-header border-0 p-0">
                        <div class="alert w-100 alert-soft-primary alert-dismissible fade show d-flex m-0" role="alert">
                            <div>
                                <img src="{{asset('/public/assets/admin/img/icons/intel.png')}}" width="22" alt="">
                            </div>
                            <div class="w-0 flex-grow-1 pl-3">
                                <strong>{{ translate('Attention!') }}</strong>
                              {{ translate('Paste each generated field into its matching Excel column. Use [ ] for an empty value.') }}
                            </div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <label class="input-label m-0">{{ translate('Attribute') }}<span class="input-label-secondary"></span></label>
                            <button type="submit" class="btn btn--primary"><i class="tio-magic-wand"></i> {{translate('Generate value')}}</button>
                        </div>
                        <div class="row g-2">
                            <div class="col-lg-6">
                                <div class="form-group mb-0">
                                    <select name="attribute_id[]" id="choice_attributes"
                                        class="form-control js-select2-custom" multiple="multiple">
                                        @foreach ($attributes as $attribute)
                                            <option value="{{ $attribute['id'] }}">{{ $attribute['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="customer_choice_options pt-3" id="customer_choice_options">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="variant_combination" id="variant_combination">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="">{{ translate('messages.Generated variant') }} <span class="form-label-secondary text-danger " data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('This field is for generated variation. Copy them & paste into excel sheet.') }} "><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="Veg non veg"> * </span></label>
                                <textarea name="" id="variation_output" class="form-control" rows="5" readonly></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="">{{ translate('messages.Generated choice option') }} <span class="form-label-secondary text-danger " data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Choice option is required if you are using product variation') }}"><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="Veg non veg"> * </span></label>
                                <textarea name="" id="choice_output" class="form-control" rows="5" readonly></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="">{{ translate('messages.Generated attributes field') }} <span class="form-label-secondary text-danger " data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Attributes is required if you are using product variation') }}"><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="Veg non veg"> * </span></label>
                                <textarea name="" id="attributes" class="form-control" rows="5" readonly></textarea>
                            </div>
                        </div>

                        <div class="btn--container justify-content-end mt-2 mb-2">
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                        </div>


                    </div>
                </div>
            </div>
        </form>
