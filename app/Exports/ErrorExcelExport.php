<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;


class ErrorExcelExport implements FromArray,WithHeadings
{
    private $errors;
    protected $data= [];
    protected $headers= [];

    public function __construct($errors)
    {
        $this->errors = $errors;
    }
    public function array(): array
    {
        foreach($this->errors as $error){
            $arr = (array) json_decode( $error['data']);
            $arr['row_no'] =  $error['row_no'];
            $arr['error_message'] =  $error['message'];
            $data[] = $arr ;
        }
        return $data;
    }

    public function headings(): array
    {
        $columns = collect(DB::getSchemaBuilder()->getColumnListing((new Customer())->getTable()));
        $header =  $columns->filter(function ($item) {
            return $item != 'created_at' &&  $item != 'updated_at';
        });
        $header = $header->toArray();
        $header[] = 'row_no';
        $header[] = 'error_message';
        return $header ;
    }
  
}