<?php

namespace App\Http\Controllers\Admin;

use App\Rules\ImageFile;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ParcelCategory;
use App\Models\Translation;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class ParcelCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $module_id = Config::get('module.current_module_id');
        $taxData = Helpers::getTaxSystemType(getTaxVatList: true, tax_payer: 'parcel');
        $categoryWiseTax = $taxData['categoryWiseTax'];
        $parcel_categories = ParcelCategory::
        when($module_id, function($query)use($module_id){
            $query->Module($module_id);
        })
        ->with($categoryWiseTax ? ['module', 'taxVats.tax'] : ['module'])
        ->orderBy('name')->paginate(config('default_pagination'));
        $taxVats = $taxData['taxVats'];

        return view('admin-views.parcel.category.index',compact('parcel_categories','categoryWiseTax','taxVats'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required|array',
            'name.0'=>'unique:parcel_categories,name',
            'name.*'=>'max:191|unique:parcel_categories,name',
            'image' => ImageFile::rules('required'),
            'description'=>'required|array',
            'description.0'=>'required',
            // One charge now, and it is ADDITIONAL (owner decision 2026-09-03). Nullable rather
            // than required: a category that adds nothing is a legitimate configuration.
            'charge'=>'nullable|numeric|min:0',
            'name.0' => 'required',
            'description.0' => 'required',
        ],[
            'name.0.required'=>translate('Default name is required'),
            'description.0.required'=>translate('Default description is required'),
        ]);

        $parcel_category = new ParcelCategory;
        $parcel_category->module_id = Config::get('module.current_module_id');
        $parcel_category->name = $request->name[array_search('default', $request->lang)];
        $parcel_category->description =  $request->description[array_search('default', $request->lang)];
        $parcel_category->image = Helpers::upload('parcel_category/', 'png', $request->file('image'));
        // The two deprecated columns are no longer written. They keep whatever they held so a
        // rollback loses nothing, and nothing reads them.
        $parcel_category->charge = (float) ($request->charge ?? 0);
        $parcel_category->save();

        if(addon_published_status('TaxModule')){
                $SystemTaxVat= \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active',1)->where('tax_payer','parcel')->where('is_default',1)->first();
                if($SystemTaxVat?->tax_type == 'category_wise'){

                    foreach($request['tax_ids'] ?? [] as $tax_ids){
                        \Modules\TaxModule\Entities\Taxable::create(
                                    [
                                        'taxable_type' => ParcelCategory::class,
                                        'taxable_id' => $parcel_category->id,
                                        'system_tax_setup_id' => $SystemTaxVat->id
                                        ,'tax_id' => $tax_ids
                                    ],
                                );
                    }

                }
            }

        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->name[$index])){
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelCategory',
                        'translationable_id' => $parcel_category->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $parcel_category->name,
                    ));
                }
            }else{
                if ($request->name[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelCategory',
                        'translationable_id' => $parcel_category->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $request->name[$index],
                    ));
                }
            }

            if($default_lang == $key && !($request->description[$index])){
                if (isset($parcel_category->description) && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelCategory',
                        'translationable_id' => $parcel_category->id,
                        'locale' => $key,
                        'key' => 'description',
                        'value' => $parcel_category->description,
                    ));
                }
            }else{
                if ($request->description[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelCategory',
                        'translationable_id' => $parcel_category->id,
                        'locale' => $key,
                        'key' => 'description',
                        'value' => $request->description[$index],
                    ));
                }
            }
        }
        Translation::insert($data);

        Toastr::success(translate('Added successfully'));
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $parcel_category= ParcelCategory::withoutGlobalScope('translate')->with('translations')->findOrFail($id);

        $taxData = Helpers::getTaxSystemType(getTaxVatList: true, tax_payer: 'parcel');
        $categoryWiseTax = $taxData['categoryWiseTax'];
        $taxVats = $taxData['taxVats'];
        $taxVatIds =  $categoryWiseTax ? $parcel_category->taxVats()->pluck('tax_id')->toArray(): [];
        return view('admin-views.parcel.category.edit',compact('parcel_category','categoryWiseTax','taxVats','taxVatIds'));
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
        $request->validate([
            'name'=>'required|array',
            'name.0'=>'unique:parcel_categories,name,'.$id,
            'name.*'=>'max:191',
            'description'=>'required|array',
            // One charge now, and it is ADDITIONAL (owner decision 2026-09-03). Nullable rather
            // than required: a category that adds nothing is a legitimate configuration.
            'charge'=>'nullable|numeric|min:0',
            'name.0' => 'required',
            'description.0' => 'required',
        ],[
            'name.0.required'=>translate('Default name is required'),
            'description.0.required'=>translate('Default description is required'),
        ]);

        $parcel_category = ParcelCategory::findOrFail($id);
        $parcel_category->name = $request->name[array_search('default', $request->lang)];
        $parcel_category->description =  $request->description[array_search('default', $request->lang)];
        $parcel_category->image = Helpers::update('parcel_category/', $parcel_category->image, 'png', $request->file('image'));
        // The two deprecated columns are no longer written. They keep whatever they held so a
        // rollback loses nothing, and nothing reads them.
        $parcel_category->charge = (float) ($request->charge ?? 0);
        $parcel_category->save();
       if(addon_published_status('TaxModule') && $parcel_category['position'] == 0){
            $taxVatIds = $parcel_category->taxVats()->pluck('tax_id')->toArray() ?? [];
            $newTaxVatIds =  array_map('intval', $request['tax_ids'] ?? []);
            sort($newTaxVatIds);
            sort($taxVatIds);
                if( $newTaxVatIds != $taxVatIds ){
                    $parcel_category->taxVats()->delete();
                $SystemTaxVat= \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active',1)->where('tax_payer','parcel')->where('is_default',1)->first();
                    if($SystemTaxVat?->tax_type == 'category_wise'){
                        foreach($request['tax_ids'] ?? [] as $tax_ids){
                            \Modules\TaxModule\Entities\Taxable::create(
                                        [
                                            'taxable_type' =>ParcelCategory::class,
                                            'taxable_id' => $parcel_category->id,
                                            'system_tax_setup_id' => $SystemTaxVat->id
                                            ,'tax_id' => $tax_ids
                                        ],
                                    );
                        }

                    }
                }
            }
        $default_lang = str_replace('_', '-', app()->getLocale());

        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->name[$index])){
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelCategory',
                            'translationable_id' => $parcel_category->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $parcel_category->name]
                    );
                }
            }else{

                if ($request->name[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelCategory',
                            'translationable_id' => $parcel_category->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $request->name[$index]]
                    );
                }
            }
            if($default_lang == $key && !($request->description[$index])){
                if (isset($parcel_category->description) && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelCategory',
                            'translationable_id' => $parcel_category->id,
                            'locale' => $key,
                            'key' => 'description'
                        ],
                        ['value' => $parcel_category->description]
                    );
                }

            }else{

                if ($request->description[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelCategory',
                            'translationable_id' => $parcel_category->id,
                            'locale' => $key,
                            'key' => 'description'
                        ],
                        ['value' => $request->description[$index]]
                    );
                }
            }
        }

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $parcel_category = ParcelCategory::findOrFail($id);
        if($parcel_category->image)
        {
            Helpers::check_and_delete('parcel_category/' , $parcel_category['image']);
        }
        $parcel_category?->taxVats()->delete();
        $parcel_category->translations()->delete();
        $parcel_category->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function status(Request $request)
    {
        $parcel_category = ParcelCategory::findOrFail($request->id);
        $parcel_category->status = $request->status;
        $parcel_category->save();
        Toastr::success(translate('messages.Parcel category status updated'));
        return back();
    }
}
