<?php

namespace App\Services\Marketing;

use App\Models\ReactTestimonial;
use App\Services\BaseService;

class ReactTestimonialService extends BaseService
{
    public function getAll(): mixed
    {
        return ReactTestimonial::withStorage()->get();
    }
}
