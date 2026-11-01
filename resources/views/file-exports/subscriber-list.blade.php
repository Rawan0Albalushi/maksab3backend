<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Subscriber list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('email') }}</th>
            <th>{{ translate('Subscribed at') }}</th>
        </thead>
        <tbody>
        @foreach($data['customers'] as $key => $customer)
            <tr>
        <td>{{ $key+1}}</td>
        <td>{{ $customer['email'] }}</td>
        <td>{{date('Y-m-d '.config('timeformat'),strtotime($customer->created_at))}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
