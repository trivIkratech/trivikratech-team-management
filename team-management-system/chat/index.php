<?php
/**
 * System-Wide Team Chat Application
 * 
 * Direct Messages with specific persons (Founder, HR, Manager, Employees),
 * Group Channels, Clickable Links & File Attachments
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireLogin();

$pageTitle = 'Team Chat & Direct Messaging';
include __DIR__ . '/../includes/header.php';
?>

<style>
.chat-app-container {
    display: flex;
    height: calc(100vh - 120px);
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-top: -10px;
}

.chat-sidebar {
    width: 320px;
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    background: var(--color-bg-secondary);
}

.chat-sidebar-header {
    padding: 16px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-room-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.chat-section-header {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    padding: 12px 10px 4px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-room-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: background 0.2s;
    margin-bottom: 3px;
}

.chat-room-item:hover, .chat-room-item.active {
    background: rgba(79, 110, 247, 0.15);
}

.chat-room-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--color-primary);
    color: #fff;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    position: relative;
}

.chat-role-badge {
    font-size: 9px;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-role-founder { background: rgba(168, 85, 247, 0.2); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.4); }
.badge-role-manager { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.4); }
.badge-role-hr { background: rgba(236, 72, 153, 0.2); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.4); }
.badge-role-employee { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4); }

.chat-room-info {
    flex: 1;
    min-width: 0;
}

.chat-room-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--color-text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-room-preview {
    font-size: 11px;
    color: var(--color-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--color-bg-card);
}

.chat-main-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--color-bg-secondary);
}

.chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.chat-bubble-row {
    display: flex;
    gap: 10px;
    max-width: 75%;
}

.chat-bubble-row.is-self {
    margin-left: auto;
    flex-direction: row-reverse;
}

.chat-bubble-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-primary);
    color: #fff;
    font-size: 11px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.chat-bubble-content {
    background: var(--color-bg-tertiary);
    border: 1px solid var(--color-border);
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    color: var(--color-text-main);
    line-height: 1.4;
    word-break: break-word;
}

.chat-bubble-row.is-self .chat-bubble-content {
    background: var(--color-primary);
    color: #ffffff;
    border: none;
}

.chat-bubble-row.is-self .chat-link {
    color: #e0e7ff;
    text-decoration: underline;
}

.chat-bubble-meta {
    font-size: 10px;
    opacity: 0.7;
    margin-top: 4px;
    display: flex;
    gap: 6px;
    align-items: center;
}

.chat-attachment-img {
    max-width: 260px;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 6px;
    cursor: pointer;
    border: 1px solid var(--color-border);
}

.chat-attachment-file {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0,0,0,0.15);
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 6px;
    text-decoration: none;
    color: inherit;
    font-size: 12px;
    border: 1px solid rgba(255,255,255,0.1);
}

.chat-input-bar {
    padding: 14px 20px;
    border-top: 1px solid var(--color-border);
    background: var(--color-bg-secondary);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-input-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-input-field {
    flex: 1;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 10px 14px;
    color: var(--color-text-main);
    font-size: 13.5px;
    resize: none;
    height: 42px;
    outline: none;
}

.chat-input-field:focus {
    border-color: var(--color-primary);
}

.chat-file-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(79, 110, 247, 0.15);
    border: 1px solid var(--color-primary);
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 12px;
    color: var(--color-text-main);
}
</style>

<div class="chat-app-container fade-in">
    <!-- LEFT SIDEBAR: DIRECT MESSAGES WITH SPECIFIC PERSONS & CHANNELS -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <h3 style="margin: 0; font-size: 15px;"><i class="fa-solid fa-comments" style="color: var(--color-primary); margin-right: 6px;"></i> Team Messages</h3>
            <button class="btn btn-primary btn-sm" onclick="openNewDMModal()" title="Start Direct Message" style="font-size: 11px; padding: 4px 10px;">
                <i class="fa-solid fa-user-plus"></i> Message Person
            </button>
        </div>
        
        <div style="padding: 10px 16px 4px 16px;">
            <input type="text" id="chat-search-input" class="form-input" placeholder="Search person or channel..." style="font-size: 12px; height: 34px;" onkeyup="filterDirectory()">
        </div>

        <div class="chat-room-list" id="chat-room-list">
            <div style="padding: 20px; text-align: center; color: var(--color-text-muted); font-size: 12px;">
                Loading team members...
            </div>
        </div>
    </div>

    <!-- RIGHT MAIN: ACTIVE CONVERSATION -->
    <div class="chat-main">
        <div class="chat-main-header" id="chat-main-header">
            <div style="display: flex; align-items: center;">
                <button id="mobile-chat-back-btn" class="btn btn-outline btn-sm" onclick="backToDirectoryOnMobile()" style="display: none; font-size: 11px; padding: 4px 10px; margin-right: 10px;" title="Back to Directory">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <div>
                    <strong id="active-room-name" style="font-size: 15px; color: var(--color-text-main);">Select a Specific Person or Group</strong>
                    <div id="active-room-subtitle" class="text-muted" style="font-size: 11px;">Click any Founder, Manager, HR, or Employee from the left list to send a private message</div>
                </div>
            </div>
        </div>

        <div class="chat-messages-area" id="chat-messages-area">
            <div style="margin: auto; text-align: center; color: var(--color-text-muted);">
                <i class="fa-solid fa-paper-plane" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                <p style="font-size: 14px;">Select a person to start 1-on-1 private messaging, link sharing, and file attachments.</p>
            </div>
        </div>

        <!-- INPUT BAR -->
        <div class="chat-input-bar">
            <!-- Selected File Preview Chip -->
            <div id="file-preview-container" style="display: none;">
                <span class="chat-file-chip">
                    <i class="fa-solid fa-paperclip"></i> <span id="file-preview-name">file.pdf</span>
                    <button type="button" onclick="clearFileAttachment()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-weight: bold; font-size: 14px;">×</button>
                </span>
            </div>

            <form id="chat-form" onsubmit="sendMessage(event)" enctype="multipart/form-data">
                <div class="chat-input-controls">
                    <!-- File Upload Icon Button -->
                    <label for="chat-file-input" style="cursor: pointer; padding: 8px 12px; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-secondary);" title="Attach Image or File">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" id="chat-file-input" style="display: none;" onchange="handleFileSelected(this)">
                    </label>

                    <input type="text" id="chat-text-input" class="chat-input-field" placeholder="Type message to specific person or paste link..." autocomplete="off">

                    <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 18px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: DIRECT MESSAGE SPECIFIC PERSON -->
<div class="modal-backdrop" id="new-dm-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card fade-in" style="width: 440px; max-width: 90vw;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="margin: 0;">Message Specific Person</h3>
            <button onclick="closeNewDMModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--color-text-muted);">×</button>
        </div>
        <p class="text-muted" style="font-size: 12px; margin-bottom: 14px;">Choose any specific Founder, Manager, HR, or Employee:</p>

        <div style="max-height: 280px; overflow-y: auto;" id="new-dm-user-list">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<script>
let currentRoomId = null;
let roomsData = [];
let usersData = [];
let pollingTimer = null;
let selectedFile = null;

document.addEventListener('DOMContentLoaded', function() {
    loadRooms();
    pollingTimer = setInterval(function() {
        if (currentRoomId) loadMessages(currentRoomId, true);
        loadRooms(true);
    }, 3000);
});

function loadRooms(isSilent = false) {
    fetch(window.BASE_URL + '/api/chat.php?action=get_rooms')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            roomsData = data.rooms;
            usersData = data.users;
            renderDirectory(roomsData, usersData);
            populateNewDMUsers(usersData, data.current_user_id);
            
            const urlParams = new URLSearchParams(window.location.search);
            const targetRoomId = urlParams.get('room_id');
            if (!currentRoomId && !isSilent) {
                if (targetRoomId) {
                    switchRoom(parseInt(targetRoomId));
                } else if (roomsData.length > 0) {
                    switchRoom(roomsData[0].id);
                }
            }
        }
    });
}

function renderDirectory(rooms, users) {
    const listEl = document.getElementById('chat-room-list');
    let html = '';

    // 1. PUBLIC TEAM CHANNELS
    html += `<div class="chat-section-header"><i class="fa-solid fa-bullhorn" style="color: var(--color-primary); font-size: 12px; width: 14px; text-align: center;"></i><span>Public Channels</span></div>`;
    const groupRooms = rooms.filter(r => r.type === 'group');
    groupRooms.forEach(r => {
        const isActive = (r.id === currentRoomId) ? 'active' : '';
        const badge = r.unread_count > 0 ? `<span class="badge badge-danger" style="border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0;">${r.unread_count}</span>` : '';
        
        html += `
            <div class="chat-room-item ${isActive}" onclick="switchRoom(${r.id})">
                <div class="chat-room-avatar" style="background: var(--color-primary);"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="chat-room-info">
                    <div class="chat-room-name">${escapeHtml(r.name)}</div>
                    <div class="chat-room-preview">${escapeHtml(r.last_message || 'Team discussion')}</div>
                </div>
                ${badge}
            </div>
        `;
    });

    // 2. DIRECT MESSAGES — SPECIFIC PERSONS BY ROLE
    html += `<div class="chat-section-header" style="margin-top: 12px;"><i class="fa-solid fa-user" style="color: var(--color-primary); font-size: 12px; width: 14px; text-align: center;"></i><span>Specific Persons (Direct Chat)</span></div>`;

    if (!users || users.length === 0) {
        html += `<div style="padding: 10px; text-align: center; color: var(--color-text-muted); font-size: 12px;">No team members found.</div>`;
    } else {
        users.forEach(u => {
            // Find if existing DM room exists
            const existingRoom = rooms.find(r => r.type === 'direct' && r.partner_id === u.id);
            const roomId = existingRoom ? existingRoom.id : null;
            const isActive = (roomId && roomId === currentRoomId) ? 'active' : '';
            const unreadBadge = u.unread_count > 0 ? `<span class="badge badge-danger" style="border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0;">${u.unread_count}</span>` : '';
            const roleBadgeClass = 'badge-role-' + u.role;

            html += `
                <div class="chat-room-item ${isActive}" onclick="startDMWithUser(${u.id})">
                    <div class="chat-room-avatar">${u.initials}</div>
                    <div class="chat-room-info">
                        <div class="chat-room-name" style="display: flex; align-items: center; gap: 6px;">
                            <span>${escapeHtml(u.name)}</span>
                            <span class="chat-role-badge ${roleBadgeClass}">${u.role}</span>
                        </div>
                        <div class="chat-room-preview">${escapeHtml(u.designation || ucfirst(u.role))}</div>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        });
    }

    listEl.innerHTML = html;
}

function filterDirectory() {
    const query = document.getElementById('chat-search-input').value.toLowerCase();
    const filteredUsers = usersData.filter(u => u.name.toLowerCase().includes(query) || u.role.toLowerCase().includes(query));
    renderDirectory(roomsData, filteredUsers);
}

function switchRoom(roomId) {
    currentRoomId = roomId;
    renderDirectory(roomsData, usersData);
    
    document.querySelector('.chat-app-container')?.classList.add('room-active');
    if (window.innerWidth <= 768) {
        const backBtn = document.getElementById('mobile-chat-back-btn');
        if (backBtn) backBtn.style.display = 'inline-flex';
    }

    const activeRoom = roomsData.find(r => r.id === roomId);
    if (activeRoom) {
        document.getElementById('active-room-name').textContent = activeRoom.name;
        document.getElementById('active-room-subtitle').textContent = activeRoom.type === 'group' ? 'Public Team Channel' : 'Direct Conversation';
    }
    
    loadMessages(roomId);
}

function backToDirectoryOnMobile() {
    currentRoomId = null;
    document.querySelector('.chat-app-container')?.classList.remove('room-active');
    const backBtn = document.getElementById('mobile-chat-back-btn');
    if (backBtn) backBtn.style.display = 'none';
}

function loadMessages(roomId, isSilent = false) {
    fetch(window.BASE_URL + '/api/chat.php?action=get_messages&room_id=' + roomId)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            renderMessages(data.messages);
        }
    });
}

function renderMessages(messages) {
    const area = document.getElementById('chat-messages-area');
    if (!messages || messages.length === 0) {
        area.innerHTML = '<div style="margin: auto; text-align: center; color: var(--color-text-muted); font-size: 13px;">Say hi! Send a message or link to start conversation.</div>';
        return;
    }

    let html = '';
    messages.forEach(m => {
        const selfClass = m.is_self ? 'is-self' : '';
        let attachmentHtml = '';
        
        if (m.file_path) {
            if (m.is_image) {
                attachmentHtml = `<br><a href="${m.file_url}" target="_blank"><img src="${m.file_url}" class="chat-attachment-img" alt="Uploaded Image"></a>`;
            } else {
                attachmentHtml = `
                    <a href="${m.file_url}" target="_blank" class="chat-attachment-file">
                        <i class="fa-solid fa-file-arrow-down" style="font-size: 16px; color: var(--color-primary);"></i>
                        <div>
                            <strong>${escapeHtml(m.file_name || 'Attachment')}</strong>
                            <div style="font-size: 10px; opacity: 0.8;">Click to Download</div>
                        </div>
                    </a>
                `;
            }
        }

        html += `
            <div class="chat-bubble-row ${selfClass}">
                <div class="chat-bubble-avatar">${m.initials}</div>
                <div>
                    <div class="chat-bubble-content">
                        ${!m.is_self ? `<div style="font-weight: 600; font-size: 11px; margin-bottom: 2px; color: var(--color-primary);">${escapeHtml(m.sender_name)}</div>` : ''}
                        ${m.formatted_html || ''}
                        ${attachmentHtml}
                        <div class="chat-bubble-meta">
                            <span>${m.time}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    const isAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 120;
    area.innerHTML = html;
    if (isAtBottom || area.scrollTop === 0) {
        area.scrollTop = area.scrollHeight;
    }
}

function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        document.getElementById('file-preview-name').textContent = selectedFile.name;
        document.getElementById('file-preview-container').style.display = 'block';
    }
}

function clearFileAttachment() {
    selectedFile = null;
    document.getElementById('chat-file-input').value = '';
    document.getElementById('file-preview-container').style.display = 'none';
}

function sendMessage(e) {
    e.preventDefault();
    if (!currentRoomId) return;

    const input = document.getElementById('chat-text-input');
    const messageText = input.value.trim();

    if (!messageText && !selectedFile) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('room_id', currentRoomId);
    formData.append('message', messageText);
    if (selectedFile) {
        formData.append('attachment', selectedFile);
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    formData.append('csrf_token', csrfToken);

    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            clearFileAttachment();
            loadMessages(currentRoomId);
            loadRooms(true);
        } else {
            alert(data.message || 'Failed to send message.');
        }
    });
}

function openNewDMModal() {
    document.getElementById('new-dm-modal').style.display = 'flex';
}

function closeNewDMModal() {
    document.getElementById('new-dm-modal').style.display = 'none';
}

function populateNewDMUsers(users, currentUserId) {
    const listEl = document.getElementById('new-dm-user-list');
    let html = '';
    
    users.forEach(u => {
        if (u.id === currentUserId) return;
        const roleClass = 'badge-role-' + u.role;
        html += `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--color-border); cursor: pointer;" onclick="startDMWithUser(${u.id})">
                <div>
                    <strong style="font-size: 13px; color: var(--color-text-main);">${escapeHtml(u.name)}</strong>
                    <span class="chat-role-badge ${roleClass}" style="margin-left: 6px;">${u.role}</span>
                    <div class="text-muted" style="font-size: 11px;">${escapeHtml(u.designation || ucfirst(u.role))}</div>
                </div>
                <button class="btn btn-primary btn-sm" style="font-size: 11px;"><i class="fa-solid fa-comments"></i> Message</button>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

function startDMWithUser(partnerId) {
    closeNewDMModal();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_or_create_dm&partner_id=${partnerId}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadRooms();
            setTimeout(() => switchRoom(data.room_id), 300);
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
