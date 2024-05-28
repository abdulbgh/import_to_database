<?php

namespace App\Imports\Api;

use App\Models\Customer;


use App\Models\BufferExcel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;

class BufferImport implements ToModel,SkipsOnFailure,WithValidation,WithHeadingRow
{
    use Importable,RemembersRowNumber,SkipsFailures;
    protected $errorData = [];
    protected $successData = [];
    protected $initial = [
        'validate_status' => false,
        'import_status' => false,
        'data' => [],
        'message' => '',
        'row_no' => null,
        'document_id' => null
    ];
    public function __construct($document)
    {
        $this->initial['document_id'] = $document->id;
    }
    public function model(array $row)
    {
            return  $this->validate($row);  
    }
    public function rules():array {
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
    }
    public function validate($row) {
        $this->setInitialForSuccess($row);
        if(isset($row['row_no'])){
           $this->reInsertErrorData($row);
        }else{
            $exists =  Customer::where('id',$row['id'])->exists(); //if exists in moduel table then the row will be skipped
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
        $exists =  Customer::where('id',$row['id'])->exists();
           //checking data is already inserted in module table , if already inserted no need to work with that data
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
        if(isset($row['error'])){
            unset($row['error']);
        }
        $this->initial = [...$this->initial,...[ 
        'validate_status' => true,
        'import_status' => true,
        'data' => json_encode($row),
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
            $exists =  Customer::where('id',$failure->values()['id'])->exists();
           if(!$exists){
            BufferExcel::updateOrCreate(
                ['row_no' => isset($failure->values()['row_no']) ?  $failure->values()['row_no'] :  $failure->row() ],
                $this->initial
            );
           }
           
        }
    }
}
