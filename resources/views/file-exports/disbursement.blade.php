
<div class="row">
    @php($address = \App\CentralLogics\Helpers::get_business_settings('address', false))
    <table>
        <thead>
            <tr>

                <th>
                    {{ translate('Disbursement invoice') }}
                </th>
                <th></th>
                <th></th>
                <th>

                </th>
            </tr>
            <tr>

                <th>
                    {{ \App\CentralLogics\Helpers::time_date_format(date("Y-m-d h:i:s",time())) }}
                </th>
                <th></th>
                <th></th>
                <th>
                    {{ $address  }}
                </th>
            </tr>
            <tr>
                <th>
                    {{ translate('Disbursement ID')  }}:{{ $data['disbursement']['id']}}
                    <br>

                </th>
                <th></th>
                <th>
                    {{ translate('Created at')  }}
                    <br>
                    {{ \App\CentralLogics\Helpers::time_date_format($data['disbursement']['created_at']) }}
                </th>
                <th>
                    {{ translate('Total amount')  }}
                    <br>
                    {{\App\CentralLogics\Helpers::format_currency($data['disbursement']['total_amount'])}}

                </th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            @if($data['type'] == 'store')

            <th>{{ translate('Store information') }}</th>
            @else
            <th>{{ translate('Deliveryman information') }}</th>
            @endif
            <th>{{ translate('Payment method') }}</th>
            <th>{{ translate('Amount') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['disbursements'] as $key => $disb)
            <tr>
        <td>{{ $loop->index+1}}</td>
        @if($data['type'] == 'store')

        <td>{{ $disb->store->name }}</td>
        @else
            <th>{{$disb->delivery_man->f_name.' '.$disb->delivery_man->l_name}}</th>
        @endif
        <td>
            <div class="name">{{translate('Payment method')}} : {{ $disb->withdraw_method?->method_name ?? translate('messages.Payment method removed') }}</div>
            @forelse((is_array($disb->withdraw_method?->method_fields) ? $disb->withdraw_method->method_fields : (json_decode($disb->withdraw_method?->method_fields ?? '', true) ?: [])) as $key=> $item)
            <br>
                <div>
                    <span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span>:</span>
                    <span class="name">{{$item}}</span>
                </div>

            @empty

            @endforelse
        </td>
        <td>
            {{\App\CentralLogics\Helpers::format_currency($disb['disbursement_amount'])}}
        </td>
        <td>
            @if($disb->status=='pending')
            <label class="badge badge-soft-primary">{{ translate('Pending') }}</label>
        @elseif($disb->status=='completed')
            <label class="badge badge-soft-success">{{ translate('Completed') }}</label>
        @else
            <label class="badge badge-soft-danger">{{ translate('Canceled') }}</label>
        @endif
        </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
