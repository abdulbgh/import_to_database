<?php

namespace App\Exports\Api\Tenant;

use App\Models\Branch;
use App\Models\Module;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ErrorLogExport implements FromArray,WithHeadings,WithStyles
{
    protected $errors;
    protected $data= [];
    protected $headers= [];
    protected $dc_id= [];
    protected $module_id= [];

    public function __construct($errors,$dc_id)
    {
        $this->errors = $errors;
        $this->dc_id = $dc_id;
       
    }
    public function array(): array
    {
       $data = [];
       $headers =  $this->headings();
        foreach($this->errors as $error){
            $mappedRow = [];
            $values = (array) json_decode( $error['data']);
            foreach($headers as $header) {
                foreach($values as $key=>$value){
                    if($key == strtolower($header)){
                        $mappedRow[strtolower($header)] = $value;
                    }
                }
            }
            $mappedRow['row_no'] =  $error['row_no'];
            $mappedRow['error_message'] =  $error['message'];
            $data[] = $mappedRow;
        }
       
        return $data;
    }

    public function headings(): array
    {
        $this->module_id = $this->getModuleIdFromDoc();
        $module = $this->resolveModuleById($this->module_id);
        $columns = collect(DB::getSchemaBuilder()->getColumnListing(($module)->getTable()));
        $header =  $columns->filter(function ($item) {
            return $item != 'id' &&  $item != 'created_at' &&  $item != 'updated_at';
        });
        $header = $header->toArray();
        $header[] = 'row_no';
        $header[] = 'error_message';
        return  array_map('strtoupper',$header) ;
    }

   

    public function  resolveModuleById($moduleId){
        $modules = Module::all();
        $model = [];
        foreach($modules as $module){
            $name = $module->name;
            if($module->id == $moduleId){
                if($name == "ORGANIZATION_PROFILE" ){
                    return  new Organization();
                }else if($name == "BRANCH" ) {
                    return  new Branch();
                }else if($name == "CUSTOMER") {
                    return new Customer();
                }
            }
        }
        return $model ;
    }
  
    public function getModuleIdFromDoc() {
       $document =  Document::find($this->dc_id);
        return $document->module_id;
    }


    public function styles(Worksheet $sheet)
    {
        
        $highestColumn = $sheet->getHighestColumn();
        for ($column = 'A'; $column <= $highestColumn; $column++) {
            $sheet->getColumnDimension($column)->setWidth(30);
        }

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]], // Bold font, size 14
           
        ];
    }
}