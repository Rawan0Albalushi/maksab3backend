@extends('layouts.vendor.app')

@section('title',translate('Item list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="btn--container align-items-center mb-0">
                <div class="mr-auto">
                    <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('Pending for approval products')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$items->total()}}</span></h1>
                    <p class="page-header-desc">{{ translate('Items you have submitted that are still waiting on the admin team.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2  border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Your item submissions waiting for admin approval.'),
                    ])

                    <form class="search-form">

                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search"  value="{{ request()?->search ?? null }}" name="search" class="form-control" placeholder="{{translate('messages.Ex search name')}}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>


            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                    data-hs-datatables-options='{
                        "columnDefs": [{
                            "targets": [],
                            "width": "5%",
                            "orderable": false
                        }],
                        "order": [],
                        "info": {
                        "totalQty": "#datatableWithPaginationInfoTotalQty"
                        },

                        "entries": "#datatableEntries",
                        "isResponsive": false,
                        "isShowPaging": false,
                            "paging":false
                    }'>
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0 w-20p">{{translate('Name')}}</th>
                            <th class="border-0 w-20p">{{translate('messages.Category')}}</th>
                            <th class="border-0 col--numeric">{{translate('messages.price')}}</th>
                            <th class="border-0">{{translate('messages.Submitted')}}</th>
                            <th class="border-0 ">{{translate('messages.Status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                        </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($items as $item)
                        @php($waiting_days = $item->created_at ? $item->created_at->diffInDays(now()) : 0)
                        <tr>
                            <td>
                                <a class="media align-items-center" href="{{route('vendor.item.requested_item_view',['id'=> $item['id']])}}">
                                    <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $item['image_full_url'] }}"
                                         data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0" title="{{ $item['name'] }}">{{Str::limit($item['name'],30,'...')}}</h5>
                                        <span class="d-block fs-12 text-muted">ID:{{$item['id']}}</span>
                                    </div>
                                </a>
                            </td>
                            <td>
                            {{Str::limit($item->category?$item->category->name:translate('messages.Category deleted'),20,'...')}}
                            </td>
                            <td class="col--numeric" data-order="{{ $item['price'] }}">
                                {{\App\CentralLogics\Helpers::format_currency($item['price'])}}
                            </td>
                            <td data-order="{{ $item->created_at }}">
                                <span class="table-when{{ !$item->is_rejected && $waiting_days >= 3 ? ' table-when--stale' : '' }}">
                                    <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($item->created_at)}}</span>
                                    <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($item->created_at)}}">
                                        {{ $item->created_at?->diffForHumans() }}
                                    </span>
                                </span>
                            </td>
                            <td>
                                    @if ($item->is_rejected == 1)
                                    <span class="badge badge-soft-danger text-capitalize">
                                        {{ translate('messages.rejected') }}
                                    </span>
                                    @if ($item->note)
                                        <span class="d-block fs-12 text-muted max-w-200px" title="{{ $item->note }}">
                                            {{ Str::limit($item->note, 40, '...') }}
                                        </span>
                                    @endif
                                    @else
                                    <span class="badge badge-soft-info text-capitalize">
                                        {{ translate('Pending') }}
                                    </span>
                                    @endif
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    @if ($item->is_rejected == 1)
                                    <a class="btn btn-sm action-btn action-btn--edit"
                                        href="{{route('vendor.item.edit',[$item['id'] , 'temp_product' => true])}}" title="{{translate('Edit item')}}"><i class="tio-edit"></i>
                                    </a>
                                    @endif
                                    <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                                        data-id="food-{{$item['id']}}" data-message="{{ translate('Want to delete this item?') }}" title="{{translate('messages.Delete item')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                </div>
                                <form action="{{route('vendor.item.delete',[$item['id']])}}"
                                        method="post" id="food-{{$item['id']}}">
                                    @csrf @method('delete')
                                    <input type="hidden" value="1" name="temp_product" >
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($items) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            <hr>
            <div class="page-area">
                <table>
                    <tfoot class="border-top">
                    {!! $items->links() !!}
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
          select: {
            style: 'multi',
            classMap: {
              checkAll: '#datatableCheckAll',
              counter: '#datatableCounter',
              counterInfo: '#datatableCounterInfo'
            }
          },
          language: {
            zeroRecords: '<div class="text-center p-4">' +
                '<img class="w-7rem mb-3" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">' +

                '</div>'
          }
        });

        $('#datatableSearch').on('mouseup', function (e) {
          let $input = $(this),
            oldValue = $input.val();

          if (oldValue == "") return;

          setTimeout(function(){
            let newValue = $input.val();

            if (newValue == ""){
              // Gotcha
              datatable.search('').draw();
            }
          }, 1);
        });

        $('#toggleColumn_index').change(function (e) {
          datatable.columns(0).visible(e.target.checked)
        })
        $('#toggleColumn_name').change(function (e) {
          datatable.columns(1).visible(e.target.checked)
        })

        $('#toggleColumn_type').change(function (e) {
          datatable.columns(2).visible(e.target.checked)
        })

        $('#toggleColumn_status').change(function (e) {
          datatable.columns(4).visible(e.target.checked)
        })
        $('#toggleColumn_price').change(function (e) {
          datatable.columns(3).visible(e.target.checked)
        })
        $('#toggleColumn_action').change(function (e) {
          datatable.columns(5).visible(e.target.checked)
        })


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        $('#category').select2({
            ajax: {
                url: '{{route("vendor.category.get-all")}}',
                data: function (params) {
                    return {
                        q: params.term, // search term
                        all:true,
                        page: params.page
                    };
                },
                processResults: function (data) {
                    return {
                    results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });
    </script>

@endpush
