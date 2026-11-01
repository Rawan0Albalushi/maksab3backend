<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;
use Illuminate\Http\UploadedFile;

class RefundStoreRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $image = $this->file('image');

        if ($image instanceof UploadedFile) {
            $this->files->set('image', [$image]);
            $this->convertedFiles = null;
        }
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'customer_reason' => 'required|string|max:254',
            'refund_method' => 'nullable|string|max:100',
            'customer_note' => 'nullable|string|max:65535',
            'image' => 'nullable|array',
            'image.*' => $this->imageRule(),
        ];
    }

    public function payload(): array
    {
        return [
            'refund_method' => $this->input('refund_method'),
            'customer_reason' => $this->input('customer_reason'),
            'customer_note' => $this->input('customer_note'),
            'images' => $this->file('image') ?? [],
        ];
    }
}
