<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;


class DatabaseSettingController extends Controller
{
    private array $filter_tables = ['module_types','admin_roles','admins','business_settings','colors','currencies',
        'failed_jobs','migrations','oauth_access_tokens','oauth_auth_codes',
        'oauth_clients','oauth_personal_access_clients','oauth_refresh_tokens',
        'password_resets','personal_access_tokens','phone_or_email_verifications',
        'social_medias','soft_credentials','users','jobs','data_settings'];

    private function clearable_tables(): array
    {
        // SHOW TABLES hands back stdClass rows whose one column is named after
        // the database, so cast before current() — PHP 8.1 deprecates it on objects.
        $tables = array_map(static fn ($row) => current((array) $row), DB::select('SHOW TABLES'));

        return array_values(array_diff($tables, $this->filter_tables));
    }

    public function db_index()
    {
        $tables = $this->clearable_tables();

        $rows = [];
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            array_push($rows, $count);
        }

        return view('admin-views.business-settings.db-index', compact('tables', 'rows'));
    }
    public function clean_db(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        // The form only ever offers the clearable tables, but the request can
        // name any table at all — intersecting here is what keeps a posted
        // "admins" or "oauth_clients" out of the delete loop.
        $requested = array_filter((array)$request->tables, 'is_string');
        $tables = array_values(array_intersect($requested, $this->clearable_tables()));

        if(count($tables) == 0) {
            Toastr::error(translate('No Table Updated'));
            return back();
        }

        try {
            DB::transaction(function () use ($tables) {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            });
        } catch (\Exception $exception) {
            Toastr::error(translate('Failed to update!'));
            return back();
        }

        Toastr::success(translate('messages.Updated successfully'));
        return back();
    }
}
