<?php
namespace App\Http\Controllers;
use App\Models\Newsletter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
class NewsletterController extends Controller
{
    public function newsLetterSubscribe(Request $request)
    {
        $emailCount = Newsletter::where('email', $request->email)->count();
        if ($emailCount) {
            Toastr::warning(translate('messages.Subscription exist'));
            return back();
        } else {
            Newsletter::create($request->all());
            Toastr::success(translate('Subscription successful'));
            return back();
        }
    }
}
