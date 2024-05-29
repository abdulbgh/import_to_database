<?php

namespace App\Exports\Api\Tenant\Client\Export\Log;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;


class ClientSuccessLogExport implements  FromArray,WithHeadings

{
    private $success_data;
    protected $data= [];
    protected $headers= [];

    public function __construct($success_data)
    {
        $this->success_data = $success_data;
    }
    public function array(): array
    {
    
        foreach($this->success_data as $item){
           
            $arr = (array) json_decode( $item['data']);
            $arr['row_no'] =  $item['row_no'];
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
        return $header ;
    }
}
