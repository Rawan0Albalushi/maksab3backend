
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Category list')}}
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
            <th>{{ translate('Category ID') }}</th>
            <th>{{ translate('Main category') }}</th>
            <th>{{ translate('Subcategory') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $category)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $category->id }}</td>
        <td> {{$category->parent?$category->parent['name']:translate('messages.Category deleted')}}
            <td>{{ $category->name }}</td>


            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
