<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Store withdraw transactions') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th></th>
                <th> 
                    {{ translate('Request status')  }}- {{  $data['request_status']?translate($data['request_status']):translate('All') }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Request time') }}</th>
                <th>{{ translate('Requested amount') }}</th>
                <th>{{ translate('Store name') }}</th>
                <th>{{ translate('messages.Owner name') }}</th>
                <th>{{ translate('Phone') }}</th>
                <th>{{ translate('messages.email') }}</th>
                <th>{{ translate('messages.request_status') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['withdraw_requests'] as $key => $wr)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{date('Y-m-d '.config('timeformat'),strtotime($wr->created_at))}}</td>
                <td>{{$wr['amount']}}</td>
                <td>
                    @if($wr->vendor)
                    {{ $wr->vendor->stores[0]->name }}
                    @else
                    {{translate('messages.Store deleted') }}
                    @endif
                </td>
                <td>{{$wr->vendor->f_name}} {{$wr->vendor->l_name}}</td>
                <td>{{$wr->vendor->phone}}</td>
                <td>{{$wr->vendor->email}}</td>
                <td>
                    @if($wr->approved==0)
                        {{ translate('Pending') }}
                    @elseif($wr->approved==1)
                        {{ translate('Approved') }}
                    @else
                        {{ translate('Denied') }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
