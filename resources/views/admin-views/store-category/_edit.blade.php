@php($store_label = \App\CentralLogics\Helpers::moduleStoreLabel())
@php($nameTranslations = $category->translations->where('key', 'name')->pluck('value', 'locale'))
<form action="{{ route('admin.store-category.update', [$category['id']]) }}" method="post" enctype="multipart/form-data"
      class="tps d-flex flex-column h-100"
      data-ajax-form
      data-ajax-refresh="[data-ajax-region]"
      data-ajax-close="#offcanvas__storeCategoryBtn">
    @csrf
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0 fs-16">{{ translate('Edit') . ' ' . $store_label . ' ' . translate('messages.Category') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                    aria-label="{{ translate('Close') }}">&times;
            </button>
        </div>

        <div class="custom-offcanvas-body p-20">
            <div class="d-flex align-items-center gap-2 mb-20">
                <div class="w-40px min-w-40px h-40px rounded overflow-hidden border">
                    <img src="{{ $category['image_full_url'] }}" alt="" class="w-100 h-100 object-cover onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.jpg') }}">
                </div>
                <div>
                    <h4 class="mb-0 fs-14 line--limit-2" title="{{ $category?->getRawOriginal('name') }}">
                        {{ $category?->getRawOriginal('name') }}
                    </h4>
                    <p class="mb-0 fs-12 text-muted">
                        #{{ $category['id'] }} &middot;
                        {{ $category->store?->name ?? translate('messages.Store deleted') }} &middot;
                        {{ translate('messages.Created') }}
                        {{ \App\CentralLogics\Helpers::date_format($category['created_at']) }}
                    </p>
                </div>
            </div>

            <div class="tps-card">
                <div class="tps-card__body">
                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('Category name') }}</p>

                        @if ($language)
                            <ul class="nav nav-tabs mb-3 border-0">
                                <li class="nav-item">
                                    <a class="nav-link text-nowrap lang_link1 active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link text-nowrap lang_link1" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="lang_form1" id="default-form1">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="default_name_edit">
                                        {{ translate('Name') }} ({{ translate('Default') }})
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    </label>
                                    <input type="text" name="name[]" id="default_name_edit" value="{{ $category?->getRawOriginal('name') }}"
                                           class="form-control" placeholder="{{ translate('messages.Ex') }}: Beverages" maxlength="191" required>
                                    <small class="tps-field__hint">
                                        {{ translate('This is the name customers see. Two or three words read best on a phone.') }}
                                    </small>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">

                            @foreach ($language as $lang)
                                <div class="d-none lang_form1" id="{{ $lang }}-form1">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="{{ $lang }}_name_edit">
                                            {{ translate('Name') }} ({{ strtoupper($lang) }})
                                            <span class="tps-opt">{{ translate('Optional') }}</span>
                                        </label>
                                        <input type="text" name="name[]" id="{{ $lang }}_name_edit"
                                               value="{{ $nameTranslations[$lang] ?? '' }}" class="form-control"
                                               placeholder="{{ translate('messages.Ex') }}: Beverages" maxlength="191">
                                        <small class="tps-field__hint">
                                            {{ translate('Leave it empty to fall back to the default name.') }}
                                        </small>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                            @endforeach
                        @else
                            <div class="tps-field">
                                <label class="tps-field__label" for="default_name_edit">
                                    {{ translate('Name') }}
                                    <span class="tps-req">*</span>
                                </label>
                                <input type="text" name="name[]" id="default_name_edit" class="form-control"
                                       placeholder="{{ translate('messages.Ex') }}: Beverages"
                                       value="{{ $category?->getRawOriginal('name') }}" maxlength="191" required>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                    </div>

                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('Placement') }}</p>

                        <div class="tps-field mb-3">
                            <label class="tps-field__label" for="store_id_edit">
                                {{ $store_label }}
                                <span class="tps-req">*</span>
                            </label>
                            <select required name="store_id" id="store_id_edit" class="form-control js-store-select2-ajax"
                                    data-placeholder="{{ translate('Select') . ' ' . $store_label }}">
                                @if ($category->store_id && $category->store)
                                    <option value="{{ $category->store->id }}" selected>{{ $category->store->name }}</option>
                                @endif
                            </select>
                            <small class="tps-field__hint">
                                {{ translate('messages.A category belongs to one store and groups what that store sells.') }}
                            </small>
                        </div>

                        <div class="tps-field">
                            <label class="tps-field__label" for="priority_edit">{{ translate('messages.Priority') }}</label>
                            <select required name="priority" id="priority_edit" class="custom-select">
                                <option {{ $category->priority == 0 ? 'selected' : '' }} value="0">{{ translate('messages.Normal') }}</option>
                                <option {{ $category->priority == 1 ? 'selected' : '' }} value="1">{{ translate('messages.medium') }}</option>
                                <option {{ $category->priority == 2 ? 'selected' : '' }} value="2">{{ translate('messages.High') }}</option>
                            </select>
                            <small class="tps-field__hint">
                                {{ translate('High priority categories are listed first in the app, then medium, then normal.') }}
                            </small>
                        </div>
                    </div>

                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('Artwork') }}</p>
                        <div class="bg-light rounded p-20 text-center">
                            <div class="mb-3">
                                <h6 class="tps-field__label justify-content-center mb-1">
                                    {{ $store_label . ' ' . translate('Category image') }}
                                    @if (empty($category['image_full_url']))
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    @endif
                                </h6>
                                <p class="mb-0 fs-12 text-muted">
                                    {{ translate('Square artwork, shown on the category tile.') }}
                                </p>
                            </div>
                            @include('admin-views.partials._image-uploader', [
                                'id' => 'store-category-image-input-' . $category['id'],
                                'name' => 'image',
                                'ratio' => '1:1',
                                'isRequired' => empty($category['image_full_url']),
                                'existingImage' => $category['image_full_url'] ?? '',
                                'imageExtension' => IMAGE_EXTENSION,
                                'imageFormat' => IMAGE_FORMAT,
                                'maxSize' => MAX_FILE_SIZE,
                                'textPosition' => 'bottom',
                                'show_clear_button' => false,
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
        <button type="button" class="btn w-100 btn--reset offcanvas-close h--40px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px"><i class="tio-save"></i> {{ translate('Update') }}</button>
    </div>
</form>
