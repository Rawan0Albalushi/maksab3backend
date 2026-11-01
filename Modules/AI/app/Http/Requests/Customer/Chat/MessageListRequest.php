<?php

namespace Modules\AI\app\Http\Requests\Customer\Chat;

use App\Http\Requests\BaseRequest;

class MessageListRequest extends BaseRequest
{
    private const MAX_PER_PAGE = 100;

    private const MAX_GUEST_ID_LENGTH = 100;

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|integer',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_PER_PAGE,
            'offset' => 'nullable|integer|min:1',
            'guest_id' => 'nullable|string|max:'.self::MAX_GUEST_ID_LENGTH,
        ];
    }
}
