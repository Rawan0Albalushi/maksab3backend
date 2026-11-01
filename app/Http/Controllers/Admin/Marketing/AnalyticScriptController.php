<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\AnalyticScript;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;

class AnalyticScriptController extends Controller
{
    public function analyticSetup()
    {
        $analytics =  AnalyticScript::select(['id', 'type', 'script_id','is_active'])->get();
        $analyticsData = [];
        foreach ($analytics as $analytic) {
            $analyticsData[$analytic['type']] = $analytic;
        }
        $analyticsTools= $this->dataArray();
        return view('admin-views.business-settings.analytics.index', compact('analyticsData','analyticsTools'));
    }
    public function analyticUpdate(Request $request)
    {
         $analyticScriptsTypes = ['meta_pixel', 'linkedin_insight', 'tiktok_tag', 'snapchat_tag', 'twitter_tag', 'pinterest_tag', 'google_tag_manager', 'google_analytics'];
        if (!in_array($request->type, $analyticScriptsTypes)) {
            Toastr::error(translate('Update failed'));
            return back();
        }
        $script = AnalyticScript::where('type',$request->type)->firstOrNew();
        $script->name =str_replace(' ', '_', ucwords(str_replace('_', ' ', $request['type'])));
        $script->type = $request->type;
        $script->script_id = $request->script_id;
        $script->save();
        Toastr::success(translate($request->type) . translate('Updated successfully'));
        return back();
    }

    public function analyticStatus(Request $request){
        $script = AnalyticScript::where('type',$request->type)->first();
        if(!$script || !$script->script_id ){
            Toastr::error(translate('Please ensure you have filled in the' . translate($request->type) . '_script_ID.'));
            return back();
        }
        $script->is_active = !$script->is_active;
        $script->save();
        Toastr::success(translate(ucwords(str_replace('_', ' ', $request->type))) .' '. translate('messages.Turned') . ' '.($script->is_active ? translate('on') : translate('off')) .' '. translate('successfully'));
        return back();
    }

    /*
     * 'group' buckets the cards into the two sections the page renders.
     * 'placeholder' is a format example, not copy — it is printed as written
     * and deliberately not routed through translate().
     *
     * 'hint' does go through translate(), but the ones written as a path
     * ("Google Analytics → Admin → Data streams") are rejected by
     * isPersistableTranslationKey(): they name menus in someone else's
     * dashboard, so they render as written and never reach the language file.
     * A hint phrased as a sentence is collected and translated as normal.
     */
    private function dataArray(){
         $analyticsTools = [
            [
                'key' => 'google_analytics',
                'group' => 'analytics',
                'title' => 'Google Analytics',
                'summary' => 'Traffic, sessions and conversion reporting for your storefront.',
                'label' => 'Measurement ID',
                'placeholder' => 'G-XXXXXXXXXX',
                'hint' => 'Google Analytics → Admin → Data streams → your web stream.',
                'modal' => 'modalForGoogleAnalytics',
                'icon' => 'google.svg',
            ],
            [
                'key' => 'google_tag_manager',
                'group' => 'analytics',
                'title' => 'Google Tag Manager',
                'summary' => 'One container to load and manage your other marketing tags.',
                'label' => 'Container ID',
                'placeholder' => 'GTM-XXXXXXX',
                'hint' => 'Shown beside the workspace name at the top of your GTM container.',
                'modal' => 'modalForGoogleTagManager',
                'icon' => 'google.svg',
            ],
            [
                'key' => 'linkedin_insight',
                'group' => 'pixel',
                'title' => 'LinkedIn Insight Tag',
                'summary' => 'Conversion tracking and retargeting for LinkedIn ads.',
                'label' => 'Partner ID',
                'placeholder' => '1234567',
                'hint' => 'LinkedIn Campaign Manager → Analytics → Insight tag.',
                'modal' => 'modalForLinkedInInsight',
                'icon' => 'linkedin.svg',
            ],
            [
                'key' => 'meta_pixel',
                'group' => 'pixel',
                'title' => 'Meta Pixel',
                'summary' => 'Conversion tracking and retargeting for Facebook and Instagram ads.',
                'label' => 'Pixel ID',
                'placeholder' => '123456789012345',
                'hint' => 'Meta Events Manager → Data sources → your pixel.',
                'modal' => 'modalForFacebookMeta',
                'icon' => 'facebook.svg',
            ],
            [
                'key' => 'pinterest_tag',
                'group' => 'pixel',
                'title' => 'Pinterest Pixel',
                'summary' => 'Conversion tracking and retargeting for Pinterest ads.',
                'label' => 'Tag ID',
                'placeholder' => '2612345678901',
                'hint' => 'Pinterest Ads → Conversions → Pinterest tag.',
                'modal' => 'modalForPinterestPixel',
                'icon' => 'pinterest.svg',
            ],
            [
                'key' => 'snapchat_tag',
                'group' => 'pixel',
                'title' => 'Snapchat Pixel',
                'summary' => 'Conversion tracking and retargeting for Snapchat ads.',
                'label' => 'Pixel ID',
                'placeholder' => '00000000-0000-0000-0000-000000000000',
                'hint' => 'Snapchat Ads Manager → Events Manager → your pixel.',
                'modal' => 'modalForSnapchatPixel',
                'icon' => 'snapchat.svg',
            ],
            [
                'key' => 'tiktok_tag',
                'group' => 'pixel',
                'title' => 'TikTok Pixel',
                'summary' => 'Conversion tracking and retargeting for TikTok ads.',
                'label' => 'Pixel ID',
                'placeholder' => 'C4XXXXXXXXXXXXXXXXXX',
                'hint' => 'TikTok Ads Manager → Assets → Events → Web events.',
                'modal' => 'modalForTikTokPixel',
                'icon' => 'tiktok.svg',
            ],
            [
                'key' => 'twitter_tag',
                'group' => 'pixel',
                'title' => 'X (Twitter) Pixel',
                'summary' => 'Conversion tracking and retargeting for X ads.',
                'label' => 'Pixel ID',
                'placeholder' => 'oXXXXX',
                'hint' => 'X Ads → Tools → Events manager → your pixel.',
                'modal' => 'modalForTwitterPixel',
                'icon' => 'twitter.svg',
            ],
        ];
        return $analyticsTools;
    }

}
