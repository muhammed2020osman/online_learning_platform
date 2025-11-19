<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    use HasFactory;
    protected $table = 'Services';
    protected $fillable = ['key_name', 'name_ar', 'name_en', 'description_ar', 'description_en', 'image', 'status', 'role_id'];
}
