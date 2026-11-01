<?php

namespace App\Http\Controllers\Admin;

use App\Rules\ImageFile;
ini_set('post_max_size','1024M');
ini_set('upload_max_filesize','1024M');

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Brian2694\Toastr\Facades\Toastr;
use Madnest\Madzipper\Facades\Madzipper;
use ZipArchive;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Storage\FileStorage;


class FileManagerController extends Controller
{


    public function index($folder_path = "cHVibGlj", $storage = 'local')
    {
        $perPage = 50;
        $page = request()->integer('page', 1);
        $search = trim((string) request('search', ''));

        if ($storage == 's3' && Helpers::getDisk() == 's3') {
            try {
                Storage::disk('s3')->exists($folder_path);
            } catch (\Exception $e) {
                Toastr::error(translate('messages.Something went wrong'));
                return back();
            }

            $folder_path = $folder_path == "cHVibGlj" ? "" : $folder_path;
            $directory = base64_decode($folder_path) . '/';

            $s3 = Storage::disk('s3');

            $files = $directory == '/' ? [] : $s3->allFiles($directory);
            $directories = $s3->allDirectories($directory);
        } else {
            $storage = 'local';
            $directory = base64_decode($folder_path);

            $files = Storage::files($directory);
            $directories = Storage::directories($directory);
        }

        $folders = FileStorage::formatFilesAndFolders($directories, 'folder');
        $files = FileStorage::formatFilesAndFolders($files, 'file');

        $collection = collect(array_merge($folders, $files));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $collection = $collection
                ->filter(fn ($row) => str_contains(mb_strtolower($row['name']), $needle))
                ->values();
        }

        $paginatedData = new LengthAwarePaginator(
            $this->withFileSizes($collection->slice(($page - 1) * $perPage, $perPage)->values(), $storage),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $decoded_path = base64_decode($folder_path);

        return view(
            'admin-views.file-manager.index',
            [
                'data' => $paginatedData,
                'folder_path' => $folder_path,
                'storage' => $storage,
                'search' => $search,
                'crumbs' => $this->pathCrumbs($decoded_path, $storage),
                'parent_token' => $this->parentFolderToken($decoded_path),
                'folder_count' => $collection->where('type', 'folder')->count(),
                'file_count' => $collection->where('type', 'file')->count(),
            ]
        );
    }

    /**
     * Sizes come from a local stat, which is cheap. S3 would need a HEAD request
     * per row, so those rows are left without one rather than paying for 50.
     */
    private function withFileSizes($rows, string $storage)
    {
        if ($storage !== 'local') {
            return $rows;
        }

        $disk = Storage::disk('local');

        return $rows->map(function ($row) use ($disk) {
            if ($row['type'] === 'file') {
                try {
                    $row['size'] = $this->readableSize((int) $disk->size($row['path']));
                } catch (\Throwable $e) {
                    $row['size'] = null;
                }
            }

            return $row;
        });
    }

    private function readableSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, $unit > 1 ? 1 : 0) . ' ' . $units[$unit];
    }

    /**
     * The folder trail shown above the grid. The local disk is browsed from
     * storage/app/public, so its first segment is the root itself and is dropped
     * here — the view renders the root chip on its own.
     */
    private function pathCrumbs(string $decoded_path, string $storage): array
    {
        $segments = array_values(array_filter(explode('/', trim($decoded_path, '/')), fn ($segment) => $segment !== ''));

        if ($storage === 'local' && ($segments[0] ?? null) === 'public') {
            array_shift($segments);
        }

        $walked = $storage === 'local' ? ['public'] : [];
        $crumbs = [];

        foreach ($segments as $segment) {
            $walked[] = $segment;
            $crumbs[] = [
                'label' => $segment,
                'token' => base64_encode(implode('/', $walked)),
            ];
        }

        return $crumbs;
    }

    /**
     * base64 token for one level up, or null when already at the root.
     */
    private function parentFolderToken(string $decoded_path): ?string
    {
        $decoded_path = trim($decoded_path, '/');

        if ($decoded_path === '' || $decoded_path === 'public') {
            return null;
        }

        $parent = dirname($decoded_path);

        return in_array($parent, ['.', '/', ''], true) ? 'cHVibGlj' : base64_encode($parent);
    }


    public function upload(Request $request)
    {
        $request->validate([
            'images.*' => ImageFile::rules('required_without:file'),
            'file' => 'required_without:images|mimetypes:application/zip',
            'path' => 'required_if:disk,local',
        ]);

        $disk = $request->disk;
        if($disk == 's3' && !$request->path){
            Toastr::warning(translate('messages.Go to a specific folder to upload files to the storage bucket') . ' (S3)');
            return back();
        }
        if ($request->hasfile('images')) {
            $images = $request->file('images');

            foreach($images as $image) {
                $name = $image->getClientOriginalName();
                if ($disk === 'local') {
                    Storage::disk($disk)->put($request->path . '/' . $name, file_get_contents($image));
                } elseif ($disk === 's3') {
                    Storage::disk($disk)->putFileAs($request->path, $image, $name);
                }
            }
        }
        if ($request->hasfile('file')) {
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            if ($disk === 's3') {
                $zipContents = file_get_contents($file->path());
                $zip = new ZipArchive;
                if ($zip->open($file->path()) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);

                        if (!$stat['name'] || $this->shouldSkip($stat['name'])) {
                            continue;
                        }

                        $filename = $stat['name'];
                        $fileContent = $zip->getFromIndex($i);
                        $format = pathinfo($filename, PATHINFO_EXTENSION);

                        $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;

                        $s3 = Storage::disk('s3');
                        $s3Path = $request->path . '/' . $imageName;
                        $s3->put($s3Path, $fileContent, 'public');
                    }
                    $zip->close();
                }
            }else{
                Madzipper::make($file)->extractTo('storage/app/'.$request->path);
            }


        }
        Toastr::success(translate('messages.Image uploaded successfully'));
        return back()->with('success', translate('messages.Image uploaded successfully'));
    }

    private function shouldSkip($filename) {
        $skipFiles = [
            '__MACOSX/',
            '.DS_Store',
            'Thumbs.db',
        ];

        foreach ($skipFiles as $skipFile) {
            if (strpos($filename, $skipFile) === 0) {
                return true;
            }
        }

        return false;
    }


    public function download($file_name,$storage='public')
    {
        $decodedFileName=base64_decode($file_name);
        if (Storage::disk($storage)->exists($decodedFileName)) {
            return Storage::disk($storage)->download($decodedFileName);
        } elseif(Storage::disk('local')->exists($decodedFileName)){
            return Storage::disk('local')->download($decodedFileName);
        }
        return false;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    }


    public function destroy($file_path)
    {
        try {
            Storage::disk('local')->delete(base64_decode($file_path));
            Storage::disk('s3')->delete(base64_decode($file_path));
        } catch (\Exception $e){

        }
        Toastr::success(translate('Deleted successfully'));
        return back()->with('success', translate('Deleted successfully'));
    }
}
