@use('App\Support\Promotion\BundleSettings')

<div class="p-xxl-20 p-3 shadow-sm bg-white rounded mb-20" id="product_bundle_section">
    <div class="">
        <div class="row g-1 align-items-center">
            <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                <div>
                    <h4 class="mb-1">
                        {{ translate('Product bundle') }}
                    </h4>
                    <p class="mb-0 fs-12">
                        {{ translate('Enable product bundles and choose the modules where bundled products will be available.') }}
                    </p>
                </div>
            </div>
            <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                <div class="">
                    <div class="form-group mb-0">
                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                            <span class="pr-1 d-flex align-items-center switch--label">
                                <span class="line--limit-1">
                                    {{ translate('messages.Status') }}
                                </span>
                            </span>
                            <input type="checkbox" class="status toggle-switch-input" name="product_bundle_status" value="1" {{ $bundleStatus ? 'checked' : '' }} id="product_bundle_status">
                            <span class="toggle-switch-label text">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-0 mt-20" id="product_bundle_options">
        <label class="mb-2 input-label text-capitalize d-flex alig-items-center" for="">
            {{ translate('Bundle available modules') }}
            <span class="text-danger">*</span>
            <span class="form-label-secondary text-danger"
                data-toggle="tooltip" data-placement="right"
                data-original-title="{{ translate('messages.Select the modules where stores may build product bundles') }}"><i class="tio-info text-muted ps--3"></i></span>
        </label>
        <div class="rounded border py-2 min-h-45px bg-white px-3">
            <div class="row g-lg-3 g-1">
                @foreach ($bundleModuleTypes as $key => $value)
                    <div class="col-lg-3 col-sm-6">
                        <div class="custom-control custom-checkbox pt-1">
                            <input class="custom-control-input" type="checkbox" {{ isset($bundleSelectedModules[$value]) && $bundleSelectedModules[$value] == 1 ? 'checked' : '' }} id="productBundleModule{{ $key }}" value="1" name="product_bundle_modules[{{ $value }}]">
                            <label class="custom-control-label" for="productBundleModule{{ $key }}">{{ BundleSettings::moduleTypeLabel($value) }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="d-flex p-2 px-3 rounded gap-2 bg-opacity-info-10 mt-20">
            <i class="tio-info text-info"></i>
            <p class="fz-12px mb-0">
                {{ translate('By enabling the product bundle feature, you can select specific modules to offer bundled products. This allows you to create product bundles, with or without discounts, which can effectively boost your sales.') }}
            </p>
        </div>
    </div>
</div>
