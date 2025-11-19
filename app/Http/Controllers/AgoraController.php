<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Agora\AccessToken2;
use App\Agora\ServiceRtc;

class AgoraController extends Controller
{
    public function generateRtcToken(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'uid'     => 'required',
        ]);

        $appId = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');

        if (!$appId || !$appCertificate) {
            return response()->json(['error' => 'Agora credentials missing'], 500);
        }

        $channelName = $request->channel;
        $uid = (string) $request->uid;

        $expireTime = 3600; // 1 hour
        $privilegeExpire = time() + $expireTime;

        $token = new AccessToken2($appId, $appCertificate, $expireTime);

        $serviceRtc = new ServiceRtc($channelName, $uid);

        $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_JOIN_CHANNEL, $privilegeExpire);
        $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_AUDIO_STREAM, $privilegeExpire);
        $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_VIDEO_STREAM, $privilegeExpire);

        $token->addService($serviceRtc);

        return response()->json([
            'token' => $token->build()
        ]);
    }
}
