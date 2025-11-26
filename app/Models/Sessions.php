<?php

// app/Models/Sessions.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use MacsiDigital\Zoom\Facades\Zoom;
use Illuminate\Support\Facades\Log;
use App\Services\AgoraService;

class Sessions extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'student_id',
        'teacher_id',
        'availability_slot_id',
        'session_title',
        'session_number',
        'session_date',
        'start_time',
        'end_time',
        'duration',
        'status',
        'join_url',
        'host_url',
        'meeting_id',
        'started_at',
        'ended_at',
        'teacher_notes',
        'homework',
        'materials_shared',
        'student_rating',
        'teacher_rating'
    ];

    protected $casts = [
        'session_date' => 'date:Y-m-d',
        'start_time' => 'date:H:i',
        'end_time' => 'date:H:i',
        'started_at' => 'date:H:i',
        'ended_at' => 'date:H:i',
        'materials_shared' => 'array',
        'duration' => 'integer',
        'session_number' => 'integer',
        'student_rating' => 'integer',
        'teacher_rating' => 'integer',
    ];

    // Status constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeToday($query)
    {
        return $query->where('session_date', now()->format('Y-m-d'));
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->where('session_date', '>=', now()->format('Y-m-d'));
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
    public function getFormattedSessionDateAttribute(): string
    {
        return $this->session_date->format('M d, Y');
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

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
            default => ucfirst($this->status)
        };
    }

    public function getCanJoinAttribute(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        $now = now();
        $sessionStart = Carbon::parse($this->session_date . ' ' . $this->start_time);
        $sessionEnd = Carbon::parse($this->session_date . ' ' . $this->end_time);

        // Allow joining 15 minutes before session starts until session ends
        return $now->between($sessionStart->subMinutes(15), $sessionEnd);
    }

    public function getCanStartAttribute(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        $now = now();
        $sessionStart = Carbon::parse($this->session_date . ' ' . $this->start_time);

        // Allow starting 15 minutes before scheduled time
        return $now >= $sessionStart->subMinutes(15);
    }

    public function getCanEndAttribute(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function getCanCancelAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW])) {
            return false;
        }

        $sessionStart = Carbon::parse($this->session_date . ' ' . $this->start_time);
        return $sessionStart->subHours(4)->isFuture();
    }

    public function getCanRescheduleAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW])) {
            return false;
        }

        $sessionStart = Carbon::parse($this->session_date . ' ' . $this->start_time);
        return $sessionStart->subHours(24)->isFuture();
    }

    public function getActualDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    public function getIsLateAttribute(): bool
    {
        if (!$this->started_at) {
            return false;
        }

        $scheduledStart = Carbon::parse($this->session_date . ' ' . $this->start_time);
        return $this->started_at > $scheduledStart->addMinutes(5); // 5 minutes grace period
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        $sessionEnd = Carbon::parse($this->session_date . ' ' . $this->end_time);
        return now() > $sessionEnd;
    }

    // Methods
    public function start(): bool
    {
        if (!$this->can_start) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function end(): bool
    {
        if (!$this->can_end) {
            return false;
        }

        $success = $this->update([
            'status' => self::STATUS_COMPLETED,
            'ended_at' => now(),
        ]);

        if ($success) {
            // Update booking progress
            $this->booking->incrementCompletedSessions();
        }

        return $success;
    }

    public function cancel(string $reason = 'Cancelled by user'): bool
    {
        if (!$this->can_cancel) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'teacher_notes' => $this->teacher_notes ? $this->teacher_notes . "\n\nCancellation: " . $reason : "Cancelled: " . $reason,
        ]);
    }

    public function markAsNoShow(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_NO_SHOW,
        ]);
    }

    public function reschedule(string $newDate, string $newStartTime, string $newEndTime): bool
    {
        if (!$this->can_reschedule) {
            return false;
        }

        return $this->update([
            'session_date' => $newDate,
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
        ]);
    }

    public function addTeacherNotes(string $notes): bool
    {
        return $this->update([
            'teacher_notes' => $this->teacher_notes ? $this->teacher_notes . "\n\n" . $notes : $notes,
        ]);
    }

    public function setHomework(string $homework): bool
    {
        return $this->update(['homework' => $homework]);
    }

    public function shareMaterials(array $materials): bool
    {
        $existingMaterials = $this->materials_shared ?? [];
        $updatedMaterials = array_merge($existingMaterials, $materials);

        return $this->update(['materials_shared' => $updatedMaterials]);
    }

    public function rateByStudent(int $rating): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        return $this->update(['student_rating' => $rating]);
    }

    public function rateByTeacher(int $rating): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        return $this->update(['teacher_rating' => $rating]);
    }

    public function createMeeting(): bool
    {
        // Replace Zoom meeting creation with Agora meeting creation via AgoraService
        try {
            $agoraService = new AgoraService();

            // Pass session id and participants; AgoraService expected to return meeting info array/object
            $meeting = $agoraService->createMeeting($this->id, $this->teacher_id, $this->student_id);

            if (!$meeting) {
                Log::error('AgoraService returned empty meeting for session ' . $this->id);
                return false;
            }

            // Accept both array and object responses
            $meetingId = is_array($meeting) ? ($meeting['id'] ?? null) : ($meeting->id ?? null);
            $joinUrl = is_array($meeting) ? ($meeting['join_url'] ?? null) : ($meeting->join_url ?? null);
            $hostUrl = is_array($meeting) ? ($meeting['host_url'] ?? null) : ($meeting->host_url ?? null);

            $this->update([
                'meeting_id' => $meetingId,
                'join_url' => $joinUrl,
                'host_url' => $hostUrl,
            ]);

            Log::info('Agora meeting created for session', ['session_id' => $this->id, 'meeting' => $meeting]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Agora meeting for session ' . $this->id . ': ' . $e->getMessage(), [
                'session_date' => (string)$this->session_date,
                'start_time' => (string)$this->start_time,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

private function generateMeetingPassword(): string
{
    return 'TUT' . $this->id . rand(100, 999);
}

// Add this accessor to get host URL from teacher_notes
public function getHostUrlAttribute(): ?string
{
    if (!$this->teacher_notes) return null;
    
    preg_match('/Host URL: (.+)/', $this->teacher_notes, $matches);
    return $matches[1] ?? null;
}

    // Static methods

public static function createForBooking(Booking $booking): void
{
    if ($booking->session_type === Booking::TYPE_SINGLE) {
        $session = self::create([
            'booking_id' => $booking->id,
            'student_id' => $booking->student_id,
            'teacher_id' => $booking->teacher_id,
            'session_number' => 1,
            'session_date' => $booking->first_session_date,
            'start_time' => $booking->first_session_start_time,
            'end_time' => $booking->first_session_end_time,
            'duration' => $booking->session_duration,
            'status' => self::STATUS_SCHEDULED,
        ]);
        
        // Don't create Zoom meeting here - wait for payment confirmation
        
    } else {
        // Create multiple sessions for package
        $startDate = Carbon::parse($booking->first_session_date);
        
        for ($i = 1; $i <= $booking->sessions_count; $i++) {
            $sessionDate = $i === 1 ? $startDate : $startDate->copy()->addWeeks($i - 1);
            
            self::create([
                'booking_id' => $booking->id,
                'student_id' => $booking->student_id,
                'teacher_id' => $booking->teacher_id,
                'session_number' => $i,
                'session_date' => $sessionDate->format('Y-m-d'),
                'start_time' => $booking->first_session_start_time,
                'end_time' => $booking->first_session_end_time,
                'duration' => $booking->session_duration,
                'status' => self::STATUS_SCHEDULED,
            ]);
        }
        
        // Don't create Zoom meetings here - wait for payment confirmation
    }
}

    public static function getStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
        ];
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
    // This creates fake URLs immediately when session is created
    if (!$session->join_url) {
        
    }
        });

        static::updated(function ($session) {
            // Auto-mark as no-show if session is overdue and still scheduled
            if ($session->status === self::STATUS_SCHEDULED && $session->is_overdue) {
                $session->update(['status' => self::STATUS_NO_SHOW]);
            }
        });
    }
}