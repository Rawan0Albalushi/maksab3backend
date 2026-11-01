
<div class="row">
    <table>
        <thead>
            <tr>

                <th>
                    {{ translate('Disbursement list') }}
                </th>
                <th></th>
                <th></th>
                <th>
                    @if($data['type'] == 'store')
                        {{ translate($data['is_provider'] ? 'Provider' : 'Store') }} - {{ $data['store'] }}
                    @else
                        {{ translate('Deliveryman') }} - {{ $data['delivery_man'] }}
                    @endif
                </th>
                <th></th>
                <th>

                </th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>ID</th>
            <th>{{ translate('Created at') }}</th>
            <th>{{ translate('Amount') }}</th>
            <th>{{ translate('Payment method') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['disbursements'] as $key => $disb)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $disb['disbursement_id'] }}</td>
        <td>{{ \App\CentralLogics\Helpers::time_date_format($disb['created_at']) }}</td>
        <td>
            {{\App\CentralLogics\Helpers::format_currency($disb['disbursement_amount'])}}
        </td>
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
        <td>{{ $disb['status'] }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
</div>
