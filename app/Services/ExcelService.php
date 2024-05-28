<?php 

namespace App\Services;

use App\Exports\ErrorExcelExport;
use App\Exports\SuccessExcelExport;
use App\Models\ImportLog;
use App\Imports\Api\ErrorImport;
use App\Imports\Api\BufferImport;
use App\Imports\Api\SuccessImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;


class ExcelService {

    public function storeFile($file,$modules_id){
        $options =  [
            'tenant_id' => 1,
            'modules_id' => $modules_id,
        ];
        $generate_path = $this->pathNameGenerate('document',true,$options,$file);
        if(Storage::exists($generate_path['pathname'].'/'.$generate_path['file_name'])){
            Storage::delete($generate_path['pathname'].'/'.$generate_path['file_name']);
        }
        $path = $file->storeAs($generate_path['pathname'],$generate_path['file_name']);
        return $path; 
        
    }

    public function ExcelImport($document) {
        try{
             $moduleImportFunc = new BufferImport($document);
             $file = storage_path('app/'.$document->name);
             $moduleImportFunc->import($file);
             return response()->json([
                 'errorData' =>  $moduleImportFunc->getErrorData(),
                 'successData' =>  $moduleImportFunc->getSuccessData(),
                 'success_count' => count($moduleImportFunc->getSuccessData()),
                 'error_count' => count($moduleImportFunc->getErrorData()),
             ]);
 
        }catch(\Exception $e){
             return response()->json([
                 'status' => false,
                 'message' => $e->getMessage()
             ]);
        }
     }

    public function ExcelGenerator($excelErrorData,$successData,$document){
        $error_path = $this->pathNameGenerate('error',false,['tenant_id' => 1 ,'modules_id'=> $document->module_id],null);
        $success_path = $this->pathNameGenerate('success',false,['tenant_id' => 1 ,'modules_id'=> $document->module_id],null);
        $error_data =  $this->ErrorDataFormatForExcel($excelErrorData);
        $success_data =  $this->SuccessDataFormatForExcel($successData);
        if(count($error_data)){
            $errro_data_export = new ErrorExcelExport($error_data);
            Excel::store($errro_data_export, $error_path);
        }
        if(count($success_data) > 0){
            $sucess_data_export = new SuccessExcelExport($success_data);
            Excel::store($sucess_data_export, $success_path);
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
        return [
            'error_file_path' => $error_path,
            'success_file_path' => $success_path,
        ];
    }

    public function pathNameGenerate($filename,$is_docs,$options,$file){
        if($is_docs){
            $name  = $options['modules_id'].'_'.$filename.'.'.$file->getClientOriginalExtension();
            $path_location = 'public/imports/tenant_'.$options['tenant_id'].'/module_'.$options['modules_id'].'/';
          
            return [
                'pathname' => $path_location,
                'file_name' => $name
            ];
        }else{
            $name = $filename.'_'.rand().'.xlsx';
            $path_location = 'public/exports/tenant_'.$options['tenant_id'].'/module_'.$options['modules_id'].'/'.$name;
            return $path_location;
        }
       
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