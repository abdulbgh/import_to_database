<?php

namespace App\Http\Controllers;

use App\Exports\ErrorExcelExport;
use Exception;

use App\Models\Customer;
use App\Models\Document;
use App\Models\ImportLog;
use App\Models\BufferExcel;
use Illuminate\Http\Request;
use App\Imports\Api\ErrorImport;
use App\Imports\Api\BufferImport;
use App\Imports\Api\SuccessImport;
use App\Services\ExcelService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelExcel;

class ExcelController extends Controller
{

    public function __construct(private readonly ExcelService $service)
    {
        
    }
    
    public function uploadDocument(Request $request){ 
       $path_name =  $this->service->storeFile($request->file('file'),$request->module_id);
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
            $bufferData =  $q_valid->pluck('data')->toArray();
            $q_error  =  BufferExcel::where('document_id',$request->document_id)->where('validate_status',0);
            $document = Document::find($request->document_id);
            if(count($bufferData) > 0){
                foreach($bufferData as $data) {
                    $data = (array) json_decode($data);
                    Customer::updateOrCreate(['id' => $data['id']],$data);
                }
            }
           
            //excel file generate for log file - success and error file
            $this->service->ExcelGenerator($q_error->get()->toArray(),$q_valid->get()->toArray(),$document);
           
            $q_valid->delete();
        DB::commit();
            return response()->json([
                'status' => true,
                'total_import' => count($bufferData)
            ]);
       }catch(Exception $e){
        DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
       }
    }

    public function getErrorData(Request $request){
          $d_id =   $request->document_id;
          $data = BufferExcel::where('document_id',$d_id)->where('validate_status',0)->get()->toArray();
          $export = new ErrorExcelExport($data);
          return   Excel::download($export, 'error_data.xlsx');
    }
    
}
