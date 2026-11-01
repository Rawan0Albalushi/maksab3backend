@extends('layouts.vendor.app')

@section('title',translate('Update coupon'))

@section('content')

    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-edit"></i> {{translate('messages.Coupon update')}}</h1>
                    <p class="page-header-desc">{{ translate('Change this coupon\'s discount, its limits or how long it stays valid.') }}</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{route('vendor.coupon.update',[$coupon['id']])}}" method="post" class="custom-validation">
                    @csrf
                    <div class="row">
                        <div class="col-12">

                                    @if($language)
                                        <ul class="nav nav-tabs mb-4">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active"
                                                href="#"
                                                id="default-link">{{translate('Default')}}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link"
                                                        href="#"
                                                        id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="lang_form" id="default-form">
                                            <div class="form-group error-wrapper">
                                                <label class="input-label" for="default_title">{{translate('messages.Title')}} ({{translate('Default')}})
                                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                                </label>
                                                <input type="text" name="title[]" id="default_title" class="form-control" placeholder="{{translate('messages.New coupon')}}" value="{{$coupon?->getRawOriginal('title')}}" required>
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                        @foreach($language as $lang)
                                            <?php
                                                if(count($coupon['translations'])){
                                                    $translate = [];
                                                    foreach($coupon['translations'] as $t)
                                                    {
                                                        if($t->locale == $lang && $t->key=="title"){
                                                            $translate[$lang]['title'] = $t->value;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <div class="d-none lang_form" id="{{$lang}}-form">
                                                <div class="form-group error-wrapper">
                                                    <label class="input-label" for="{{$lang}}_title">{{translate('messages.Title')}} ({{strtoupper($lang)}})</label>
                                                    <input type="text" name="title[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('messages.New coupon')}}" value="{{$translate[$lang]['title']??''}}"  >
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                            </div>
                                        @endforeach


                                    @endif
                        </div>

                    </div>
                    <div class="row">
                          <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="coupon_type">{{translate('Coupon type')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <select id="coupon_type" name="coupon_type" class="form-control" >
                                    @if (($store_data->sub_self_delivery == 1 && !in_array($store_data->module?->module_type, ['service', 'rental'])) || $coupon['coupon_type']=='free_delivery')
                                    <option value="free_delivery" {{$coupon['coupon_type']=='free_delivery'?'selected':''}}>{{translate('Free delivery')}}</option>
                                    @endif
                                    <option value="default" {{$coupon['coupon_type']=='default'?'selected':''}}>{{translate('Default')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="coupon_code">{{translate('messages.code')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <input id="coupon_code" type="text" class="form-control" value="{{$coupon['code']}}"
                                        maxlength="100" disabled>
                                <input type="hidden" name="code" value="{{$coupon['code']}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="coupon_limit">{{translate('Limit for same user')}}</label>
                                <input type="number" required name="limit" id="coupon_limit" value="{{$coupon['limit']}}" class="form-control" max="100"
                                        placeholder="{{ translate('messages.Ex') }}: 10">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="date_from">{{translate('Start date')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <input type="date" name="start_date" class="form-control" id="date_from" placeholder="{{translate('Select date')}}" value="{{date('Y-m-d',strtotime($coupon['start_date']))}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="date_to">{{translate('messages.Expire date')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <input type="date" name="expire_date" class="form-control" placeholder="{{translate('Select date')}}" id="date_to" value="{{date('Y-m-d',strtotime($coupon['expire_date']))}}"
                                        data-hs-flatpickr-options='{
                                        "dateFormat": "Y-m-d"
                                    }'>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 {{$coupon['coupon_type']=='free_delivery'?'d-none':''}}" id="discount_type_div">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="discount_type">{{translate('Discount type')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <select name="discount_type" id="discount_type" class="form-control" {{$coupon['coupon_type']=='free_delivery'?'disabled':''}}>
                                    <option value="amount" {{$coupon['discount_type']=='amount'?'selected':''}}>
                                        {{ translate('Amount').' ('.\App\CentralLogics\Helpers::currency_symbol().')'  }}
                                    </option>
                                    <option value="percent" {{$coupon['discount_type']=='percent'?'selected':''}}>
                                        {{ translate('Percent').' (%)' }}
                                    </option>
                                </select>
                            </div>
                        </div>
                         <div class="col-sm-6 col-lg-3">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="min_purchase">{{translate('messages.Min purchase')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <input id="min_purchase" type="number" name="min_purchase" step="0.01" value="{{$coupon['min_purchase']}}"
                                        min="0" max="999999999999.99" class="form-control"
                                        placeholder="100" required>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 {{$coupon['coupon_type']=='free_delivery'?'d-none':''}}" id="discount_div">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="discount">{{translate('Discount')}}
                                    <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                </label>
                                <input type="number" id="discount" min="1" max="999999999999.99" step="0.01" value="{{$coupon['discount']}}"
                                        name="discount" class="form-control" required {{$coupon['coupon_type']=='free_delivery'?'readonly':''}}>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 {{$coupon['coupon_type']=='free_delivery'?'d-none':''}}" id="max_discount_div">
                            <div class="form-group error-wrapper">
                                <label class="input-label" for="max_discount">{{translate('messages.Max discount')}}</label>
                                <input type="number" min="{{$coupon['discount_type']=='percent'?'0.01':'0'}}" max="999999999999.99" step="0.01"
                                        value="{{$coupon['max_discount']}}" name="max_discount" id="max_discount" class="form-control"
                                        {{$coupon['coupon_type']=='free_delivery' || $coupon['discount_type']=='amount' ?'readonly':''}}
                                        {{$coupon['coupon_type']!='free_delivery' && $coupon['discount_type']=='percent' ?'required':''}}>
                            </div>
                        </div>

                    </div>
                    <div class="btn--container justify-content-end">
                        <button id="reset_btn" type="button" class="btn btn--reset location-reload" ><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/vendor-coupon.js')}}"></script>

@endpush
