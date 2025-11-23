<?php

// app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Sessions;
use Carbon\Carbon;
use MacsiDigital\Zoom\Facades\Zoom;


class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'student_id',
        'teacher_id',
        'course_id',
        'subject_id',
        'language_id',
        'order_id',
        'session_type',
        'sessions_count',
        'sessions_completed',
        'first_session_date',
        'first_session_start_time',
        'first_session_end_time',
        'session_duration',
        'price_per_session',
        'subtotal',
        'discount_percentage',
        'discount_amount',
        'total_amount',
        'currency',
        'special_requests',
        'status',
        'booking_date',
        'cancellation_reason',
        'cancelled_at',
        'refund_amount',
        'refund_percentage'
    ];

    protected $casts = [
        'first_session_date' => 'datetime',
        'first_session_start_time' => 'datetime:H:i',
        'first_session_end_time' => 'datetime:H:i',
        'booking_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'price_per_session' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_percentage' => 'decimal:2',
        'sessions_count' => 'integer',
        'sessions_completed' => 'integer',
        'session_duration' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Session type constants
    const TYPE_SINGLE = 'single';
    const TYPE_PACKAGE = 'package';
    // Relationships

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function order()
{
    return $this->belongsTo(\App\Models\Orders::class, 'order_id');
}

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Sessions::class);
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_PENDING_PAYMENT])
                    ->where('first_session_date', '>=', now()->format('Y-m-d'));
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    // Accessors & Mutators
    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    public function getFormattedFirstSessionDateAttribute(): string
    {
        return $this->first_session_date->format('M d, Y');
    }

    public function getFormattedFirstSessionTimeAttribute(): string
    {
        return $this->first_session_start_time->format('H:i') . ' - ' . $this->first_session_end_time->format('H:i');
    }

    public function getRemainingSessionsAttribute(): int
    {
        return max(0, $this->sessions_count - $this->sessions_completed);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->sessions_count == 0) return 0;
        return round(($this->sessions_completed / $this->sessions_count) * 100, 1);
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS]);
    }

    public function getIsCancellableAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED])) {
            return false;
        }

        $firstSessionDateTime = Carbon::parse($this->first_session_date . ' ' . $this->first_session_start_time);
        return $firstSessionDateTime->subHours(24)->isFuture();
    }

    public function getIsReschedulableAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED])) {
            return false;
        }

        $firstSessionDateTime = Carbon::parse($this->first_session_date . ' ' . $this->first_session_start_time);
        return $firstSessionDateTime->subHours(4)->isFuture();
    }

    public function getCanJoinSessionAttribute(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $now = now();
        $firstSessionDateTime = Carbon::parse($this->first_session_date . ' ' . $this->first_session_start_time);
        $sessionEndTime = Carbon::parse($this->first_session_date . ' ' . $this->first_session_end_time);

        return $now->between($firstSessionDateTime->subMinutes(15), $sessionEndTime);
    }

    // Methods
    public function calculateRefund(): array
    {
        $firstSessionDateTime = Carbon::parse($this->first_session_date . ' ' . $this->first_session_start_time);
        $hoursUntilSession = now()->diffInHours($firstSessionDateTime);

        if ($hoursUntilSession >= 48) {
            $refundPercentage = 100;
        } elseif ($hoursUntilSession >= 24) {
            $refundPercentage = 80;
        } elseif ($hoursUntilSession >= 4) {
            $refundPercentage = 50;
        } else {
            $refundPercentage = 0;
        }

        $refundAmount = ($this->total_amount * $refundPercentage) / 100;

        return [
            'refund_percentage' => $refundPercentage,
            'refund_amount' => $refundAmount,
        ];
    }

    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'sessions_completed' => $this->sessions_count
        ]);
    }

    public function incrementCompletedSessions(): bool
    {
        $newCount = $this->sessions_completed + 1;
        
        $updateData = ['sessions_completed' => $newCount];
        
        if ($newCount >= $this->sessions_count) {
            $updateData['status'] = self::STATUS_COMPLETED;
        }

        return $this->update($updateData);
    }

    public function cancel(string $reason = 'Cancelled by student'): bool
    {
        $refundInfo = $this->calculateRefund();
        
        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'refund_amount' => $refundInfo['refund_amount'],
            'refund_percentage' => $refundInfo['refund_percentage'],
        ]);
    }


public function createMeetingsForSessions(): void
{
    // Get all sessions for this booking
    $sessions = $this->sessions;
    
    foreach ($sessions as $session) {
        $session->createMeeting();
    }
}
}
