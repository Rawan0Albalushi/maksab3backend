
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Attributes list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }}

                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Unit') }}</th>
            <th>ID</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $attribute)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $attribute->unit }}</td>
        <td>{{ $attribute->id }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
