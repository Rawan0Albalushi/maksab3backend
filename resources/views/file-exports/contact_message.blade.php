<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Contact messages') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Message analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total')  }}: {{ $data->count() }}


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
            <th>{{ translate('Name') }}</th>
            <th>{{ translate('email') }}</th>
            <th>{{ translate('Subject') }}</th>
            <th>{{ translate('message') }}</th>
            <th>{{ translate('Reply') }}</th>
            <th>{{ translate('Seen') }}</th>
            <th>{{ translate('Created at') }} </th>
        </thead>
        <tbody>
        @foreach($data as $key => $message)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $message->name }}</td>
        <td>{{ $message->email }}</td>
        <td>{{ $message->subject }}</td>
        <td>{{ $message->message }}</td>
        <td>{{ $message->reply ?? translate('messages.N/A') }}</td>
        <td>{{ $message->seen == 0 ? translate('unseen') : translate('Seen') }}</td>
        <td>{{  \App\CentralLogics\Helpers::time_date_format($message->created_at)}}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
