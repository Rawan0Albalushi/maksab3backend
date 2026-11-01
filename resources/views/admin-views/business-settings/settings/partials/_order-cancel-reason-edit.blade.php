<form action="{{ route('admin.business-settings.order-cancel-reasons.update') }}" method="post" class="d-flex flex-column h-100">
    @csrf
    @method('put')
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0">{{ translate('Order cancellation reason') }} {{ translate('Update') }}</h3>
            <button type="button"
                class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="Close">&times;</button>
        </div>
        <div class="custom-offcanvas-body p-20">
            <div class="bg--secondary rounded p-20 mb-20">

                @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)

                @if ($language)
                    <ul class="nav nav-tabs mb-4 border-0">
                        <li class="nav-item">
                            <a class="nav-link lang_link1 active" href="#"
                                id="default-link">{{ translate('Default') }}</a>
                        </li>
                        @foreach (json_decode($language) as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link1" href="#"
                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <input type="hidden" name="reason_id" value="{{ $reason->id }}" />

                <div class="row">
                    <div class="col-12">
                        <div class="form-group lang_form1" id="default-form1">
                            <label class="input-label" for="reason">{{ translate('Order cancellation reason') }}
                                ({{ translate('Default') }}) </label>
                            <input id="reason" class="form-control" name='reason[]'
                                value="{{ $reason?->getRawOriginal('reason') }}" type="text">
                        </div>
                        <input type="hidden" name="lang[]" value="default">

                        @if ($language)
                            @forelse(json_decode($language) as $lang)
                                <?php
                                if ($reason?->translations) {
                                    $translate = [];
                                    foreach ($reason?->translations as $t) {
                                        if ($t->locale == $lang && $t->key == 'reason') {
                                            $translate[$lang]['reason'] = $t;
                                        }
                                    }
                                }
                                ?>
                                <div class="form-group d-none lang_form1" id="{{ $lang }}-form1">
                                    <label class="input-label" for="reason{{ $lang }}">{{ translate('Order cancellation reason') }}
                                        ({{ strtoupper($lang) }})</label>
                                    <input id="reason{{ $lang }}" class="form-control" name='reason[]'
                                        placeholder="{{ translate('Ex') . ': ' . translate('Item is broken') }}"
                                        value="{{ $translate[$lang]['reason']['value'] ?? null }}" type="text">
                                </div>
                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                            @empty
                            @endforelse
                        @endif

                        <div class="form-group">
                            <label class="input-label" for="user_type">{{ translate('User type') }}</label>
                            <select name="user_type" id="user_type" class="form-control h--45px" required>
                                <option value="">{{ translate('messages.Select user type') }}</option>
                                <option {{ $reason->user_type == 'admin' ? 'selected' : '' }} value="admin">
                                    {{ translate('messages.admin') }}</option>
                                <option {{ $reason->user_type == 'store' ? 'selected' : '' }} value="store">
                                    {{ translate('messages.Store') }}</option>
                                <option {{ $reason->user_type == 'customer' ? 'selected' : '' }} value="customer">
                                    {{ translate('messages.Customer') }}</option>
                                <option {{ $reason->user_type == 'deliveryman' ? 'selected' : '' }} value="deliveryman">
                                    {{ translate('Deliveryman') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
        <button type="button" class="btn w-100 btn--secondary offcanvas-close h--40px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px"><i class="tio-save"></i> {{ translate('Update') }}</button>
    </div>
</form>