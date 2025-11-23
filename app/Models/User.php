<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'nationality',
        'phone_number',
        'gender',
        'password',
        'fcm_token',
        'verified',
        'verification_code',
        // Add other fields as needed (e.g. role_id, is_active)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Example relationship for user_type (if needed)
    public function user_type()
    {
        return $this->hasOne(UserType::class, 'id', 'user_type_id');
    }


    public function teacherServices()
    {
        return $this->hasMany(TeacherServices::class, 'teacher_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewed_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function getProfilePictureAttribute()
    {
        $attachment = $this->attachments()
            ->where('attached_to_type', 1)
            ->latest()
            ->first();

        return $attachment ? $attachment->file_path : null;
    }
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'user_id');
    }
    public function teacherInfo()
    {
        return $this->hasOne(TeacherInfo::class, 'teacher_id');
    }
    public function teacherClasses()
    {
        return $this->hasMany(TeacherTeachClasses::class, 'teacher_id');
    }
    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }
    public function teacherSubjects()
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_id');
    }
    public function availableSlots()
    {
        return $this->hasMany(AvailabilitySlot::class, 'teacher_id');
    }
    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
    public function paymentMethods()
    {
        return $this->hasMany(UserPaymentMethod::class, 'user_id');
    }

    /**
     * Teacher bank account stored in user_payment_methods table.
     * We consider a bank account entry when bank_name is present.
     */
    public function teacherBankAccount()
    {
        return $this->hasOne(\App\Models\UserPaymentMethod::class, 'user_id')
            ->whereNotNull('bank_name');
    }


    public function defaultPaymentMethod()
    {
        return $this->hasOne(UserPaymentMethod::class, 'user_id')->where('is_default', true);
    }

    // function that return only the path of profile photo
    public function getProfilePhotoPathAttribute()
    {
        $attachment = $this->attachments()
            ->where('attached_to_type', 1)
            ->where('user_id', $this->id)
            ->latest()
            ->first();

        return $attachment ? $attachment->file_path : null;
    }
}
