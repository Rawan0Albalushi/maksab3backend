<?php

namespace App\Repositories;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use App\Support\Storage\FileStorage;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(protected Notification $notification)
    {
    }

    public function add(array $data): string|object
    {
        $notification = $this->notification->newInstance();
        foreach ($data as $key => $column) {
            $notification[$key] = $column;
        }
        $notification->save();
        return $notification;
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->notification->with($relations)->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        return $this->notification->with($relations)->paginate($dataLimit);
    }

    public function getListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');
        return $this->notification->with($relations)->where($filters)
            ->when(isset($key ), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('title', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()
            ->paginate($dataLimit);
    }

    public function update(string $id, array $data): bool|string|object
    {
        $notification = $this->notification->find($id);
        foreach ($data as $key => $column) {
            $notification[$key] = $column;
        }
        $notification->save();
        return $notification;
    }

    public function delete(string $id): bool
    {
        $notification = $this->notification->find($id);

        FileStorage::delete('notification/' , $notification['image']);
        
        $notification->delete();

        return true;
    }

    public function getExportList(Request $request): Collection
    {
        $key = explode(' ', $request['search'] ?? '');
        return $this->notification->with(['zone'])->when(isset($key ), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('title', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()
            ->get();
    }
}
