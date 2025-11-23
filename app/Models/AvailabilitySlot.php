<?php

// app/Models/AvailabilitySlot.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AvailabilitySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'course_id',
        'order_id',
        'date',
        'day_number',
        'start_time',
        'end_time',
        'duration',
        'is_available',
        'is_booked',
        'booking_id',
        'repeat_type'
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration' => 'integer',
        'is_available' => 'boolean',
        'is_booked' => 'boolean',
    ];

    // Repeat type constants
    const REPEAT_NONE = 'none';
    const REPEAT_WEEKLY = 'weekly';
    const REPEAT_DAILY = 'daily';

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('is_booked', false);
    }

    public function dayOfTheWeek()
    {
        $day = $this->day_number;
        switch ($day) {
            case 1: return 'Sunday';
            case 2: return 'Monday';
            case 3: return 'Tuesday';
            case 4: return 'Wednesday';
            case 5: return 'Thursday';
            case 6: return 'Friday';
            case 7: return 'Saturday';
            default: return null;
        }
    }
    // times
    public function times()
    {
        return $this->hasMany(AvailabilitySlot::class, 'teacher_id');
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->format('Y-m-d'));
    }

    public function scopeToday($query)
    {
        return $query->where('date', now()->format('Y-m-d'));
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeInTimeRange($query, $startTime, $endTime)
    {
        return $query->where('start_time', '>=', $startTime)
                    ->where('end_time', '<=', $endTime);
    }

    public function scopeByDayOfWeek($query, $dayOfWeek)
    {
        return $query->whereRaw('DAYOFWEEK(date) = ?', [$dayOfWeek]);
    }

    // Accessors & Mutators
    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
        }
        return $minutes . 'm';
    }

    public function getDayNameAttribute(): string
    {
        return $this->date->format('l');
    }

    public function getDayNameArabicAttribute(): string
    {
        $days = [
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        return $days[$this->day_name] ?? $this->day_name;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_available) return 'unavailable';
        if ($this->is_booked) return 'booked';
        if ($this->isPast()) return 'expired';
        return 'available';
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'available' => 'متاح',
            'booked' => 'محجوز',
            'unavailable' => 'غير متاح',
            'expired' => 'منتهي',
            default => 'غير محدد'
        };
    }

    public function getIsBookableAttribute(): bool
    {
        if (!$this->is_available || $this->is_booked) {
            return false;
        }

        // Check if slot is at least 2 hours in the future
        $slotDateTime = Carbon::parse($this->date . ' ' . $this->start_time);
        return $slotDateTime->subHours(2)->isFuture();
    }

    public function getIsPastAttribute(): bool
    {
        return $this->isPast();
    }

    public function getIsCurrentAttribute(): bool
    {
        $now = now();
        $slotStart = Carbon::parse($this->date . ' ' . $this->start_time);
        $slotEnd = Carbon::parse($this->date . ' ' . $this->end_time);

        return $now->between($slotStart, $slotEnd);
    }

    public function getTimeUntilStartAttribute(): ?string
    {
        if ($this->isPast()) {
            return null;
        }

        $slotStart = Carbon::parse($this->date . ' ' . $this->start_time);
        $diffInHours = now()->diffInHours($slotStart, false);

        if ($diffInHours < 24) {
            return now()->diffForHumans($slotStart);
        }

        return $slotStart->diffForHumans();
    }

    // Methods
    public function isPast(): bool
    {
        $slotEnd = Carbon::parse($this->date . ' ' . $this->end_time);
        return $slotEnd->isPast();
    }

    public function isFuture(): bool
    {
        $slotStart = Carbon::parse($this->date . ' ' . $this->start_time);
        return $slotStart->isFuture();
    }

    public function isToday(): bool
    {
        return $this->date->isToday();
    }

    public function book(Booking $booking): bool
    {
        if (!$this->is_bookable) {
            return false;
        }

        return $this->update([
            'is_booked' => true,
            'booking_id' => $booking->id,
        ]);
    }

    public function unbook(): bool
    {
        if (!$this->is_booked) {
            return false;
        }

        return $this->update([
            'is_booked' => false,
            'booking_id' => null,
        ]);
    }

    public function makeUnavailable(): bool
    {
        return $this->update(['is_available' => false]);
    }

    public function makeAvailable(): bool
    {
        if ($this->is_booked) {
            return false; // Cannot make available if booked
        }

        return $this->update(['is_available' => true]);
    }

    public function reschedule(string $newDate, string $newStartTime, string $newEndTime): bool
    {
        if ($this->is_booked) {
            return false; // Cannot reschedule booked slots
        }

        $newDuration = Carbon::parse($newStartTime)->diffInMinutes(Carbon::parse($newEndTime));

        return $this->update([
            'date' => $newDate,
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
            'duration' => $newDuration,
        ]);
    }

    public function overlaps(AvailabilitySlot $otherSlot): bool
    {
        if ($this->date->format('Y-m-d') !== $otherSlot->date->format('Y-m-d')) {
            return false;
        }

        $thisStart = Carbon::parse($this->start_time);
        $thisEnd = Carbon::parse($this->end_time);
        $otherStart = Carbon::parse($otherSlot->start_time);
        $otherEnd = Carbon::parse($otherSlot->end_time);

        return $thisStart < $otherEnd && $otherStart < $thisEnd;
    }

    public function canConflictWith(AvailabilitySlot $otherSlot): bool
    {
        return $this->teacher_id === $otherSlot->teacher_id && $this->overlaps($otherSlot);
    }

    // Static methods
    public static function createSlot(array $data): self
    {
        // Auto-calculate duration if not provided
        if (!isset($data['duration'])) {
            $startTime = Carbon::parse($data['start_time']);
            $endTime = Carbon::parse($data['end_time']);
            $data['duration'] = $startTime->diffInMinutes($endTime);
        }

        return self::create($data);
    }

    public static function createRecurringSlots(array $data, int $weeks = 12): array
    {
        $slots = [];
        $startDate = Carbon::parse($data['date']);

        for ($i = 0; $i < $weeks; $i++) {
            $slotDate = $startDate->copy()->addWeeks($i);
            
            $slotData = array_merge($data, [
                'date' => $slotDate->format('Y-m-d'),
                'repeat_type' => self::REPEAT_WEEKLY
            ]);

            $slots[] = self::createSlot($slotData);
        }

        return $slots;
    }

    public static function bulkCreate(array $slotsData): array
    {
        $createdSlots = [];

        foreach ($slotsData as $slotData) {
            // Check for conflicts before creating
            $conflicts = self::where('teacher_id', $slotData['teacher_id'])
                           ->where('date', $slotData['date'])
                           ->where(function ($query) use ($slotData) {
                               $query->whereBetween('start_time', [$slotData['start_time'], $slotData['end_time']])
                                     ->orWhereBetween('end_time', [$slotData['start_time'], $slotData['end_time']])
                                     ->orWhere(function ($q) use ($slotData) {
                                         $q->where('start_time', '<=', $slotData['start_time'])
                                           ->where('end_time', '>=', $slotData['end_time']);
                                     });
                           })
                           ->exists();

            if (!$conflicts) {
                $createdSlots[] = self::createSlot($slotData);
            }
        }

        return $createdSlots;
    }

    public static function getTeacherAvailability(int $teacherId, int $courseId, string $startDate, string $endDate): array
    {
        $slots = self::where('teacher_id', $teacherId)
                    ->where('course_id', $courseId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->available()
                    ->orderBy('date')
                    ->orderBy('start_time')
                    ->get();

        return $slots->groupBy(function ($slot) {
            return $slot->date->format('Y-m-d');
        })->map(function ($daySlots, $date) {
            return [
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'day_name_arabic' => $daySlots->first()->day_name_arabic,
                'slots' => $daySlots->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'start_time' => $slot->start_time->format('H:i'),
                        'end_time' => $slot->end_time->format('H:i'),
                        'duration' => $slot->duration,
                        'formatted_time' => $slot->formatted_time,
                        'is_bookable' => $slot->is_bookable,
                        'time_until_start' => $slot->time_until_start,
                    ];
                })->values()
            ];
        })->values()->toArray();
    }

    public static function generateWeeklySchedule(int $teacherId, int $courseId, array $weeklySchedule, Carbon $startDate, int $weeks = 12): array
    {
        /**
         * $weeklySchedule format:
         * [
         *     'monday' => [
         *         ['start_time' => '09:00', 'end_time' => '09:50'],
         *         ['start_time' => '10:00', 'end_time' => '10:50']
         *     ],
         *     'tuesday' => [...],
         *     ...
         * ]
         */

        $createdSlots = [];
        $dayMap = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];

        for ($week = 0; $week < $weeks; $week++) {
            foreach ($weeklySchedule as $dayName => $timeSlots) {
                $dayOfWeek = $dayMap[strtolower($dayName)] ?? null;
                if ($dayOfWeek === null) continue;

                $date = $startDate->copy()->addWeeks($week)->startOfWeek()->addDays($dayOfWeek);

                foreach ($timeSlots as $timeSlot) {
                    $slotData = [
                        'teacher_id' => $teacherId,
                        'course_id' => $courseId,
                        'date' => $date->format('Y-m-d'),
                        'start_time' => $timeSlot['start_time'],
                        'end_time' => $timeSlot['end_time'],
                        'is_available' => true,
                        'repeat_type' => self::REPEAT_WEEKLY
                    ];

                    $createdSlots[] = self::createSlot($slotData);
                }
            }
        }

        return $createdSlots;
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($slot) {
            // Auto-calculate duration if not set
            if (!$slot->duration) {
                $startTime = Carbon::parse($slot->start_time);
                $endTime = Carbon::parse($slot->end_time);
                $slot->duration = $startTime->diffInMinutes($endTime);
            }

            // Validate that end_time is after start_time
            if (Carbon::parse($slot->start_time) >= Carbon::parse($slot->end_time)) {
                throw new \InvalidArgumentException('End time must be after start time');
            }
        });

        static::updating(function ($slot) {
            // Prevent booking if slot is not available
            // Note: When booking a slot, we set both is_booked=true and is_available=false.
            // Check: is_available should be true BEFORE we're trying to set is_booked to true.
            // If it's already false (from a previous partial update), allow the booking flow.
            if ($slot->isDirty('is_booked') && $slot->is_booked) {
                // Get the value BEFORE this update (the original value)
                $wasAvailable = $slot->getOriginal('is_available');
                // Only reject if it WAS unavailable before this update
                if ($wasAvailable === 0 || $wasAvailable === false) {
                    throw new \InvalidArgumentException('Cannot book unavailable slot');
                }
            }
        });

        static::deleting(function ($slot) {
            // Prevent deletion of booked slots
            if ($slot->is_booked) {
                throw new \InvalidArgumentException('Cannot delete booked slot');
            }
        });
    }
}