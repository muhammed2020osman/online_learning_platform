<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;
    protected $fillable = [
        'teacher_id',
        'category_id',
        'service_id',
        'name',
        'description',
        'course_type',
        'price',
        'duration_hours',
        'status',
    ];
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }
    
    public function availabilitySlots()
    {
        return $this->hasMany(AvailabilitySlot::class, 'course_id');
    }
    public function coverImage()
    {
        return $this->morphOne(Attachment::class, 'attached_to');
    }
    protected $appends = [
        'teacher_profile',
        'teacher_basic',
        'teacher_reviews',
    ];

    public function getTeacherProfileAttribute()
    {
        return $this->attributes['teacher_profile'] ?? null;
    }

    public function setTeacherProfileAttribute($value)
    {
        $this->attributes['teacher_profile'] = $value;
    }

    public function getTeacherBasicAttribute()
    {
        return $this->attributes['teacher_basic'] ?? null;
    }

    public function educationLevel()
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function service()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function setTeacherBasicAttribute($value)
    {
        $this->attributes['teacher_basic'] = $value;
    }

    public function getTeacherReviewsAttribute()
    {
        return $this->attributes['teacher_reviews'] ?? null;
    }

    public function setTeacherReviewsAttribute($value)
    {
        $this->attributes['teacher_reviews'] = $value;
    }

    public function countstudents()
    {
        // The previous implementation used a hasManyThrough via CourseLesson -> Booking
        // but some deployments don't store a `lesson_id` on `bookings` (bookings may reference course_id or sessions instead).
        // To avoid SQL errors when `bookings.lesson_id` doesn't exist, use a direct bookings relation filtered by course_id.
        // This returns confirmed bookings for this course. If you need a distinct student count, compute it with a query using COUNT(DISTINCT student_id).
        return $this->hasMany(Booking::class, 'course_id', 'id')
            ->where('status', 'confirmed'); // or whatever status means "paid"
    }

    // Courses for language service (individual and group)
    public static function languageCourses($type = null)
    {
        $query = self::where('service_type', 'language');
        if ($type) {
            $query->where('course_type', $type); // 'individual' or 'group'
        }
        return $query->get();
    }

    // Courses for subjects service (individual and group)
    public static function subjectCourses($type = null)
    {
        $query = self::where('service_type', 'subject');
        if ($type) {
            $query->where('course_type', $type);
        }
        return $query->get();
    }

    // Courses for training service (individual and group)
    public static function trainingCourses($type = null)
    {
        $query = self::where('service_type', 'training');
        if ($type) {
            $query->where('course_type', $type);
        }
        return $query->get();
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }
}
