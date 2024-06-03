<?php 


namespace App\Services\Api\Tenant\Client\Import;

use App\Exports\Api\Tenant\ErrorLogExport;
use App\Exports\Api\Tenant\SuccessLogExport;
use App\Models\Customer;
use App\Models\ImportLog;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Imports\Api\Tenant\BufferImport;
use App\Models\BufferExcel;

class ClientImportService {
     public  $disk;

    public function __construct(private readonly Customer $customer)
    {
        $this->disk = config('filesystems.default');
    }

    public function getImportPathName($tenant,$module) {
        $base_path =  $this->customer->getImportBasePath();
        $base_path .= '/tenant_'.$tenant.'/module_'.$module.'/';
        return $base_path;
    }

    public function getExportPathName($tenant,$module) {
        $base_path =  $this->customer->getExportBasePath();
        $base_path .= '/tenant_'.$tenant.'/module_'.$module.'/';
        return $base_path;
    }
    public function storeFile($file,$base_path){
        $file_name = rand(). '.'. $file->getClientOriginalExtension();
        $file_path = $base_path . $file_name;
        if(Storage::disk($this->disk)->exists($file_path)){
            Storage::disk($this->disk)->delete($file_path);
        }
        $path = Storage::disk($this->disk)->putFileAs($base_path,$file,$file_name);
        return $path;  
    }

    public function ExcelImport($document) {
        try{
             $moduleImportFunc = new BufferImport($document);
             $file = Storage::disk($this->disk)->path($document->name);
             $moduleImportFunc->import($file);
             $error_data =  BufferExcel::where('document_id',$document->id)->where('validate_status',0)->get()->toArray();
             return response()->json([
                 'errorData' =>  $error_data,
                 'successData' =>  $moduleImportFunc->getSuccessData(),
                 'success_count' => count($moduleImportFunc->getSuccessData()),
                 'error_count' => count($error_data),
             ]);
        }catch(\Exception $e){
             return response()->json([
                 'status' => false,
                 'message' => $e->getMessage()
             ]);
        }
     }

    public function ExcelExportGeneratorForLog($excelErrorData,$successData,$document,$base_path){
        $error_data =  $this->ErrorDataFormatForExcel($excelErrorData);
        $success_data =  $this->SuccessDataFormatForExcel($successData);
        if(count($error_data)){
            $error_path = $base_path . rand().'.xlsx';
            $errro_data_export = new ErrorLogExport($error_data,$document->id);
            Excel::store($errro_data_export, $error_path,$this->disk);
        }
        if(count($success_data) > 0){
            $success_path = $base_path . rand().'.xlsx';
            $sucess_data_export = new SuccessLogExport($success_data,$document->id);
            Excel::store($sucess_data_export, $success_path,$this->disk);
        }
    
        ImportLog::create([
            'file_path' => $document->name,
            'module_id' => $document->module_id,
            'imported_by' => 1, //auth user id,
            'success_count' => count($successData),
            'failed_count' => count($excelErrorData),
            'error_file_path' => count($excelErrorData) > 0 ? $error_path : null,
            'success_file_path' => count($successData) > 0  ? $success_path : null,
        ]);
        
    }
    public function ErrorDataFormatForExcel($excelErrorData){
        $error_data = [];
        $data =  [];
        foreach($excelErrorData as $error){
            $data['data'] = $error['data'];
            $data['row_no'] = $error['row_no'];
            $data['message'] = $error['message'];
            $error_data[] = $data;
        }
       return $error_data;
    }

    public function SuccessDataFormatForExcel($successData){
        $success_data = [];
        $data =  [];
        foreach($successData as $item){
            $data['data'] = $item['data'];
            $data['row_no'] = $item['row_no'];
            $success_data[] = $data;
        }
       
       return $success_data;
    }
}