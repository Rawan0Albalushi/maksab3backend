<!DOCTYPE html>
<?php
    $lang = \App\CentralLogics\Helpers::system_default_language();
    $site_direction = \App\CentralLogics\Helpers::system_default_direction();
?>
<html lang="{{ $lang }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Email template') }}</title>

    @include('email-templates.partials._style-base')

</head>


<body style="background-color: #e9ecef;padding:15px">
    <table dir="{{ $site_direction }}" class="main-table">
        <tbody>
            <tr>
                <td class="main-table-td">
                    <img class="mail-img-1"
                    src="{{ $data['logo_full_url'] ?? asset('/public/assets/admin/img/blank1.png') }}"

                    id="logoViewer" alt="">
                    <h2 id="mail-title" class="mt-2">{{ $title?? translate('Main title or subject of the mail') }}</h2>
                    <div class="mb-1" id="mail-body">{!! $body?? translate('Hi sabrina,') !!}</div>
                    <img class="mb-2 mail-img-3" id="bannerViewer"
                    src="{{ $data['image_full_url'] ?? asset('/public/assets/admin/img/blank2.png') }}"
                    alt="">
                    <hr>
                    <div class="mb-2" id="mail-footer">
                        {{ $footer_text ?? translate('Please contact us for any queries; we\'re always happy to help.') }}
                    </div>
                    <div>
                        {{ translate('Thanks & regards') }},
                    </div>
                    <div class="mb-4">
                        {{ $company_name }}
                    </div>
                </td>


                @if ($data?->button_url)
                    <span class="d-block text-center" style="margin-top: 16px">
                                        <a type="button" href="{{ $data['button_url']??'#' }}" class="cmn-btn" id="mail-button">{{ $data['button_name']??'Submit' }}</a>

                    </span>
                    @endif
            </tr>

            <tr>
                <td>
                    @include('email-templates.partials._footer')
                </td>
            </tr>
        </tbody>
    </table>


</body>
</html>
