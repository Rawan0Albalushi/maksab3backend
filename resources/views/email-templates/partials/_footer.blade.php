@php($landing_data = \App\CentralLogics\Helpers::landing_policy_statuses())
<span class="privacy">
    <a href="{{ route('privacy-policy') }}" id="privacy-check" style="{{ (isset($data['privacy']) && $data['privacy'] == 1)?'':'display:none;' }}">{{ translate('Privacy policy')}}</a>
    @if (isset($landing_data['refund_policy_status']) && $landing_data['refund_policy_status'] == 1)
    <a href="{{ route('refund') }}" id="refund-check" style="{{ (isset($data['refund']) && $data['refund'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Refund policy') }}</a>
    @endif
    @if (isset($landing_data['cancellation_policy_status']) && $landing_data['cancellation_policy_status'] == 1)
    <a href="{{ route('cancelation') }}" id="cancelation-check" style="{{ (isset($data['cancelation']) && $data['cancelation'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Cancellation policy') }}</a>
    @endif
    <a href="{{ route('contact-us') }}" id="contact-check" style="{{ (isset($data['contact']) && $data['contact'] == 1)?'':'display:none;' }}"><span class="dot"></span>{{ translate('Contact us') }}</a>
</span>
<span class="social" style="text-align:center">
    @foreach (\App\CentralLogics\Helpers::social_media_active() as $social)
        <a href="{{ $social->link }}" target=”_blank” id="{{ $social->name }}-check" style="margin: 0 5px;text-decoration:none;{{ (isset($data[$social->name]) && $data[$social->name] == 1)?'':'display:none;' }}">
            <img src="{{asset('/public/assets/admin/img/img/')}}/{{ $social->name }}.png" alt="">
        </a>
    @endforeach
</span>
<span class="copyright" id="mail-copyright">
    {{ $copyright_text ?? \App\CentralLogics\Helpers::copyright_text() }}
</span>
