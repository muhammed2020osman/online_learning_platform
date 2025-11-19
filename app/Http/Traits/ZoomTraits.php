<?php

// app/Traits/ZoomMeetingTrait.php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use App\Models\Sessions;

trait ZoomMeetingTrait
{
    public function createMeeting(array $data)
    {
        // Replace with your Zoom API Key & Secret or Bearer Token
        $zoomToken = config('services.zoom.token'); // Store token in config/services.php

        $response = Http::withToken($zoomToken)->post('https://api.zoom.us/v2/users/me/meetings', [
            'topic' => $data['topic'] ?? 'Default Topic',
            'type' => 2, // Scheduled Meeting
            'start_time' => $data['start_time'],
            'duration' => $data['duration'] ?? 30, // in minutes
            'timezone' => 'Asia/Riyadh',
            'agenda' => $data['agenda'] ?? '',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'waiting_room' => true,
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to create Zoom meeting: ' . $response->body());
        }

        $meetingData = $response->json();

        // Save to database
        $meeting = Sessions::create([
            'topic' => $meetingData['topic'],
            'zoom_meeting_id' => $meetingData['id'],
            'start_time' => $meetingData['start_time'],
            'duration' => $meetingData['duration'],
            'join_url' => $meetingData['join_url'],
            'password' => $meetingData['password'] ?? null,
        ]);

        return $meeting;
    }
}
