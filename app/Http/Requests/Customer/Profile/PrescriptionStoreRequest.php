<?php

namespace App\Http\Requests\Customer\Profile;

use App\Rules\ImageFile;
use App\Http\Requests\BaseRequest;
use Illuminate\Support\Arr;

class PrescriptionStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'saved_images' => 'required|array|min:1',
            'saved_images.*' => ImageFile::rules('required'),
        ];
    }

    public function savedImages(): array
    {
        return array_values(array_filter(Arr::wrap($this->file('saved_images'))));
    }
}
