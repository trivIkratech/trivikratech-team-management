<!-- ======================================================== -->
<!-- 📞 INCOMING CALL MODAL -->
<!-- ======================================================== -->
<div id="incoming-call-modal" class="call-modal-backdrop" style="display: none;">
    <div class="call-incoming-card fade-in">
        <div class="call-avatar-pulse-wrapper">
            <div class="call-avatar-pulse"></div>
            <div class="call-avatar-circle" id="incoming-caller-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
        </div>
        
        <h3 id="incoming-caller-name" style="margin: 12px 0 4px 0; font-size: 18px; color: #ffffff;">Caller Name</h3>
        <div id="incoming-caller-sub" style="font-size: 13px; color: rgba(255,255,255,0.75); margin-bottom: 8px;">Incoming Voice Call...</div>
        <div id="incoming-call-type-badge" class="call-type-badge">
            <i class="fa-solid fa-phone"></i> Audio Call
        </div>

        <div class="call-incoming-actions">
            <button type="button" class="btn-call-action btn-call-reject" onclick="rejectIncomingCall()" title="Decline Call">
                <i class="fa-solid fa-phone-slash"></i>
                <span>Decline</span>
            </button>
            <button type="button" class="btn-call-action btn-call-accept" onclick="acceptIncomingCall()" title="Accept Call">
                <i class="fa-solid fa-phone-volume"></i>
                <span>Accept</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- 🎛️ ACTIVE CALL OVERLAY / FLOATING WINDOW -->
<!-- ======================================================== -->
<div id="active-call-modal" class="call-modal-backdrop" style="display: none;">
    <div class="call-active-window" id="call-active-window">
        <!-- Top Call Header -->
        <div class="call-window-header">
            <div class="call-peer-info">
                <span class="call-status-indicator pulse"></span>
                <strong id="active-call-peer-name">User Name</strong>
                <span class="call-timer-badge" id="active-call-timer">00:00</span>
            </div>
            <div class="call-header-controls">
                <button type="button" class="call-header-btn" onclick="toggleCallWindowSize()" title="Minimize / Maximize" id="call-size-toggle-btn">
                    <i class="fa-solid fa-down-left-and-up-right-to-center"></i>
                </button>
            </div>
        </div>

        <!-- Call Media Stage -->
        <div class="call-media-stage" id="call-media-stage">
            <!-- Dedicated Remote Audio Stream (Plays voice clearly even when video is hidden) -->
            <audio id="remote-audio" autoplay playsinline></audio>

            <!-- Remote Video / Screen Stream -->
            <video id="remote-video" autoplay playsinline style="display: none;"></video>
            
            <!-- Audio-Only / No-Video Avatar Display -->
            <div id="call-audio-avatar-stage" class="call-audio-stage">
                <div class="call-audio-wave-circle">
                    <div class="call-wave-ring wave-1"></div>
                    <div class="call-wave-ring wave-2"></div>
                    <div class="call-audio-avatar-inner" id="active-call-avatar-inner">
                        <i class="fa-solid fa-user" style="font-size: 40px; color: #ffffff;"></i>
                    </div>
                </div>
                <h4 id="active-call-stage-name" style="color: #ffffff; margin: 16px 0 4px 0; font-size: 16px;">Connecting...</h4>
                <div id="active-call-connection-status" style="color: rgba(255,255,255,0.7); font-size: 13px;">Calling...</div>
            </div>

            <!-- Local Video Preview (Picture in Picture) -->
            <div class="call-local-pip" id="call-local-pip" style="display: none;">
                <video id="local-video" autoplay playsinline muted></video>
                <span class="pip-label">You</span>
            </div>
        </div>

        <!-- Floating Controls Bar -->
        <div class="call-controls-bar">
            <!-- Mute/Unmute Mic -->
            <button type="button" class="call-ctrl-btn" id="ctrl-mute-mic-btn" onclick="toggleMuteMic()" title="Mute / Unmute Microphone">
                <i class="fa-solid fa-microphone"></i>
                <span class="ctrl-label">Mute</span>
            </button>

            <!-- Video On/Off -->
            <button type="button" class="call-ctrl-btn" id="ctrl-toggle-cam-btn" onclick="toggleCamera()" title="Turn Camera On / Off">
                <i class="fa-solid fa-video"></i>
                <span class="ctrl-label">Camera</span>
            </button>

            <!-- Screen Share -->
            <button type="button" class="call-ctrl-btn" id="ctrl-screen-share-btn" onclick="toggleScreenShare()" title="Share Screen">
                <i class="fa-solid fa-desktop"></i>
                <span class="ctrl-label">Share</span>
            </button>

            <!-- End Call -->
            <button type="button" class="call-ctrl-btn ctrl-btn-hangup" onclick="hangupCurrentCall()" title="End Call">
                <i class="fa-solid fa-phone-slash"></i>
                <span class="ctrl-label">End</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- 🎨 CALLING STYLES -->
<!-- ======================================================== -->
<style>
.call-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(10, 15, 30, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
}

/* Incoming Call Card */
.call-incoming-card {
    background: linear-gradient(145deg, #1e293b, #0f172a);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6), 0 0 40px rgba(79, 110, 247, 0.25);
    border-radius: 20px;
    padding: 36px 32px;
    width: 360px;
    max-width: 90vw;
    text-align: center;
    color: #ffffff;
    animation: callCardPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes callCardPop {
    0% { transform: scale(0.85); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.call-avatar-pulse-wrapper {
    position: relative;
    width: 96px;
    height: 96px;
    margin: 0 auto 16px auto;
}

.call-avatar-pulse {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    background: rgba(79, 110, 247, 0.4);
    animation: ringPulse 1.8s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
}

@keyframes ringPulse {
    0% { transform: scale(0.9); opacity: 0.8; }
    50% { transform: scale(1.3); opacity: 0.2; }
    100% { transform: scale(1.6); opacity: 0; }
}

.call-avatar-circle {
    position: relative;
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f6ef7, #2563eb);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
    border: 3px solid rgba(255, 255, 255, 0.2);
}

.call-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    background: rgba(79, 110, 247, 0.2);
    border: 1px solid rgba(79, 110, 247, 0.4);
    color: #93c5fd;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 24px;
}

.call-incoming-actions {
    display: flex;
    justify-content: space-around;
    gap: 20px;
}

.btn-call-action {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    transition: transform 0.2s;
}

.btn-call-action:hover {
    transform: scale(1.08);
}

.btn-call-action i {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

.btn-call-reject i {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
}

.btn-call-accept i {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    animation: acceptShake 1.2s infinite ease-in-out;
}

@keyframes acceptShake {
    0%, 100% { transform: rotate(0deg); }
    20%, 60% { transform: rotate(-10deg); }
    40%, 80% { transform: rotate(10deg); }
}

/* Active Call Window */
.call-active-window {
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 18px;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.7);
    width: 820px;
    max-width: 95vw;
    height: 560px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
    transition: all 0.3s ease;
}

.call-active-window.is-minimized {
    width: 340px;
    height: 240px;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 100000;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.8);
}

.call-window-header {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(8px);
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    z-index: 10;
}

.call-peer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ffffff;
    font-size: 14px;
}

.call-status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
}

.call-status-indicator.pulse {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: statusPulse 1.5s infinite;
}

@keyframes statusPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.call-timer-badge {
    background: rgba(255, 255, 255, 0.12);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11.5px;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: #e2e8f0;
}

.call-header-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #ffffff;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: background 0.2s;
}

.call-header-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Media Stage */
.call-media-stage {
    flex: 1;
    position: relative;
    background: #090d16;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#remote-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000000;
}

.call-audio-stage {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.call-audio-wave-circle {
    position: relative;
    width: 110px;
    height: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.call-wave-ring {
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    border: 2px solid rgba(79, 110, 247, 0.4);
    animation: waveRing 2s infinite ease-out;
}

.call-wave-ring.wave-2 {
    animation-delay: 0.7s;
}

@keyframes waveRing {
    0% { transform: scale(0.85); opacity: 0.8; }
    100% { transform: scale(1.4); opacity: 0; }
}

.call-audio-avatar-inner {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(29, 78, 216, 0.4);
    border: 3px solid rgba(255, 255, 255, 0.2);
    position: relative;
    z-index: 2;
}

/* Local Video Picture-in-Picture */
.call-local-pip {
    position: absolute;
    bottom: 16px;
    right: 16px;
    width: 180px;
    height: 120px;
    border-radius: 12px;
    overflow: hidden;
    background: #1e293b;
    border: 2px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    z-index: 5;
    transition: all 0.2s;
}

.call-local-pip video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror self preview */
}

.call-local-pip .pip-label {
    position: absolute;
    bottom: 4px;
    left: 6px;
    background: rgba(0, 0, 0, 0.6);
    color: #ffffff;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 4px;
}

/* Floating Controls Bar */
.call-controls-bar {
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    z-index: 10;
}

.call-ctrl-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #ffffff;
    border-radius: 12px;
    padding: 8px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    min-width: 58px;
    transition: all 0.2s;
}

.call-ctrl-btn i {
    font-size: 16px;
}

.call-ctrl-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.call-ctrl-btn.is-active-off {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
    color: #fca5a5;
}

.call-ctrl-btn.ctrl-btn-hangup {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-color: #ef4444;
    color: #ffffff;
    padding: 8px 20px;
}

.call-ctrl-btn.ctrl-btn-hangup:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}
</style>
