<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    protected $guarded = [''];


    public function getImportBasePath(){
        return config('module_storage.upload.client_import');
    }

    public function getExportBasePath(){
        return config('module_storage.upload.client_export');
    }
}
