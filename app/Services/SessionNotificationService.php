<?php

namespace App\Services;

use App\Models\Sessions;
use App\Models\Booking;
use App\Jobs\SendSessionReminderJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SessionNotificationService
{
    /**
     * Process all upcoming sessions and send appropriate notifications
     */
    public function processUpcomingSessions(): array
    {
        $results = [
            'two_hour_reminders' => 0,
            'one_hour_zoom_created' => 0,
            'errors' => 0,
        ];

        try {
            // Get sessions that need 2-hour reminders
            $twoHourSessions = $this->getSessionsForTwoHourReminder();
            Log::info('Found sessions for 2-hour reminder', ['count' => $twoHourSessions->count()]);
            
            foreach ($twoHourSessions as $session) {
                try {
                    $this->sendTwoHourReminder($session);
                    $results['two_hour_reminders']++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 2-hour reminder', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors']++;
                }
            }

            // Get sessions that need Zoom meeting creation (1 hour before)
            $oneHourSessions = $this->getSessionsForZoomCreation();
            Log::info('Found sessions for Zoom creation', ['count' => $oneHourSessions->count()]);
            
            foreach ($oneHourSessions as $session) {
                try {
                    $this->createZoomAndNotify($session);
                    $results['one_hour_zoom_created']++;
                } catch (\Exception $e) {
                    Log::error('Failed to create Zoom meeting', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors']++;
                }
            }

            Log::info('Session processing completed', $results);
        } catch (\Exception $e) {
            Log::error('Critical error in processUpcomingSessions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Get sessions that need 2-hour reminder
     * Sessions that start in 115-125 minutes (2 hours ± 5 minutes buffer)
     */
    private function getSessionsForTwoHourReminder()
    {
        $now = Carbon::now();
        $twoHoursFromNow = $now->copy()->addMinutes(115);
        $twoHoursBuffer = $now->copy()->addMinutes(125);

        Log::info('getSessionsForTwoHourReminder window', [
            'now' => $now->toDateTimeString(),
            'from' => $twoHoursFromNow->toDateTimeString(),
            'to' => $twoHoursBuffer->toDateTimeString(),
        ]);

        // DEBUG: Print all sessions for today
        $todaySessions = Sessions::where('session_date', $now->format('Y-m-d'))->get();
        Log::info('DEBUG: All sessions for today', [
            'count' => $todaySessions->count(),
            'sessions' => $todaySessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'session_date' => (string)$s->session_date,
                    'start_time' => (string)$s->start_time,
                    'status' => $s->status,
                    'two_hour_reminder_sent_at' => $s->two_hour_reminder_sent_at,
                    'meeting_id' => $s->meeting_id,
                    'zoom_creation_attempted_at' => $s->zoom_creation_attempted_at,
                ];
            })->toArray(),
        ]);

        // DEBUG: Show what times we're comparing
        Log::info('DEBUG: SQL comparison times', [
            'from' => $twoHoursFromNow->format('Y-m-d H:i:s'),
            'to' => $twoHoursBuffer->format('Y-m-d H:i:s'),
        ]);

        // DEBUG: Test the CONCAT result for each session
        $testSessions = Sessions::selectRaw("id, CONCAT(DATE(session_date), ' ', TIME(start_time)) as concat_result, session_date, start_time")
            ->where('session_date', $now->format('Y-m-d'))
            ->get();
        Log::info('DEBUG: CONCAT results for all today sessions', [
            'sessions' => $testSessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'concat_result' => $s->concat_result,
                    'session_date' => (string)$s->session_date,
                    'start_time' => (string)$s->start_time,
                ];
            })->toArray(),
        ]);

        $sessions = Sessions::with(['booking.teacher', 'booking.student', 'booking.course.subject'])
            ->where('status', 'scheduled')
            ->whereNull('two_hour_reminder_sent_at')
            ->where(function ($query) use ($twoHoursFromNow, $twoHoursBuffer) {
                $query->whereRaw("CONCAT(DATE(session_date), ' ', TIME(start_time)) BETWEEN ? AND ?", [
                    $twoHoursFromNow->format('Y-m-d H:i:s'),
                    $twoHoursBuffer->format('Y-m-d H:i:s')
                ]);
            })
            ->get();

        Log::info('Sessions found for 2-hour reminder', [
            'count' => $sessions->count(),
            'sessions' => $sessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'datetime' => (string)$s->session_date . ' ' . (string)$s->start_time,
                    'booking_id' => $s->booking_id,
                ];
            })->toArray(),
        ]);

        return $sessions;
    }

    /**
     * Get sessions that need Zoom meeting creation
     * Sessions that start in 55-65 minutes (1 hour ± 5 minutes buffer)
     */
    private function getSessionsForZoomCreation()
    {
        $now = Carbon::now();
        $oneHourFromNow = $now->copy()->addMinutes(55);
        $oneHourBuffer = $now->copy()->addMinutes(65);

        Log::info('getSessionsForZoomCreation window', [
            'now' => $now->toDateTimeString(),
            'from' => $oneHourFromNow->toDateTimeString(),
            'to' => $oneHourBuffer->toDateTimeString(),
        ]);

        $sessions = Sessions::with(['booking.teacher', 'booking.student', 'booking.course.subject'])
            ->where('status', 'scheduled')
            ->whereNull('meeting_id') // Only sessions without Zoom meeting
            ->whereNull('zoom_creation_attempted_at')
            ->where(function ($query) use ($oneHourFromNow, $oneHourBuffer) {
                $query->whereRaw("CONCAT(DATE(session_date), ' ', TIME(start_time)) BETWEEN ? AND ?", [
                    $oneHourFromNow->format('Y-m-d H:i:s'),
                    $oneHourBuffer->format('Y-m-d H:i:s')
                ]);
            })
            ->get();

        Log::info('Sessions found for Zoom creation', [
            'count' => $sessions->count(),
            'sessions' => $sessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'datetime' => (string)$s->session_date . ' ' . (string)$s->start_time,
                    'booking_id' => $s->booking_id,
                ];
            })->toArray(),
        ]);

        return $sessions;
    }

    /**
     * Send 2-hour reminder to student and teacher
     */
    private function sendTwoHourReminder(Sessions $session): void
    {
        $sessionDateTime = $this->parseSessionDateTime($session);
        $notificationService = new NotificationService();

        // Prepare notification data
        $courseName = $session->booking->course->subject->name_en ?? 'Lesson';
        $timeString = $sessionDateTime->format('h:i A');
        $dateString = $sessionDateTime->format('M d, Y');

        // Send to student
        $titleStudent = app()->getLocale() == 'ar' 
            ? 'تذكير: حصتك تبدأ قريباً' 
            : 'Reminder: Your lesson starts soon';
        
        $messageStudent = app()->getLocale() == 'ar'
            ? "حصة {$courseName} ستبدأ في {$timeString} يوم {$dateString}. استعد للانضمام!"
            : "Your {$courseName} lesson starts at {$timeString} on {$dateString}. Get ready to join!";

        $notificationService->send(
            $session->booking->student,
            'session_reminder_2h',
            $titleStudent,
            $messageStudent,
            [
                'session_id' => $session->id,
                'booking_id' => $session->booking_id,
                'session_date' => $session->session_date,
                'session_time' => $session->start_time,
                'type' => 'session_reminder'
            ]
        );

        // Send to teacher
        $titleTeacher = app()->getLocale() == 'ar' 
            ? 'تذكير: حصة قادمة' 
            : 'Reminder: Upcoming lesson';
        
        $messageTeacher = app()->getLocale() == 'ar'
            ? "لديك حصة {$courseName} في {$timeString} يوم {$dateString} مع {$session->booking->student->first_name}."
            : "You have a {$courseName} lesson at {$timeString} on {$dateString} with {$session->booking->student->first_name}.";

        $notificationService->send(
            $session->booking->teacher,
            'session_reminder_2h',
            $titleTeacher,
            $messageTeacher,
            [
                'session_id' => $session->id,
                'booking_id' => $session->booking_id,
                'session_date' => $session->session_date,
                'session_time' => $session->start_time,
                'type' => 'session_reminder'
            ]
        );

        // Mark reminder as sent
        $session->update(['two_hour_reminder_sent_at' => now()]);

        Log::info('2-hour reminder sent', [
            'session_id' => $session->id,
            'student_id' => $session->booking->student_id,
            'teacher_id' => $session->booking->teacher_id
        ]);
    }

    /**
     * Create Zoom meeting and send links to participants
     */
    private function createZoomAndNotify(Sessions $session): void
    {
        // Mark attempt to prevent duplicate processing
        $session->update(['zoom_creation_attempted_at' => now()]);

        // Dispatch job to create Zoom meeting
        $success = $session->createMeeting();

        Log::info('Zoom meeting creation job dispatched', [
            'session_id' => $session->id,
            'booking_id' => $session->booking_id
        ]);
    }

    /**
     * Parse session date and time into Carbon instance
     */
    private function parseSessionDateTime(Sessions $session): Carbon
    {
        $date = $session->session_date instanceof Carbon
            ? $session->session_date->format('Y-m-d')
            : substr((string)$session->session_date, 0, 10);

        $startTime = $session->start_time instanceof Carbon
            ? $session->start_time->format('H:i:s')
            : (preg_match('/\d{2}:\d{2}/', (string)$session->start_time) 
                ? Carbon::parse($session->start_time)->format('H:i:s') 
                : (string)$session->start_time);

        return Carbon::parse($date . ' ' . $startTime);
    }

    /**
     * Manual trigger for testing - process specific session
     */
    public function testSessionNotification(int $sessionId): array
    {
        $session = Sessions::with(['booking.teacher', 'booking.student', 'booking.course.subject'])
            ->findOrFail($sessionId);

        $results = [
            'session_id' => $sessionId,
            'two_hour_reminder_sent' => false,
            'zoom_created' => false,
            'errors' => []
        ];

        try {
            $this->sendTwoHourReminder($session);
            $results['two_hour_reminder_sent'] = true;
        } catch (\Exception $e) {
            $results['errors'][] = 'Two hour reminder: ' . $e->getMessage();
        }

        try {
            $this->createZoomAndNotify($session);
            $results['zoom_created'] = true;
        } catch (\Exception $e) {
            $results['errors'][] = 'Zoom creation: ' . $e->getMessage();
        }

        return $results;
    }
}