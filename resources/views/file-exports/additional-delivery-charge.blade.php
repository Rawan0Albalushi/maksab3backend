<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Additional delivery charge list') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }},
                        {{ translate('messages.Zone') }}: {{ $data['zone'] ?: translate('All') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    @foreach (($data['data']->first() ?? []) as $header => $ignore)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
