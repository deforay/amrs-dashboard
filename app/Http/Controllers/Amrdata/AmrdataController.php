<?php

namespace App\Http\Controllers\Amrdata;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Amrdata\Amrdata;
use App\Service\FacilitiesService;
use App\Service\AmrdataService;
use Yajra\DataTables\Facades\DataTables;
use DB;
use Redirect;
use Session;
use View;
use App\Exports\AmrdataSheetExport;
use Maatwebsite\Excel\Facades\Excel;
use Response;

date_default_timezone_set('Asia/Calcutta');

class AmrdataController extends Controller
{
    public function export(Request $request) 
    {
        // if ($request->isMethod('post')){
            $req = $request->all();
            $specimenDate = $request->input('specimenDate');
            $facilityCode = $request->input('facilityCode');
            $gender = $request->input('gender');
            if($specimenDate){
                $specimenDate = explode(' to ',$specimenDate);
                $startSpecimenDate = date("Y-m-d", strtotime($specimenDate[0]));
                $endSpecimenDate = date("Y-m-d", strtotime($specimenDate[1]));
            }
            $datetime = date('d-m-Y-H:i:s');
            if(file_exists('temporary')){
                mkdir('temporary', 0777);
            }
            $name = 'temporary/AMRS-Data-'.$datetime;
            $amrdata['facilityCode'] = $facilityCode;
            $amrdata['gender'] = $gender;
            $amrdata['startSpecimenDate'] = $startSpecimenDate;
            $amrdata['endSpecimenDate'] = $endSpecimenDate;
            $data['amrdata_values'] = 'amrdata_values';
            $data['amrdata_interpretation'] = 'amrdata_interpretation';
            $export = new AmrdataSheetExport($data,$amrdata);
            Excel::store($export, $name.'.xlsx');
            return $name.'.xlsx';
        // }
    }

    public function exportDownload(){
        $name = explode("/",$_GET["file"])[1];
        $file = storage_path()."/app/".$_GET["file"];
        $headers = array(
            'Content-Type: application/xlsx',
          );
        return Response::download($file, $name.'.xlsx', $headers);
    }

    //
    public function index()
    {
        if(session('login')==true){
            $facilityService = new FacilitiesService();
            $facilityData = $facilityService->getallfacilities();
            return view('amrdata.index',compact('facilityData'));
        }
        else{
            return Redirect::to('login')->with('status', 'Authentication Failed!');
        }
    }

    public function amrAntibioticsShow(Request $request){
        $data = $request->all();
        $amrId = $data['amrId'];
        $amrdataService = new AmrdataService();
        $data = $amrdataService->getAmrAntibiotics($amrId);
        $view = View::make('amrdata.amrAntibioticsShow', ['data'=>$data]);
        $contents = (string) $view;
        return $contents;
    }

    public function getamrdata(Request $request)
    {
        $req = $request->all();
        $specimenDate = $req['specimenDate'];
        if($specimenDate){
            $specimenDate = explode(' to ',$specimenDate);
            $startSpecimenDate = date("Y-m-d", strtotime($specimenDate[0]));
            $endSpecimenDate = date("Y-m-d", strtotime($specimenDate[1]));
        }
        if(session('roleType')=="user"){
            $data = DB::table('users')
            ->join('user_facility_map','user_facility_map.user_id','=','users.user_id')
            ->join('facilities','facilities.facility_id','=','user_facility_map.facility_id')
            ->join('amr_surveillance','facilities.facility_code','=','amr_surveillance.laboratory')
            ->where('users.user_id',session('userId'))
            ->where('facilities.status','active')
            ->whereBetween('amr_surveillance.spec_date',[$startSpecimenDate,$endSpecimenDate])
            ->select('amr_surveillance.*')
            ->get();
            return DataTables::of($data)
                    ->addColumn('action', function($data){
                        $role = session('role');
                        $button = '';
                        if (isset($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit']) && ($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit'] == "allow")){
                            $button .= '<div class="row"><a href="/editamrdata/'.$data->amr_id.'" name="edit" id="amrId'.$data->amr_id.'" class="edit btn btn-dark btn-sm ml-3"><i class="mdi mdi-border-color"></i></a>';
                        }
                        $button .= '<a href="javascript:void(0);" onclick="showAntibioticsDetails(this,'.$data->amr_id.')" id="antibiotics'.$data->amr_id.'" class="edit btn btn-dark btn-sm ml-3"><i class="mdi mdi-plus"></i></a></div>';
                        return $button;
                    })
                    ->editColumn('laboratory', function ($data) {
                        $facilityService = new FacilitiesService();
                        $facilityName = $facilityService->getfacility($data->laboratory);
                        $facilityname = $facilityName[0]->facility_name;
                        $facilityname = $facilityname.'('.$facilityName[0]->facility_code.')';
                        return $facilityname;
                    })
                    ->editColumn('date_birth', function ($data) {
                        if($data->date_birth){
                            $dob = date("d-M-Y", strtotime($data->date_birth));
                            return $dob;
                        }
                    })
                    ->editColumn('spec_date', function ($data) {
                        if($data->spec_date){
                            $dob = date("d-M-Y", strtotime($data->spec_date));
                            return $dob;
                        }
                    })
                    ->editColumn('date_data', function ($data) {
                        if($data->date_data){
                            $dob = date("d-M-Y", strtotime($data->date_data));
                            return $dob;
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
        }
        else{
            $data = DB::table('amr_surveillance')
                    ->join('facilities','facilities.facility_code','=','amr_surveillance.laboratory')
                    ->select('amr_surveillance.*')
                    ->where('facilities.status','active')
                    ->get();
                    // dd($data);
            return DataTables::of($data)
                        ->addColumn('action', function($data){
                            $role = session('role');
                            $button = '';
                            if (isset($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit']) && ($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit'] == "allow")){
                                $button .= '<a href="/editamrdata/'.$data->amr_id.'" name="edit" id="amrId'.$data->amr_id.'" class="edit btn btn-dark btn-sm"><i class="mdi mdi-border-color"></i></a>';
                            }
                            $button .= '<a href="javascript:void(0);" onclick="showAntibioticsDetails(this,'.$data->amr_id.')" id="antibiotics'.$data->amr_id.'" class="edit btn btn-dark btn-sm ml-1"><i class="mdi mdi-plus"></i></a></div>';
                            return $button;
                        })
                        ->editColumn('laboratory', function ($data) {
                            $facilityService = new FacilitiesService();
                            $facilityName = $facilityService->getfacility($data->laboratory);
                            $facilityname = $facilityName[0]->facility_name;
                            $facilityname = $facilityname.'('.$facilityName[0]->facility_code.')';
                            return $facilityname;
                        })
                        ->editColumn('date_birth', function ($data) {
                            if($data->date_birth){
                                $dob = date("d-M-Y", strtotime($data->date_birth));
                                return $dob;
                            }
                        })
                        ->editColumn('spec_date', function ($data) {
                            if($data->spec_date){
                                $dob = date("d-M-Y", strtotime($data->spec_date));
                                return $dob;
                            }
                        })
                        ->editColumn('date_data', function ($data) {
                            if($data->date_data){
                                $dob = date("d-M-Y", strtotime($data->date_data));
                                return $dob;
                            }
                        })
                        ->rawColumns(['action'])
                        ->make(true);
        }

   }

   public function getFilterData(Request $request){
        $role = session('role');
        $requestData = $request->all();
        $specimenDate = $request->input('specimenDate');
        if($specimenDate){
            $specimenDate = explode(' to ',$specimenDate);
            $startSpecimenDate = date("Y-m-d", strtotime($specimenDate[0]));
            $endSpecimenDate = date("Y-m-d", strtotime($specimenDate[1]));
        }
    //    dd($startSpecimenDate);
        $data = DB::table('users')
            ->join('user_facility_map','user_facility_map.user_id','=','users.user_id')
            ->join('facilities','facilities.facility_id','=','user_facility_map.facility_id')
            ->join('amr_surveillance','facilities.facility_code','=','amr_surveillance.laboratory')
            ->where('facilities.status','active')
            ->where('users.user_id',session('userId'));
            if($request->input('facilityCode'))
                $data=$data->where('facilities.facility_code',$request->input('facilityCode'));
            if($request->input('gender'))
                $data=$data->where('amr_surveillance.sex',$request->input('gender'));
            if($request->input('specimenDate'))
                $data=$data->whereBetween('amr_surveillance.spec_date',[$startSpecimenDate,$endSpecimenDate]);
            $data ->select('amr_surveillance.*')
            ->get();
        //   return NULL;
        if(!empty($data)){
            return DataTables::of($data)
                    ->addColumn('action', function($data){
                        $role = session('role');
                        $button = '';
                        if (isset($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit']) && ($role['App\\Http\\Controllers\\Amrdata\\AmrdataController']['edit'] == "allow")){
                            $button .= '<a href="/editamrdata/'.$data->amr_id.'" name="edit" id="amrId'.$data->amr_id.'" class="edit btn btn-dark btn-sm"><i class="mdi mdi-border-color"></i></a>';
                        }
                        $button .= '<a href="javascript:void(0);" onclick="showAntibioticsDetails(this,'.$data->amr_id.')" id="antibiotics'.$data->amr_id.'" class="edit btn btn-dark btn-sm ml-1"><i class="mdi mdi-plus"></i></a></div>';
                        return $button;
                    })
                    ->editColumn('laboratory', function ($data) {
                        $facilityService = new FacilitiesService();
                        $facilityName = $facilityService->getfacility($data->laboratory);
                        $facilityname = $facilityName[0]->facility_name;
                        $facilityname = $facilityname.'('.$facilityName[0]->facility_code.')';
                        return $facilityname;
                    })
                    ->editColumn('date_birth', function ($data) {
                        if($data->date_birth){
                            $dob = date("d-M-Y", strtotime($data->date_birth));
                            return $dob;
                        }
                    })
                    ->editColumn('spec_date', function ($data) {
                        if($data->spec_date){
                            $dob = date("d-M-Y", strtotime($data->spec_date));
                            return $dob;
                        }
                    })
                    ->editColumn('date_data', function ($data) {
                        if($data->date_data){
                            $dob = date("d-M-Y", strtotime($data->date_data));
                            return $dob;
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
        }
        else
            return 0;
   }

   public function editamrdata($id){
        if(session('login')==true){
            $amrdataService = new  AmrdataService();
            $data = $amrdataService->getAmrdata($id);
            $facilityService = new FacilitiesService();
            $facilityName = $facilityService->getallfacilities();
            return view('amrdata.editamrdata',compact('data','facilityName'));
        }
        else{
            return Redirect::to('login')->with('status', 'Authentication Failed!');
        }
   }
    public function amrdataUpdate(Request $request)
    {
        $amrdataService = new AmrdataService();
        $msg = $amrdataService->updateamrdata($request);
        return Redirect::route('amrdata.index')->with('status', $msg);
    }
}
