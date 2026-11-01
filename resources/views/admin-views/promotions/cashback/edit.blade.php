@extends('layouts.admin.app')

@section('title',translate('Edit cashback offer'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/cashback.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Edit cashback offer')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change how much this cashback offer returns, or the orders it applies to.') }}</p>
        </div>

        <div class="card">
            <div class="card-body" id="form_data">
                <form action="{{route('admin.users.cashback.update',['id'=>$cashback?->id ])}}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            @if ($language)
                            <ul class="nav nav-tabs mb-3 border-0">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                    href="#"
                                    id="default-link">{{translate('Default')}}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                            href="#"
                                            id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                        @endforeach
                                    </ul>
                        </div>

                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="lang_form" id="default-form">
                                <div class="form-group">
                                    <label class="input-label"
                                        for="default_title">{{ translate('messages.Title') }}
                                        ({{ translate('Default') }})
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="title[]" maxlength="254" value="{{$cashback?->getRawOriginal('title')}}" id="default_title"
                                        class="form-control" placeholder="{{ translate('Eid dhamaka') }}" >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            </div>
                                @foreach ($language as $lang)
                                <?php
                                if(count($cashback['translations'])){
                                    $translate = [];
                                    foreach($cashback['translations'] as $t)
                                    {
                                        if($t->locale == $lang && $t->key=="title"){
                                            $translate[$lang]['title'] = $t->value;
                                        }
                                        if($t->locale == $lang && $t->key=="description"){
                                            $translate[$lang]['description'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                                    <div class="d-none lang_form"
                                        id="{{ $lang }}-form">
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                ({{ strtoupper($lang) }})
                                            </label>
                                            <input type="text" name="title[]" maxlength="254" id="{{ $lang }}_title" value="{{$translate[$lang]['title']??''}}"
                                                class="form-control" placeholder="{{ translate('Eid dhamaka') }}"
                                                 >
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.Title') }} ({{ translate('Default') }})</label>
                                        <input type="text" name="title[]" maxlength="254" class="form-control"
                                            placeholder="{{ translate('Eid dhamaka') }}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4 col-lg-4 col-sm-6" id="customer_wise">
                            <div class="form-group">
                                <label class="input-label" for="select_customer">{{translate('Select customer')}}   <span class="text-danger">*</span></label>
                                <select required name="customer_id[]" id="select_customer"
                                class="form-control multiple-select2"
                                data-placeholder="{{translate('Select customer')}}"
                                multiple="multiple" data-ajax-url="{{ route('admin.users.customer.select-list') }}" placeholder="{{translate('Select customer')}}">
                                <option value="all" {{in_array('all', json_decode($cashback->customer_id))?'selected':''}}>{{translate('All')}} </option>
                                @foreach($selected_customers as $user)
                                <option value="{{$user->id}}" selected>{{$user->f_name.' '.$user->l_name}}</option>
                            @endforeach
                            </select>

                            </div>
                        </div>



                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Cashback type')}} <span class="form-label-secondary text-danger"
                                    data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *
                                    </span></label>
                                <select name="cashback_type" class="form-control"  data-mas_discount="{{ $cashback?->max_discount ?? null }}" id="cashback_type" required>
                                    <option {{ $cashback->cashback_type ==  'percentage' ? 'selected'  : '' }} value="percentage">{{translate('messages.percentage')}} (%)</option>
                                    <option {{ $cashback->cashback_type ==  'amount' ? 'selected'  : '' }} value="amount">{{translate('Amount')}} {{ \App\CentralLogics\Helpers::currency_symbol() }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Cashback amount')}}

                                    <span  class=" {{ $cashback->cashback_type ==  'percentage' ? 'd-none'  : '' }}   " id='cuttency_symbol'>({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    </span>
                                    <span  class=" {{ $cashback->cashback_type ==  'percentage' ? ''  : 'd-none' }}"  id="percentage">(%)</span>

                                    <span
                                    class="input-label-secondary text--title" data-toggle="tooltip"
                                    data-placement="right"
                                    data-original-title="{{ translate('Set the cash back amount/percentage a customer will receive after a successful order.') }}">
                                    <i class="tio-info-outined"></i>
                                </span>
                                <span class="form-label-secondary text-danger"
                                data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.Required.')}}"> *
                                </span>

                                </label>
                                <input type="number"   step="0.01" min="1" value="{{  $cashback->cashback_amount }}" max="{{ $cashback->cashback_type ==  'percentage' ? '100'  : '999999999.99' }}"  placeholder="{{ translate('messages.Ex') . ': 100' }}"  name="cashback_amount" id="Cash_back_amount" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Minimum Purchase')}} ({{ \App\CentralLogics\Helpers::currency_symbol() }})   <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" id="min_purchase" required name="min_purchase" value="{{ $cashback->min_purchase }}" min="0" max="999999999999.99" class="form-control"
                                placeholder="{{ translate('messages.Ex') . ': 100' }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="max_discount">{{translate('Maximum discount')}} ({{ \App\CentralLogics\Helpers::currency_symbol() }}) </label>
                                <input type="number" step="0.01" min="0" placeholder="{{ translate('messages.Ex') . ': 100' }}"  max="999999999999.99"  {{ $cashback->cashback_type ==  'percentage' ? 'required'  : 'readonly' }}   value="{{ $cashback->max_discount }}" name="max_discount" id="max_discount" class="form-control" >
                            </div>
                        </div>

                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Start date')}}   <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" value="{{ $cashback->start_date }}" class="form-control" id="date_from" required>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('End date')}}   <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" value="{{ $cashback->end_date }}"  class="form-control" id="date_to" required>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-4 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Limit for same user')}}   <span class="text-danger">*</span></label>
                                <input type="number" step="1" name="same_user_limit" value="{{ $cashback->same_user_limit }}"  value="0" min="0" max="9999999" class="form-control" required
                                placeholder="{{ translate('messages.Ex') . ': 5' }}">
                            </div>
                        </div>

                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>


        "use strict";
        $('#reset_btn').click(function(){
            setTimeout(reset_select, 100);
        })
        function reset_select(){
            $('#select_customer').trigger('change');
            if($('#cashback_type').val() == 'amount')
                    {
                        $('#max_discount').attr("readonly","true");
                        $('#max_discount').removeAttr("required");
                        $('#percentage').addClass('d-none');
                        $('#cuttency_symbol').removeClass('d-none');
                        $('#Cash_back_amount').attr('max',99999999999);
                    }else{
                        $('#max_discount').removeAttr("readonly");
                        $('#max_discount').attr("required","true");
                        $('#percentage').removeClass('d-none');
                        $('#cuttency_symbol').addClass('d-none');
                        $('#Cash_back_amount').attr('max',100);
                    }
        }
        $(document).on('ready', function () {
            $('#date_from').attr('min',(new Date()).toISOString().split('T')[0]);
            $('#date_from').attr('max','{{date("Y-m-d",strtotime($cashback["end_date"]))}}');
            $('#date_to').attr('min','{{date("Y-m-d",strtotime($cashback["start_date"]))}}');
        });



        $(document).ready(function() {

                $('#cashback_type').on('change', function() {


                    if($('#cashback_type').val() == 'amount')
                    {
                        $('#max_discount').attr("readonly","true");
                        $('#max_discount').removeAttr("required");
                        $('#max_discount').val( $(this).data("max_discount"));
                        $('#percentage').addClass('d-none');
                        $('#cuttency_symbol').removeClass('d-none');
                        $('#Cash_back_amount').attr('max',99999999999);
                    }
                    else
                    {
                        $('#max_discount').removeAttr("readonly");
                        $('#max_discount').attr("required","true");
                        $('#percentage').removeClass('d-none');
                        $('#cuttency_symbol').addClass('d-none');
                        $('#Cash_back_amount').attr('max',100);

                    }
                });

                $('#date_from').attr('min',(new Date()).toISOString().split('T')[0]);
                $('#date_to').attr('min',(new Date()).toISOString().split('T')[0]);

                $('.js-select2-custom').each(function () {
                    let select2 = $.HSCore.components.HSSelect2.init($(this));
                });
            });

            $("#date_from").on("change", function () {
                $('#date_to').attr('min',$(this).val());
            });

            $("#date_to").on("change", function () {
                $('#date_from').attr('max',$(this).val());
            });




    </script>
@endpush
