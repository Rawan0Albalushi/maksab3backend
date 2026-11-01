@extends('layouts.vendor.app')

@section('title',translate('Deliveryman'))


@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/deliveryman.svg')}}" class="w--26" alt="">
                </span>
                <span>
                   {{translate('Deliveryman')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$delivery_men->total()}}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Everyone who delivers for your store, and whether they are on shift.') }}</p>
        </div>
        <div class="card">
            <div class="card-header justify-content-end">
                <form class="search-form" >
                    <div class="input-group input--group">
                        <input  type="search" name="search" class="form-control" value="{{request()?->search ?? ''}}"
                                placeholder="{{translate('messages.Ex search name')}}" aria-label="{{translate('messages.Ex search name')}}" >
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0 text-capitalize">#</th>
                        <th class="border-0 text-capitalize">{{translate('Name')}}</th>
                        <th class="border-0 text-capitalize">{{translate('messages.Availability status')}}</th>
                        <th class="border-0 text-capitalize">{{translate('Phone')}}</th>
                        <th class="border-0 text-capitalize text-center">{{translate('messages.Active orders')}}</th>
                        <th class="border-0 text-capitalize text-center">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($delivery_men as $key=>$dm)
                        <tr>
                            <td>{{$key+$delivery_men->firstItem()}}</td>
                            <td>
                                <a class="media align-items-center" href="{{route('vendor.delivery-man.preview',[$dm['id']])}}">
                                    <img class="avatar avatar-lg mr-3 onerror-image"
                                         data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                         src="{{ $dm['image_full_url']}}"
                                         alt="{{$dm['f_name']}} {{$dm['l_name']}}">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0">{{$dm['f_name'].' '.$dm['l_name']}}</h5>
                                        <span class="rating">
                                            <i class="tio-star"></i> {{count($dm->rating)>0?number_format($dm->rating[0]->average, 1, '.', ' '):0}}
                                        </span>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <div>
                                    {{translate('Currently assigned orders')}} : {{$dm->current_orders}}
                                </div>
                                <div>
                                    {{translate('Active status')}} :
                                    @if($dm->application_status == 'approved')
                                        @if($dm->active)
                                        <strong class="text-capitalize text-success">{{translate('messages.online')}}</strong>
                                        @else
                                        <strong class="text-capitalize text-danger">{{translate('messages.offline')}}</strong>
                                        @endif
                                    @elseif ($dm->application_status == 'denied')
                                        <strong class="text-capitalize text-danger">{{translate('Denied')}}</strong>
                                    @else
                                        <strong class="text-capitalize text-primary">{{translate('Pending')}}</strong>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <a class="deco-none" href="tel:{{$dm['phone']}}">{{$dm['phone']}}</a>
                            </td>
                            <td class="text-center">
                                {{ $dm->orders_count ?? 0 }}
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--edit" href="{{route('vendor.delivery-man.edit',[$dm['id']])}}" title="{{translate('Edit')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert"
                                       data-id="delivery-man-{{$dm['id']}}"
                                       data-message="{{translate('Want to remove this deliveryman?')}}"
                                       href="javascript:"  title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                </div>
                                <form action="{{route('vendor.delivery-man.delete',[$dm['id']])}}" method="post" id="delivery-man-{{$dm['id']}}">
                                    @csrf @method('delete')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($delivery_men) !== 0)
                <hr>
                @endif
                    @if(count($delivery_men) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                    @endif

            </div>
            <div class="page-area">
                <table>
                    <tfoot>
                    {!! $delivery_men->links() !!}
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/datatable-search.js')}}"></script>
@endpush
