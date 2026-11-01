@if($customer)
    <div class="card-body">
        <div class="media gap-3 flex-wrap">
            @include('partials._user-avatar', [
                'imageUrl'  => $customer->image_full_url,
                'proStatus' => $customer->pro_status ?? false,
                'size'      => 70,
            ])
            <div class="media-body">
                <div class="key-value-list d-flex flex-column gap-2 text-dark" style="--min-width: 60px">
                    <div class="key-val-list-item d-flex gap-3">
                        <div>{{ translate('Name') }}</div>:
                        <div class="font-semibold">{{$customer['f_name']? $customer['f_name'].' '.$customer['l_name'] : translate('messages.Incomplete Profile')}}</div>
                    </div>
                    <div class="key-val-list-item d-flex gap-3">
                        <div>{{ translate('Contact') }}</div>:
                        <a href="tel:{{ $customer['phone'] }}" class="text-dark font-semibold">{{$customer['phone'] ?? translate('messages.N/A')}}</a>
                    </div>
                    <div class="key-val-list-item d-flex gap-3">
                        <div>{{ translate('email') }}</div>:
                        <a href="mailto:{{ $customer['email'] }}" class="text-dark font-semibold">{{$customer['email'] ?? translate('messages.N/A')}}</a>
                    </div>
                    @foreach($customer->addresses as $address)
                        <div class="key-val-list-item d-flex gap-3">
                            <div>{{ translate('Address') }}</div>:
                            <a href="https://www.google.com/maps/search/?api=1&query={{ data_get($address,'latitude',0)}},{{ data_get($address,'longitude',0)}}" target="_blank">{{ $address['address'] }}</a>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>



    </div>
@endif