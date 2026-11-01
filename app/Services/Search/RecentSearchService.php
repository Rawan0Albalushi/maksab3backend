<?php

namespace App\Services\Search;

use App\Models\RecentSearch;
use App\Services\BaseService;

class RecentSearchService extends BaseService
{
    public function record(array $data): void
    {
        RecentSearch::create([
            'user_id' => $data['user_id'],
            'user_type' => 'App\\Models\\User',
            'keyword' => $data['keyword'],
            'route_name' => 'api.v1.items.search',
            'route_uri' => $data['route_uri'],
            'route_full_url' => $data['route_full_url'],
        ]);
    }
}
