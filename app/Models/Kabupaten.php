<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kabupaten extends Model
{
    
    protected $table = 'dc_kabupaten';
    protected $primaryKey = 'id';
    protected $attributes = ['id', 'nama', 'id_provinsi', 'kode'];
}
