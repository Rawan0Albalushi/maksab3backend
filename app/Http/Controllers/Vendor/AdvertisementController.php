<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Rules\WordValidation;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Admin\AdvertisementStoreRequest;
use App\Http\Requests\Admin\AdvertisementUpdateRequest;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;

class AdvertisementController extends Controller
{
    /**
     * Advertisements arrive by route model binding, which knows nothing of stores; only this
     * store's own may be edited or copied.
     */
    private function authorizeAdvertisement(Advertisement $advertisement): void
    {
        abort_unless((string) $advertisement->store_id === (string) Helpers::get_store_id(), 404);
    }

    public function index(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
       $total_adds= Advertisement::where('store_id',Helpers::get_store_id())->count();

       $adds=Advertisement::where('store_id',Helpers::get_store_id())
        ->when($request?->type == 'pending'  || $request?->ads_type === 'pending',function($query){
            $query->where('status','pending');
        })
        ->when(!($request?->type == 'pending'  || $request?->ads_type === 'pending'),function($query){
            $query->whereNot('status','pending');
        })
        ->when($request?->ads_type === 'denied',function($query){
            $query->where('status','denied');
        })
        ->when($request?->ads_type === 'running',function($query){
            $query->valid();
        })
        ->when($request?->ads_type === 'approved',function($query){
            $query->approved();
        })
        ->when($request?->ads_type === 'expired',function($query){
            $query->expired();
        })
        ->when($request?->search ,function($query)use($key) {
            foreach ($key as $value) {
                $query->where('id', 'like', "%{$value}%");
            };
        })
        ->latest()
        ->paginate(config('default_pagination'));

        return view("vendor-views.advertisement.list",compact('adds','total_adds'));
    }


    public function create()
    {
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $store =  Helpers::get_store_data();

        $review = (int) $store->reviews_comments()->count();
        $reviewsInfo = $store->reviews()
        ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
        ->groupBy('items.store_id')
        ->first();
        $rating = (float)  $reviewsInfo?->average_rating ?? 0;
        $review=  round($review,1);
        $rating=  round($rating,1);

        return view("vendor-views.advertisement.create",compact('defaultLang','language','review','rating' ));
    }


    public function store(AdvertisementStoreRequest $request)
    {
        $dateRange = $request->dates;
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();


        if (auth('vendor_employee')->check()) {
            $loable_type = 'App\Models\VendorEmployee';
            $logable_id = auth('vendor_employee')->id();
        } elseif (auth('vendor')->check() || request('vendor')) {
            $loable_type = 'App\Models\Vendor';
            $logable_id = auth('vendor')->id();

            if(request('vendor')){
                $logable_id =request('vendor')->id;
            }
        }


        $advertisement = New Advertisement();
        $advertisement->store_id = Helpers::get_store_id();
        $advertisement->add_type = $request->advertisement_type;
        $advertisement->title = $request->title[array_search('default', $request->lang)];
        $advertisement->description = $request->description[array_search('default', $request->lang)];
        $advertisement->start_date = $startDate;
        $advertisement->end_date = $endDate;
        $advertisement->priority = null;
        $advertisement->is_rating_active = $request->advertisement_type == 'store_promotion' ?  $request?->rating ?? 0 : 0;
        $advertisement->is_review_active = $request->advertisement_type == 'store_promotion' ?  $request?->review ?? 0 : 0;
        $advertisement->is_paid =  0 ;
        $advertisement->created_by_id = $logable_id;
        $advertisement->created_by_type = $loable_type;
        $advertisement->status = 'pending';

        $advertisement->cover_image = $request->has('cover_image') &&  $request->advertisement_type == 'store_promotion' ?  Helpers::upload(dir: 'advertisement/', format:$request->file('cover_image')->getClientOriginalExtension(), image:$request->file('cover_image')) : null;
        $advertisement->profile_image = $request->has('profile_image') &&  $request->advertisement_type == 'store_promotion' ?  Helpers::upload(dir: 'advertisement/', format:$request->file('profile_image')->getClientOriginalExtension(), image:$request->file('profile_image')) : null;
        $advertisement->video_attachment = $request->has('video_attachment') &&  $request->advertisement_type == 'video_promotion' ?  Helpers::upload(dir: 'advertisement/', format:$request->file('video_attachment')->getClientOriginalExtension(), image:$request->file('video_attachment')) : null;
        $advertisement->save();
        $advertisement->module_id= $advertisement->store?->module?->id;
        $advertisement->module_type= $advertisement->store?->module?->module_type;
        $advertisement->save();
        Helpers::add_or_update_translations(request: $request, key_data:'title' , name_field:'title' , model_name: 'Advertisement' ,data_id: $advertisement->id,data_value: $advertisement->title);

        Helpers::add_or_update_translations(request: $request, key_data:'description' , name_field:'description' , model_name: 'Advertisement' ,data_id: $advertisement->id,data_value: $advertisement->description);
        try {
            if(SendNotification::canSendMail('new_advertisement_mail_status_admin', 'admin', 'advertisement_add')){
                SendNotification::mail(Admin::where('role_id', 1)->first()?->getRawOriginal('email'), new \App\Mail\AdminAdversitementMail($advertisement?->store?->name,'new_advertisement' ,$advertisement->id));
        }
        } catch (\Throwable $th) {
            Log::warning('vendor.advertisement_controller.store_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }

        return response()->json(['type'=> 'vendor' ,'message'=>translate('Added successfully') ], 200);

    }


    public function show($advertisement,Request $request)
    {
        $request_page_type=$request?->request_page_type ?? null;
        $nextId = Advertisement::where('store_id',Helpers::get_store_id())->where('id', '>', $advertisement)
        ->when($request_page_type == 'update-requests' , function($query){
            $query->where('is_updated',1)->whereNotIn('status' ,['pending']);
        })
        ->when($request_page_type == 'denied-requests' , function($query){
            $query->whereIn('status' ,['denied']);
        })
        ->when($request_page_type == 'pending-requests' , function($query){
            $query->where('is_updated',0)->whereIn('status' ,['pending']);
        })
        ->min('id');
        $previousId = Advertisement::where('store_id',Helpers::get_store_id())->where('id', '<', $advertisement)
        ->when($request_page_type == 'update-requests' , function($query){
            $query->where('is_updated',1)->whereNotIn('status' ,['pending']);
        })
        ->when($request_page_type == 'denied-requests' , function($query){
            $query->whereIn('status' ,['denied']);
        })
        ->when($request_page_type == 'pending-requests' , function($query){
            $query->where('is_updated',0)->whereIn('status' ,['pending']);
        })
        ->max('id');
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());

        $advertisement= Advertisement::where('store_id',Helpers::get_store_id())->where('id',$advertisement)->withStorage()->with('store.storage')->withoutGlobalScope('translate')->with('translations')->firstOrFail();
        return view("vendor-views.advertisement.details",compact('advertisement','nextId','previousId','request_page_type','language','defaultLang'));
    }

    public function edit(Request $request, $advertisement)
    {
        $advertisement =Advertisement::where('store_id',Helpers::get_store_id())->withoutGlobalScope('translate')->withStorage()->with('translations')->where('id',$advertisement)->with('store.storage')->firstOrFail();
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $request_page_type=$request?->request_page_type ;
        $store =  Helpers::get_store_data();

        $review = (int) $store->reviews_comments()->count();
        $reviewsInfo = $store->reviews()
        ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
        ->groupBy('items.store_id')
        ->first();
        $rating = (float)  $reviewsInfo?->average_rating ?? 0;
        $review=  round($review,1);
        $rating=  round($rating,1);


        // edit.blade.php asked Request::is('.../copy-advertisement/*') on four lines
        $is_copy = $request->is('vendor-panel/advertisement/copy-advertisement/*');

        return view("vendor-views.advertisement.edit",compact('advertisement','request_page_type','language','defaultLang','review','rating','is_copy' ));
    }

    public function status(Request $request)
    {

        $request->validate([
            'pause_note' => ['required_if:status,paused', new WordValidation],
            'cancellation_note' => ['required_if:status,denied', new WordValidation],
        ]);

        $advertisement =Advertisement::where('id',$request->id)->where('store_id', Helpers::get_store_id())->with('store')->firstOrFail();
        // A vendor pauses its own ad and resumes a paused one. "approved" is otherwise the admin's
        // call -- setting it on a pending or denied ad skipped review.
        $allowed = $request->status === 'paused' || ($request->status === 'approved' && $advertisement->status === 'paused');
        $advertisement->status = $allowed ? $request->status : $advertisement->status;
        $advertisement->pause_note = $request?->pause_note ?? null;
        $advertisement->cancellation_note = $request?->cancellation_note ?? null;
        $advertisement?->save();
        if( $request->status == 'paused'){
            $email_type='advertisement_pause';
            Toastr::success( translate('Updated successfully'));
        }
        elseif($request->status == 'approved' && $request?->approved == null){
            $email_type='advertisement_resume';
            Toastr::success(translate('Updated successfully'));
        }


        return back();
    }


    public function update(AdvertisementUpdateRequest $request, Advertisement $advertisement)
    {
        $this->authorizeAdvertisement($advertisement);
        $dateRange = $request->dates;
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();



        $advertisement->title = $request->title[array_search('default', $request->lang)];
        $advertisement->description = $request->description[array_search('default', $request->lang)];
        $advertisement->start_date = $startDate;
        $advertisement->end_date = $endDate;
        $advertisement->is_rating_active = $request->advertisement_type == 'store_promotion' ?  $request?->rating ?? 0 : 0;
        $advertisement->is_review_active = $request->advertisement_type == 'store_promotion' ?  $request?->review ?? 0 : 0;

        $advertisement->is_updated = $advertisement->status == 'pending' ? 0 : 1;
        $advertisement->status = 'pending';

        if( $advertisement->add_type != $request->advertisement_type){
            if($request->advertisement_type == 'video_promotion' &&  !$request->has('video_attachment')){
                return response([ 'file_required' => 1 , 'message' => translate('You must need to add a promotional video file')], 200);
            }

            if($request->advertisement_type == 'store_promotion' &&  (!$request->has('cover_image') || !$request->has('profile_image'))  ){
                return response([ 'file_required' => 1 , 'message' => translate('You must need to add cover & profile image')], 200);
            }

            if($advertisement->cover_image && $request->advertisement_type == 'video_promotion')
            {
                Helpers::check_and_delete('advertisement/' , $advertisement->cover_image);
            }
            if($advertisement->profile_image && $request->advertisement_type == 'video_promotion')
            {
                Helpers::check_and_delete('advertisement/' , $advertisement->profile_image);
            }
            if($advertisement->video_attachment && $request->advertisement_type == 'store_promotion')
            {
                Helpers::check_and_delete('advertisement/' , $advertisement->video_attachment);
            }
        }

        $advertisement->add_type = $request->advertisement_type;
        $advertisement->cover_image = $request->has('cover_image') &&  $request->advertisement_type == 'store_promotion' ? Helpers::update(dir:'advertisement/', old_image: $advertisement->cover_image, format:$request->file('cover_image')->getClientOriginalExtension(), image: $request->file('cover_image')) : $advertisement->cover_image;
        $advertisement->profile_image = $request->has('profile_image') &&  $request->advertisement_type == 'store_promotion' ? Helpers::update(dir:'advertisement/', old_image: $advertisement->profile_image, format:$request->file('profile_image')->getClientOriginalExtension(), image: $request->file('profile_image')) : $advertisement->profile_image;
        $advertisement->video_attachment = $request->has('video_attachment') &&  $request->advertisement_type == 'video_promotion' ? Helpers::update(dir:'advertisement/', old_image: $advertisement->video_attachment, format:$request->file('video_attachment')->getClientOriginalExtension(), image: $request->file('video_attachment')) : $advertisement->video_attachment;


        $advertisement->save();
        Helpers::add_or_update_translations(request: $request, key_data:'title' , name_field:'title' , model_name: 'Advertisement' ,data_id: $advertisement->id,data_value: $advertisement->title);
        Helpers::add_or_update_translations(request: $request, key_data:'description' , name_field:'description' , model_name: 'Advertisement' ,data_id: $advertisement->id,data_value: $advertisement->description);

        try {
            if(SendNotification::canSendMail('update_advertisement_mail_status_admin', 'admin', 'advertisement_update')){
                    SendNotification::mail(Admin::where('role_id', 1)->first()?->getRawOriginal('email'), new \App\Mail\AdminAdversitementMail($advertisement?->store?->name,'update_advertisement' ,$advertisement->id));
            }
        } catch (\Throwable $th) {
            Log::warning('vendor.advertisement_controller.update_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }
        return response()->json(['message' => translate('Updated successfully')], 200);
    }





    public function destroy(string $id)
    {
        $advertisement =Advertisement::where('store_id',Helpers::get_store_id())->where('id',$id)->first();

        if($advertisement?->cover_image)
        {
            Helpers::check_and_delete('advertisement/' , $advertisement->cover_image);
        }
        if($advertisement?->profile_image)
        {
            Helpers::check_and_delete('advertisement/' , $advertisement->profile_image);
        }
        if($advertisement?->video_attachment)
        {
            Helpers::check_and_delete('advertisement/' , $advertisement->video_attachment);
        }
        $advertisement?->translations()?->delete();
        $module_id =$advertisement?->module_id;

        $advertisement?->delete();

        $adds=Advertisement::whereNotNull('priority')->where('module_id',$module_id)
        ->orderByRaw('ISNULL(priority), priority ASC')->get();

        $newPriority = 1;
        foreach ($adds as $advertisement) {
            $advertisement->priority = $newPriority++;
            $advertisement->save();
        }
        Toastr::success(translate('Deleted successfully'));
        return back();
    }



    public function copyAdd(Request $request, Advertisement $advertisement)
    {
        $this->authorizeAdvertisement($advertisement);
        $advertisement->loadMissing(['storage', 'store.storage']);

        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $request_page_type=$request?->request_page_type ;
        $store =  Helpers::get_store_data();

        $review = (int) $store->reviews_comments()->count();
        $reviewsInfo = $store->reviews()
        ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
        ->groupBy('items.store_id')
        ->first();
        $rating = (float)  $reviewsInfo?->average_rating ?? 0;
        $review=  round($review,1);
        $rating=  round($rating,1);


        // edit.blade.php asked Request::is('.../copy-advertisement/*') on four lines
        $is_copy = $request->is('vendor-panel/advertisement/copy-advertisement/*');

        return view("vendor-views.advertisement.edit",compact('advertisement','request_page_type','language','defaultLang','review','rating','is_copy' ));

    }




    public function copyAddPost(Advertisement $advertisement , AdvertisementUpdateRequest $request)
    {
        $this->authorizeAdvertisement($advertisement);

        if (auth('vendor_employee')->check()) {
            $loable_type = 'App\Models\VendorEmployee';
            $logable_id = auth('vendor_employee')->id();
        } elseif (auth('vendor')->check() || request('vendor')) {
            $loable_type = 'App\Models\Vendor';
            $logable_id = auth('vendor')->id();

            if(request('vendor')){
                $logable_id =request('vendor')->id;
            }
        }

            $dateRange = $request->dates;
            list($startDate, $endDate) = explode(' - ', $dateRange);
            $startDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($startDate));
            $endDate = \Carbon\Carbon::createFromFormat('m/d/Y', trim($endDate));
            $startDate = $startDate->startOfDay();
            $endDate = $endDate->endOfDay();


            $newAdvertisement = New Advertisement();


            $newAdvertisement->store_id = Helpers::get_store_id();
            $newAdvertisement->add_type = $request->advertisement_type;
            $newAdvertisement->title = $request->title[array_search('default', $request->lang)];
            $newAdvertisement->description = $request->description[array_search('default', $request->lang)];
            $newAdvertisement->start_date = $startDate;
            $newAdvertisement->end_date = $endDate;
            $newAdvertisement->priority = null;
            $newAdvertisement->is_rating_active = $request->advertisement_type == 'store_promotion' ?  $request?->rating ?? 0 : 0;
            $newAdvertisement->is_review_active = $request->advertisement_type == 'store_promotion' ?  $request?->review ?? 0 : 0;
            $newAdvertisement->is_paid =  0 ;
            $newAdvertisement->created_by_id = $logable_id;
            $newAdvertisement->created_by_type = $loable_type;
            $newAdvertisement->status = 'pending';





        if($request->advertisement_type == 'store_promotion' ){
            if($request->has('cover_image')){
                $newAdvertisement->cover_image =  Helpers::upload(dir: 'advertisement/', format:$request->file('cover_image')->getClientOriginalExtension(), image:$request->file('cover_image'));
            } else{
                $newAdvertisement->cover_image =$this->copyAttachment($advertisement ,'cover_image');
            }
            if($request->has('profile_image')){
                $newAdvertisement->profile_image =  Helpers::upload(dir: 'advertisement/', format:$request->file('profile_image')->getClientOriginalExtension(), image:$request->file('profile_image'));
            } else{
                $newAdvertisement->profile_image =$this->copyAttachment($advertisement ,'profile_image');
            }

        }

        if($request->advertisement_type == 'video_promotion' ){
            if($request->has('video_attachment')){
                $newAdvertisement->video_attachment =  Helpers::upload(dir: 'advertisement/', format:$request->file('video_attachment')->getClientOriginalExtension(), image:$request->file('video_attachment'));
            } else{
                $newAdvertisement->video_attachment =$this->copyAttachment($advertisement ,'video_attachment');
            }
        }

            $newAdvertisement->save();
            $newAdvertisement->module_id= $newAdvertisement->store?->module?->id;
            $newAdvertisement->module_type= $newAdvertisement->store?->module?->module_type;
            $newAdvertisement->save();

            Helpers::add_or_update_translations(request: $request, key_data:'title' , name_field:'title' , model_name: 'Advertisement' ,data_id: $newAdvertisement->id,data_value: $newAdvertisement->title);
            Helpers::add_or_update_translations(request: $request, key_data:'description' , name_field:'description' , model_name: 'Advertisement' ,data_id: $newAdvertisement->id,data_value: $newAdvertisement->description);

            try {
                if(SendNotification::canSendMail('new_advertisement_mail_status_admin', 'admin', 'advertisement_add')){
                    SendNotification::mail(Admin::where('role_id', 1)->first()?->getRawOriginal('email'), new \App\Mail\AdminAdversitementMail($advertisement?->store?->name,'new_advertisement' ,$advertisement->id));
            }
            } catch (\Throwable $th) {
                Log::warning('vendor.advertisement_controller.copy_add_post_failed', [
                    'error' => $th->getMessage(),
                    'file' => $th->getFile().':'.$th->getLine(),
                ]);
            }


            return response()->json(['message' => translate('messages.Advertisement Copied Successfully')], 200);

    }



    private function copyAttachment($attachment , $fileKeyName)
    {

        $oldDisk = 'public';
            if ($attachment->storage && count($attachment->storage) > 0) {
                foreach ($attachment->storage as $value) {
                    if ($value['key'] == $fileKeyName) {
                        $oldDisk = $value['value'];
                        }
                }
            }
                    $oldPath = "advertisement/{$attachment->{$fileKeyName}}";
                    $newFileName =Carbon::now()->toDateString() . "-" . uniqid() . '.'.explode('.',$attachment->{$fileKeyName})[1];
                    $newPath = "advertisement/{$newFileName}";
                    $dir = 'advertisement/';
                    $newDisk = Helpers::getDisk();

            try{
                if (Storage::disk($oldDisk)->exists($oldPath)) {
                    if (!Storage::disk($newDisk)->exists($dir)) {
                        Storage::disk($newDisk)->makeDirectory($dir);
                    }
                    $fileContents = Storage::disk($oldDisk)->get($oldPath);
                    Storage::disk($newDisk)->put($newPath, $fileContents);
                }
            } catch (\Exception $e) {
                Log::warning('vendor.advertisement_controller.copy_attachment_failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ]);
            }

            return $newFileName ?? null;

    }
}
