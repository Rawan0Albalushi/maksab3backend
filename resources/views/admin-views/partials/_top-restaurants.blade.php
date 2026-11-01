<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between">
        {{ translate('top selling stores') }}
    </h5>
    @php($params = session('dash_params'))
    @if ($params['zone_id'] != 'all')
    @else
    @endif
    <a href="{{ route('admin.store.list') }}" class="fz-12px font-medium text-006AE5">{{ translate('View all') }}</a>
</div>

<div class="card-body __top-resturant-card">

    @if (count($top_restaurants) > 0)
        <div class="__top-resturant">
            @foreach ($top_restaurants as $key => $item)
                <a href="{{ route('admin.store.view', $item->id) }}">
                    <div class="position-relative overflow-hidden">
                        <img class="onerror-image"
                            data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                            src="{{ $item['logo_full_url'] ?? asset('public/assets/admin/img/100x100/1.png') }}"
                            title="{{ $item?->name }}">
                        <h5 class="info m-0">
                            {{ translate('Order') }}:  {{ $item['order_count'] }}
                        </h5>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="empty--data d-flex flex-column align-items-center justify-content-center h-100 w-100">
            <img src="{{ asset('/public/assets/admin/img/no-store.png') }}" alt="public">
            <h5 class="secondary-clr">
                {{ translate('No stores available') }}
            </h5>
        </div>
    @endif

</div>
