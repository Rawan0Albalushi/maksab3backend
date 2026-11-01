<div class="border rounded-10 p-3 p-xxl-20">
    <div class="row g-lg-5 gx-3 gy-3 earnings-breakdown">
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/order-commission.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Commission paid') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['admin_commission'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['admin_commission_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 purple rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/subscription.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Subscription fee') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['subscription_fee'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['subscription_fee_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item text-sm-left">
                 <div class="flex-shrink-0 danger rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/4.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Discount on item') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['discount_on_item'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['discount_on_item_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Coupon contribution') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['coupon_contribution'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['coupon_contribution_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/order-commission.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Free delivery') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['free_delivery'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['free_delivery_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('BOGO discount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['bogo_discount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['bogo_discount_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                 <div class="flex-shrink-0 purple rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/other-income.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Happy hour discount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['happy_hour_discount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['happy_hour_discount_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item text-sm-left">
                 <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{asset('public/assets/admin/img/report/earning-breakdown/additional-fees.svg')}}" alt="earning">
                </div>
                <div class="mb-2">{{ translate('Bundle discount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($summary['breakdown']['bundle_discount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $summary['breakdown']['bundle_discount_percentage'] ?? 0 }}% {{ translate('Of total') }}
                </div>
            </div>
        </div>
    </div>
</div>
