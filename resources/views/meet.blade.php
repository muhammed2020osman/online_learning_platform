<!DOCTYPE html>
<html>
<head>
    <title>Live Session</title>
    <style>
        body { margin:0; background:#000; }
        #local-player, #remote-player { width: 100%; height: 100vh; }
    </style>
    <script src="https://download.agora.io/sdk/release/AgoraRTC_N.js"></script>
</head>
<body>

<div id="local-player"></div>
<div id="remote-player"></div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const channel = urlParams.get("channel");
    const token = urlParams.get("token");
    const type = urlParams.get("type");

    const appId = "{{ env('AGORA_APP_ID') }}"; 
    const uid = Math.floor(Math.random() * 100000);

    const client = AgoraRTC.createClient({ mode: "rtc", codec: "vp8" });

    async function startCall() {

        await client.join(appId, channel, token, uid);

        // Local camera + mic
        const localTracks = await AgoraRTC.createMicrophoneAndCameraTracks();

        localTracks[1].play("local-player");

        await client.publish(localTracks);

        // Remote user
        client.on("user-published", async (user, mediaType) => {
            await client.subscribe(user, mediaType);

            if (mediaType === "video") {
                user.videoTrack.play("remote-player");
            }
            if (mediaType === "audio") {
                user.audioTrack.play();
            }
        });

        client.on("user-left", () => {
            document.getElementById("remote-player").innerHTML = "";
        });
    }

    startCall();
</script>

</body>
</html>
