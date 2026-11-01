<?php

namespace App\Services\Marketing;

use App\Models\Newsletter;
use App\Services\BaseService;

class NewsletterService extends BaseService
{
    public function create(array $payload): Newsletter
    {
        return Newsletter::create(['email' => $payload['email']]);
    }
}
