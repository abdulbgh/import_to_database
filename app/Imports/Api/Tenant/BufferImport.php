<?php

namespace App\Imports\Api\Tenant;

use App\Models\Branch;
use App\Models\Module;
use App\Models\Customer;
use App\Models\BufferExcel;
use App\Models\Organization;
use Exception;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;

class BufferImport implements ToModel,SkipsOnFailure,WithValidation,WithHeadingRow,WithChunkReading
{
    use Importable,RemembersRowNumber,SkipsFailures;
   
    protected $initial = [
        'validate_status' => false,
        'import_status' => false,
        'data' => [],
        'message' => '',
        'row_no' => null,
        'document_id' => null,
        
    ];
    protected  $module_id = null;
    protected  $successData = [];
    protected  $errorData = [];

    public function __construct($document)
    {
        $this->initial['document_id'] = $document->id;
        $this->module_id = $document->module_id;
    }
    public function model(array $row)
    {
            return  $this->validate($row);  
    }
    public function rules():array {
         if(!empty($this->resolveRule())){
           return  $this->resolveRule();
         }else{
             throw new \Exception('No Module Found for  Validation');
         }
    }
    public function validate($row) {
        
        $this->setInitialForSuccess($row);
        if(isset($row['row_no'])){
           $this->reInsertErrorData($row);
        }else{
            $model =   $this->resolveModuleById($this->module_id);
            $exists = $this->checkExists($model,$row);
        
            if(!$exists){
                $model =  BufferExcel::updateOrCreate(
                    ['row_no' =>  $this->getRowNumber()],
                    $this->initial
                );
                $this->successData[] = $this->initial;
                return $model;
            }
        }
    }
   
    public function reInsertErrorData($row){
        $model =   $this->resolveModuleById($this->module_id);
        $exists = $this->checkExists($model,$row);
        if(!$exists){
            $model =  BufferExcel::updateOrCreate(
                ['row_no' => isset($row['row_no']) ?  $row['row_no'] :   $this->getRowNumber()],
                $this->initial
            );
            $this->successData[] = $this->initial;
            return $model;
        }
    }

    public function getSuccessData() {
        return $this->successData;
    }
    public function getErrorData() {
        return $this->errorData;
    }
   
    public function setInitialForSuccess($row) {
        $data = $row;
        if(isset($data['row_no'])){
            unset($data['error_message']);
            unset($data['row_no']);
        }
        $this->initial = [...$this->initial,...[ 
        'validate_status' => true,
        'import_status' => true,
        'data' => json_encode($data),
        'message' => '',
        'row_no' => isset($row['row_no']) ?  $row['row_no'] :   $this->getRowNumber()] //row_no column  will exist on error excel file .
    ];
    }
    public function setInitialForError($row,$error) {
       
        $this->initial = [...$this->initial,
            ...[ 
            'validate_status' => false,
            'import_status' => false,
            'data' => json_encode($error->values()),
            'message' => implode(',',$error->errors()),
            'row_no' => isset($error->values()['row_no']) ?  $error->values()['row_no'] :  $error->row()
            ]
        ];
    }
    public function onFailure(Failure ...$failures)
    {
        foreach($failures as $failure){
            $this->errorData[] = [
                'row_no' => $failure->row(),
                'error' => $failure->errors(),
                'value' => $failure->values(),
            ];
            $this->setInitialForError($this->initial,$failure);
            $model =   $this->resolveModuleById($this->module_id);
            $exists =  $this->checkExists($model,$failure->values());
            if(!$exists){
                BufferExcel::updateOrCreate(
                    ['row_no' => isset($failure->values()['row_no']) ?  $failure->values()['row_no'] :  $failure->row() ],
                    $this->initial
                );
            }
           
        }
    }

    public function checkExists($model,$row) {
        $exists = false;
        $query = $model['query'];
        if(in_array('email',$model['unique_column']) ){
            $exists = $model['query']->where('email',$row['email'])->exists();
        }else{
            foreach($model['unique_column'] as $column){
                $query->where($column,$row[$column]);
            }
            $exists =  $query->exists();
        }
        return $exists;
    }

    public function resolveRule() {
        $modules = Module::all();
        foreach($modules as $module){
            $name = $module->name;
            if($module->id == $this->module_id){
                if($name == "ORGANIZATION_PROFILE" ){
                    return [
                        'name' => 'required',
                        'email' => 'required|email'
                    ];
                }else if($name == "BRANCH" ) {
                    return [
                        'name' => 'required',
                        'email' => 'required|email'
                    ];
                }else if($name == "CUSTOMER" ) {
                    return [
                        'first_name' => 'required|string|alpha',
                        'email' => 'required|email',
                        'dob' => 'required|date_format:Y-m-d',
                        'age' => [
                            'required',
                            'numeric',
                            function ($attribute, $value, $fail) {
                                if ($value < 18) {
                                    $fail('The '.$attribute.' must be at least 18 years old.');
                                }
                            },
                        ],
                     ];
                }else{
                  return  [];
                }
            }
        }
    }

    public function  resolveModuleById($moduleId){
        $modules = Module::all();
        $model = [];
        foreach($modules as $module){
            $name = $module->name;
            if($module->id == $moduleId){
                if($name == "ORGANIZATION_PROFILE" ){
                    $model['object'] = new Organization();
                    $model['query'] =  Organization::query();
                    $model['unique_column'] =  ['email'];  
                }else if($name == "BRANCH" ) {
                    $model['object'] = new Branch();
                    $model['query'] =  Branch::query();
                    $model['unique_column'] =  ['name'];
                }else if($name == "CUSTOMER"){
                    $model['object'] = new Customer();
                    $model['query'] =  Customer::query();
                    $model['unique_column'] =  ['email'];
                }
            }
        }
        return $model ;
    }
    

    public function chunkSize(): int
    {
        return 300;
    }
}

