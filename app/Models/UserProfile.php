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
        // The profile photo is stored in the `attachments` table and the
        // `profile_photo_id` column on this model holds the attachment id.
        // Return the Eloquent relation so callers expecting a BelongsTo
        // relation don't receive null or a string.
        return $this->belongsTo(Attachment::class, 'profile_photo_id');
    }

    // Convenience accessor to get the profile photo URL (if needed elsewhere)
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profilePhoto && $this->profilePhoto->file_path) {
            return asset('storage/' . $this->profilePhoto->file_path);
        }
        return null;
    }
}
