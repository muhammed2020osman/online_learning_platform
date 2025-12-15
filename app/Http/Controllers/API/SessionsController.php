<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Sessions;
use Illuminate\Http\JsonResponse;   


class SessionsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sessions",
     *     summary="Get all sessions for the authenticated user",
     *     tags={"Sessions"},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of sessions for the authenticated user"
     *     )
     * )
     */
    
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->get('status', null); // optional: filter by status
        $perPage = $request->get('per_page', 15);

        // Build query based on user type
        $query = Sessions::with(['teacher:id,first_name,last_name,email,nationality', 'student:id,first_name,last_name,email', 'booking']);

        if ($user->role_id == 3) {
            // TEACHER: Get all sessions for this teacher
            $query->where('teacher_id', $user->id);
        } elseif ($user->role_id == 4) {
            // STUDENT: Get all sessions for this student
            $query->where('student_id', $user->id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user role'
            ], 403);
        }

        // Filter by status if provided
        if ($status) {
            $query->where('status', $status);
        }

        // Order by date and time (newest first)
        $sessions = $query->orderByDesc('session_date')->orderByDesc('start_time')->paginate($perPage);

        // Transform sessions with full data
        $transformedSessions = $sessions->through(function ($session) use ($user) {
            return $this->transformSession($session, $user);
        });

        return response()->json([
            'success' => true,
            'data' => $transformedSessions,
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ]
        ]);
    }

    /**
     * Get grouped sessions by time (for teacher view)
     * Sessions that share the same time/date are grouped together
     * GET /api/sessions/grouped
     */
    /**
     * @OA\Get(
     *     path="/api/sessions/grouped",
     *     summary="Get grouped sessions (teacher view)",
     *     tags={"Sessions"},
     *     @OA\Response(response=200, description="Grouped sessions")
     * )
     */
    public function groupedSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role_id != 3) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is for teachers only'
            ], 403);
        }

        $status = $request->get('status', null);

        // Get all sessions for this teacher
        $query = Sessions::with(['teacher:id,first_name,last_name,email,nationality', 'student:id,first_name,last_name,email', 'booking'])
                        ->where('teacher_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        $allSessions = $query->orderBy('session_date')->orderBy('start_time')->get();

        // Group by session_date and start_time
        $grouped = $allSessions->groupBy(function ($session) {
            return $session->session_date . '|' . $session->start_time;
        })->map(function ($sessionGroup) use ($user) {
            $firstSession = $sessionGroup->first();
            
            return [
                'session_datetime' => [
                    'date' => $firstSession->session_date,
                    'start_time' => $firstSession->start_time,
                    'end_time' => $firstSession->end_time,
                    'duration' => $firstSession->duration,
                ],
                'group_info' => [
                    'total_students' => $sessionGroup->count(),
                    'session_type' => $sessionGroup->count() > 1 ? 'group' : 'single',
                    'status' => $firstSession->status,
                    'meeting_id' => $firstSession->meeting_id,
                    'join_url' => $firstSession->join_url,
                    'host_url' => $firstSession->host_url,
                ],
                'students' => $sessionGroup->map(function ($session) {
                    return $this->transformSession($session, null);
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $grouped,
            'total_groups' => count($grouped),
        ]);
    }

    /**
     * Transform a session record with full related data
     */
    private function transformSession($session, $user): array
    {
        // Get subject data if booking exists
        $subjectData = null;
        if ($session->booking) {
            // If booking has course, get course name as subject
            if ($session->booking->course) {
                $subjectData = [
                    'id' => $session->booking->course->id,
                    'name' => $session->booking->course->name,
                    'course_id' => $session->booking->course->id,
                ];
            } else {
                // Check if booking has a subject_id directly
                // (for service bookings without course)
                if (isset($session->booking->subject_id)) {
                    $subject = \App\Models\Subject::find($session->booking->subject_id);
                    if ($subject) {
                        $subjectData = [
                            'id' => $subject->id,
                            'name_en' => $subject->name_en,
                            'name_ar' => $subject->name_ar,
                        ];
                    }
                }
            }
        }

        // Normalize session date to Y-m-d and provide day info
        $rawDate = $session->session_date;
        try {
            if ($rawDate instanceof \Carbon\Carbon) {
                $sessionDateFormatted = $rawDate->format('Y-m-d');
                $dayNumber = $rawDate->dayOfWeek; // 0 (Sunday) .. 6 (Saturday)
                $dayName = $rawDate->format('l');
            } else {
                $dt = \Carbon\Carbon::parse((string) $rawDate);
                $sessionDateFormatted = $dt->format('Y-m-d');
                $dayNumber = $dt->dayOfWeek;
                $dayName = $dt->format('l');
            }
        } catch (\Exception $e) {
            // Fallback: take first 10 chars
            $sessionDateFormatted = substr((string) $rawDate, 0, 10);
            try {
                $dt = \Carbon\Carbon::parse($sessionDateFormatted);
                $dayNumber = $dt->dayOfWeek;
                $dayName = $dt->format('l');
            } catch (\Exception $ex) {
                $dayNumber = null;
                $dayName = null;
            }
        }

        return [
            'id' => $session->id,
            'booking_id' => $session->booking_id,
            'session_number' => $session->session_number,
            'session_title' => $session->session_title,
            'session_date' => $sessionDateFormatted,
            'day_name' => $dayName,
            'day_number' => $dayNumber,
            'start_time' => $session->start_time instanceof \Carbon\Carbon 
                ? $session->start_time->format('H:i:s') 
                : $session->start_time,
            'end_time' => $session->end_time instanceof \Carbon\Carbon 
                ? $session->end_time->format('H:i:s') 
                : $session->end_time,
            'duration' => $session->duration,
            'status' => $session->status,
            'teacher' => [
                'id' => $session->teacher->id,
                'name' => $session->teacher->first_name . ' ' . $session->teacher->last_name,
                'email' => $session->teacher->email,
            ],
            'student' => [
                'id' => $session->student->id,
                'name' => $session->student->first_name . ' ' . $session->student->last_name,
                'email' => $session->student->email,
            ],
            'meeting' => [
                'meeting_id' => $session->meeting_id,
                'join_url' => $session->join_url,
                'host_url' => $session->host_url,
            ],
            'subject' => $subjectData,
            'booking' => $session->booking ? [
                'id' => $session->booking->id,
                'reference' => $session->booking->booking_reference,
                'type' => $session->booking->session_type,
                'total_sessions' => $session->booking->sessions_count,
                'completed_sessions' => $session->booking->sessions_completed,
            ] : null,
            'session_info' => [
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'teacher_notes' => $session->teacher_notes,
                'homework' => $session->homework,
                'materials_shared' => $session->materials_shared,
            ],
            'ratings' => [
                'student_rating' => $session->student_rating,
                'teacher_rating' => $session->teacher_rating,
            ],
        ];
    }

    /**
     * Get details of a specific session
     * GET /api/sessions/{sessionId}
     */
    /**
     * @OA\Get(
     *     path="/api/sessions/{sessionId}",
     *     summary="Get session details",
     *     tags={"Sessions"},
     *     @OA\Parameter(name="sessionId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session details")
     * )
     */
    public function show(Request $request, $sessionId): JsonResponse
    {
        $user = $request->user();

        $session = Sessions::with(['teacher:id,first_name,last_name,email,nationality', 'student:id,first_name,last_name,email', 'booking'])->where('id', $sessionId)
            ->where(function ($query) use ($user) {
                if ($user->role_id == 3) { // teacher
                    $query->where('teacher_id', $user->id);
                } elseif ($user->role_id == 4) { // student
                    $query->where('student_id', $user->id);
                }
            })->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformSession($session, $user)
        ]);
    }
}   