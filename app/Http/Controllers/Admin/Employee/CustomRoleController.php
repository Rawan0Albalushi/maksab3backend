<?php

namespace App\Http\Controllers\Admin\Employee;

use App\Contracts\Repositories\CustomRoleRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ViewPaths\Admin\CustomRole as CustomRoleViewPath;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CustomRoleAddRequest;
use App\Http\Requests\Admin\CustomRoleUpdateRequest;
use App\Navigation\AdminRolePermissionForm;
use App\Services\Admin\CustomRoleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CustomRoleController extends BaseController
{
    public function __construct(
        protected CustomRoleRepositoryInterface $roleRepo,
        protected CustomRoleService $roleService,
        protected TranslationRepositoryInterface $translationRepo
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $roles = $this->roleRepo->getListWhere(
            searchValue: request()?->search,
            dataLimit: config('default_pagination')
        );
        $permissionLabels = AdminRolePermissionForm::make()->labels();
        $permissionTotal = count($permissionLabels);
        return view(CustomRoleViewPath::LIST[VIEW], compact('roles','permissionLabels','permissionTotal'));
    }

    public function getAddView(): View
    {
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $permissionGroups = AdminRolePermissionForm::make()->groups();
        return view(CustomRoleViewPath::ADD[VIEW], compact('language','defaultLang','permissionGroups'));
    }

    public function add(CustomRoleAddRequest $request): RedirectResponse
    {
        $role = $this->roleRepo->add(data: $this->roleService->getAddData($request->all()));
        $this->translationRepo->addByModel(request: $request, model: $role, modelPath: 'App\Models\AdminRole', attribute: 'name');
        Toastr::success(translate('Added successfully'));
        return redirect()->route('admin.users.custom-role.list');
    }

    public function getUpdateView(string|int $id): View
    {
        $data = $this->roleService->roleCheck(role: $id);

        if (array_key_exists('flag', $data) && $data['flag'] == 'unauthorized') {
            return view('errors.404');
        }
        $role = $this->roleRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $permissionGroups = AdminRolePermissionForm::make()->groups();
        return view(CustomRoleViewPath::UPDATE[VIEW], compact('role','language','defaultLang','permissionGroups'));
    }

    public function update(CustomRoleUpdateRequest $request, $id): RedirectResponse|View
    {
        $data = $this->roleService->roleCheck(role: $id);

        if (array_key_exists('flag', $data) && $data['flag'] == 'unauthorized') {
            return view('errors.404');
        }

        $role = $this->roleRepo->update(id: $id ,data: $this->roleService->getAddData($request->all()));
        $this->translationRepo->updateByModel(request: $request, model: $role, modelPath: 'App\Models\AdminRole', attribute: 'name');
        Toastr::success(translate('Updated successfully'));
        return redirect()->route('admin.users.custom-role.list');
    }

    public function delete($id): RedirectResponse|View
    {
        $data = $this->roleService->roleCheck(role: $id);

        if (array_key_exists('flag', $data) && $data['flag'] == 'unauthorized') {
            return view('errors.404');
        }
        $role = $this->roleRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);

        if ($role?->employees()->exists()) {
            Toastr::error(translate('Move its employees to another role before deleting this one.'));
            return back();
        }

        $this->roleRepo->delete(id: $id);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function search(Request $request): JsonResponse
    {
        $roles = $this->roleRepo->getSearchList($request);
        $permissionLabels = AdminRolePermissionForm::make()->labels();
        $permissionTotal = count($permissionLabels);
        return response()->json([
            'view'=>view(CustomRoleViewPath::SEARCH[VIEW],compact('roles','permissionLabels','permissionTotal'))->render(),
            'count'=>$roles->count()
        ]);
    }

    public function view($id)
    {
        $role = $this->roleRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $permissionForm = AdminRolePermissionForm::make();
        $permissionGroups = $permissionForm->groups();
        $permissionLabels = $permissionForm->labels();
        return response()->json([
            'view' => view('admin-views.custom-role.partials._view_role', compact('role', 'permissionGroups', 'permissionLabels'))->render(),
        ]);
    }


}
