<?php

namespace Modules\AI\app\Http\Requests\Customer\Chat;

use App\Http\Requests\BaseRequest;

class SendMessageRequest extends BaseRequest
{
    private const MAX_MESSAGE_LENGTH = 2000;

    private const MAX_GUEST_ID_LENGTH = 100;

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:'.self::MAX_MESSAGE_LENGTH,
            'conversation_id' => 'nullable|integer',
            'guest_id' => 'nullable|string|max:'.self::MAX_GUEST_ID_LENGTH,
        ];
    }
}
