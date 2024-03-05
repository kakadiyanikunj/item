<?php

namespace App\Http\Controllers\AssetManagment;

use Exception;
use Illuminate\Http\Request;
use App\Traits\CommonFunctions;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Support\Renderable;
use App\Models\AssetItemMaster;
use App\Models\AssetGroupMaster;
use Illuminate\Support\Facades\Validator;

class AssetItemMasterController extends Controller
{
    use CommonFunctions;

    /* public function __construct()
    {
        $this->middleware('permission:recruiter-asset-management', ['only' => ['index', 'edit', 'create', 'store', 'destroy']]);
    } */

    public function index(Request $request)
    {
        try {
            if(auth('candidate')->check()){
                $recruiter_id = \Helpers::get_recruiter_id(auth('candidate')->id());
                }else{
                $recruiter_id = auth('recruiter')->user()->id;
                }
            if ($request->ajax()) {
                try {
                    $columns = ['id','image','ag.name', 'ac.name', 'asc.name', 'ai.item_code', 'ai.name', 'ai.brand', 'ai.color', 'ai.size', 'ai.unit', 'ai.qnty', 'ai.min_stock_alert', 'status', 'ai.created_at'];
                    $skip = $request->get('start');
                    $take = $request->get('length');
                    $search = $request->input('search.value');
                    $order = $columns[$request->input('order.0.column')];
                    $dir = $request->input('order.0.dir');

                    $data = AssetItemMaster::from('recruiter_asset_items as ai')
                        ->join('recruiter_asset_group as ag', 'ai.asset_group_id', '=', 'ag.id')
                        ->join('recruiter_asset_categories as ac', 'ai.asset_category_id', 'ac.id')
                        ->join('recruiter_asset_sub_categories as asc', 'ai.asset_sub_category_id', '=', 'asc.id')
                        ->select('ai.*', 'ac.name as category_name', 'asc.name as sub_category_name', 'ag.name as group_name');
                    if (Auth::guard('recruiter')->check() || auth('candidate')->check()) {
                        $data = $data->where('ai.recruiter_id', $recruiter_id);
                    }
                    $data = $data->where(function ($query) use ($search) {
                        if (!empty($search)) {
                            $query->orWhere('ai.name', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ag.name', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ac.name', 'LIKE', '%' . $search . '%');
                            $query->orWhere('asc.name', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.brand', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.color', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.size', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.unit', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.qnty', 'LIKE', '%' . $search . '%');
                            $query->orWhere('ai.min_stock_alert', 'LIKE', '%' . $search . '%');
                            $query->orWhere(DB::raw("DATE_FORMAT(ai.created_at,'%d-%m-%Y')"), "like", "%" . $search . "%");
                        }
                    });
                    $recordsTotal = $data->count();
                    if (!empty($search)) {
                        $recordsFiltered = $data->count();
                    } else {
                        $recordsFiltered = $recordsTotal;
                    }
                    $data = $data->skip($skip)->take($take)->orderBy($order, $dir)->get();
                    $table_data = [];
                    if (!empty($data)) {
                        $no = $skip + 1;
                        foreach ($data as $item) {
                            $status = '';
                            if ($item->status == "0") {
                                $status = '<span class="update-status badge bg-danger text-white">In Active</span>';
                            } else {
                                $status = '<span class="update-status badge bg-success text-white">Active</span>';
                            }
                            $table_data[] = [
                                'DT_RowIndex' => $no,
                                'name' => $item->name,
                                'group_name' => $item->group_name,
                                'category_name' => $item->category_name,
                                'sub_category_name' => $item->sub_category_name,
                                'item_code' => $item->item_code,
                                'brand' => $item->brand,
                                'color' => $item->color,
                                'size' => $item->size,
                                'unit' => $item->unit,
                                'qnty' => $item->qnty,
                                'min_stock_alert' => $item->min_stock_alert,
                                'image' => $item->image,
                                'status' => $status,
                                'created_at' => date('d-m-Y', strtotime($item->created_at)),
                                'id' => encrypt($item->id),
                            ];
                            $no++;
                        }
                    }

                    return response()->json([
                        "draw" => intval(request('draw')),
                        "recordsTotal" => intval($recordsTotal),
                        "recordsFiltered" => intval($recordsFiltered),
                        "data" => $table_data
                    ]);
                } catch (Exception $e) {
                    Log::error("AssetItemMasterController.php : index() : Exception ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
                    return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
                }
            }

            return view('asset-managment.items.index');
        } catch (Exception $e) {
            Log::error("AssetItemMasterController.php : index() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function create()
    {
        try {
            if(auth('candidate')->check()){
                $recruiter_id = \Helpers::get_recruiter_id(auth('candidate')->id());
                }else{
                $recruiter_id = auth('recruiter')->user()->id;
                }
            $data['group'] = AssetGroupMaster::where('recruiter_id', $recruiter_id)->where('status', '1')->pluck('name', 'id');
            $data['items'] = new AssetItemMaster();
            $data['title'] = "Add Items";
            return view('asset-managment.items.createOrUpdate', $data);
        } catch (Exception $e) {
            Log::error("AssetItemMasterController.php : create() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function store(Request $request)
    {

        try {
            if(auth('candidate')->check()){
                $recruiter_id = \Helpers::get_recruiter_id(auth('candidate')->id());
                }else{
                $recruiter_id = auth('recruiter')->user()->id;
                }
            $validateArray = [
                'name' => 'required',
                'asset_group_id' => 'required',
                'asset_sub_category_id' => 'required',
                'asset_category_id' => 'required',
                'brand' => 'required',
                'color' => 'required',
                'size' => 'required',
                'unit'=>'required',
                'min_stock_alert' => 'required',
                'image' => 'mimes:jpeg,png,jpg,gif|max_mb:2',
            ];
            $validateMessage = [
                'name.required' => 'Name is required.',
                'asset_group_id.required' => 'Group is required.',
                'asset_sub_category_id.required' => 'Sub category is required.',
                'asset_category_id.required' => 'Category is required.',
                'brand.required' => 'Brand is required.',
                'color.required' => 'Color is required',
                'size.required' => 'Size is required.',
                'unit.required' => 'Unit is required.',
                'min_stock_alert.required' => 'Min stock alert is required.',
                'image.max_mb' => 'File size must be less than 2 MB.',
                'image.mimes' => "You're only allowed to upload jpeg or jpg or png or gif images.",
            ];

            $validator = Validator::make($request->all(), $validateArray, $validateMessage);
            if ($validator->fails()) {
                Log::error('LoginController.php : store() : Validation error occurred.', ['fails' => $validator->fails(), 'errors' => $validator->errors()->all(), 'request' => $request]);
                return redirect()->back()->withErrors($validator->errors());
            }


            $group_id = $request->asset_group_id;
            $category_id = sprintf("%03d", $request->asset_category_id);
            $sub_category_id = sprintf("%03d", $request->asset_sub_category_id);
            $brand = strtoupper(substr($request->brand, 0, 2));


            $icon = "";
            $data = array();
            if ($request->id) {
                $data = AssetItemMaster::find($request->id);
                $icon = $data->image;
            }
            if ($image = $request->file('image')) {
                $image = $request->image;
                $temp_relative_dir = config('constants.asset_management_items');
                $file_name = $this->generateName($image, 'asset_items_icon');
                $this->saveFileByStorage($image, $temp_relative_dir, $file_name);
                if ($data) {
                    $this->deleteFileByStorage($temp_relative_dir, $data->image);
                }
                $icon = $file_name;
                // $name = "asset_items_icon_" . time() . rand(1, 999) . '.' . $image->getClientOriginalExtension();
                // $target_path = base_path('public/modules/recruiter/asset_managment/items/');
                // if ($image->move($target_path, $name)) {
                //     if ($icon && !empty($data->image) && file_exists(base_path('public/modules/recruiter/asset_managment/items/' . $data->image))) {
                //         unlink(base_path('public/modules/recruiter/asset_managment/items/' . $data->image));
                //     }
                //     $icon = $name;
                // }
            }
            $object = AssetItemMaster::updateOrCreate([
                'id' => $request->id,
            ], [
                'name' => $request->name,
                'asset_sub_category_id' => $request->asset_sub_category_id,
                'asset_group_id' => $request->asset_group_id,
                'asset_category_id' => $request->asset_category_id,
                'recruiter_id' => $recruiter_id,
                'brand' => $request->brand,
                'color' => $request->color,
                'size' => $request->size,
                'unit' => $request->unit,
                'min_stock_alert' => $request->min_stock_alert,
                'image' => $icon,
                'status' => '0',
            ]);
            if ($object->id) {
                $item_code = "SKU" . $group_id . $category_id . $sub_category_id . $brand . sprintf("%04d", $object->id);
                $data = AssetItemMaster::find($object->id);
                $data->item_code = $item_code;
                $data->save();
            }
            return redirect()->route('recruiter.asset.items.index')->with(['success' => 'Item save successfully','model' => 'Asset Management']);
        } catch (Exception $e) {
            Log::error("AssetItemMasterController.php : store() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function edit($id)
    {
        try {
            if(auth('candidate')->check()){
                $recruiter_id = \Helpers::get_recruiter_id(auth('candidate')->id());
                }else{
                $recruiter_id = auth('recruiter')->user()->id;
                }
            $data['group'] = AssetGroupMaster::where('recruiter_id', $recruiter_id)->where('status', '1')->pluck('name', 'id');
            $data['items'] = AssetItemMaster::find(decrypt($id));
            $data['title'] = "Edit Items";
            return view('asset-managment.items.createOrUpdate', $data);
        } catch (Exception $e) {
            Log::error("AssetItemMasterController.php : edit() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
        }
    }

    public function destroy(Request $request,$id)
    {
        try {
            $deleteData = AssetItemMaster::find(decrypt($id));
            if ($deleteData->delete()) {
                $temp_relative_dir = config('constants.asset_management_items');
                $this->deleteFileByStorage($temp_relative_dir, $deleteData->image);
                if($request->ajax()){
                    return response()->json(['status' => 200, 'message' => 'Item delete successfully.']);
                } else {
                    return redirect()->back()->with('success', 'Item delete successfully.');
                }
            } else {
                return redirect()->back()->with('error', 'Something want wrong.');
            }
        } catch (Exception $e) {
            Log::error("AssetItemMasterController.php : destroy() : ", ["Exception" => $e->getMessage(), "\nTraceAsString" => $e->getTraceAsString()]);
            if($request->ajax()){
                return response()->json(['status' => 400, 'message' => 'Something went wrong. Please try after sometime.']);
            } else {
                return redirect()->back()->with('error', 'Something went wrong. Please try after sometime.');
            }
        }
    }
}
