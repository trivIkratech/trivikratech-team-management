/**
 * 📞 100% Free WebRTC Internal Audio & Video Calling Manager
 * 
 * Features:
 * - Dedicated remote audio player with Echo Cancellation & Noise Suppression
 * - Instant HD Screen Sharing with seamless track replacement
 * - Google Public Free STUN server traversal
 * - Native Web Audio API Synthesized Ringtone
 * - Mic Mute / Unmute & Camera Toggle
 * - Local PiP Preview & Remote Fullscreen/Fit Display
 * - Live Call Duration Timer
 * - Incoming Call Notification & Ringing
 */

const WebRTCCalling = (function () {
    // WebRTC & State variables
    let peerConnection = null;
    let localStream = null;
    let screenStream = null;
    let blankTrack = null;
    let currentCallId = null;
    let currentPeerUser = null;
    let callType = 'audio'; // 'audio' | 'video'
    let isCaller = false;
    let isConnected = false;
    let isScreenSharing = false;
    let isCameraActive = false;
    let incomingCallData = null;
    
    // Polling timers
    let statusPollInterval = null;
    let icePollInterval = null;
    let incomingCheckInterval = null;
    let callTimerInterval = null;
    let callSeconds = 0;
    let lastSignalId = 0;
    
    // Audio synthesizer for ringing sounds
    let audioCtx = null;
    let ringOscillator = null;
    let isRingingAudio = false;

    // Free Google STUN Servers
    const rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
            { urls: 'stun:stun3.l.google.com:19302' },
            { urls: 'stun:stun4.l.google.com:19302' }
        ]
    };

    /**
     * Unlock browser audio playback context on user click
     */
    function unlockAudioContext() {
        try {
            const remoteAudio = document.getElementById('remote-audio');
            if (remoteAudio) {
                remoteAudio.play().catch(() => {});
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
        } catch (e) {}
    }

    /**
     * Create a continuous blank video track so video m-line is ALWAYS negotiated in SDP.
     * Keeps video DTLS channel hot for instant screen share & camera toggling.
     */
    function createBlankVideoTrack(width = 320, height = 240) {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            
            function renderBlank() {
                ctx.fillStyle = '#0a0f1d';
                ctx.fillRect(0, 0, width, height);
                if (peerConnection && peerConnection.connectionState !== 'closed') {
                    requestAnimationFrame(renderBlank);
                }
            }
            renderBlank();

            const stream = canvas.captureStream(5);
            const track = stream.getVideoTracks()[0];
            return track;
        } catch (e) {
            console.warn('Canvas captureStream error:', e);
            return null;
        }
    }

    /**
     * Web Audio API Synthesized Ringtone Generator
     */
    function startRingtoneSound(isIncoming = true) {
        stopRingtoneSound();
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            audioCtx = new AudioContext();

            const freq1 = isIncoming ? 440 : 400;
            const freq2 = isIncoming ? 480 : 450;

            function playBurst() {
                if (!isRingingAudio || !audioCtx) return;
                try {
                    const osc1 = audioCtx.createOscillator();
                    const osc2 = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();

                    osc1.type = 'sine';
                    osc2.type = 'sine';
                    osc1.frequency.setValueAtTime(freq1, audioCtx.currentTime);
                    osc2.frequency.setValueAtTime(freq2, audioCtx.currentTime);

                    gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + (isIncoming ? 1.5 : 1.2));

                    osc1.connect(gain);
                    osc2.connect(gain);
                    gain.connect(audioCtx.destination);

                    osc1.start();
                    osc2.start();
                    osc1.stop(audioCtx.currentTime + (isIncoming ? 1.5 : 1.2));
                    osc2.stop(audioCtx.currentTime + (isIncoming ? 1.5 : 1.2));
                } catch (e) {}
            }

            isRingingAudio = true;
            playBurst();
            ringOscillator = setInterval(() => {
                if (isRingingAudio) playBurst();
            }, isIncoming ? 3000 : 3500);

        } catch (e) {
            console.warn('Audio synthesis warning:', e);
        }
    }

    function stopRingtoneSound() {
        isRingingAudio = false;
        if (ringOscillator) {
            clearInterval(ringOscillator);
            ringOscillator = null;
        }
        if (audioCtx) {
            try { audioCtx.close(); } catch(e) {}
            audioCtx = null;
        }
    }

    /**
     * Create WebRTC Peer Connection with Pre-allocated Audio & Video Channels
     */
    function createPeerConnection() {
        if (peerConnection) {
            try { peerConnection.close(); } catch(e) {}
        }

        peerConnection = new RTCPeerConnection(rtcConfig);

        // 1. Attach Local Audio Track
        if (localStream) {
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                peerConnection.addTrack(audioTrack, localStream);
            }
        }

        // 2. Attach Local Video Track (Webcam or Blank Track)
        let videoTrack = (localStream && localStream.getVideoTracks().length > 0) ? localStream.getVideoTracks()[0] : null;
        if (!videoTrack) {
            blankTrack = createBlankVideoTrack();
            if (blankTrack) {
                if (!localStream) localStream = new MediaStream();
                localStream.addTrack(blankTrack);
                videoTrack = blankTrack;
            }
        }
        if (videoTrack) {
            peerConnection.addTrack(videoTrack, localStream);
        }

        // 3. Remote Track Handler
        peerConnection.ontrack = function (event) {
            console.log('WebRTC ontrack received:', event.track.kind);
            const remoteAudio = document.getElementById('remote-audio');
            const remoteVideo = document.getElementById('remote-video');
            const audioAvatarStage = document.getElementById('call-audio-avatar-stage');

            // Handle Remote Audio
            if (event.track.kind === 'audio') {
                if (remoteAudio) {
                    if (event.streams && event.streams[0]) {
                        remoteAudio.srcObject = event.streams[0];
                    } else {
                        remoteAudio.srcObject = new MediaStream([event.track]);
                    }
                    remoteAudio.play().catch(e => console.warn('Audio auto-play:', e));
                }
            }

            // Handle Remote Video (Camera or Screen Share)
            if (event.track.kind === 'video') {
                if (remoteVideo) {
                    if (event.streams && event.streams[0]) {
                        remoteVideo.srcObject = event.streams[0];
                    } else {
                        remoteVideo.srcObject = new MediaStream([event.track]);
                    }
                }

                const handleVideoState = () => {
                    if (event.track.readyState === 'live' && !event.track.muted && event.track.enabled) {
                        if (remoteVideo) {
                            remoteVideo.style.display = 'block';
                            remoteVideo.play().catch(e => console.warn('Video play error:', e));
                        }
                        if (audioAvatarStage) audioAvatarStage.style.display = 'none';
                    }
                };

                event.track.onunmute = () => {
                    console.log('Remote video track active / unmuted');
                    handleVideoState();
                };

                event.track.onended = () => {
                    console.log('Remote video ended');
                    if (remoteVideo && !isCameraActive && !isScreenSharing) {
                        remoteVideo.style.display = 'none';
                        if (audioAvatarStage) audioAvatarStage.style.display = 'flex';
                    }
                };

                handleVideoState();
            }
        };

        // 4. Local ICE Candidate Handler
        peerConnection.onicecandidate = function (event) {
            if (event.candidate && currentCallId) {
                sendIceCandidate(currentCallId, JSON.stringify(event.candidate));
            }
        };

        // 5. Connection State
        peerConnection.onconnectionstatechange = function () {
            console.log('WebRTC Connection State:', peerConnection.connectionState);
            const statusEl = document.getElementById('active-call-connection-status');
            if (peerConnection.connectionState === 'connected') {
                stopRingtoneSound();
                isConnected = true;
                if (statusEl) statusEl.textContent = 'Connected';
                startCallTimer();
                
                // Ensure audio is playing
                const remoteAudio = document.getElementById('remote-audio');
                if (remoteAudio) remoteAudio.play().catch(() => {});
            } else if (peerConnection.connectionState === 'disconnected' || peerConnection.connectionState === 'failed') {
                if (statusEl) statusEl.textContent = 'Disconnected';
                setTimeout(() => {
                    hangupCall();
                }, 1500);
            }
        };

        return peerConnection;
    }

    /**
     * Start Outgoing Audio or Video Call
     */
    async function startCall(receiverId, receiverName, type = 'audio', roomId = null) {
        if (currentCallId) {
            alert('You are already in an active call.');
            return;
        }

        unlockAudioContext();
        callType = type;
        isCaller = true;
        isConnected = false;
        isScreenSharing = false;
        isCameraActive = (type === 'video');
        currentPeerUser = { id: receiverId, name: receiverName };

        // 1. Request user media with clean echo cancellation
        try {
            const constraints = {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                video: (type === 'video') ? { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } : false
            };
            localStream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            console.error('Media permission error:', err);
            alert(`Unable to access ${type === 'video' ? 'Camera/Microphone' : 'Microphone'}. Please check browser permissions.`);
            return;
        }

        setupLocalPreview();

        // 2. Open Active Call Window
        openActiveCallModal(receiverName, `Calling ${receiverName}...`, type);
        startRingtoneSound(false);

        // 3. Create WebRTC Offer
        createPeerConnection();
        const offer = await peerConnection.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: true
        });
        await peerConnection.setLocalDescription(offer);

        // 4. Send SDP Offer to Backend API
        const formData = new FormData();
        formData.append('action', 'initiate');
        formData.append('receiver_id', receiverId);
        formData.append('call_type', type);
        if (roomId) formData.append('room_id', roomId);
        formData.append('sdp_offer', JSON.stringify(offer));

        fetch('/api/call.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    currentCallId = data.call_id;
                    lastSignalId = 0;
                    startCallerPolling(currentCallId);
                } else {
                    alert(data.message || 'Failed to start call.');
                    hangupCall();
                }
            })
            .catch(err => {
                console.error('Initiate call error:', err);
                alert('Connection error starting call.');
                hangupCall();
            });
    }

    /**
     * Caller Polling loop
     */
    function startCallerPolling(callId) {
        clearInterval(statusPollInterval);
        clearInterval(icePollInterval);

        statusPollInterval = setInterval(() => {
            if (!currentCallId) {
                clearInterval(statusPollInterval);
                return;
            }

            fetch(`/api/call.php?action=get_status&call_id=${callId}`)
                .then(res => res.json())
                .then(async data => {
                    if (!data || !data.success) return;

                    handleRemoteMediaState(data.media_state);

                    if (data.status === 'connected' && data.sdp_answer && !isConnected) {
                        const statusEl = document.getElementById('active-call-connection-status');
                        if (statusEl) statusEl.textContent = 'Connecting media...';
                        stopRingtoneSound();
                        
                        try {
                            const answerDesc = new RTCSessionDescription(JSON.parse(data.sdp_answer));
                            if (peerConnection && peerConnection.signalingState !== 'stable') {
                                await peerConnection.setRemoteDescription(answerDesc);
                            }
                        } catch (e) {
                            console.error('Set remote answer error:', e);
                        }
                    } else if (data.status === 'rejected') {
                        stopRingtoneSound();
                        const statusEl = document.getElementById('active-call-connection-status');
                        if (statusEl) statusEl.textContent = 'Call Declined';
                        setTimeout(() => hangupCall(false), 1200);
                    } else if (data.status === 'ended' || data.status === 'missed') {
                        stopRingtoneSound();
                        const statusEl = document.getElementById('active-call-connection-status');
                        if (statusEl) statusEl.textContent = 'Call Ended';
                        setTimeout(() => hangupCall(false), 1000);
                    }
                })
                .catch(() => {});
        }, 1500);

        icePollInterval = setInterval(() => {
            if (!currentCallId) {
                clearInterval(icePollInterval);
                return;
            }
            pollRemoteIceCandidates(callId);
        }, 1200);
    }

    /**
     * Check incoming calls
     */
    function checkIncomingCall() {
        if (currentCallId || incomingCallData) return;

        fetch('/api/call.php?action=check_incoming')
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.has_call && data.call) {
                    incomingCallData = data.call;
                    showIncomingCallModal(data.call);
                }
            })
            .catch(() => {});
    }

    /**
     * Display Incoming Call Modal
     */
    function showIncomingCallModal(call) {
        const modal = document.getElementById('incoming-call-modal');
        if (!modal) return;

        document.getElementById('incoming-caller-name').textContent = call.caller_name || 'Team Member';
        document.getElementById('incoming-caller-sub').textContent = `${call.caller_designation || 'Colleague'} is calling...`;
        
        const typeBadge = document.getElementById('incoming-call-type-badge');
        if (typeBadge) {
            typeBadge.innerHTML = (call.call_type === 'video') 
                ? '<i class="fa-solid fa-video"></i> HD Video Call' 
                : '<i class="fa-solid fa-phone"></i> Voice Call';
        }

        const avatarCircle = document.getElementById('incoming-caller-avatar');
        if (avatarCircle) {
            const initials = (call.caller_name || 'U').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            avatarCircle.textContent = initials;
        }

        modal.style.display = 'flex';
        startRingtoneSound(true);
    }

    /**
     * Accept Incoming Call
     */
    async function acceptCall() {
        if (!incomingCallData) return;
        stopRingtoneSound();
        unlockAudioContext();

        const call = incomingCallData;
        const callId = call.call_id;
        currentCallId = callId;
        callType = call.call_type;
        isCaller = false;
        isConnected = false;
        isScreenSharing = false;
        isCameraActive = (callType === 'video');
        currentPeerUser = { id: call.caller_id, name: call.caller_name };

        const incModal = document.getElementById('incoming-call-modal');
        if (incModal) incModal.style.display = 'none';

        // 1. Request user media with clean echo cancellation
        try {
            const constraints = {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                video: (callType === 'video') ? { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } : false
            };
            localStream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            console.error('Media permission error on accept:', err);
            alert(`Unable to access ${callType === 'video' ? 'Camera/Microphone' : 'Microphone'}.`);
            rejectCall();
            return;
        }

        setupLocalPreview();

        // 2. Open Active Call Window
        openActiveCallModal(call.caller_name, 'Connecting...', callType);

        // 3. WebRTC Peer Connection + Remote Offer
        createPeerConnection();
        try {
            const offerDesc = new RTCSessionDescription(JSON.parse(call.sdp_offer));
            await peerConnection.setRemoteDescription(offerDesc);

            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);

            // 4. Send SDP Answer to API
            const formData = new FormData();
            formData.append('action', 'answer');
            formData.append('call_id', callId);
            formData.append('sdp_answer', JSON.stringify(answer));

            fetch('/api/call.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        lastSignalId = 0;
                        startCalleePolling(callId);
                    } else {
                        alert(data.message || 'Failed to answer call.');
                        hangupCall();
                    }
                });
        } catch (e) {
            console.error('WebRTC answer error:', e);
            hangupCall();
        }
    }

    /**
     * Callee Polling loop
     */
    function startCalleePolling(callId) {
        clearInterval(statusPollInterval);
        clearInterval(icePollInterval);

        statusPollInterval = setInterval(() => {
            if (!currentCallId) {
                clearInterval(statusPollInterval);
                return;
            }

            fetch(`/api/call.php?action=get_status&call_id=${callId}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        handleRemoteMediaState(data.media_state);
                        if (data.status === 'ended') {
                            const statusEl = document.getElementById('active-call-connection-status');
                            if (statusEl) statusEl.textContent = 'Call Ended';
                            setTimeout(() => hangupCall(false), 800);
                        }
                    }
                })
                .catch(() => {});
        }, 1500);

        icePollInterval = setInterval(() => {
            if (!currentCallId) {
                clearInterval(icePollInterval);
                return;
            }
            pollRemoteIceCandidates(callId);
        }, 1200);
    }

    /**
     * Handle Remote Media State update
     */
    function handleRemoteMediaState(mediaStateStr) {
        if (!mediaStateStr) return;
        try {
            const state = JSON.parse(mediaStateStr);
            const remoteVideo = document.getElementById('remote-video');
            const audioAvatarStage = document.getElementById('call-audio-avatar-stage');

            if (state.is_screen_sharing || state.is_video_on) {
                if (remoteVideo) {
                    remoteVideo.style.display = 'block';
                    remoteVideo.play().catch(() => {});
                }
                if (audioAvatarStage) audioAvatarStage.style.display = 'none';
            } else if (!state.is_screen_sharing && !state.is_video_on && callType !== 'video') {
                if (remoteVideo) remoteVideo.style.display = 'none';
                if (audioAvatarStage) audioAvatarStage.style.display = 'flex';
            }
        } catch (e) {}
    }

    /**
     * Broadcast Media State update
     */
    function broadcastMediaState(stateObj) {
        if (!currentCallId) return;
        const formData = new FormData();
        formData.append('action', 'update_state');
        formData.append('call_id', currentCallId);
        formData.append('media_state', JSON.stringify(stateObj));
        fetch('/api/call.php', { method: 'POST', body: formData }).catch(() => {});
    }

    /**
     * Send ICE Candidate
     */
    function sendIceCandidate(callId, candidateStr) {
        const formData = new FormData();
        formData.append('action', 'send_ice');
        formData.append('call_id', callId);
        formData.append('candidate', candidateStr);
        fetch('/api/call.php', { method: 'POST', body: formData }).catch(() => {});
    }

    /**
     * Fetch Remote ICE Candidates
     */
    function pollRemoteIceCandidates(callId) {
        fetch(`/api/call.php?action=get_ice&call_id=${callId}&last_signal_id=${lastSignalId}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.signals && data.signals.length > 0) {
                    data.signals.forEach(async sig => {
                        lastSignalId = Math.max(lastSignalId, sig.id);
                        try {
                            const candidateObj = JSON.parse(sig.candidate);
                            if (peerConnection && peerConnection.remoteDescription) {
                                await peerConnection.addIceCandidate(new RTCIceCandidate(candidateObj));
                            }
                        } catch (e) {
                            console.warn('Add ICE candidate warning:', e);
                        }
                    });
                }
            })
            .catch(() => {});
    }

    /**
     * Reject Incoming Call
     */
    function rejectCall() {
        stopRingtoneSound();
        if (incomingCallData) {
            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('call_id', incomingCallData.call_id);
            fetch('/api/call.php', { method: 'POST', body: formData }).catch(() => {});
            incomingCallData = null;
        }
        const incModal = document.getElementById('incoming-call-modal');
        if (incModal) incModal.style.display = 'none';
    }

    /**
     * Hangup Active Call
     */
    function hangupCall(notifyServer = true) {
        stopRingtoneSound();
        clearInterval(statusPollInterval);
        clearInterval(icePollInterval);
        stopCallTimer();

        if (notifyServer && currentCallId) {
            const formData = new FormData();
            formData.append('action', 'end');
            formData.append('call_id', currentCallId);
            fetch('/api/call.php', { method: 'POST', body: formData }).catch(() => {});
        }

        if (peerConnection) {
            try { peerConnection.close(); } catch(e) {}
            peerConnection = null;
        }

        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }
        if (screenStream) {
            screenStream.getTracks().forEach(t => t.stop());
            screenStream = null;
        }
        if (blankTrack) {
            try { blankTrack.stop(); } catch(e) {}
            blankTrack = null;
        }

        currentCallId = null;
        currentPeerUser = null;
        incomingCallData = null;
        isConnected = false;
        isScreenSharing = false;
        isCameraActive = false;

        const activeModal = document.getElementById('active-call-modal');
        if (activeModal) activeModal.style.display = 'none';

        const remoteAudio = document.getElementById('remote-audio');
        if (remoteAudio) {
            remoteAudio.pause();
            remoteAudio.srcObject = null;
        }

        const remoteVideo = document.getElementById('remote-video');
        if (remoteVideo) {
            remoteVideo.pause();
            remoteVideo.srcObject = null;
            remoteVideo.style.display = 'none';
        }

        const localVideo = document.getElementById('local-video');
        if (localVideo) {
            localVideo.pause();
            localVideo.srcObject = null;
        }

        const pip = document.getElementById('call-local-pip');
        if (pip) pip.style.display = 'none';

        const shareBtn = document.getElementById('ctrl-screen-share-btn');
        if (shareBtn) {
            shareBtn.classList.remove('is-active-off');
            shareBtn.querySelector('.ctrl-label').textContent = 'Share';
        }
    }

    /**
     * Open Active Call Modal
     */
    function openActiveCallModal(peerName, initialStatus, type) {
        const modal = document.getElementById('active-call-modal');
        if (!modal) return;

        document.getElementById('active-call-peer-name').textContent = peerName;
        document.getElementById('active-call-stage-name').textContent = peerName;
        document.getElementById('active-call-connection-status').textContent = initialStatus;
        document.getElementById('active-call-timer').textContent = '00:00';

        const avatarInner = document.getElementById('active-call-avatar-inner');
        if (avatarInner) {
            const initials = (peerName || 'U').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            avatarInner.innerHTML = `<span style="font-size: 32px; font-weight: bold; color: #fff;">${initials}</span>`;
        }

        const camBtn = document.getElementById('ctrl-toggle-cam-btn');
        if (camBtn) {
            if (type === 'video') {
                camBtn.classList.remove('is-active-off');
            } else {
                camBtn.classList.add('is-active-off');
            }
        }

        const shareBtn = document.getElementById('ctrl-screen-share-btn');
        if (shareBtn) {
            shareBtn.classList.remove('is-active-off');
            shareBtn.querySelector('.ctrl-label').textContent = 'Share';
        }

        modal.style.display = 'flex';
    }

    function setupLocalPreview() {
        const pip = document.getElementById('call-local-pip');
        const localVideo = document.getElementById('local-video');
        if (localStream && callType === 'video') {
            if (localVideo) {
                localVideo.srcObject = localStream;
                localVideo.style.transform = 'scaleX(-1)';
                localVideo.play().catch(() => {});
            }
            if (pip) {
                pip.style.display = 'block';
                const pipLabel = pip.querySelector('.pip-label');
                if (pipLabel) pipLabel.textContent = 'You';
            }
        } else {
            if (pip) pip.style.display = 'none';
        }
    }

    /**
     * Controls: Mute/Unmute Mic
     */
    function toggleMute() {
        if (!localStream) return;
        const audioTracks = localStream.getAudioTracks();
        if (audioTracks.length > 0) {
            const isEnabled = audioTracks[0].enabled;
            audioTracks[0].enabled = !isEnabled;

            const btn = document.getElementById('ctrl-mute-mic-btn');
            if (btn) {
                if (audioTracks[0].enabled) {
                    btn.classList.remove('is-active-off');
                    btn.querySelector('i').className = 'fa-solid fa-microphone';
                    btn.querySelector('.ctrl-label').textContent = 'Mute';
                } else {
                    btn.classList.add('is-active-off');
                    btn.querySelector('i').className = 'fa-solid fa-microphone-slash';
                    btn.querySelector('.ctrl-label').textContent = 'Unmuted';
                }
            }
        }
    }

    /**
     * Controls: Camera On / Off
     */
    async function toggleCam() {
        const btn = document.getElementById('ctrl-toggle-cam-btn');
        const pip = document.getElementById('call-local-pip');
        const localVideo = document.getElementById('local-video');

        if (isCameraActive) {
            isCameraActive = false;
            const videoTracks = localStream ? localStream.getVideoTracks() : [];
            videoTracks.forEach(t => {
                if (t !== blankTrack) t.stop();
            });

            if (peerConnection) {
                const videoSender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video') || peerConnection.getSenders().find(s => s.kind === 'video');
                if (!blankTrack || blankTrack.readyState === 'ended') blankTrack = createBlankVideoTrack();
                if (videoSender && blankTrack) {
                    videoSender.replaceTrack(blankTrack);
                }
            }

            if (btn) btn.classList.add('is-active-off');
            if (pip && !isScreenSharing) pip.style.display = 'none';
            broadcastMediaState({ is_video_on: false, is_screen_sharing: isScreenSharing });
        } else {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
                });
                const newVideoTrack = stream.getVideoTracks()[0];
                isCameraActive = true;

                if (localStream) {
                    localStream.addTrack(newVideoTrack);
                } else {
                    localStream = stream;
                }

                if (peerConnection && !isScreenSharing) {
                    const videoSender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video') || peerConnection.getSenders().find(s => s.kind === 'video');
                    if (videoSender) {
                        await videoSender.replaceTrack(newVideoTrack);
                    } else {
                        peerConnection.addTrack(newVideoTrack, localStream);
                    }
                }

                if (!isScreenSharing) {
                    if (localVideo) {
                        localVideo.srcObject = new MediaStream([newVideoTrack]);
                        localVideo.style.transform = 'scaleX(-1)';
                        localVideo.play().catch(() => {});
                    }
                    if (pip) {
                        pip.style.display = 'block';
                        const pipLabel = pip.querySelector('.pip-label');
                        if (pipLabel) pipLabel.textContent = 'You';
                    }
                }

                if (btn) btn.classList.remove('is-active-off');
                broadcastMediaState({ is_video_on: true, is_screen_sharing: isScreenSharing });
            } catch (e) {
                alert('Could not enable camera. Please check permissions.');
            }
        }
    }

    /**
     * Controls: Screen Share (Instant & Rock Solid)
     */
    async function toggleScreen() {
        const btn = document.getElementById('ctrl-screen-share-btn');
        const pip = document.getElementById('call-local-pip');
        const localVideo = document.getElementById('local-video');

        if (isScreenSharing && screenStream) {
            // STOP SCREEN SHARING
            isScreenSharing = false;
            screenStream.getTracks().forEach(t => t.stop());
            screenStream = null;

            if (peerConnection) {
                const videoSender = peerConnection.getSenders().find(s => (s.track && s.track.kind === 'video') || s.kind === 'video');
                const cameraTrack = (localStream && isCameraActive) ? localStream.getVideoTracks().find(t => t !== blankTrack && t.readyState === 'live') : null;
                
                if (videoSender) {
                    if (cameraTrack) {
                        await videoSender.replaceTrack(cameraTrack);
                    } else {
                        if (!blankTrack || blankTrack.readyState === 'ended') blankTrack = createBlankVideoTrack();
                        await videoSender.replaceTrack(blankTrack || null);
                    }
                }
            }

            if (btn) {
                btn.classList.remove('is-active-off');
                btn.querySelector('.ctrl-label').textContent = 'Share';
            }

            if (isCameraActive && localStream) {
                if (localVideo) {
                    localVideo.srcObject = localStream;
                    localVideo.style.transform = 'scaleX(-1)';
                    localVideo.play().catch(() => {});
                }
                if (pip) {
                    pip.style.display = 'block';
                    const pipLabel = pip.querySelector('.pip-label');
                    if (pipLabel) pipLabel.textContent = 'You';
                }
            } else {
                if (pip) pip.style.display = 'none';
            }

            broadcastMediaState({ is_screen_sharing: false, is_video_on: isCameraActive });
        } else {
            // START SCREEN SHARING
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: {
                        cursor: 'always',
                        displaySurface: 'monitor'
                    },
                    audio: false
                });

                const screenTrack = screenStream.getVideoTracks()[0];
                if (!screenTrack) return;

                isScreenSharing = true;

                // Replace video track on WebRTC sender
                if (peerConnection) {
                    let videoSender = peerConnection.getSenders().find(s => (s.track && s.track.kind === 'video') || s.kind === 'video');
                    if (videoSender) {
                        await videoSender.replaceTrack(screenTrack);
                    } else {
                        peerConnection.addTrack(screenTrack, screenStream);
                    }
                }

                // Show local preview in PiP
                if (localVideo) {
                    localVideo.srcObject = screenStream;
                    localVideo.style.transform = 'none';
                    localVideo.play().catch(() => {});
                }
                if (pip) {
                    pip.style.display = 'block';
                    const pipLabel = pip.querySelector('.pip-label');
                    if (pipLabel) pipLabel.textContent = 'Sharing Screen';
                }

                if (btn) {
                    btn.classList.add('is-active-off');
                    btn.querySelector('.ctrl-label').textContent = 'Stop';
                }

                broadcastMediaState({ is_screen_sharing: true, is_video_on: isCameraActive });

                screenTrack.onended = () => {
                    if (isScreenSharing) {
                        toggleScreen();
                    }
                };
            } catch (e) {
                console.warn('Screen share cancelled or failed:', e);
            }
        }
    }

    /**
     * Call Duration Timer
     */
    function startCallTimer() {
        stopCallTimer();
        callSeconds = 0;
        const timerEl = document.getElementById('active-call-timer');
        callTimerInterval = setInterval(() => {
            callSeconds++;
            const mins = String(Math.floor(callSeconds / 60)).padStart(2, '0');
            const secs = String(callSeconds % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = `${mins}:${secs}`;
        }, 1000);
    }

    function stopCallTimer() {
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }
    }

    /**
     * Window Size Toggle
     */
    function toggleSize() {
        const win = document.getElementById('call-active-window');
        const btn = document.getElementById('call-size-toggle-btn');
        if (!win) return;

        win.classList.toggle('is-minimized');
        if (win.classList.contains('is-minimized')) {
            if (btn) btn.innerHTML = '<i class="fa-solid fa-up-right-and-down-left-from-center"></i>';
        } else {
            if (btn) btn.innerHTML = '<i class="fa-solid fa-down-left-and-up-right-to-center"></i>';
        }
    }

    function init() {
        incomingCheckInterval = setInterval(checkIncomingCall, 3500);
    }

    return {
        init,
        startCall,
        acceptCall,
        rejectCall,
        hangupCall,
        toggleMute,
        toggleCam,
        toggleScreen,
        toggleSize,
        checkIncomingCall
    };
})();

// Global helpers
function startAudioCall(receiverId, receiverName, roomId = null) {
    WebRTCCalling.startCall(receiverId, receiverName, 'audio', roomId);
}

function startVideoCall(receiverId, receiverName, roomId = null) {
    WebRTCCalling.startCall(receiverId, receiverName, 'video', roomId);
}

function acceptIncomingCall() {
    WebRTCCalling.acceptCall();
}

function rejectIncomingCall() {
    WebRTCCalling.rejectCall();
}

function hangupCurrentCall() {
    WebRTCCalling.hangupCall(true);
}

function toggleMuteMic() {
    WebRTCCalling.toggleMute();
}

function toggleCamera() {
    WebRTCCalling.toggleCam();
}

function toggleScreenShare() {
    WebRTCCalling.toggleScreen();
}

function toggleCallWindowSize() {
    WebRTCCalling.toggleSize();
}

document.addEventListener('DOMContentLoaded', function () {
    WebRTCCalling.init();
});
