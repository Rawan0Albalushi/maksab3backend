<?php

namespace App\Http\Controllers;

use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\BusinessSetting;
use Gregwar\Captcha\CaptchaBuilder;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Support\Notification\SendNotification;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\Log;

class DeliveryManController extends Controller
{

    public function create()
    {
        $status = BusinessSetting::where('key', 'toggle_dm_registration')->first();
        if(!isset($status) || $status->value == '0')
        {
            Toastr::error(translate('No data found'));
            return back();
        }

        $custome_recaptcha = new CaptchaBuilder;
        $custome_recaptcha->build();
        Session::put('six_captcha', $custome_recaptcha->getPhrase());

        return view('dm-registration', compact('custome_recaptcha'));
    }

    public function store(Request $request)
    {
        $status = BusinessSetting::where('key', 'toggle_dm_registration')->first();
        if(!isset($status) || $status->value == '0')
        {
            Toastr::error(translate('No data found'));
            return back();
        }


        if($request->referral_code){
            $referal_user = DeliveryMan::where('ref_code',$request->referral_code)->first();
            if (!$referal_user || !$referal_user->status) {
                    Toastr::error(translate('Referer code not found'));
                    return back()->withInput();
            }
            Helpers::deliverymanReferralNotification($referal_user);
        }


        $recaptcha = Helpers::get_business_settings('recaptcha');
        if (isset($recaptcha) && $recaptcha['status'] == 1) {
            $request->validate([
                'g-recaptcha-response' => [
                    function ($attribute, $value, $fail) {
                        $secret_key = Helpers::get_business_settings('recaptcha')['secret_key'];
                        $gResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret' => $secret_key,
                            'response' => $value,
                            'remoteip' => \request()->ip(),
                        ]);

                        if (!$gResponse->successful()) {
                            $fail(translate('ReCaptcha Failed'));
                        }
                    },
                ],
            ]);
        } else if(session('six_captcha') != $request->custome_recaptcha)
        {
            Toastr::error(translate('reCAPTCHA failed'));
            return back()->withInput();
        }

        $request->validate([
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => EmailAddress::rules('required', 'delivery_men'),
            'phone' => PhoneNumber::rules('required', 'delivery_men'),
            'zone_id' => 'required',
            'vehicle_id' => 'required',
            'earning' => 'required',
            'password' => StrongPassword::rules('required'),
        ], [
            'f_name.required' => translate('messages.First name is required'),
            'zone_id.required' => translate('messages.Select a zone'),
            'vehicle_id.required' => translate('messages.Select a vehicle'),
            'earning.required' => translate('Select deliveryman type')
        ]);

        if ($request->has('image')) {
            $image_name = FileStorage::upload('delivery-man/', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = FileStorage::upload('delivery-man/', $img);
                array_push($id_img_names, ['img'=>$identity_image, 'storage'=> FileStorage::getDisk()]);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        $dm = New DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->identity_number = $request->identity_number;
        $dm->identity_type = $request->identity_type;
        $dm->vehicle_id = $request->vehicle_id;
        $dm->zone_id = $request->zone_id;
        $dm->identity_image = $identity_image;
        $dm->image = $image_name;
        $dm->active = 0;
        $dm->earning = $request->earning;
        $dm->password = bcrypt($request->password);
        $dm->application_status= 'pending';
        $dm->ref_by= $request->earning ? $referal_user?->id ?? null : null;
        $dm->ref_code = Helpers::generate_referer_code('deliveryman');
        $dm->save();

        try{
            $admin= Admin::where('role_id', 1)->first();

            if(SendNotification::canSendMail('registration_mail_status_dm', 'deliveryman', 'deliveryman_registration')  ){
                SendNotification::mail($request->email, new \App\Mail\DmSelfRegistration('pending', $dm));
            }
            if(SendNotification::canSendMail('dm_registration_mail_status_admin', 'admin', 'deliveryman_self_registration')) {
                SendNotification::mail($admin?->getRawOriginal('email'), new \App\Mail\DmRegistration('pending', $dm));
            }
        }catch(\Exception $ex){
            Log::error('delivery_man_controller.store_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
        }
        Toastr::success(translate('messages.Application placed successfully'));
        return redirect()->route('home');
    }
}
