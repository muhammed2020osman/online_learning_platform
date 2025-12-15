<?php

namespace App\Services;

use App\Agora\RtcTokenBuilder;

class AgoraService
{
    /**
     * Generate a RTC token for a given channel and user account.
     * @param string $channelName
     * @param string|int $userAccount
     * @param int $role (RtcTokenBuilder::RolePublisher|RoleSubscriber|RoleAttendee)
     * @param int|null $expireSeconds
     * @return string|null
     */
    public function generateRtcToken($channelName, $userAccount, $role = \App\Agora\RtcTokenBuilder::RoleSubscriber, $expireSeconds = null)
    {
        $appId = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');

        if (! $appId || ! $appCertificate) {
            return null;
        }

        $expireTimeSeconds = $expireSeconds ?? (int) env('AGORA_TOKEN_TTL', 3600);
        $privilegeExpireTs = time() + $expireTimeSeconds;

        return \App\Agora\RtcTokenBuilder::buildTokenWithUserAccount(
            $appId,
            $appCertificate,
            $channelName,
            (string) $userAccount,
            $role,
            $privilegeExpireTs
        );
    }

    /**
     * Create a lightweight "Agora meeting" for a session.
     * This does NOT create a scheduled meeting on Agora (RTC is real-time).
     * Instead it prepares a channel name and tokens for teacher and student and
     * returns URLs that point to the local /meet web route with channel+token.
     *
     * @param int $sessionId
     * @param int $teacherId
     * @param int $studentId
     * @return array|null
     */
    public function createMeeting(int $sessionId, int $teacherId, int $studentId): ?array
    {
        $appId = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');
        if (! $appId || ! $appCertificate) {
            return null;
        }

        $channel = 'session_' . $sessionId;

        // Use stable user accounts so tokens can be associated with a user
        $teacherAccount = 'teacher_' . $teacherId;
        $studentAccount = 'student_' . $studentId;

        $expireSeconds = (int) env('AGORA_TOKEN_TTL', 3600);

        $teacherToken = $this->generateRtcToken($channel, $teacherAccount, \App\Agora\RtcTokenBuilder::RolePublisher, $expireSeconds);
        $studentToken = $this->generateRtcToken($channel, $studentAccount, \App\Agora\RtcTokenBuilder::RoleSubscriber, $expireSeconds);

        $meetUrl = rtrim(config('app.url', url('/')), '\/') . '/meet';

        $joinUrl = $meetUrl . '?channel=' . urlencode($channel) . '&token=' . urlencode($studentToken ?? '') . '&uid=' . urlencode($studentAccount) . '&type=join';
        $hostUrl = $meetUrl . '?channel=' . urlencode($channel) . '&token=' . urlencode($teacherToken ?? '') . '&uid=' . urlencode($teacherAccount) . '&type=host';

        return [
            'id' => $channel,
            'channel' => $channel,
            'join_url' => $joinUrl,
            'host_url' => $hostUrl,
            'tokens' => [
                'teacher' => $teacherToken,
                'student' => $studentToken,
            ],
            'accounts' => [
                'teacher' => $teacherAccount,
                'student' => $studentAccount,
            ],
            'expires_in' => $expireSeconds,
        ];
    }
}
