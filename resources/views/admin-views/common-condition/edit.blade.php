@extends('layouts.admin.app')

@section('title',translate('messages.Update condition'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('messages.Update Common Condition')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Rename this condition or change the languages it is shown in.') }}</p>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.common-condition.update',[$condition['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-20">
                        <h3 class="mb-1 fs-18">{{ translate('Update Common Condition') }}</h3>
                        <p class="mb-0">{{ translate('Here you can update and manage common condition names that will be displayed to customers.') }}</p>
                    </div>
                    <div class="bg-light2 rounded p-20">
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
                                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="col-12">
                                @if($language)
                                    <div class="form-group mb-0 lang_form" id="default-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{ translate('Default') }})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New condition')}}" maxlength="191" value="{{$condition?->getRawOriginal('name')}}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    @foreach($language as $lang)
                                        <?php
                                            if(count($condition['translations'])){
                                                $translate = [];
                                                foreach($condition['translations'] as $t)
                                                {
                                                    if($t->locale == $lang && $t->key=="name"){
                                                        $translate[$lang]['name'] = $t->value;
                                                    }
                                                }
                                            }
                                        ?>
                                        <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{strtoupper($lang)}})</label>
                                            <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New condition')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}">
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    @endforeach
                                @else
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}}</label>
                                        <input type="text" name="name" class="form-control" placeholder="{{translate('messages.New condition')}}" value="{{$condition['name']}}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endif
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/common-condition-index.js"></script>
    <script>
        "use strict";
        $('#reset_btn').click(function(){
            $('#module_id').val("{{ $condition->module_id }}").trigger('change');
            $('#viewer').attr('src', "{{asset('storage/app/public/condition')}}/{{$condition['image']}}");
        })
    </script>
@endpush
