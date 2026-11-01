<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Basic campaign list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Message analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total campaign')  }}: {{ $data->count() }}
                    <br>
                    {{ translate('Currently running')  }}: {{ $data->where('status',1)->count() }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: : {{ $search ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Campaign name') }}</th>
            <th>{{ translate('Description') }}</th>
            <th>{{ translate('Start date') }}</th>
            <th>{{ translate('End date') }}</th>
            <th>{{ translate('Daily start time') }}</th>
            <th>{{ translate('Daily end time') }}</th>
            <th>{{ config('module.current_module_type') === 'service' ? translate('Total provider joined') : translate('Total store joined') }} </th>
        </thead>
        <tbody>
        @foreach($data as $key => $campaign)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $campaign->title }}</td>
        <td>{{ $campaign->description }}</td>
        <td>{{ $campaign->start_date->format('d M Y') }}</td>
        <td>{{ $campaign->end_date->format('d M Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($campaign->start_time)->format("H:i A") }}</td>
        <td>{{ \Carbon\Carbon::parse($campaign->end_time)->format("H:i A") }}</td>
        <td>{{ $campaign->stores_count }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
