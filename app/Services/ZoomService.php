<?php

namespace App\Services;

use App\Agora\AccessToken2;
use App\Agora\ServiceRtc;
use Illuminate\Support\Facades\Log;

class AgoraService
{
    protected $appId;
    protected $appCertificate;

    public function __construct()
    {
        $this->appId = env('AGORA_APP_ID');
        $this->appCertificate = env('AGORA_APP_CERTIFICATE');
    }

    /**
     * Create Agora "meeting"
     * Simulates Zoom response: id, host_url, join_url
     */
    public function createMeeting($sessionId, $teacherId, $studentId): ?array
    {
        try {
            $channelName = "session_" . $sessionId;

            // Generate teacher token (host)
            $teacherToken = $this->generateToken($channelName, $teacherId, true);

            // Generate student token (participant)
            $studentToken = $this->generateToken($channelName, $studentId, false);

            // Build URLs for your web app
            $hostUrl = url("/meet?channel={$channelName}&token={$teacherToken}&type=host");
            $joinUrl = url("/meet?channel={$channelName}&token={$studentToken}&type=join");

            return [
                "id"        => $channelName,
                "host_url"  => $hostUrl,
                "join_url"  => $joinUrl,
            ];

        } catch (\Exception $e) {
            Log::error("AgoraService createMeeting error: " . $e->getMessage());
            return null;
        }
    }


    /**
     * Generate RTC token (Teacher has publish privileges)
     */
    private function generateToken($channelName, $uid, $isHost)
    {
        $expire = 3600 * 2; // 2 hours
        $privilegeExpire = time() + $expire;

        $token = new AccessToken2($this->appId, $this->appCertificate, $expire);
        $rtcService = new ServiceRtc($channelName, $uid);

        // All users can JOIN
        $rtcService->addPrivilege(ServiceRtc::PRIVILEGE_JOIN_CHANNEL, $privilegeExpire);

        // Host can publish audio/video
        if ($isHost) {
            $rtcService->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_AUDIO_STREAM, $privilegeExpire);
            $rtcService->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_VIDEO_STREAM, $privilegeExpire);
            $rtcService->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_DATA_STREAM, $privilegeExpire);
        }

        $token->addService($rtcService);
        return $token->build();
    }
}
