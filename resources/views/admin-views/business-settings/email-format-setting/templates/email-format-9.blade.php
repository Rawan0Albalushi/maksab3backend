<table class="main-table">
    <tbody>
        <tr>
            <td class="main-table-td">
                <h2 class="mb-3" id="mail-title">{{ $data['title']?? translate('Main title or subject of the mail') }}</h2>
                <div class="mb-1" id="mail-body">{!! $data['body']?? translate('Hi sabrina,') !!}</div>
                <table class="bg-section p-10 w-100">
                    <tbody>
                        <tr>
                            <td class="p-10">
                                <span class="d-block text-center">
                                    @php($restaurant_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
                                    <img class="mb-2 mail-img-2 onerror-image" data-onerror-image="{{ asset('/public/assets/admin/img/blank3.png') }}"

                                    src="{{ $data?->logo ? $data->logo_full_url : \App\CentralLogics\Helpers::get_full_url('business',$restaurant_logo?->value,$restaurant_logo?->storage[0]?->value ?? 'public', 'favicon') }}"

                                    id="logoViewer" alt="">
                                    <h3 class="mb-3 mt-0">{{ translate('Order information') }}</h3>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table class="order-table w-100">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <h3 class="subtitle">{{ translate('Order summary') }}</h3>
                                                <span class="d-block">{{ translate('Order') }}#_48573</span>
                                                <span class="d-block">23 Jul, 2023 4:30 am</span>
                                            </td>
                                            <td class="email-template-09-max-width">
                                                <h3 class="subtitle">{{ translate('Delivery address') }}</h3>
                                                <span class="d-block">{{ translate('Munam Shahariar') }}</span>
                                                <span class="d-block" >4517 Washington Ave. Manchester, Kentucky 39495</span>
                                            </td>
                                        </tr>
                                        <td colspan="2">
                                            <table class="w-100">
                                                <thead class="bg-section-2">
                                                    <tr>
                                                        <th class="text-left p-1 px-3">{{ translate('Product') }}</th>
                                                        <th class="text-right p-1 px-3">{{ translate('price') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="text-left p-2 px-3">
                                                            1. The school of life - emotional baggage tote bag - canvas tote bag (navy) x 1
                                                        </td>
                                                        <td class="text-right p-2 px-3">
                                                            <h4>
                                                                $5,465
                                                            </h4>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-left p-2 px-3">
                                                            2. 3USB Head Phone x 1
                                                        </td>
                                                        <td class="text-right p-2 px-3">
                                                            <h4>
                                                                $354
                                                            </h4>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2">
                                                            <hr class="mt-0">
                                                            <table class="w-100">
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Item price') }}</td>
                                                                    <td class="text-right p-1 px-3">$85</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Addon') }}</td>
                                                                    <td class="text-right p-1 px-3">$85</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Sub Total') }}</td>
                                                                    <td class="text-right p-1 px-3">$90</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Discount') }}</td>
                                                                    <td class="text-right p-1 px-3">$10</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Coupon discount') }}</td>
                                                                    <td class="text-right p-1 px-3">$00</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('VAT/tax') }}</td>
                                                                    <td class="text-right p-1 px-3">$15</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">{{ translate('Delivery charge') }}</td>
                                                                    <td class="text-right p-1 px-3">$20</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="email-template-table-td-width"></td>
                                                                    <td class="p-1 px-3">
                                                                        <h4>{{ translate('Total') }}</h4>
                                                                    </td>
                                                                    <td class="text-right p-1 px-3">
                                                                        <span class="text-base">$105</span>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <div class="mb-2" id="mail-footer">
                    {{ $data['footer_text'] ?? translate('Please contact us for any queries; we\'re always happy to help.') }}
                </div>
                <div>
                    {{ translate('Thanks & regards') }},
                </div>
                <div class="mb-4">
                    {{ \App\CentralLogics\Helpers::get_business_settings('business_name', false) }}
                </div>
            </td>
        </tr>
        <tr>
            <td>
            <span class="privacy">
                <a href="#" id="privacy-check" style="{{ (isset($data['privacy']) && $data['privacy'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Privacy policy')}}</a>
                <a href="#" id="refund-check" style="{{ (isset($data['refund']) && $data['refund'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Refund policy') }}</a>
                <a href="#" id="cancelation-check" style="{{ (isset($data['cancelation']) && $data['cancelation'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Cancellation policy') }}</a>
                <a href="#" id="contact-check" style="{{ (isset($data['contact']) && $data['contact'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Contact us') }}</a>
            </span>
                <span class="social email-template-social-span">
                    <a href="" id="facebook-check"  class="email-template-social-media"  style="{{ (isset($data['facebook']) && $data['facebook'] == 1)?'':'display:none;' }}">
                        <img src="{{asset('/public/assets/admin/img/img/facebook.png')}}" alt="">
                    </a>
                    <a href="" id="instagram-check"  class="email-template-social-media"  style="{{ (isset($data['instagram']) && $data['instagram'] == 1)?'':'display:none;' }}">
                        <img src="{{asset('/public/assets/admin/img/img/instagram.png')}}" alt="">
                    </a>
                    <a href="" id="twitter-check"  class="email-template-social-media"  style="{{ (isset($data['twitter']) && $data['twitter'] == 1)?'':'display:none;' }}">
                        <img src="{{asset('/public/assets/admin/img/img/twitter.png')}}" alt="">
                    </a>
                    <a href="" id="linkedin-check"  class="email-template-social-media"  style="{{ (isset($data['linkedin']) && $data['linkedin'] == 1)?'':'display:none;' }}">
                        <img src="{{asset('/public/assets/admin/img/img/linkedin.png')}}" alt="">
                    </a>
                    <a href="" id="pinterest-check"  class="email-template-social-media"  style="{{ (isset($data['pinterest']) && $data['pinterest'] == 1)?'':'display:none;' }}">
                        <img src="{{asset('/public/assets/admin/img/img/pinterest.png')}}" alt="">
                    </a>
                </span>
                <span class="copyright" id="mail-copyright">
                    {{ $data['copyright_text']?? \App\CentralLogics\Helpers::copyright_text() }}
                </span>
            </td>
        </tr>
    </tbody>
</table>
