@extends('layouts.admin.app')

@section('title',translate('Modules'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/module.png') }}" alt="">
                        </span>
                        <span>
                            {{translate('messages.module_type')}}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The kind of business a module runs, which decides the screens and rules it gets.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5>{{translate('Add new module')}}</h5></div>
            <div class="card-body">
                <form action="{{route('admin.module.create')}}" method="get" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="input-label" for="exampleFormControlInput1">{{translate('Type')}}</label>
                        <input type="text" name="module_type" class="form-control" placeholder="{{translate('messages.New category')}}" value="{{old('name')}}" required maxlength="191">
                    </div>

                    <div class="form-group">
                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Description')}}</label>
                        <textarea class="ckeditor form-control" name="module_description"></textarea>
                    </div>

                    <div class="form-group pt-2">
                        <button type="submit" class="btn btn-primary"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                    </div>

                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header pb-0">
                <h5>{{translate('messages.module_type_list')}}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle" data-hs-datatables-options='{
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th>{{translate('messages.module_type')}}</th>
                                <th>{{translate('messages.Description')}}</th>
                            </tr>
                        </thead>

                        <tbody id="table-div">
                        @foreach($module_type as $key=>$module)
                            <tr>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($module['module_type'], 20,'...')}}
                                    </span>
                                </td>
                                <td>
                                    {!! $module->description !!}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/ckeditor/ckeditor.js')}}"></script>
    <script>
        "use strict";
        $(document).ready(function () {
            $('.ckeditor').ckeditor();
        });
    </script>
@endpush
