<div class="row">
    <div class="col-lg-12 text-center ">
        <h1>{{ translate('Basic campaign') }} {{ $data['campaign']->title }} </h1>
    </div>
    <div class="col-lg-12">



        <table>
            <thead>
                <tr>
                    <th>{{ translate('Message analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Total vendors') }}: {{ $data['stores']->count() }}
                        <br>

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
                        {{ translate('Search bar content') }}: : {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th> </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th class="border-0">{{ translate('messages.SL') }}</th>
                    <th class="border-0 w--15">{{ translate('messages.Store') }}</th>
                    <th class="border-0 w--25">{{ translate('messages.owner') }}</th>
                    <th class="border-0">{{ translate('Contact information') }}</th>
                    <th class="border-0">{{ translate('Joining date') }}</th>
                    <th class="border-0">{{ translate('messages.Status') }}</th>

            </thead>
            <tbody>
                @foreach ($data['stores'] as $key => $store)
                    <tr>

                        <td>{{ $key + 1 }}</td>
                        <td>
                            <div class="store-items d-flex align-items-center gap-2">


                                <h6 class="fw-medium title-clr">

                                    {{ Str::limit($store->name, 30, '...') }}
                                </h6>
                            </div>
                        </td>
                        <td>
                            <span title=" {{ $store->vendor->f_name . ' ' . $store->vendor->l_name }}"
                                class="max-w--220px min-w-135px line--limit-1 font-size-sm title-clr">
                                {{ $store->vendor->f_name . ' ' . $store->vendor->l_name }}
                            </span>
                        </td>
                        <td title="{{ $store->email }}">
                            {{ $store->email }}
                            <br>
                            {{ $store['phone'] }}
                        </td>
                        <td>
                            <div class="title-clr">
                                {{ \App\CentralLogics\Helpers::date_format($store->pivot->created_at ?? $data['campaign']->created_at) }}
                            </div>
                        </td>
                        @php($status = $store->pivot ? $store->pivot->campaign_status : translate('No data found'))
                        <td class="text-capitalize">
                            @if ($status == 'pending')
                                <span class="badge badge-soft-info border-0">
                                    {{ translate('messages.Not approved') }}
                                </span>
                            @elseif($status == 'confirmed')
                                <span class="badge badge-soft-success border-0">
                                    {{ translate('messages.confirmed') }}
                                </span>
                            @elseif($status == 'rejected')
                                <span class="badge badge-soft-danger border-0">
                                    {{ translate('messages.rejected') }}
                                </span>
                            @else
                                <span class="badge badge-soft-info border-0">
                                    {{ translate(str_replace('_', ' ', $status)) }}
                                </span>
                            @endif

                        </td>


                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
