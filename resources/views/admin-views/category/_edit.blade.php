@php($isMain = $category->position == 0)
@php($nameTranslations = $category->translations->where('key', 'name')->pluck('value', 'locale'))
<form action="{{ route('admin.category.update', [$category['id']]) }}" method="post" enctype="multipart/form-data"
      class="tps d-flex flex-column h-100"
      data-ajax-form
      data-ajax-refresh="[data-ajax-region]"
      data-ajax-close="#offcanvas__categoryBtn">
    @method('post')
    @csrf
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0 fs-16">
                {{ $isMain ? translate('Edit Main Category') : translate('Edit Main Sub Category') }}
            </h3>
            <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                    aria-label="{{ translate('Close') }}">&times;
            </button>
        </div>

        <div class="custom-offcanvas-body p-20">
            {{-- Which record is open. The list shows a truncated name, so the full one, the id and
                 the date it was created are the three things worth confirming before editing. --}}
            <div class="d-flex align-items-center gap-2 mb-20">
                @if ($isMain)
                    <div class="w-40px min-w-40px h-40px rounded overflow-hidden border">
                        <img src="{{ $category['image_full_url'] }}" alt="" class="w-100 h-100 object-cover">
                    </div>
                @endif
                <div>
                    <h4 class="mb-0 fs-14 line--limit-2" title="{{ $category?->getRawOriginal('name') }}">
                        {{ $category?->getRawOriginal('name') }}
                    </h4>
                    <p class="mb-0 fs-12 text-muted">
                        #{{ $category['id'] }} &middot;
                        {{ translate('messages.Created') }}
                        {{ \App\CentralLogics\Helpers::date_format($category['created_at']) }}
                    </p>
                </div>
            </div>

            {{-- Availability is the one control an admin comes here for most often, so it sits
                 above the fold as its own row rather than at the bottom of the name card. --}}
            <div class="tps-switchbar mb-20">
                <div class="tps-switchbar__text">
                    <h6>{{ translate('Availability') }}</h6>
                    <p>
                        {{ $isMain
                            ? translate('Turn this off and the main category, its subcategories and their items disappear from the app.')
                            : translate('Turn this off and the subcategory and its items disappear from the app.') }}
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="tps-pill {{ $category['status'] ? 'tps-pill--on' : 'tps-pill--off' }}">
                        {{ $category['status'] ? translate('messages.Active') : translate('messages.Inactive') }}
                    </span>
                    <label class="toggle-switch toggle-switch-sm p-0 m-0" for="status">
                        <input type="checkbox" name="status" value="1" {{ $category['status'] ? 'checked' : '' }}
                               class="toggle-switch-input" id="status">
                        <span class="toggle-switch-label">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="tps-card mb-20">
                <div class="tps-card__body">
                    <div class="tps-group">
                        <p class="tps-group__label">
                            {{ $isMain ? translate('Main category name') : translate('Main Sub Category Name') }}
                        </p>

                        @if ($language)
                            <ul class="nav nav-tabs mb-3 border-0">
                                <li class="nav-item">
                                    <a class="nav-link text-nowrap lang_link1 active" href="#"
                                       id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link text-nowrap lang_link1" href="#"
                                           id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
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
                                    <input type="text" name="name[]" id="default_name_edit"
                                           value="{{ $category?->getRawOriginal('name') }}" class="form-control"
                                           placeholder="{{ translate('messages.New main category') }}" maxlength="191"
                                           required>
                                    <small class="tps-field__hint">
                                        {{ translate('Renaming does not change the link customers already have — the slug stays as it is.') }}
                                    </small>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">

                            @foreach ($language as $key => $lang)
                                <div class="d-none lang_form1" id="{{ $lang }}-form1">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="{{ $lang }}_name_edit">
                                            {{ translate('Name') }} ({{ strtoupper($lang) }})
                                            <span class="tps-opt">{{ translate('Optional') }}</span>
                                        </label>
                                        <input type="text" name="name[]" id="{{ $lang }}_name_edit"
                                               value="{{ $nameTranslations[$lang] ?? '' }}" class="form-control"
                                               placeholder="{{ translate('messages.Type Main Category Name') }}"
                                               maxlength="191">
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
                                    {{ $isMain ? translate('Main category name') : translate('Main Sub Category Name') }}
                                    <span class="tps-req">*</span>
                                </label>
                                <input type="text" name="name" id="default_name_edit" class="form-control"
                                       placeholder="{{ translate('messages.New main category') }}"
                                       value="{{ $category?->getRawOriginal('name') }}" maxlength="191" required>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                    </div>

                    {{-- Read-only, and posted back unchanged. Re-parenting is supported by the
                         model layer (CategoryObserver resyncs items.top_category_id) but not by
                         this panel: items also carry a denormalised `category_ids` JSON column
                         that nothing resyncs, so moving a sub here would leave it stale. --}}
                    <input name="parent_id" value="{{ $category->parent_id ?? 0 }}" hidden>

                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('Placement') }}</p>

                        @if (! $isMain)
                            <div class="tps-field mb-3">
                                <label class="tps-field__label">{{ translate('Main category') }}</label>
                                <div class="tps-readonly tps-readonly--text">
                                    <span class="tps-readonly__value">
                                        {{ $category?->parent?->name ?? translate('Invalid main category') }}
                                    </span>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Moving a subcategory to a different main category is not done here — delete it and add it under the other one.') }}
                                </small>
                            </div>
                        @endif

                        <div class="tps-field">
                            <label class="tps-field__label" for="priority_edit">
                                {{ translate('messages.Priority') }}
                            </label>
                            <select required name="priority" id="priority_edit"
                                    data-original-title="{{ translate('Select priority') }}"
                                    class="custom-select">
                                <option {{ $category['priority'] == 0 ? 'selected' : '' }} value="0">
                                    {{ translate('messages.Normal') }}</option>
                                <option {{ $category['priority'] == 1 ? 'selected' : '' }} value="1">
                                    {{ translate('messages.medium') }}</option>
                                <option {{ $category['priority'] == 2 ? 'selected' : '' }} value="2">
                                    {{ translate('messages.High') }}</option>
                            </select>
                            <small class="tps-field__hint">
                                {{ translate('High priority categories are listed first in the app, then medium, then normal.') }}
                            </small>
                        </div>

                        @if ($isMain && $categoryWiseTax)
                            <div class="tps-field mt-3">
                                <label class="tps-field__label" for="tax__rate_edit">
                                    {{ translate('Select tax rate') }}
                                    <span class="tps-req">*</span>
                                </label>
                                <select name="tax_ids[]" required id="tax__rate_edit"
                                        class="form-control js-select2-custom1" multiple="multiple"
                                        data-placeholder="{{ translate('Type & select tax rate') }}">
                                    @foreach ($taxVats as $taxVat)
                                        <option {{ in_array($taxVat->id, $taxVatIds) ? 'selected' : '' }}
                                                value="{{ $taxVat->id }}">{{ $taxVat->name }}
                                            ({{ $taxVat->tax_rate }}%)
                                        </option>
                                    @endforeach
                                </select>
                                <small class="tps-field__hint">
                                    {{ translate('Every item in this category is taxed at the rates you pick here.') }}
                                </small>
                            </div>
                        @endif
                    </div>

                    @if ($isMain)
                        <div class="tps-group">
                            <p class="tps-group__label">{{ translate('Artwork') }}</p>
                            <div class="bg-light rounded p-20 text-center">
                                <div class="mb-3">
                                    <h6 class="mb-1">{{ translate('Main Category Image') }}
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    </h6>
                                    <p class="mb-0 fs-12 text-muted">
                                        {{ translate('Square artwork, shown on the category tile.') }}
                                    </p>
                                </div>
                                @include('admin-views.partials._image-uploader', [
                                    'id' => 'category-image-input-' . $category['id'],
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
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div
        class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
        <button type="button"
                class="btn w-100 btn--reset offcanvas-close h--40px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px"><i class="tio-save"></i> {{ translate('Update') }}</button>
    </div>
</form>
