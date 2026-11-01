<?php

namespace App\Http\Controllers\Admin\System;

use App\Models\Module;
use App\Support\Addon\AddonCompatibility;
use App\Traits\System\ActivationTrait;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\View\View;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;

class AddonController extends Controller
{
    use ActivationTrait;
    public function __construct(){
        if (is_dir('Modules\Gateways\Traits') && trait_exists('Modules\Gateways\Traits\SmsGateway')) {
            $this->extendWithSmsGatewayTrait();
        }
    }

    private function extendWithSmsGatewayTrait()
    {
        $extendedControllerClass = $this->generateExtendedControllerClass();
        eval($extendedControllerClass);
    }

    private function generateExtendedControllerClass()
    {
        $baseControllerClass = get_class($this);
        $traitClassName = 'Modules\Gateways\Traits\SmsGateway';

        $extendedControllerClass = "
            class ExtendedController extends $baseControllerClass {
                use $traitClassName;
            }
        ";

        return $extendedControllerClass;
    }

    public function index(): Factory|View|Application
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            if(!in_array($directory, ['TaxModule','ReelsModule','AI'])) {
                $sub_dirs = self::getDirectories('Modules/' . $directory);
                if (in_array('Addon', $sub_dirs)) {
                    $addons[] = 'Modules/' . $directory;
                }
            }
        }
        return view('admin-views.system.addon.index', compact('addons'));
    }

    public function publish(Request $request): JsonResponse
    {
        if (getEnvMode() == 'demo') {
            return response()->json([
                'status' => 'demo',
                'message'=> translate('messages.Update option is disable for demo')
            ]);
        }
        $full_data = include($request['path'] . '/Addon/info.php');
        $path = $request['path'];
        $addon_name = $full_data['name'];

        $going_active = ! ($full_data['is_published'] && AddonCompatibility::isCompatible(name: $addon_name));

        if ($going_active && ! AddonCompatibility::isCompatible(name: $addon_name)) {
            return response()->json([
                'status' => 'error',
                'message' => $this->incompatibleAddonMessage(addonName: $addon_name),
            ]);
        }

        // The licence is only a condition of turning an add-on ON. Gating both
        // directions on it strands an add-on that is published with no purchase
        // code — every click on its switch reopens the licence modal, so it can
        // never be turned off.
        if ($going_active && ($full_data['purchase_code'] == null || $full_data['username'] == null)) {
            return response()->json([
                'flag' => 'inactive',
                'view' => view('admin-views.system.addon.partials.activation-modal-data', compact('full_data', 'path', 'addon_name'))->render(),
            ]);
        }

        if ($going_active && $full_data['name'] == 'Builder') {
            $issues = $this->checkBuilderRequirements();
            if (!empty($issues)) {
                return response()->json([
                    'flag'  => 'requirements_missing',
                    'view'  => view('admin-views.system.addon.partials.builder-requirements-modal-data',
                                    compact('issues', 'addon_name'))->render(),
                ]);
            }
        }

        $full_data['is_published'] = $going_active ? 1 : 0;
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        if ($full_data['name'] == 'Rental') {
            $this->rentalPublish($full_data['is_published']);
        }

        if ($full_data['name'] == 'RideShare') {
            $this->rideSharePublish($full_data['is_published']);
        }

        if ($full_data['name'] == 'Service') {
            $this->servicePublish($full_data['is_published']);
        }

        if ($full_data['name'] == 'Builder') {
            $ok = $this->builderPublish($full_data['is_published']);
            if (!$ok) {
                $full_data['is_published'] = $going_active ? 0 : 1;
                file_put_contents(
                    base_path($request['path'] . '/Addon/info.php'),
                    "<?php return " . var_export($full_data, true) . ";"
                );
                return response()->json([
                    'status'  => 'error',
                    'message' => translate('Builder activation failed during the bundle copy. Check logs and try again.'),
                ]);
            }
        }

        // is_published goes back so the card can flip in place — the page never
        // reloads, so the switch has to be told what the file now says.
        return response()->json([
            'status'       => 'success',
            'is_published' => (int) $full_data['is_published'],
        ]);
    }

    public function activation(Request $request): Redirector|RedirectResponse|Application|JsonResponse
    {
        // The modal on the add-on page posts here over ajax so the card can flip
        // without a reload. A plain post (JS unavailable) keeps the old
        // flash-and-redirect flow, so the form never dead-ends on raw JSON.
        $wants_json = $request->ajax();

        if (getEnvMode() == 'demo') {
            $message = translate('messages.Update option is disable for demo');
            if ($wants_json) {
                return response()->json(['status' => 'demo', 'message' => $message]);
            }
            Toastr::info($message);
            return back();
        }
        $full_data = include($request['path'] . '/Addon/info.php');
        $addon_name = $full_data['name'];

        if (! AddonCompatibility::isCompatible(name: $addon_name)) {
            $message = $this->incompatibleAddonMessage(addonName: $addon_name);
            if ($wants_json) {
                return response()->json(['status' => 'error', 'message' => $message]);
            }
            Toastr::error($message);
            return back();
        }

            $response= $this->getRequestConfig(
                        name:  $request['name'],
                        email:  $request['email'],
                        username: $request['username'],
                        purchaseKey: $request['purchase_code'],
                        softwareId: base64_encode($full_data['software_id']),
                        softwareType: 'addon'
                    );
            $status =  base64_encode(data_get($response, 'active', 1));


        if ((int)base64_decode($status)) {
            if ($full_data['name'] == 'Builder') {
                $issues = $this->checkBuilderRequirements();
                if (!empty($issues)) {
                    if ($wants_json) {
                        return response()->json([
                            'flag' => 'requirements_missing',
                            'view' => view('admin-views.system.addon.partials.builder-requirements-modal-data',
                                        compact('issues', 'addon_name'))->render(),
                        ]);
                    }
                    \session()->flash('builder_requirements_issues', $issues);
                    \session()->flash('builder_requirements_addon', $full_data['name']);
                    return back();
                }
            }


            $full_data['is_published'] = 1;
            $full_data['username'] = $request['username'];
            $full_data['purchase_code'] = $request['purchase_code'];
            $str = "<?php return " . var_export($full_data, true) . ";";
            file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

            if ($full_data['name'] == 'Rental') {
                $this->rentalPublish($full_data['is_published']);
            }
            if ($full_data['name'] == 'RideShare') {
                $this->rideSharePublish($full_data['is_published']);
            }
            if ($full_data['name'] == 'Service') {
                $this->servicePublish($full_data['is_published']);
            }


            if ($full_data['name'] == 'Builder') {
                $ok = $this->builderPublish($full_data['is_published']);
                if (!$ok) {
                    $full_data['is_published'] = 0;
                    file_put_contents(
                        base_path($request['path'] . '/Addon/info.php'),
                        "<?php return " . var_export($full_data, true) . ";"
                    );
                    $message = translate('Builder activation failed during the bundle copy. Check logs and try again.');
                    if ($wants_json) {
                        return response()->json(['status' => 'error', 'message' => $message]);
                    }
                    Toastr::error($message);
                    return back();
                }
            }

            if ($wants_json) {
                return response()->json([
                    'status'       => 'success',
                    'is_published' => 1,
                ]);
            }

            Toastr::success(translate('Activated successfully'));
            return back();
        }

        $activation_url = base64_decode('aHR0cHM6Ly9hY3RpdmF0aW9uLjZhbXRlY2guY29t');
        $activation_url .= '?username=' . $request['username'];
        $activation_url .= '&purchase_code=' . $request['purchase_code'];
        $activation_url .= '&domain=' . url('/') . '&';

        if ($wants_json) {
            return response()->json(['status' => 'redirect', 'url' => $activation_url]);
        }

        return redirect($activation_url);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_upload' => 'required|mimes:zip|max:' . (ADDON_MAX_FILE_SIZE * 1024)
        ]);

        if ($validator->errors()->count() > 0) {
            $error = Helpers::error_processor($validator);
            return response()->json(['status' => 'error', 'message' => $error[0]['message']]);
        }

        $file = $request->file('file_upload');
        try {
            Helpers::validateFile($file, ADDON_MAX_FILE_SIZE);
        } catch (\App\Exceptions\InvalidUploadException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
        $filename = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp', $filename);
        $zip = new \ZipArchive();

        if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {
            $extractPath = base_path('Modules/');
            if (!File::isWritable($extractPath)) {
                        $status = 'error';
                        $message = translate('messages.File is not writable. Please check your file permissions.');
                        return response()->json(['status' => $status, 'message' => $message]);
                    }
            foreach ($this->zipAddonInfos(zip: $zip) as $uploadedInfo) {
                if (! AddonCompatibility::meetsRequirement(name: $uploadedInfo['name'], version: $uploadedInfo['version'])) {
                    $zip->close();
                    Storage::delete($tempPath);
                    return response()->json([
                        'status' => 'error',
                        'message' => $this->incompatibleAddonMessage(addonName: $uploadedInfo['name']),
                    ]);
                }
            }
            $zip->extractTo($extractPath);
            $zip->close();
            if(File::exists($extractPath.'/'.explode('.', $filename)[0].'/Addon/info.php')){
                File::chmod($extractPath.'/'.explode('.', $filename)[0].'/Addon', 0777);
                Toastr::success(translate('File upload successfully!'));
                $status = 'success';
                $message = translate('File upload successfully!');
            }else{
                File::deleteDirectory($extractPath.'/'.explode('.', $filename)[0]);
                $status = 'error';
                $message = translate('Invalid file!');
            }
        }else{
            $status = 'error';
            $message = translate('File upload fail!');
        }

        Storage::delete($tempPath);

        return response()->json([
            'status' => $status,
            'message'=> $message
        ]);
    }

    public function delete_theme(Request $request){
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }
        $path = $request->path;

        $full_path = base_path($path);

        if(File::deleteDirectory($full_path)){
            return response()->json([
                'status' => 'success',
                'message'=> translate('File delete successfully')
            ]);
        }else{
            return response()->json([
                'status' => 'error',
                'message'=> translate('file_delete_fail')
            ]);
        }

    }

    function getDirectories(string $path): array
    {
        $fullPath = base_path($path);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];

        foreach (scandir($fullPath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($fullPath . DIRECTORY_SEPARATOR . $item)) {
                $directories[] = $item;
            }
        }

        return $directories;
    }

    private function rentalPublish(int|bool $is_published): bool
    {
        try {
            $module = Module::firstOrNew(
                ['module_type' => 'rental'],
                ['module_name' => 'Rental']
            );

            if ($is_published) {
                Artisan::call('migrate', ['--force' => true]);
                $module->status = 1;
            } else {
                $module->status = 0;
            }

            $module->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function rideSharePublish(int|bool $is_published): bool
    {
        try {
            $module = Module::firstOrNew(
                ['module_type' => 'ride-share'],
                ['module_name' => 'RideShare']
            );

            if ($is_published) {
                Artisan::call('migrate', ['--force' => true]);
                $module->status = 1;
            } else {
                $module->status = 0;
            }

            $module->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function servicePublish(int|bool $is_published): bool
    {
        try {
            $module = Module::firstOrNew(
                ['module_type' => 'service'],
                ['module_name' => 'Service']
            );

            if ($is_published) {
                Artisan::call('migrate', ['--force' => true]);
                $module->status = 1;
            } else {
                $module->status = 0;
            }

            $module->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function zipAddonInfos(\ZipArchive $zip): array
    {
        $infos = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (! preg_match('#^([^/]+)/Addon/info\.php$#', (string) $zip->getNameIndex($index), $match)) {
                continue;
            }

            $contents = (string) $zip->getFromIndex($index);

            $infos[] = [
                'name' => $this->addonInfoValue(contents: $contents, key: 'name') ?? $match[1],
                'version' => $this->addonInfoValue(contents: $contents, key: 'version'),
            ];
        }

        return $infos;
    }

    private function addonInfoValue(string $contents, string $key): ?string
    {
        return preg_match('/[\'"]'.$key.'[\'"]\s*=>\s*[\'"]?([^\'",\s)]+)/', $contents, $match) ? $match[1] : null;
    }

    private function incompatibleAddonMessage(string $addonName): string
    {
        return translate('This add-on version is not compatible with your panel. Please upload :addon version :version or newer.', [
            'addon' => $addonName,
            'version' => AddonCompatibility::requiredVersion(name: $addonName),
        ]);
    }

    private function checkBuilderRequirements(): array
    {
        $issues = [];

        if (PHP_VERSION_ID < 80100) {
            $issues[] = [
                'key' => 'php_version',
                'message' => "PHP 8.1+ required (found " . PHP_VERSION . ")",
                'fix' => "Upgrade PHP via your hosting control panel.",
            ];
        }

        $extensions = ['zip', 'pdo_mysql', 'mbstring', 'fileinfo', 'openssl',
                       'tokenizer', 'xml', 'ctype', 'bcmath'];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = [
                    'key' => "ext_$ext",
                    'message' => "PHP extension '$ext' is not loaded",
                    'fix' => "Enable the $ext extension in php.ini or via your hosting control panel.",
                ];
            }
        }
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $issues[] = [
                'key' => 'ext_image',
                'message' => "Neither 'gd' nor 'imagick' PHP extension is loaded",
                'fix' => "Enable at least one image extension (typically GD) via php.ini.",
            ];
        }

        $functions = ['symlink', 'readlink', 'copy', 'unlink', 'rmdir',
                      'glob', 'class_implements', 'realpath'];
        foreach ($functions as $fn) {
            if (!function_exists($fn)) {
                $issues[] = [
                    'key' => "fn_$fn",
                    'message' => "PHP function '$fn' is disabled",
                    'fix' => "Remove '$fn' from `disable_functions` in php.ini, or contact your host.",
                ];
            }
        }

        $writable = [
            public_path()                => 'public/',
            base_path('bootstrap/cache') => 'bootstrap/cache/',
            storage_path()               => 'storage/',
            base_path('Modules/Builder') => 'Modules/Builder/',
        ];
        foreach ($writable as $path => $label) {
            if (is_dir($path) && !is_writable($path)) {
                $issues[] = [
                    'key' => 'write_' . md5($path),
                    'message' => "Path is not writable: $label",
                    'fix' => "Run `chmod -R 777 $label` and ensure the web server user owns it.",
                ];
            }
        }

        try {
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $issues[] = [
                'key' => 'db_connection',
                'message' => "Database connection failed: " . $e->getMessage(),
                'fix' => "Verify DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env.",
            ];
        }

        $distZip = base_path('Modules/Builder/resources/dist/build.zip');
        if (!file_exists($distZip)) {
            $issues[] = [
                'key' => 'dist_zip_missing',
                'message' => "Builder pre-built bundle missing at Modules/Builder/resources/dist/build.zip",
                'fix' => "Re-upload the addon zip — the bundle appears incomplete.",
            ];
        }

        $link = base_path('build');
        if (!file_exists($link) && !is_link($link) && function_exists('symlink')) {
            @symlink('public/build', $link);
        }
        if (!file_exists($link) && !is_link($link)) {
            $issues[] = [
                'key' => 'symlink_missing',
                'message' => "Cannot create the build → public/build symlink at $link",
                'fix' => "Run `ln -s public/build build` from the project root, or enable the symlink() function in php.ini.",
            ];
        } elseif (file_exists($link) && !is_link($link)) {
            $issues[] = [
                'key' => 'symlink_blocked',
                'message' => "Path 'build' exists but is not a symlink",
                'fix' => "Remove it and recreate: `rm -rf build && ln -s public/build build`.",
            ];
        }

        return $issues;
    }

    private function builderPublish(int|bool $is_published): bool
    {
        try {
            $hostBuild = public_path('build');
            $distZip   = base_path('Modules/Builder/resources/dist/build.zip');

            if ($is_published) {
                if (!file_exists($distZip)) {
                    info('Builder activate: bundle zip missing at ' . $distZip);
                    return false;
                }

                if (File::isDirectory($hostBuild)) {
                    File::deleteDirectory($hostBuild);
                }
                File::makeDirectory($hostBuild, 0755, true);

                $zip = new \ZipArchive();
                if ($zip->open($distZip) !== true) {
                    info('Builder activate: failed to open ' . $distZip);
                    return false;
                }
                $extracted = $zip->extractTo($hostBuild);
                $zip->close();
                if (!$extracted) {
                    info('Builder activate: extract failed (check write permission on public/)');
                    return false;
                }

                Artisan::call('migrate', ['--force' => true]);
            } else {
                if (File::isDirectory($hostBuild)) {
                    File::deleteDirectory($hostBuild);
                }
            }

            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            return true;
        } catch (\Exception $e) {
            info('Builder publish failed: ' . $e->getMessage());
            return false;
        }
    }
}
