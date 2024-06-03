<?php

namespace App\Http\Controllers\Api\Tenant\Client\Import;


use App\Models\Branch;
use App\Models\Module;
use App\Models\Customer;
use App\Models\Document;
use App\Models\BufferExcel;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Api\Tenant\ErrorLogExport;
use App\Services\Api\Tenant\Client\Import\ClientImportService;

class ClientImportController extends Controller
{
    public function __construct(private readonly ClientImportService $service)
    {
        
    }
    public function uploadDocument(Request $request){ 
       $base_path =  $this->service->getImportPathName(1,$request->module_id);
       $path_name =  $this->service->storeFile($request->file('file'),$base_path);
       $document =  Document::updateOrCreate(
        [
            'module_id' => $request->module_id,
        ],
        [
            'name' => $path_name,
            'imported_by' => 1, // auth user
            
        ]);
      return  $this->service->ExcelImport($document,$request);  
    }
    public function transferToModule(Request $request){
        
       try{
        DB::beginTransaction();
            $q_valid  =  BufferExcel::where('document_id',$request->document_id)->where('validate_status',1);
            $buffer_valid_data =  $q_valid->pluck('data')->toArray();
            $q_error  =  BufferExcel::where('document_id',$request->document_id)->where('validate_status',0);
            $document = Document::find($request->document_id);
            if(count($buffer_valid_data) > 0){
                foreach($buffer_valid_data as $data) {
                    $data = (array) json_decode($data);
                    Customer::updateOrCreate(['email' => $data['email']],$data); 
                }                 
            }
            $path = $this->service->getExportPathName(1,$document->module_id);
             //excel file generate for log file - success and error file
            $this->service->ExcelExportGeneratorForLog($q_error->get()->toArray(),$q_valid->get()->toArray(),$document,$path);
            $q_valid->delete();
        DB::commit();
            return response()->json([
                'status' => true,
                'total_import' => count($buffer_valid_data)
            ]);
       }catch(\Exception $e){
        DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
       }
    }
    public function getErrorDataAsExcel(Request $request){
          $dc_id =   $request->document_id;
          $data = BufferExcel::where('document_id',$dc_id)->where('validate_status',0)->get()->toArray();
          $export = new ErrorLogExport($data,$dc_id);
          return   Excel::download($export, 'error_data.xlsx');
    }

    // public function ModuleDataTransfer($model,$data) {
    //     if($model['unique_column'] == 'email'){
    //         $model['object']::updateOrCreate(['email' => $data['email']],$data);
    //     }else{
    //         $columnForupdate = collect($data)->filter(function ($value,$item) use ($model){
    //              if(in_array($item,$model['unique_column'])){
    //                 return [$item => $value];
    //              }
    //         });
    //         $model['object']::updateOrCreate($columnForupdate->toArray(),$data);
    //     }
    //     return $model ;
    // }

    public function  resolveModuleById($moduleId){
        $modules = Module::all();
        $model = [];
        foreach($modules as $module){
            $name = $module->name;
            if($module->id == $moduleId){
                if($name == "ORGANIZATION_PROFILE" ){
                    $model['object'] = new Organization();
                    $model['unique_column'] =  ['email'];  
                }else if($name == "BRANCH" ) {
                    $model['object'] = new Branch();
                    $model['unique_column'] =  ['name'];
                }else if($name == "CUSTOMER"){
                    $model['object'] = new Customer();
                    $model['unique_column'] =  ['email'];
                }else{
                    $model = [];
                }
            }
        }
        return $model ;
    }
    


}
