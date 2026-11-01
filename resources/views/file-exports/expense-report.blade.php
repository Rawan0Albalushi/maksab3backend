<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Expense reports') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    @if(isset($data['module']))
                    {{ translate('Module' )}} - {{ $data['module']?translate($data['module']):translate('All') }}
                    <br>
                    @endif

                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ (isset($data['module_type']) && $data['module_type'] == 'rental')?translate('Provider'):translate('vendor')}} - {{ $data['store']??translate('All') }}
                    @if (!isset($data['type']) )
                    <br>
                    {{ translate('Customer' )}} - {{ $data['customer']??translate('All') }}
                    @endif
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            @if (isset($data['module_type']))
            <th>{{$data['module_type'] == 'rental'? translate('Trip ID') : ($data['module_type'] == 'service'? translate('Booking ID') : translate('messages.Order ID')) }}</th>
            @elseif(addon_published_status('Rental'))
                <th>{{ translate('messages.Order ID') }}</th>
                <th>{{ translate('Trip ID') }}</th>
            @endif
            <th>{{translate('Date & time')}}</th>
            <th>{{ translate('Expense type') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{translate('Expense amount')}}</th>
        </thead>
        <tbody>
        @foreach($data['expenses'] as $key => $exp)
            <tr>
                <td>{{ $key+1}}</td>
                @if (isset($data['module_type']))
                    <td>
                        @if ($data['module_type'] == 'service')
                            {{ $exp['service_booking_id'] }}
                        @elseif ($exp->order && $data['module_type'] != 'rental')
                            {{ $exp['order_id'] }}
                        @elseif ($exp->trip && $data['module_type'] == 'rental')
                            {{ $exp['trip_id'] }}
                        @endif
                    </td>
                @elseif(addon_published_status('Rental'))
                    <td>{{ $exp['order_id'] }}</td>
                    <td>{{ $exp['trip_id'] }}</td>
                @endif
                <td>
                    {{date('Y-m-d '.config('timeformat'),strtotime($exp->created_at))}}
                </td>
                <td>{{translate("messages.{$exp['type']}")}}</td>
                <td class="text-center">
                    @if ($exp->order)

                    @if($exp->order?->is_guest)
                    @php($customer_details = json_decode($exp->order['delivery_address'],true))
                    <strong>{{$customer_details['contact_person_name']}}</strong>

                    @elseif($exp->order?->customer)

                    {{$exp->order?->customer['f_name'].' '.$exp->order?->customer['l_name']}}
                    @else
                        <label
                            class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                    @endif

                    @elseif($exp->trip)
                    @if ($exp?->trip?->customer)

                        {{ $exp?->trip?->customer?->fullName }}

                        @elseif($exp?->trip?->user_info['contact_person_name'])
                            <div class="font-medium">
                                {{$exp?->trip?->user_info['contact_person_name'] }}
                            </div>
                        @else
                            {{ translate('messages.Guest user') }}
                        @endif


                    @elseif ($exp->serviceBooking)
                    @if($exp->serviceBooking?->is_guest)
                        <strong>{{ $exp->serviceBooking['user_info']['contact_person_name'] ?? translate('messages.Guest user') }}</strong>
                    @elseif($exp->serviceBooking?->customer)
                        {{ $exp->serviceBooking?->customer['f_name'].' '.$exp->serviceBooking?->customer['l_name'] }}
                    @else
                        <label class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                    @endif

                    @elseif ($exp['type'] == 'add_fund_bonus')
                    {{ $exp->user->f_name.' '.$exp->user->l_name }}
                    @else
                    <label class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>

                    @endif
                </td>
                <td>{{\App\CentralLogics\Helpers::format_currency($exp['amount'])}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
