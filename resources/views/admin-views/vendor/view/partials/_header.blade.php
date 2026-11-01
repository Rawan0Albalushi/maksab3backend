    @php
        $verified_seller_badge = \App\CentralLogics\Helpers::get_business_settings('verified_seller_badge');
    @endphp
    <div class="page-header pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-20">
            <div>
                <h1 class="page-header-title text-break m-0">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/provider-details.svg') }}" class="w--26" alt="">
                    </span>
                    <span>{{ $store->name }}</span>
                </h1>
                <p class="page-header-desc">{{ translate('This store\'s orders, earnings, items and account state in one place.') }}</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-3">
                @if ($store->status == 1 && $store->vendor->status == 1 && $verified_seller_badge == 1)
                    @php
                        $verified_seller = $store->storeConfig?->verified_seller;
                    @endphp
                    <a href="javascript:"
                       class="btn fs-14 h-35px py-1 d-center {{ $verified_seller ? 'btn-secondary bg--EDEDED border-0 title-clr' : 'btn-primary' }}"
                       data-toggle="modal"
                       data-target="{{ $verified_seller ? '#remove-badge-btn' : '#badge-modal-btn' }}">
                        <i class="{{ $verified_seller ? 'tio-clear-circle-outlined' : 'tio-verified-outlined' }}"></i> {{ $verified_seller ? translate('Removed verified badge') : translate('Give verified badge') }}
                    </a>
                @endif
                <a href=" {{ !isset($store->vendor->status) || $store->vendor->status == 0 ? route('admin.store.edit', [$store->id, 'pending'=>1]) : route('admin.store.edit', [$store->id]) }}"
                    class="btn btn--primary border-0 h-35px py-1 d-center bg--soft-priamry-10 text-primary m-0 float-right">
                    <i class="tio-edit"></i> {{  $store->module_type == 'rental' ? translate('Edit provider') :translate('Edit store') }}
                </a>
                @if (!isset($store->vendor->status) || $store->vendor->status == 0)
                    @if (!isset($store->vendor->status))
                        <a class="btn btn--danger border-0  py-1 d-center h-35px bg--soft-danger-10 text-danger m-0 text-capitalize font-weight-bold float-right "
                            href="javascript:" data-toggle="modal" data-target="#confirmation-reason-btn"><i
                                class="tio-clear font-weight-bold pr-1"></i> {{ translate('messages.Reject') }}</a>
                    @endif
                    <a class="btn btn--primary border-0   py-1 d-center h-35px m-0 text-capitalize font-weight-bold float-right swal_fire_alert"
                        data-url="{{ route('admin.store.application', [$store['id'], 1]) }}"
                         data-title="{{translate('messages.Are you sure?')}}"
                                       data-image_url="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                       data-confirm_button_text="{{ translate('messages.Yes') }}"
                                       data-cancel_button_text="{{ translate('messages.No') }}"
                                       data-message="{{translate('messages.You want to approve the vendor joining request.')}}"
                        href="javascript:"><i
                            class="tio-done font-weight-bold pr-1"></i>{{ translate('Approve') }}</a>
                @endif
            </div>
        </div>
        @if ($store->vendor->status)
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <span class="hs-nav-scroller-arrow-prev d-none">
                    <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                        <i class="tio-chevron-left"></i>
                    </a>
                </span>

                <span class="hs-nav-scroller-arrow-next d-none">
                    <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                        <i class="tio-chevron-right"></i>
                    </a>
                </span>

                <ul class="nav nav-tabs page-header-tabs mb-2">
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == null ? 'active' : '' }}"
                            href="{{ route('admin.store.view', $store->id) }}">{{ translate('Overview') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'order' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'order']) }}"
                            aria-disabled="true">{{ translate('messages.Orders') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'item' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'item']) }}"
                            aria-disabled="true">{{ translate('messages.Items') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'reviews' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'reviews']) }}"
                            aria-disabled="true">{{ translate('messages.Reviews') }}</a>
                    </li>
                    @if(addon_published_status('ReelsModule') && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType($store->module_type))
                        <li class="nav-item">
                            <a class="nav-link {{ request('tab') == 'reels' ? 'active' : '' }}"
                                href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'reels']) }}"
                                aria-disabled="true">{{ translate('messages.Reels') }}</a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'discount' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'discount']) }}"
                            aria-disabled="true">{{ translate('messages.Discounts') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'transaction' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'transaction']) }}"
                            aria-disabled="true">{{ translate('Transactions') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'settings' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'settings']) }}"
                            aria-disabled="true">{{ translate('Settings') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'conversations' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'conversations']) }}"
                            aria-disabled="true">{{ translate('Conversations') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('tab') == 'meta-data' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'meta-data']) }}"
                            aria-disabled="true">{{ translate('Meta data') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link  {{ request('tab') == 'disbursements' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'disbursements']) }}"
                            aria-disabled="true">{{ translate('messages.disbursements') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link  {{ request('tab') == 'business_plan' ? 'active' : '' }}"
                            href="{{ route('admin.store.view', ['store' => $store->id, 'tab' => 'business_plan']) }}"
                            aria-disabled="true">{{ translate('Business plan') }}</a>
                    </li>
                </ul>
            </div>
        @endif
    </div>

    <div class="modal shedule-modal fade" id="badge-modal-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{asset('public/assets/admin/img/badge-big.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Give Verified Badge to this vendor?') }}</h3>
                        <p class="mb-0">{{ translate('This will mark the vendor as verified, and a trusted badge will be displayed on the vendor details for customers') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.store.verified-seller', [$store->id]) }}" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal shedule-modal fade" id="remove-badge-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{asset('public/assets/admin/img/remove-badge.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Want to remove the verified badge from this vendor?') }}</h3>
                        <p class="mb-0">{{ translate('The vendor will lose its verified status, but you can reassign the badge at any time') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.store.verified-seller', [$store->id]) }}" class="btn min-w-120px btn--danger"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>


    <div class="modal shedule-modal fade" id="confirmation-reason-btn" tabindex="-1"
        aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <form action="{{ route('admin.store.application', [$store['id'], 0]) }}" method="get">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{ asset('public/assets/admin/img/delete-confirmation.png') }}" alt="icon"
                            class="mb-3">
                        <h3 class="mb-2">{{ translate('messages.Are you sure?') }}</h3>
                        <p class="mb-0">{{ translate('You want to deny this joining application?') }}</p>
                    </div>
                    <div class="px-3 mt-4">
                        <h5 class="mb-2">{{ translate('messages.Reason') }}</h5>
                        <textarea name="rejection_note" id="" class="form-control" rows="2" required
                            placeholder="{{ translate('Enter the reason for denial') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No') }}</button>
                    <button type="submit" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</button>
                </div>
            </form>
            </div>
        </div>
    </div>
