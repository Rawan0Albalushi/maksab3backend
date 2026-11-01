<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Repositories\ZoneRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Notification;
use App\Enums\ViewPaths\Admin\Notification as NotificationViewPath;
use App\Exports\PushNotificationExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\NotificationAddRequest;
use App\Http\Requests\Admin\NotificationUpdateRequest;
use App\Services\System\NotificationService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Support\Notification\SendNotification;

class NotificationController extends BaseController
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepo,
        protected NotificationService $notificationService,
        protected ZoneRepositoryInterface $zoneRepo
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getAddView($request);
    }

    private function getAddView($request): View
    {
        $target = $request->input('target');
        $notifications = $this->notificationRepo->getListWhere(
            searchValue: $request['search'],
            filters: $target && $target != 'all' ? ['tergat' => $target] : [],
            relations: ['storage', 'zone'],
            dataLimit: config('default_pagination'),
        );
        $zones = $this->zoneRepo->getList();
        return view(NotificationViewPath::INDEX[VIEW], compact('notifications','zones','target'));
    }

    public function add(NotificationAddRequest $request): JsonResponse
    {
        $notification = $this->notificationRepo->add(data: $this->notificationService->getAddData($request->all()));
        $topic = $this->notificationService->getTopic($request->all());
        $notification->image = $notification->image ? $notification->image_full_url :'';

        try {
            SendNotification::pushToTopic($notification->only(['title', 'description', 'image', 'zone_id']), $topic, 'push_notification');
        } catch (Exception) {
            Toastr::warning(translate('messages.Push notification failed'));
        }

        return response()->json();
    }

    public function getUpdateView(string|int $id): RedirectResponse
    {
        return redirect()->route('admin.notification.add-new');
    }

    public function update(NotificationUpdateRequest $request, $id): RedirectResponse
    {
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $id]);
        $notification = $this->notificationRepo->update(id: $id ,data: $this->notificationService->getUpdateData($request->all(),notification: $notification));

        $topic = $this->notificationService->getTopic($request->all());
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $id]);
        $notification->image = $notification->image ? $notification->image_full_url :'';

        try {
            SendNotification::pushToTopic($notification->only(['title', 'description', 'image', 'zone_id']), $topic, 'push_notification');
        } catch (Exception) {
            Toastr::warning(translate('messages.Push notification failed'));
        }

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->notificationRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);
        Toastr::success(translate('messages.Notification status updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->notificationRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $notifications = $this->notificationRepo->getExportList($request);
        $data=[
            'data' =>$notifications,
            'search' =>$request['search'] ?? null
        ];
        if($request['type'] == 'csv'){
            return Excel::download(new PushNotificationExport($data), Notification::EXPORT_CSV);
        }
        return Excel::download(new PushNotificationExport($data), Notification::EXPORT_XLSX);
    }
}
