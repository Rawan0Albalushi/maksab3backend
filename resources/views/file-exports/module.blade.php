
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Module list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }},
                    {{ translate('Type')  }}: {{ isset($data['module_type']) ? translate($data['module_type']) : translate('All') }},
                    {{ translate('messages.Status')  }}: {{ isset($data['status']) ? ($data['status'] == 1 ? translate('messages.Active') : translate('messages.Inactive')) : translate('All') }}
                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Name') }}</th>
            <th>{{ translate('Module ID') }}</th>
            <th>{{ translate('Type') }}</th>
            <th>{{ translate('Total stores') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $addon)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $addon->module_name }}</td>
        <td>{{ $addon->id }}</td>
        <td>
            {{ translate($addon->module_type) }}
        </td>
        <td>
            {{ $addon->stores_count }}
        </td>

        <td>{{ $addon?->status == 1 ? translate('Active') : translate('Inactive') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
