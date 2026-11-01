<?php

namespace App\Traits\Order;

use App\CentralLogics\Helpers;
use App\Mail\CustomerRegistration;
use App\Models\User;
use App\Services\Customer\UserService;
use App\Services\Order\CartService;
use App\Support\Notification\SendNotification;

trait GuestAccountTrait
{
    protected function createNewUser($request)
    {
        if (!$request->create_new_user) {
            return false;
        }

        $createsAt = ['tenant_id' => 0, 'sub_tenant_id' => 0];

        $validationError = match (true) {
            !$request->password => [
                'status_code' => 403,
                'message'     => translate('messages.The password is required'),
                'code'        => 'password',
            ],
            app(UserService::class)->existsUnscoped('phone', $request->contact_person_number, $createsAt) => [
                'status_code' => 403,
                'message'     => translate('messages.Phone already taken'),
                'code'        => 'phone_person_email',
            ],
            app(UserService::class)->existsUnscoped('email', $request->contact_person_email, $createsAt) => [
                'status_code' => 403,
                'message'     => translate('messages.Email already taken'),
                'code'        => 'contact_person_email',
            ],
            default => null,
        };

        if ($validationError) {
            return $validationError;
        }

        $user = new User();
        $user->f_name = $request->contact_person_name;
        $user->email = $request->contact_person_email;
        $user->phone = $request->contact_person_number;
        $user->password = bcrypt($request->password);
        $user->ref_code = Helpers::generate_referer_code($user);
        $user->login_medium = 'manual';
        $user->tenant_id     = $createsAt['tenant_id'];
        $user->sub_tenant_id = $createsAt['sub_tenant_id'];
        $user->save();

        try {
            if (SendNotification::canSendMail('registration_mail_status_user', 'customer', 'customer_registration') && $request->contact_person_email) {
                SendNotification::mail($request->contact_person_email, new CustomerRegistration($request->contact_person_name));
            }
        } catch (\Exception $exception) {
            info('createNewUser' ,[$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }
        if ($request->guest_id  && isset($user->id)) {

            app(CartService::class)->claimGuestRows($request->guest_id, $user->id);
        }

        return ['newUser' => true, 'user' => $user, 'token' => $user->createToken('RestaurantCustomerAuth')->accessToken];
    }
}
