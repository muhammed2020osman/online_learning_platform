<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Attachment;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'bio', 'description', 'profile_photo_id', 
        'terms_accepted', 'verified', 'language_pref'
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class , 'user_id');
    }

    public function profilePhoto(): BelongsTo
    {
        $profilePhotoPath = Attachment::find($this->user_id)->file_path ?? null;
        return $profilePhotoPath;
    }
}
