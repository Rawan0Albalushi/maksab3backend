@extends('layouts.admin.app')

@section('title',translate('Parcel category'))


@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/parcel.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Parcel category')}}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $parcel_categories->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('The kinds of parcel customers can send, each with its own size limit and charge.') }}</p>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.parcel.category.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
                    @php($defaultLang = str_replace('_', '-', app()->getLocale()))
                    @if($language)
                    <div class="col-12">
                        <ul class="nav nav-tabs mb-3 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active"
                                href="#"
                                id="default-link">{{translate('Default')}}</a>
                            </li>
                            @foreach (json_decode($language) as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link"
                                        href="#"
                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <div class="col-md-6">
                        @if ($language)
                        <div class="lang_form" id="default-form">
                            <div class="form-group">
                                <label class="input-label" for="default_name">{{translate('Name')}} ({{ translate('Default') }})</label>
                                <input type="text" name="name[]" id="default_name" class="form-control" placeholder="{{translate('messages.New item')}}"  >
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            <div class="form-group">
                                <label class="input-label" for="description">{{translate('Short description')}} ({{ translate('Default') }})</label>
                                <textarea type="text" name="description[]" class="form-control ckeditor"  ></textarea>
                            </div>
                        </div>
                            @foreach(json_decode($language) as $lang)
                                <div class="d-none lang_form" id="{{$lang}}-form">
                                    <div class="form-group">
                                        <label class="input-label" for="{{$lang}}_name">{{translate('Name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]" id="{{$lang}}_name" class="form-control" placeholder="{{translate('messages.New item')}}"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                    <div class="form-group">
                                        <label class="input-label" for="description">{{translate('Short description')}} ({{strtoupper($lang)}})</label>
                                        <textarea type="text" name="description[]" class="form-control ckeditor"  ></textarea>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div id="default-form">
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{ translate('Default') }})</label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New item')}}" required>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Short description')}}</label>
                                    <textarea type="text" name="description[]" class="form-control ckeditor"></textarea>
                                </div>
                            </div>
                        @endif
                        <input name="position" value="0" class="initial-hidden">
                    </div>
                    <div class="col-md-6">
                        <div class="h-100 d-flex flex-column">
                            <label class="text-center d-block mt-auto">
                                {{translate('messages.Image')}}
                                <small class="text-danger">* ( {{translate('messages.Ratio')}} 200x200)</small>
                            </label>
                            <div class="text-center py-3 my-auto">
                                <img class="img--120" id="viewer"
                                    src="{{asset('public/assets/admin/img/900x400/img1.jpg')}}"
                                    alt="image"/>
                            </div>
                            <div class="custom-file">
                                <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                    accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" required>
                                <label class="custom-file-label" for="customFileEg1">{{translate('Choose file')}}</label>
                            </div>
                        </div>
                    </div>
                    {{-- ONE charge, and it is ADDITIONAL: the delivery rule prices the parcel and this
                         is added on top, like a weight band or a dimension class (owner decision
                         2026-09-03). It replaces the per-km / minimum pair a category used to price
                         with — those columns survive for rollback and nothing reads them. --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label text-capitalize">{{ translate('Additional charge') }}
                                ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                            </label>
                            <input type="number" step=".01" min="0" class="form-control" name="charge"
                                placeholder="{{ translate('Ex') }}: 50" value="0">
                        </div>
                    </div>
                    @if ($categoryWiseTax)
                    <div class="col-md-6">

                                <span class="mb-2 d-block title-clr fw-normal">{{ translate('Select tax rate') }}</span>
                                <select name="tax_ids[]" id="tax__rate" class="form-control js-select2-custom"
                                    multiple="multiple" required placeholder="{{ translate('Type & select tax rate') }}">
                                    @foreach ($taxVats as $taxVat)
                                        <option value="{{ $taxVat->id }}"> {{ $taxVat->name }}
                                            ({{ $taxVat->tax_rate }}%)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                    <div class="col-12">
                        <div class="btn--container justify-content-end">
                            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i> {{translate('messages.Add Parcel Category')}}</button>
                        </div>
                    </div>
                </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Parcel types customers choose from, each with its own delivery charge.'),
                    ])

                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle" data-hs-datatables-options='{
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">ID</th>
                                <th class="border-0">{{translate('Name')}}</th>
                                <th class="border-0">{{translate('messages.Module')}}</th>
                                <th class="border-0">{{translate('messages.Status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Orders count')}}</th>
                                <th class="border-0 text-center">{{translate('Additional charge')}}</th>
                                  @if ($categoryWiseTax)
                                <th  class="border-0 ">{{ translate('VAT/tax') }}</th>
                                @endif
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>
                        </thead>

                        <tbody id="table-div">
                        @foreach($parcel_categories as $key=>$category)
                            <tr>
                                <td>{{$key+$parcel_categories->firstItem()}}</td>
                                <td>{{$category->id}}</td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($category['name'], 20,'...')}}
                                    </span>
                                </td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($category->module->module_name, 15,'...')}}
                                    </span>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$category->id}}">
                                    <input type="checkbox" data-url="{{route('admin.parcel.category.status',[$category['id'],$category->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$category->id}}" {{$category->status?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="text-center">
                                        {{$category->orders_count}}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        {{ \App\CentralLogics\Helpers::format_currency($category->charge) }}
                                    </div>
                                </td>
                                      @if ($categoryWiseTax)
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                            <span> {{ $tax }} : <span class="font-bold">
                                                    ({{ $key }}%)
                                                </span> </span>
                                            <br>
                                        @empty
                                            <span> {{ translate('N/A') }} </span>
                                        @endforelse
                                    </span>
                                </td>
                                @endif
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('admin.parcel.category.edit',[$category['id']])}}" title="{{translate('Edit category')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                        data-id="category-{{$category['id']}}" data-message="{{ translate('Want to delete this category?') }}" title="{{translate('messages.Delete category')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.parcel.category.destroy',[$category['id']])}}" method="post" id="category-{{$category['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($parcel_categories) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $parcel_categories->links() !!}
            </div>
            @if(count($parcel_categories) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif
        </div>

    </div>

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {

            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        function readURL(input) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();

                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this);
        });

        $(".lang_link").click(function(e){
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.substring(0, form_id.length - 5);
            console.log(lang);
            $("#"+lang+"-form").removeClass('d-none');
            if(lang == '{{$defaultLang}}')
            {
                $(".from_part_2").removeClass('d-none');
            }
            else
            {
                $(".from_part_2").addClass('d-none');
            }
        });

        $('#reset_btn').click(function(){
            $('#module_id').val(null).trigger('change');
            $('#viewer').attr('src', "{{asset('public/assets/admin/img/900x400/img1.jpg')}}");
        })
    </script>
@endpush
