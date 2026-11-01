
<form action="{{route('admin.brand.update',[$brand['id']])}}" method="post" enctype="multipart/form-data"
      data-ajax-form
      data-ajax-refresh="[data-ajax-region]">
    @csrf

            <div class="d-flex flex-column h-100">
                <div class="d-flex p-3 justify-content-between mb-3 bg-light">
                    <h4 class="mb-0">{{translate('Update brand')}}</h4>
                    <span class="circle bg-light withdraw-info-hide2 cursor-pointer">
                        <i class="tio-clear"></i>
                    </span>
                </div>


                <div class="p-3">
                    <div class="bg-light p-3 rounded">
                        <h4>{{translate('messages.Status')}}</h4>
                        <p class="fs-12">{{ translate('messages.If you turn off the switch, the brand will not be active or visible in the customer app and website.') }}</p>

                        <div class="maintenance-mode-toggle-bar d-flex flex-wrap justify-content-between border rounded align-items-center py-2 px-3">
                            <h5 class="text-capitalize m-0 text--primary">{{translate('messages.Status')}}</h5>

                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" name="brand_status"  {{ $brand->status == 1 ? 'checked' :  '' }}  class="status toggle-switch-input">
                                <span class="toggle-switch-label text mb-0">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded">
                        @if($language)
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link1 active" href="#" id="default-link1">{{translate('Default')}}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link1" href="#" id="{{ $lang }}-link1">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($language)
                            <div class="form-group lang_form1" id="default-form1">
                                <label class="input-label">
                                    {{translate('Name')}} ({{ translate('Default') }})
                                    <small class="text-danger">*</small>
                                </label>
                                <input type="text" name="name[]" value="{{$brand?->getRawOriginal('name')}}"  class="form-control" placeholder="{{translate('messages.New brand')}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            @foreach($language as $key => $lang)

                            <?php
                            if(count($brand['translations'])){
                                $translate = [];
                                foreach($brand['translations'] as $t)
                                {
                                    if($t->locale == $lang && $t->key=="name"){
                                        $translate[$lang]['name'] = $t->value;
                                    }
                                }
                            }
                        ?>

                                <div class="form-group d-none lang_form1" id="{{$lang}}-form1">
                                    <label class="input-label">
                                        {{translate('Name')}} ({{strtoupper($lang)}})
                                        <small class="text-danger">*</small>
                                    </label>
                                    <input type="text" name="name[]" value="{{$translate[$lang]['name']??''}}"  class="form-control" placeholder="{{translate('messages.New brand')}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            @endforeach
                        @else
                            <div class="form-group">
                                <label class="input-label">
                                    {{translate('Name')}}
                                    <small class="text-danger">*</small>
                                </label>
                                <input type="text" name="name" class="form-control" placeholder="{{translate('messages.type_brand_name')}}" value="{{$brand['name']}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                    </div>

                    <div class="bg-light p-3 rounded my-4">
                        <h4>{{translate('Brand logo')}} <small class="text-danger">*</small></h4>
                        <p class="fs-12">{{ translate('messages.It will show in website & app.') }}</p>
                        <div class="d-flex justify-content-center">
                            <label class="text-center position-relative d-inline-block mb-3">
                                <img class="img--176 border" id="viewer2"
                                        @if(isset($brand))
                                            src="{{ $brand['image_full_url'] }}"
                                        @else
                                            src="{{asset('public/assets/admin/img/upload-img.png')}}"
                                        @endif
                                        alt="image"/>
                                <div class="icon-file-group">
                                    <div class="icon-file">
                                        <input type="file" name="image" id="customFileEg2" class="custom-file-input read-url"
                                                accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" >
                                        <i class="tio-edit"></i>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <p class="text-center fs-12">{{'JPG, JPEG, PNG' . ' image, max ' . 1 . ' MB (ratio ' . '1:1' . ')'}}</p>
                    </div>

                </div>

                <div class="bg-white bottom-0 d-flex gap-3 mt-auto p-3 position-sticky shadow-lg">
                    <button  type="reset" id="reset_btn2" class="btn btn-secondary btn-block withdraw-info-hide2"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn-primary btn-block mt-0" ><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                </div>
            </div>
        </form>


<script>
    "use strict";
    $('#reset_btn2').click(function(){
        $('#viewer2').attr('src', "{{ $brand['image_full_url'] }}");
    })
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function (e) {
                $('#viewer2').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#customFileEg2").change(function () {
        readURL(this);
    });
    $(".lang_link1").click(function(e) {
        e.preventDefault();
        $(".lang_link1").removeClass('active');
        $(".lang_form1").addClass('d-none');
        $(this).addClass('active');
        let form_id = this.id;
        let lang = form_id.substring(0, form_id.length - 6);
        $("#" + lang + "-form1").removeClass('d-none');
        if (lang === 'default') {
            $(".default-form1").removeClass('d-none');
        }
    })
</script>