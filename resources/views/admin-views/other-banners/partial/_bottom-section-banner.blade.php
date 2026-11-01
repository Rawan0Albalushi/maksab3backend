@php($bannerImage = $bottom_section_banner?->value ? \App\CentralLogics\Helpers::get_full_url('promotional_banner', $bottom_section_banner->value, $bottom_section_banner->storage[0]?->value ?? 'public') : null)

<div class="row g-3">
    <div class="col-xl-8">
        <form action="{{ route('admin.promotional-banner.store') }}" method="POST" enctype="multipart/form-data" class="tps-card">
            @csrf
            <input type="hidden" name="key" value="bottom_section_banner">

            <div class="tps-card__head">
                <span class="tps-card__brand">
                    <img src="{{ asset('public/assets/admin/img/other-banner.png') }}" alt="">
                </span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('Bottom Section Banner') }}</h2>
                    <p class="tps-card__subtitle">{{ translate('One wide artwork strip pinned under the store list on the module home screen.') }}</p>
                </div>
                <div class="tps-card__aside">
                    @if ($bottom_section_banner)
                        <div class="status-toggle" data-status="{{ $bottom_section_banner->status ? 1 : 0 }}">
                            <label class="toggle-switch toggle-switch-sm mb-0" for="status-{{ $bottom_section_banner->id }}">
                                <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                       id="status-{{ $bottom_section_banner->id }}"
                                       data-id="status-{{ $bottom_section_banner->id }}"
                                       data-type="status"
                                       data-image-on="{{ asset('public/assets/admin/img/modal') }}/promotional-on.png"
                                       data-image-off="{{ asset('public/assets/admin/img/modal') }}/promotional-off.png"
                                       data-title-on="{{ translate('By Turning ON Promotional Banner Section') }}"
                                       data-title-off="{{ translate('By Turning OFF Promotional Banner Section') }}"
                                       data-text-on="<p>{{ translate('Promotional banner will be enabled. You can see promotional activity') }}</p>"
                                       data-text-off="<p>{{ translate('Promotional banner will be disabled. You will be unable to see promotional activity.') }}</p>"
                                       aria-label="{{ translate('Bottom Section Banner') }}"
                                       {{ $bottom_section_banner->status ? 'checked' : '' }}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                            <span class="status-toggle__text" aria-live="polite">
                                {{ $bottom_section_banner->status ? translate('messages.Active') : translate('messages.Inactive') }}
                            </span>
                        </div>
                    @else
                        <span class="tps-pill tps-pill--warn">{{ translate('Not set') }}</span>
                    @endif
                </div>
            </div>

            <div class="tps-card__body">
                <div class="tps-group">
                    <h3 class="tps-group__label">{{ translate('Banner artwork') }}</h3>

                    <div class="pbn-drop" id="bottom_section_banner_uploader"
                         data-showing-saved-image="{{ $bannerImage ? 1 : 0 }}">
                        @include('admin-views.partials._image-uploader', [
                            'id' => 'bottom_section_banner_image',
                            'name' => 'image',
                            'ratio' => '5:1',
                            'boxRatio' => '5:1',
                            'isRequired' => false,
                            'existingImage' => $bannerImage,
                            'imageExtension' => IMAGE_EXTENSION,
                            'imageFormat' => IMAGE_FORMAT,
                            'maxSize' => MAX_FILE_SIZE,
                            'textPosition' => 'bottom',
                        ])
                    </div>
                </div>
            </div>

            <div class="tps-card__foot">
                <span class="tps-foot-note">{{ translate('Saving replaces the banner customers see right now.') }}</span>
                <button type="submit" class="btn btn--primary">
                    <i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}
                </button>
            </div>
        </form>
    </div>

    <div class="col-xl-4">
        <div class="pbn-aside">
            <div class="tps-card">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-devices-apple"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ translate('Preview') }}</h2>
                        <p class="tps-card__subtitle">{{ translate('Where the banner sits on the module home screen.') }}</p>
                    </div>
                </div>
                <div class="tps-card__body">
                    <div class="pbn-stage">
                        <div class="pbn-phone">
                            <div class="pbn-skel pbn-phone__search"></div>
                            <div class="pbn-phone__tiles">
                                <div class="pbn-skel pbn-phone__tile"></div>
                                <div class="pbn-skel pbn-phone__tile"></div>
                                <div class="pbn-skel pbn-phone__tile"></div>
                                <div class="pbn-skel pbn-phone__tile"></div>
                            </div>
                            <div class="pbn-phone__row">
                                <div class="pbn-skel pbn-phone__thumb"></div>
                                <div class="pbn-phone__lines">
                                    <div class="pbn-skel pbn-phone__line"></div>
                                    <div class="pbn-skel pbn-phone__line pbn-phone__line--short"></div>
                                </div>
                            </div>
                            <div class="pbn-phone__row">
                                <div class="pbn-skel pbn-phone__thumb"></div>
                                <div class="pbn-phone__lines">
                                    <div class="pbn-skel pbn-phone__line"></div>
                                    <div class="pbn-skel pbn-phone__line pbn-phone__line--short"></div>
                                </div>
                            </div>
                            <div class="pbn-phone__slot {{ $bannerImage ? 'has-image' : '' }}" id="bottom_section_banner_slot">
                                <img src="{{ $bannerImage }}" alt="">
                                <span class="pbn-phone__empty">{{ translate('Banner area') }}</span>
                            </div>
                        </div>
                        <p class="pbn-stage__caption">{{ translate('Module home screen') }}</p>
                    </div>
                </div>
            </div>

            <div class="tps-note tps-note--info mt-3">
                <i class="tio-info-outined"></i>
                <p>{{ translate('Keep the message in the middle of the artwork. Narrow phones crop the sides, so text near an edge is the first thing customers lose.') }}</p>
            </div>
        </div>
    </div>
</div>

<form id="bottom_section_banner_form" action="{{ route('admin.remove_image') }}" method="post">
    @csrf
    <input type="hidden" name="id" value="{{ $bottom_section_banner?->id }}">
    <input type="hidden" name="model_name" value="ModuleWiseBanner">
    <input type="hidden" name="image_path" value="promotional_banner">
    <input type="hidden" name="field_name" value="value">
</form>

@if ($bottom_section_banner)
    <form action="{{ route('admin.promotional-banner.update-status', [$bottom_section_banner->id, $bottom_section_banner->status ? 0 : 1]) }}"
          method="get" id="status-{{ $bottom_section_banner->id }}_form"></form>
@endif

@push('script_2')
    <script>
        (function () {
            var wrapper = document.getElementById('bottom_section_banner_uploader');
            if (!wrapper) return;

            var slot = document.getElementById('bottom_section_banner_slot');
            var slotImage = slot ? slot.querySelector('img') : null;
            var input = wrapper.querySelector('.single_file_input');

            function paintPreview(src) {
                if (!slotImage) return;
                slotImage.setAttribute('src', src || '');
                slot.classList.toggle('has-image', !!src);
            }

            input?.addEventListener('change', function () {
                if (!input.files || !input.files[0]) return;

                wrapper.setAttribute('data-showing-saved-image', '0');

                var reader = new FileReader();
                reader.onload = function (e) {
                    paintPreview(e.target.result);
                };
                reader.readAsDataURL(input.files[0]);
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('#bottom_section_banner_uploader .remove_btn');
                if (!btn) return;

                if (wrapper.getAttribute('data-showing-saved-image') !== '1') {
                    paintPreview('');
                    return;
                }

                e.preventDefault();
                e.stopImmediatePropagation();

                $('#toggle-status-title').empty().append(@json(translate('warning')));
                $('#toggle-status-message').empty().append(@json('<p>'.translate('Are you sure you want to remove this image?').'</p>'));
                $('#toggle-status-image').attr('src', '{{ asset('public/assets/admin/img/modal') }}/mail-warning.png');
                $('#toggle-status-ok-button').attr('toggle-ok-button', 'bottom_section_banner');
                $('#toggle-status-modal').modal('show');
            }, true);
        })();
    </script>
@endpush
