<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Languages extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_ar',
        'status',
    ];

    public function teacherLanguages()
    {
        return $this->hasMany(TeacherLanguage::class, 'language_id');
    }
}
