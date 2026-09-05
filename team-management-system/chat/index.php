<?php
/**
 * System-Wide Team Chat Application
 * 
 * Features:
 * - Direct Messages with specific persons (Founder, HR, Manager, Employees)
 * - Custom Group Creation & Multi-Member channels
 * - In-Box Message Editing with (edited) status
 * - Complete Chat History Deletion from database & individual message deletion
 * - @Person Mentions with interactive autocomplete & notifications
 * - Clickable links & file/image attachments
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireLogin();

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if ($isEmbed): ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($_GET['theme'] ?? $_COOKIE['app_theme'] ?? 'dark'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title>Team Chat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        window.CURRENT_USER_ID = <?php echo (int)$_SESSION['user_id']; ?>;
        window.CURRENT_USER_ROLE = '<?php echo e($_SESSION['user_role']); ?>';
        
        // Dynamic Theme Sync with Parent Window & LocalStorage
        function syncActiveTheme() {
            let activeTheme = 'dark';
            try {
                if (window.parent && window.parent.document && window.parent.document.documentElement) {
                    activeTheme = window.parent.document.documentElement.getAttribute('data-theme') || 'dark';
                } else {
                    activeTheme = localStorage.getItem('app_theme') || 'dark';
                }
            } catch(e) {
                activeTheme = localStorage.getItem('app_theme') || 'dark';
            }
            document.documentElement.setAttribute('data-theme', activeTheme);
            if (document.body) document.body.setAttribute('data-theme', activeTheme);
        }
        syncActiveTheme();
        window.addEventListener('storage', syncActiveTheme);
        window.addEventListener('message', function(e) {
            if (e.data && e.data.theme) {
                document.documentElement.setAttribute('data-theme', e.data.theme);
                if (document.body) document.body.setAttribute('data-theme', e.data.theme);
            }
        });
    </script>
</head>
<body class="is-embed" style="background: var(--color-bg-primary); margin: 0; padding: 0; height: 100vh; max-height: 100vh; overflow: hidden;">
<?php else:
    $pageTitle = 'Team Chat & Direct Messaging';
    include __DIR__ . '/../includes/header.php';
endif; ?>

<style>
.chat-app-container {
    display: flex;
    height: calc(100vh - 120px);
    max-height: 100%;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-top: -10px;
    position: relative;
}

body.is-embed .chat-app-container {
    height: 100vh !important;
    max-height: 100vh !important;
    margin: 0 !important;
    border: none !important;
    border-radius: 0 !important;
    overflow: hidden !important;
}

.chat-sidebar {
    width: 320px;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    background: var(--color-bg-secondary);
    overflow: hidden;
}

@media (max-width: 768px) {
    .chat-sidebar {
        width: 100% !important;
        display: flex !important;
    }
    .chat-main {
        display: none !important;
        width: 100% !important;
    }
    .chat-app-container.room-active .chat-sidebar {
        display: none !important;
    }
    .chat-app-container.room-active .chat-main {
        display: flex !important;
    }
    .chat-app-container.room-active #mobile-chat-back-btn {
        display: inline-flex !important;
    }
}

body.is-embed.narrow-embed .chat-sidebar {
    width: 100% !important;
    display: flex !important;
}
body.is-embed.narrow-embed .chat-main {
    display: none !important;
    width: 100% !important;
}
body.is-embed.narrow-embed .chat-app-container.room-active .chat-sidebar {
    display: none !important;
}
body.is-embed.narrow-embed .chat-app-container.room-active .chat-main {
    display: flex !important;
}
body.is-embed.narrow-embed .chat-app-container.room-active #mobile-chat-back-btn {
    display: inline-flex !important;
}

@media (max-width: 600px) {
    .hide-on-narrow {
        display: none !important;
    }
}
body.narrow-embed .hide-on-narrow {
    display: none !important;
}

.chat-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.chat-room-list {
    flex: 1 1 0%;
    min-height: 0;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding: 6px;
    -webkit-overflow-scrolling: touch;
}

/* Custom Scrollbars */
.chat-room-list::-webkit-scrollbar,
.chat-messages-area::-webkit-scrollbar,
#new-dm-user-list::-webkit-scrollbar {
    width: 6px;
}
.chat-room-list::-webkit-scrollbar-track,
.chat-messages-area::-webkit-scrollbar-track,
#new-dm-user-list::-webkit-scrollbar-track {
    background: transparent;
}
.chat-room-list::-webkit-scrollbar-thumb,
.chat-messages-area::-webkit-scrollbar-thumb,
#new-dm-user-list::-webkit-scrollbar-thumb {
    background: rgba(150, 150, 150, 0.35);
    border-radius: 4px;
}
.chat-room-list::-webkit-scrollbar-thumb:hover,
.chat-messages-area::-webkit-scrollbar-thumb:hover,
#new-dm-user-list::-webkit-scrollbar-thumb:hover {
    background: rgba(150, 150, 150, 0.6);
}

.chat-section-header {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    padding: 10px 8px 4px 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.chat-room-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 2px;
    position: relative;
}

.chat-room-item:hover, .chat-room-item.active {
    background: rgba(79, 110, 247, 0.15);
}

.chat-room-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-primary);
    color: #fff;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
    position: relative;
}

.chat-role-badge {
    font-size: 8.5px;
    padding: 1px 5px;
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
    font-size: 12.5px;
    color: var(--color-text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-room-preview {
    font-size: 10.5px;
    color: var(--color-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-main {
    flex: 1 1 0%;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: var(--color-bg-card);
    position: relative;
    min-width: 0;
    overflow: hidden;
}

.chat-main-header {
    flex-shrink: 0;
    padding: 10px 14px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--color-bg-secondary);
    gap: 8px;
    min-width: 0;
}

.chat-messages-area {
    flex: 1 1 0%;
    min-height: 0;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    -webkit-overflow-scrolling: touch;
}

.chat-bubble-row {
    display: flex;
    gap: 10px;
    max-width: 78%;
    position: relative;
    group: relative;
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
    position: relative;
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

.chat-edited-tag {
    font-size: 9px;
    opacity: 0.8;
    font-style: italic;
    background: rgba(0, 0, 0, 0.15);
    padding: 1px 5px;
    border-radius: 3px;
}

.chat-mention {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(79, 110, 247, 0.25);
    color: #93c5fd;
    padding: 1px 7px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 12px;
    border: 1px solid rgba(79, 110, 247, 0.4);
}

.chat-bubble-row.is-self .chat-mention {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.4);
}

/* Hover Action Toolbar on message */
.chat-msg-actions {
    position: absolute;
    top: -12px;
    right: 8px;
    display: none;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 2px 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 10;
    gap: 4px;
}

.chat-bubble-row.is-self .chat-msg-actions {
    right: auto;
    left: 8px;
}

.chat-bubble-row:hover .chat-msg-actions {
    display: flex;
}

.chat-action-btn {
    background: none;
    border: none;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-size: 11px;
    padding: 3px 6px;
    border-radius: 4px;
    transition: all 0.15s;
}

.chat-action-btn:hover {
    color: var(--color-primary);
    background: rgba(79, 110, 247, 0.1);
}

.chat-action-btn.btn-delete:hover {
    color: var(--color-danger);
    background: rgba(239, 68, 68, 0.1);
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
    padding: 12px 20px;
    border-top: 1px solid var(--color-border);
    background: var(--color-bg-secondary);
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
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

/* Edit banner above input */
#edit-banner {
    display: none;
    align-items: center;
    justify-content: space-between;
    background: rgba(79, 110, 247, 0.12);
    border-left: 3px solid var(--color-primary);
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    color: var(--color-text-main);
}

/* Mention Autocomplete Dropdown */
#mention-popover {
    display: none;
    position: absolute;
    bottom: 60px;
    left: 60px;
    width: 280px;
    max-height: 200px;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
    overflow-y: auto;
    z-index: 1000;
}

.mention-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.15s;
}

.mention-item:hover, .mention-item.selected {
    background: rgba(79, 110, 247, 0.2);
}

.mention-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--color-primary);
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* User Online/Offline Status Dots & Badges */
.user-status-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    border: 2px solid var(--color-bg-secondary);
    background: #64748b;
    transition: all 0.3s ease;
    z-index: 2;
}

.user-status-dot.online {
    background: #10b981;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.8);
}

.user-status-dot.offline {
    background: #64748b;
    opacity: 0.75;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--color-bg-tertiary);
    color: var(--color-text-secondary);
    border: 1px solid var(--color-border);
}

.status-pill.online {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.35);
}

.status-pill.offline {
    background: rgba(148, 163, 184, 0.1);
    color: var(--color-text-muted);
}

.status-pill .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    display: inline-block;
}

.status-pill.online .dot {
    box-shadow: 0 0 6px #10b981;
    animation: statusPulse 2s infinite ease-in-out;
}

@keyframes statusPulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.6; }
    100% { transform: scale(1); opacity: 1; }
}

.user-online-text {
    font-size: 10px;
    font-weight: 500;
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.user-online-text.online {
    color: #10b981;
    font-weight: 600;
}

.user-online-text.offline {
    color: var(--color-text-muted);
}
</style>

<div class="chat-app-container fade-in">
    <!-- LEFT SIDEBAR: DIRECT MESSAGES, CHANNELS & CUSTOM GROUPS -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <h3 style="margin: 0; font-size: 15px;"><i class="fa-solid fa-comments" style="color: var(--color-primary); margin-right: 4px;"></i> Team Messages</h3>
            <div style="display: flex; gap: 6px;">
                <button class="btn btn-outline btn-sm" onclick="openCreateGroupModal()" title="Create Group Chat" style="font-size: 11px; padding: 4px 8px;">
                    <i class="fa-solid fa-users-plus"></i> + Group
                </button>
                <button class="btn btn-primary btn-sm" onclick="openNewDMModal()" title="Direct Message Person" style="font-size: 11px; padding: 4px 8px;">
                    <i class="fa-solid fa-user"></i> DM
                </button>
            </div>
        </div>
        
        <div style="padding: 10px 14px 4px 14px; flex-shrink: 0;">
            <input type="text" id="chat-search-input" class="form-input" placeholder="Search person, group, or channel..." style="font-size: 12px; height: 34px;" onkeyup="filterDirectory()">
        </div>

        <div class="chat-room-list" id="chat-room-list">
            <div style="padding: 20px; text-align: center; color: var(--color-text-muted); font-size: 12px;">
                Loading chat rooms & team directory...
            </div>
        </div>
    </div>

    <!-- RIGHT MAIN: ACTIVE CONVERSATION -->
    <div class="chat-main">
        <div class="chat-main-header" id="chat-main-header">
            <div style="display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1;">
                <button id="mobile-chat-back-btn" class="btn btn-outline btn-sm" onclick="backToDirectoryOnMobile()" style="display: none; padding: 4px 8px; height: 30px; font-size: 11px; flex-shrink: 0;" title="Back to Directory">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div style="min-width: 0; flex: 1;">
                    <strong id="active-room-name" style="font-size: 13.5px; color: var(--color-text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">Select a Specific Person or Group</strong>
                    <div id="active-room-subtitle" class="text-muted" style="font-size: 10.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">Click any person or group from the left sidebar to start messaging</div>
                </div>
            </div>
            
            <!-- Room Top Actions (View Members, Delete Group, Clear History) -->
            <div id="room-actions-bar" style="display: none; align-items: center; gap: 5px; flex-shrink: 0;">
            </div>
        </div>

        <div class="chat-messages-area" id="chat-messages-area">
            <div style="margin: auto; text-align: center; color: var(--color-text-muted);">
                <i class="fa-solid fa-paper-plane" style="font-size: 40px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                <p style="font-size: 13px;">Select a person or group to start messaging, file sharing, and tagging.</p>
            </div>
        </div>

        <!-- INPUT BAR -->
        <div class="chat-input-bar" style="padding: 8px 12px; gap: 6px;">
            <!-- Edit Message Banner -->
            <div id="edit-banner">
                <div>
                    <i class="fa-solid fa-pen" style="color: var(--color-primary); margin-right: 4px;"></i>
                    <strong>Editing message:</strong> <span id="edit-preview-text" style="opacity: 0.85;"></span>
                </div>
                <button type="button" onclick="cancelEditMessage()" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-size: 12px; font-weight: bold;">Cancel (Esc)</button>
            </div>

            <!-- Selected File Preview Chip -->
            <div id="file-preview-container" style="display: none;">
                <span class="chat-file-chip">
                    <i class="fa-solid fa-paperclip"></i> <span id="file-preview-name">file.pdf</span>
                    <button type="button" onclick="clearFileAttachment()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-weight: bold; font-size: 14px;">×</button>
                </span>
            </div>

            <!-- Mention Autocomplete Popover -->
            <div id="mention-popover"></div>

            <form id="chat-form" onsubmit="handleChatSubmit(event)" enctype="multipart/form-data" style="margin: 0;">
                <div class="chat-input-controls" style="display: flex; align-items: center; gap: 6px;">
                    <!-- File Upload Icon Button -->
                    <label for="chat-file-input" style="cursor: pointer; height: 36px; width: 36px; display: flex; align-items: center; justify-content: center; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-secondary); flex-shrink: 0; margin: 0;" title="Attach Image or File">
                        <i class="fa-solid fa-paperclip" style="font-size: 13px;"></i>
                        <input type="file" id="chat-file-input" style="display: none;" onchange="handleFileSelected(this)">
                    </label>

                    <input type="text" id="chat-text-input" class="chat-input-field" placeholder="Type message or @ to tag..." autocomplete="off" oninput="handleInputForMentions(event)" onkeydown="handleInputKeydown(event)" style="height: 36px; padding: 6px 10px; font-size: 12.5px;">

                    <button type="submit" id="chat-submit-btn" class="btn btn-primary" style="height: 36px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; flex-shrink: 0;">
                        <i class="fa-solid fa-paper-plane" id="chat-btn-icon" style="font-size: 12px;"></i> <span id="chat-btn-label" class="hide-on-narrow">Send</span>
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
            <h3 class="card-title" style="margin: 0;"><i class="fa-solid fa-user-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Message Specific Person</h3>
            <button onclick="closeNewDMModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--color-text-muted);">×</button>
        </div>
        <p class="text-muted" style="font-size: 12px; margin-bottom: 14px;">Select any Founder, Manager, HR, or Employee to start a private 1-on-1 chat:</p>

        <div style="max-height: 280px; overflow-y: auto;" id="new-dm-user-list">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<!-- MODAL: CREATE CUSTOM GROUP CHAT -->
<div class="modal-backdrop" id="new-group-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card fade-in" style="width: 480px; max-width: 92vw;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="margin: 0;"><i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Create Custom Group</h3>
            <button onclick="closeCreateGroupModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--color-text-muted);">×</button>
        </div>
        <form onsubmit="submitCreateGroup(event)">
            <div style="padding: 16px 0;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Group Name *</label>
                    <input type="text" id="group-name-input" class="form-input" placeholder="e.g. Sprint Alpha, HR Coordination, Marketing Team" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Group Members *</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 8px;" id="group-member-checkboxes">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeCreateGroupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Group</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: VIEW GROUP MEMBERS -->
<div class="modal-backdrop" id="view-group-members-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card fade-in" style="width: 480px; max-width: 92vw;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 class="card-title" id="view-group-title" style="margin: 0;"><i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Group Members</h3>
                <div id="view-group-subtitle" class="text-muted" style="font-size: 11px; margin-top: 2px;">Loading members...</div>
            </div>
            <button onclick="closeViewGroupMembersModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--color-text-muted);">×</button>
        </div>
        <div style="padding: 12px 0;">
            <div style="max-height: 280px; overflow-y: auto;" id="view-group-members-list">
                <!-- Populated via JS -->
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border); padding-top: 12px; margin-top: 6px;">
            <div id="group-delete-container">
                <!-- Populated if creator/founder -->
            </div>
            <button type="button" class="btn btn-outline" onclick="closeViewGroupMembersModal()">Close</button>
        </div>
    </div>
</div>

<script>
let currentUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
let currentUserRole = '<?php echo e($_SESSION['user_role'] ?? ''); ?>';
let currentRoomId = null;
let roomsData = [];
let usersData = [];
let pollingTimer = null;
let selectedFile = null;
let editingMessageId = null;
let mentionMatchQuery = null;
let mentionSelectedIndex = 0;
let roomLastMessageId = {};
let isFirstLoadOfRoom = true;

// Real-time Cross-Tab / Cross-Window Broadcast Bus
const chatBroadcastBus = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('tms_realtime_chat_bus') : null;
if (chatBroadcastBus) {
    chatBroadcastBus.onmessage = function(event) {
        if (!event.data) return;
        handleBroadcastEvent(event.data);
    };
}

// LocalStorage Fallback for older browsers
window.addEventListener('storage', function(e) {
    if (e.key === 'tms_realtime_chat_event' && e.newValue) {
        try {
            const data = JSON.parse(e.newValue);
            handleBroadcastEvent(data);
        } catch(err) {}
    }
});

function broadcastChatEvent(payload) {
    if (chatBroadcastBus) {
        try { chatBroadcastBus.postMessage(payload); } catch(e) {}
    }
    try {
        localStorage.setItem('tms_realtime_chat_event', JSON.stringify({ ...payload, _ts: Date.now() }));
    } catch(e) {}
}

function handleBroadcastEvent(data) {
    if (!data || !data.type) return;
    
    if (data.type === 'new_message') {
        const msg = data.message;
        if (!msg || !msg.room_id) return;
        
        // If message is for currently open room
        if (currentRoomId && msg.room_id == currentRoomId) {
            const existingBubble = document.getElementById('msg-bubble-' + msg.id);
            if (!existingBubble) {
                appendSingleMessageBubble(msg);
                roomLastMessageId[currentRoomId] = Math.max(roomLastMessageId[currentRoomId] || 0, msg.id);
                
                if (!msg.is_self && msg.sender_id != currentUserId) {
                    const preview = msg.message || (msg.file_name ? 'Shared a file: ' + msg.file_name : 'New message');
                    triggerChatNotification('New Message from ' + (msg.sender_name || 'Team Member'), preview, true);
                }
            }
        } else {
            // Update sidebar unread counters
            loadRooms(true);
        }
    } else if (data.type === 'edit_message') {
        if (currentRoomId && data.room_id == currentRoomId) {
            updateMessageBubbleText(data.message_id, data.new_text);
        }
    } else if (data.type === 'delete_message') {
        if (currentRoomId && data.room_id == currentRoomId) {
            removeMessageBubble(data.message_id);
        }
    } else if (data.type === 'wipe_history') {
        if (currentRoomId && data.room_id == currentRoomId) {
            loadMessages(currentRoomId);
        }
        loadRooms(true);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadRooms();
    
    // Fast Polling: 1200ms when tab active, 3000ms when hidden
    function startRealtimePolling() {
        if (pollingTimer) clearInterval(pollingTimer);
        const interval = document.visibilityState === 'visible' ? 1200 : 3000;
        pollingTimer = setInterval(function() {
            if (currentRoomId && !editingMessageId) {
                loadMessages(currentRoomId, true);
            }
            loadRooms(true);
        }, interval);
    }
    
    startRealtimePolling();
    document.addEventListener('visibilitychange', startRealtimePolling);
    
    // Close popovers on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#mention-popover') && !e.target.closest('#chat-text-input')) {
            hideMentionPopover();
        }
    });
});

function loadRooms(isSilent = false) {
    fetch(window.BASE_URL + '/api/chat.php?action=get_rooms')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentUserId = data.current_user_id || currentUserId;
            currentUserRole = data.current_user_role || currentUserRole;
            roomsData = data.rooms;
            usersData = data.users;
            renderDirectory(roomsData, usersData);
            populateNewDMUsers(usersData, currentUserId);
            populateGroupMembers(usersData, currentUserId);
            
            const totalUnread = roomsData.reduce((acc, r) => acc + (parseInt(r.unread_count || 0, 10)), 0);
            if (window.parent && window.parent !== window) {
                try {
                    window.parent.postMessage({ type: 'chat_unread_updated', unread_count: totalUnread }, '*');
                } catch(e) {}
            }
            
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

    // 1. PUBLIC TEAM CHANNELS (#General)
    const publicRooms = rooms.filter(r => r.type === 'group' && r.id === 1);
    if (publicRooms.length > 0) {
        html += `<div class="chat-section-header"><i class="fa-solid fa-bullhorn" style="color: var(--color-primary); font-size: 11px;"></i><span>Public Channels</span></div>`;
        publicRooms.forEach(r => {
            const isActive = (r.id === currentRoomId) ? 'active' : '';
            const badge = r.unread_count > 0 ? `<span class="badge badge-danger" style="border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0;">${r.unread_count}</span>` : '';
            const onlineText = (r.online_count > 0) ? `<span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 6px; vertical-align: middle; margin-right: 3px;"></i>${r.online_count} online</span> • ` : '';
            html += `
                <div class="chat-room-item ${isActive}" onclick="switchRoom(${r.id})">
                    <div class="chat-room-avatar" style="background: var(--color-primary);">
                        <i class="fa-solid fa-bullhorn"></i>
                        <span class="user-status-dot online" title="Active Channel"></span>
                    </div>
                    <div class="chat-room-info">
                        <div class="chat-room-name">${escapeHtml(r.name)}</div>
                        <div class="chat-room-preview">${onlineText}${escapeHtml(r.last_message || 'Team general discussion')}</div>
                    </div>
                    ${badge}
                </div>
            `;
        });
    }

    // 2. CUSTOM GROUPS
    const customGroups = rooms.filter(r => r.type === 'group' && r.id !== 1);
    if (customGroups.length > 0) {
        html += `<div class="chat-section-header" style="margin-top: 10px;"><i class="fa-solid fa-users" style="color: var(--color-primary); font-size: 11px;"></i><span>Custom Groups</span></div>`;
        customGroups.forEach(r => {
            const isActive = (r.id === currentRoomId) ? 'active' : '';
            const badge = r.unread_count > 0 ? `<span class="badge badge-danger" style="border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0;">${r.unread_count}</span>` : '';
            const onlineText = (r.online_count > 0) ? `<span style="color: #10b981; font-weight: 600;">${r.online_count} online</span> • ` : '';
            html += `
                <div class="chat-room-item ${isActive}" onclick="switchRoom(${r.id})">
                    <div class="chat-room-avatar" style="background: #8b5cf6;">
                        <i class="fa-solid fa-users"></i>
                        <span class="user-status-dot ${r.online_count > 0 ? 'online' : 'offline'}"></span>
                    </div>
                    <div class="chat-room-info">
                        <div class="chat-room-name">${escapeHtml(r.name)}</div>
                        <div class="chat-room-preview"><i class="fa-solid fa-user-group" style="font-size: 10px;"></i> ${onlineText}${r.member_count || 2} members • ${escapeHtml(r.last_message || 'Group created')}</div>
                    </div>
                    ${badge}
                </div>
            `;
        });
    }

    // 3. DIRECT MESSAGES — SPECIFIC PERSONS BY ROLE
    html += `<div class="chat-section-header" style="margin-top: 12px;"><i class="fa-solid fa-user" style="color: var(--color-primary); font-size: 11px;"></i><span>Specific Persons (Direct Chat)</span></div>`;

    if (!users || users.length === 0) {
        html += `<div style="padding: 10px; text-align: center; color: var(--color-text-muted); font-size: 12px;">No team members found.</div>`;
    } else {
        users.forEach(u => {
            if (currentUserId && u.id == currentUserId) return;
            const existingRoom = rooms.find(r => r.type === 'direct' && r.partner_id === u.id);
            const roomId = existingRoom ? existingRoom.id : null;
            const isActive = (roomId && roomId === currentRoomId) ? 'active' : '';
            const unreadBadge = u.unread_count > 0 ? `<span class="badge badge-danger" style="border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0;">${u.unread_count}</span>` : '';
            const roleBadgeClass = 'badge-role-' + u.role;
            const isOnline = !!u.is_online;
            const statusLabel = isOnline ? '● Online' : (u.last_seen_text || 'Offline');

            html += `
                <div class="chat-room-item ${isActive}" onclick="startDMWithUser(${u.id})">
                    <div class="chat-room-avatar">
                        ${u.initials}
                        <span class="user-status-dot ${isOnline ? 'online' : 'offline'}" title="${isOnline ? 'Online' : (u.last_seen_text || 'Offline')}"></span>
                    </div>
                    <div class="chat-room-info">
                        <div class="chat-room-name" style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                            <span style="overflow: hidden; text-overflow: ellipsis;">${escapeHtml(u.name)}</span>
                            <span class="chat-role-badge ${roleBadgeClass}">${u.role}</span>
                        </div>
                        <div class="chat-room-preview" style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                            <span style="overflow: hidden; text-overflow: ellipsis;">${escapeHtml(u.designation || ucfirst(u.role))}</span>
                            <span class="user-online-text ${isOnline ? 'online' : 'offline'}">${statusLabel}</span>
                        </div>
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
    const filteredRooms = roomsData.filter(r => r.name.toLowerCase().includes(query));
    renderDirectory(filteredRooms, filteredUsers);
}

function updateActiveRoomHeaderUI(roomInfo) {
    if (!roomInfo) return;
    const nameEl = document.getElementById('active-room-name');
    const subEl = document.getElementById('active-room-subtitle');
    if (!nameEl || !subEl) return;

    nameEl.textContent = roomInfo.name || 'Chat';
    
    if (roomInfo.type === 'direct') {
        const isOnline = !!roomInfo.is_online;
        const statusText = isOnline ? 'Online' : (roomInfo.last_seen_text || 'Offline');
        const roleBadge = roomInfo.role ? `<span class="chat-role-badge badge-role-${roomInfo.role}" style="margin-right: 6px;">${roomInfo.role}</span>` : '';
        const desig = roomInfo.designation ? `<span class="text-muted" style="font-size: 11px; margin-left: 6px;">(${escapeHtml(roomInfo.designation)})</span>` : '';
        
        subEl.innerHTML = `
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px;">
                ${roleBadge}
                <span class="status-pill ${isOnline ? 'online' : 'offline'}">
                    <span class="dot"></span> ${statusText}
                </span>
                ${desig}
            </div>
        `;
    } else {
        const onlineCount = roomInfo.online_count || 0;
        const memberCount = roomInfo.member_count || 2;
        const groupLabel = (roomInfo.id === 1) ? 'Public Team Channel' : `Custom Group`;
        
        subEl.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 2px;">
                <span>${groupLabel}</span>
                <span class="status-pill ${onlineCount > 0 ? 'online' : 'offline'}">
                    <span class="dot"></span> ${onlineCount} Online
                </span>
                <span class="text-muted" style="font-size: 11px;">• ${memberCount} members</span>
            </div>
        `;
    }
}

function switchRoom(roomId) {
    currentRoomId = roomId;
    isFirstLoadOfRoom = true;
    cancelEditMessage();
    renderDirectory(roomsData, usersData);
    
    document.querySelector('.chat-app-container')?.classList.add('room-active');
    if (window.innerWidth <= 768) {
        const backBtn = document.getElementById('mobile-chat-back-btn');
        if (backBtn) backBtn.style.display = 'inline-flex';
    }

    const activeRoom = roomsData.find(r => r.id === roomId);
    if (activeRoom) {
        updateActiveRoomHeaderUI(activeRoom);
        
        const actionsBar = document.getElementById('room-actions-bar');
        if (actionsBar) {
            let actionsHtml = '';
            if (activeRoom.type === 'group') {
                actionsHtml += `<button type="button" class="btn btn-outline btn-sm" onclick="openViewGroupMembersModal(${activeRoom.id})" title="View Group Members" style="font-size: 11px; padding: 4px 8px; height: 30px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-users"></i> <span class="hide-on-narrow">Members</span></button>`;
                if (activeRoom.id !== 1 && activeRoom.can_clear_history) {
                    actionsHtml += `<button type="button" class="btn btn-outline btn-sm" onclick="confirmDeleteGroup(${activeRoom.id})" title="Permanently delete this group" style="color: var(--color-danger); border-color: rgba(239, 68, 68, 0.4); font-size: 11px; padding: 4px 8px; height: 30px; display: inline-flex; align-items: center;"><i class="fa-solid fa-trash"></i></button>`;
                }
            }
            actionsHtml += `<button type="button" class="btn btn-outline btn-sm" onclick="confirmDeleteHistory()" title="Permanently delete all chat history in this room" style="color: var(--color-danger); border-color: rgba(239, 68, 68, 0.4); font-size: 11px; padding: 4px 8px; height: 30px; display: inline-flex; align-items: center;"><i class="fa-solid fa-trash-can"></i></button>`;
            actionsBar.innerHTML = actionsHtml;
            actionsBar.style.display = 'flex';
        }
    }
    
    loadMessages(roomId, false);
}

function backToDirectoryOnMobile() {
    currentRoomId = null;
    document.querySelector('.chat-app-container')?.classList.remove('room-active');
    const backBtn = document.getElementById('mobile-chat-back-btn');
    if (backBtn) backBtn.style.display = 'none';
}

function triggerChatNotification(title, message, isIncoming = false) {
    // 1. Play Sound
    if (typeof window.playNotificationSound === 'function') {
        window.playNotificationSound();
    }
    if (window.parent && window.parent !== window) {
        try {
            window.parent.postMessage({ type: 'play_sound' }, '*');
        } catch(e) {}
    }

    // 2. Show Toast Popup Card at Top-Right
    const notifObj = {
        title: title,
        message: message,
        category: 'chat',
        icon: isIncoming ? 'fa-solid fa-comments' : 'fa-solid fa-paper-plane'
    };

    if (typeof window.showToastNotification === 'function') {
        window.showToastNotification(notifObj);
    }
    if (window.parent && window.parent !== window) {
        try {
            window.parent.postMessage({ type: 'show_toast', notif: notifObj }, '*');
        } catch(e) {}
    }
}

function buildMessageBubbleHtml(m) {
    const isSelf = m.is_self || (m.sender_id == currentUserId);
    const selfClass = isSelf ? 'is-self' : '';
    let attachmentHtml = '';
    
    if (m.file_path) {
        if (m.is_image && m.file_url) {
            attachmentHtml = `<br><a href="${m.file_url}" target="_blank"><img src="${m.file_url}" class="chat-attachment-img" alt="Uploaded Image"></a>`;
        } else if (m.file_url) {
            attachmentHtml = `
                <a href="${m.file_url}" target="_blank" class="chat-attachment-file">
                    <i class="fa-solid fa-file-arrow-down" style="font-size: 16px; color: var(--color-primary);"></i>
                    <div>
                        <strong>${escapeHtml(m.file_name || 'Attachment')}</strong>
                        <div style="font-size: 10px; opacity: 0.8;">Click to Download</div>
                    </div>
                </a>
            `;
        } else if (m.is_temp) {
            attachmentHtml = `
                <div class="chat-attachment-file" style="opacity: 0.8;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 14px; color: var(--color-primary);"></i>
                    <div>
                        <strong>${escapeHtml(m.file_name || 'Uploading file...')}</strong>
                        <div style="font-size: 10px; opacity: 0.8;">Uploading...</div>
                    </div>
                </div>
            `;
        }
    }

    // Action Toolbar (Edit / Delete)
    let actionsHtml = '';
    if (!m.is_temp && (m.can_edit || m.can_delete || isSelf)) {
        actionsHtml += `<div class="chat-msg-actions">`;
        if (m.can_edit || (isSelf && !m.file_path)) {
            const rawMsgEscaped = escapeAttr(m.message || '');
            actionsHtml += `<button type="button" class="chat-action-btn" onclick="startEditMessage(${m.id}, '${rawMsgEscaped}')" title="Edit Message"><i class="fa-solid fa-pen"></i></button>`;
        }
        if (m.can_delete || isSelf || currentUserRole === 'founder') {
            actionsHtml += `<button type="button" class="chat-action-btn btn-delete" onclick="deleteSingleMessage(${m.id})" title="Delete Message"><i class="fa-solid fa-trash-can"></i></button>`;
        }
        actionsHtml += `</div>`;
    }

    const editedHtml = (m.is_edited == 1) ? `<span class="chat-edited-tag">(edited)</span>` : '';
    const tempPulse = m.is_temp ? 'opacity: 0.7;' : '';

    return `
        <div class="chat-bubble-row ${selfClass}" id="msg-bubble-${m.id}" data-msg-id="${m.id}" style="${tempPulse}">
            <div class="chat-bubble-avatar">${m.initials || 'U'}</div>
            <div style="position: relative;">
                ${actionsHtml}
                <div class="chat-bubble-content">
                    ${!isSelf ? `<div style="font-weight: 600; font-size: 11px; margin-bottom: 2px; color: var(--color-primary);">${escapeHtml(m.sender_name || 'Team Member')}</div>` : ''}
                    <div class="chat-msg-text">${m.formatted_html || escapeHtml(m.message || '')}</div>
                    ${attachmentHtml}
                    <div class="chat-bubble-meta">
                        <span>${m.time || 'Just now'}</span>
                        ${editedHtml}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function appendSingleMessageBubble(m) {
    const area = document.getElementById('chat-messages-area');
    if (!area) return;
    
    // Check if empty placeholder exists
    if (area.innerHTML.includes('No messages here yet')) {
        area.innerHTML = '';
    }
    
    const existing = document.getElementById('msg-bubble-' + m.id);
    if (existing) {
        existing.outerHTML = buildMessageBubbleHtml(m);
        return;
    }
    
    const isAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 250;
    area.insertAdjacentHTML('beforeend', buildMessageBubbleHtml(m));
    
    if (isAtBottom || m.is_self) {
        area.scrollTo({ top: area.scrollHeight, behavior: 'smooth' });
    }
}

function updateMessageBubbleText(messageId, newText) {
    const bubble = document.getElementById('msg-bubble-' + messageId);
    if (bubble) {
        const textEl = bubble.querySelector('.chat-msg-text');
        if (textEl) textEl.innerHTML = escapeHtml(newText).replace(/\n/g, '<br>');
        const metaEl = bubble.querySelector('.chat-bubble-meta');
        if (metaEl && !metaEl.querySelector('.chat-edited-tag')) {
            metaEl.insertAdjacentHTML('beforeend', '<span class="chat-edited-tag">(edited)</span>');
        }
    }
}

function removeMessageBubble(messageId) {
    const bubble = document.getElementById('msg-bubble-' + messageId);
    if (bubble) {
        bubble.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        bubble.style.opacity = '0';
        bubble.style.transform = 'scale(0.95)';
        setTimeout(() => bubble.remove(), 200);
    }
}

function loadMessages(roomId, isSilent = false) {
    if (!roomId) return;
    const sinceId = (isSilent && roomLastMessageId[roomId]) ? roomLastMessageId[roomId] : 0;
    
    fetch(window.BASE_URL + '/api/chat.php?action=get_messages&room_id=' + roomId + (sinceId > 0 ? '&since_id=' + sinceId : ''))
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Live update header status if available
            if (data.room_info && currentRoomId === roomId) {
                updateActiveRoomHeaderUI(data.room_info);
            }

            if (sinceId > 0) {
                // Incremental fetch
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        appendSingleMessageBubble(m);
                        roomLastMessageId[roomId] = Math.max(roomLastMessageId[roomId] || 0, m.id);
                        
                        if (!m.is_self) {
                            const preview = m.message || (m.file_name ? 'Shared a file: ' + m.file_name : 'New message');
                            triggerChatNotification('New Message from ' + (m.sender_name || 'Team Member'), preview, true);
                        }
                    });
                }
            } else {
                // Initial / full load for this room
                renderMessages(data.messages || []);
                if (data.max_id) {
                    roomLastMessageId[roomId] = data.max_id;
                } else if (data.messages && data.messages.length > 0) {
                    roomLastMessageId[roomId] = Math.max(...data.messages.map(m => m.id));
                }
            }
        }
    })
    .catch(() => {});
}

function renderMessages(messages) {
    const area = document.getElementById('chat-messages-area');
    if (!messages || messages.length === 0) {
        area.innerHTML = '<div style="margin: auto; text-align: center; color: var(--color-text-muted); font-size: 13px;"><i class="fa-solid fa-comments" style="font-size: 32px; opacity: 0.3; display: block; margin-bottom: 8px;"></i>No messages here yet. Say hello or type @ to tag someone!</div>';
        return;
    }

    let html = '';
    messages.forEach(m => {
        html += buildMessageBubbleHtml(m);
    });
    
    area.innerHTML = html;
    area.scrollTop = area.scrollHeight;
}

// ---------------- MESSAGE SUBMIT / EDIT / DELETE ----------------

function handleChatSubmit(e) {
    e.preventDefault();
    if (!currentRoomId) return;

    if (editingMessageId) {
        saveEditedMessage();
    } else {
        sendMessage();
    }
}

function sendMessage() {
    const input = document.getElementById('chat-text-input');
    const messageText = input.value.trim();
    const fileToUpload = selectedFile;

    if (!messageText && !fileToUpload) return;

    // 1. Optimistic Instant UI rendering
    const tempId = 'temp_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    const optimisticMsg = {
        id: tempId,
        is_temp: true,
        room_id: currentRoomId,
        sender_id: currentUserId,
        sender_name: 'You',
        sender_role: currentUserRole,
        message: messageText,
        formatted_html: escapeHtml(messageText).replace(/\n/g, '<br>'),
        time: nowTime,
        initials: 'YOU',
        is_self: true,
        can_edit: false,
        can_delete: false,
        is_edited: 0,
        file_path: fileToUpload ? 'temp' : null,
        file_name: fileToUpload ? fileToUpload.name : null,
        is_image: fileToUpload ? fileToUpload.type.startsWith('image/') : false,
        file_url: (fileToUpload && fileToUpload.type.startsWith('image/')) ? URL.createObjectURL(fileToUpload) : null
    };

    // Instant append to DOM & auto scroll
    appendSingleMessageBubble(optimisticMsg);

    // Instant chime sound and top-right toast notification
    const previewMsg = messageText ? (messageText.length > 60 ? messageText.substring(0, 60) + '...' : messageText) : (fileToUpload ? 'Attachment: ' + fileToUpload.name : 'Message sent');
    triggerChatNotification('Message Sent', previewMsg, false);

    // Clear input & selection immediately so user can keep typing
    input.value = '';
    clearFileAttachment();
    hideMentionPopover();
    input.focus();

    // Prepare FormData
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('room_id', currentRoomId);
    formData.append('message', messageText);
    if (fileToUpload) {
        formData.append('attachment', fileToUpload);
    }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    formData.append('csrf_token', csrfToken);

    // Send HTTP POST in background
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.message) {
            const confirmedMsg = data.message;
            const tempBubble = document.getElementById('msg-bubble-' + tempId);
            if (tempBubble) {
                tempBubble.id = 'msg-bubble-' + confirmedMsg.id;
                tempBubble.setAttribute('data-msg-id', confirmedMsg.id);
                tempBubble.outerHTML = buildMessageBubbleHtml(confirmedMsg);
            }
            roomLastMessageId[currentRoomId] = Math.max(roomLastMessageId[currentRoomId] || 0, confirmedMsg.id);
            
            // Broadcast to other open tabs/windows in real-time
            broadcastChatEvent({
                type: 'new_message',
                room_id: currentRoomId,
                message: confirmedMsg
            });

            loadRooms(true);
        } else {
            const tempBubble = document.getElementById('msg-bubble-' + tempId);
            if (tempBubble) {
                tempBubble.style.borderColor = 'var(--color-danger)';
                tempBubble.insertAdjacentHTML('beforeend', '<div style="color: var(--color-danger); font-size: 10px; margin-top: 4px;"><i class="fa-solid fa-circle-exclamation"></i> Failed to send</div>');
            }
            alert(data.message || 'Failed to send message.');
        }
    })
    .catch(err => {
        const tempBubble = document.getElementById('msg-bubble-' + tempId);
        if (tempBubble) {
            tempBubble.style.borderColor = 'var(--color-danger)';
        }
    });
}

function startEditMessage(messageId, currentText) {
    editingMessageId = messageId;
    const input = document.getElementById('chat-text-input');
    input.value = currentText;
    input.focus();

    document.getElementById('edit-preview-text').textContent = currentText.length > 50 ? currentText.substring(0, 50) + '...' : currentText;
    document.getElementById('edit-banner').style.display = 'flex';
    document.getElementById('chat-btn-label').textContent = 'Save';
    document.getElementById('chat-btn-icon').className = 'fa-solid fa-check';
}

function cancelEditMessage() {
    editingMessageId = null;
    const input = document.getElementById('chat-text-input');
    input.value = '';
    document.getElementById('edit-banner').style.display = 'none';
    document.getElementById('chat-btn-label').textContent = 'Send';
    document.getElementById('chat-btn-icon').className = 'fa-solid fa-paper-plane';
}

function saveEditedMessage() {
    const input = document.getElementById('chat-text-input');
    const newText = input.value.trim();
    if (!newText || !editingMessageId) return;

    const savedId = editingMessageId;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    // Instant UI update
    updateMessageBubbleText(savedId, newText);
    cancelEditMessage();

    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=edit_message&message_id=${savedId}&message=${encodeURIComponent(newText)}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            broadcastChatEvent({
                type: 'edit_message',
                room_id: currentRoomId,
                message_id: savedId,
                new_text: newText
            });
        } else {
            alert(data.message || 'Failed to edit message.');
            loadMessages(currentRoomId);
        }
    });
}

function deleteSingleMessage(messageId) {
    if (!confirm('Are you sure you want to permanently delete this message?')) return;

    // Instant UI removal
    removeMessageBubble(messageId);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_message&message_id=${messageId}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            broadcastChatEvent({
                type: 'delete_message',
                room_id: currentRoomId,
                message_id: messageId
            });
            loadRooms(true);
        } else {
            alert(data.message || 'Failed to delete message.');
            loadMessages(currentRoomId);
        }
    });
}

function confirmDeleteHistory() {
    if (!currentRoomId) return;
    const confirmed = confirm('WARNING: This will permanently wipe and delete ALL chat messages and attachments in this conversation from the database.\n\nAre you sure you want to proceed?');
    if (!confirmed) return;

    const targetRoom = currentRoomId;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_room_history&room_id=${targetRoom}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('chat-messages-area').innerHTML = '<div style="margin: auto; text-align: center; color: var(--color-text-muted); font-size: 13px;"><i class="fa-solid fa-comments" style="font-size: 32px; opacity: 0.3; display: block; margin-bottom: 8px;"></i>No messages here yet. Say hello or type @ to tag someone!</div>';
            roomLastMessageId[targetRoom] = 0;
            broadcastChatEvent({
                type: 'wipe_history',
                room_id: targetRoom
            });
            loadRooms(true);
            alert('Chat history completely cleared from database.');
        } else {
            alert(data.message || 'Failed to clear chat history.');
        }
    });
}

// ---------------- GROUP MEMBERS & GROUP DELETION ----------------

function openViewGroupMembersModal(roomId = null) {
    const targetRoomId = roomId || currentRoomId;
    if (!targetRoomId) return;

    document.getElementById('view-group-members-modal').style.display = 'flex';
    document.getElementById('view-group-members-list').innerHTML = '<div style="text-align: center; padding: 20px; color: var(--color-text-muted); font-size: 12px;">Loading group members...</div>';
    document.getElementById('group-delete-container').innerHTML = '';

    fetch(window.BASE_URL + '/api/chat.php?action=get_group_members&room_id=' + targetRoomId)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('view-group-title').innerHTML = `<i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> ${escapeHtml(data.group_name)}`;
            document.getElementById('view-group-subtitle').textContent = `${data.members.length} Member(s) in this group`;

            let html = '';

            // If admin, show add member bar
            if (data.is_admin_or_founder && data.available_users && data.available_users.length > 0) {
                html += `
                    <div style="display: flex; gap: 8px; align-items: center; padding: 10px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 12px;">
                        <select id="group-add-member-select" class="form-select" style="font-size: 13px; padding: 6px 36px 6px 12px; height: 38px; line-height: 24px; flex: 1; border-radius: var(--radius-md); box-sizing: border-box;">
                            <option value="">+ Select Team Member to Add</option>
                            ${data.available_users.map(u => `<option value="${u.id}">${escapeHtml(u.name)} (${u.role})</option>`).join('')}
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addMemberToGroup(${data.room_id})" style="font-size: 12px; height: 38px; padding: 0 16px; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                            <i class="fa-solid fa-user-plus"></i> Add Member
                        </button>
                    </div>
                `;
            }

            data.members.forEach(m => {
                const roleClass = 'badge-role-' + m.role;
                const adminBadge = (m.is_creator == 1) ? '<span class="badge badge-primary" style="font-size: 9px; padding: 1px 6px; margin-left: 6px;"><i class="fa-solid fa-crown" style="font-size: 8px;"></i> Admin</span>' : '';
                const isOnline = !!m.is_online;
                const statusText = isOnline ? 'Online' : (m.last_seen_text || 'Offline');
                
                let removeBtn = '';
                if (data.is_admin_or_founder && m.is_creator != 1) {
                    removeBtn = `
                        <button type="button" class="btn btn-ghost btn-sm" onclick="removeMemberFromGroup(${data.room_id}, ${m.id}, '${escapeAttr(m.name)}')" title="Remove ${escapeAttr(m.name)} from group" style="color: var(--color-danger); padding: 3px 8px; font-size: 11px; margin-left: 8px;">
                            <i class="fa-solid fa-user-minus"></i> Remove
                        </button>
                    `;
                }

                html += `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-bottom: 1px solid var(--color-border);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="chat-room-avatar" style="width: 32px; height: 32px; font-size: 11px;">
                                ${m.initials}
                                <span class="user-status-dot ${isOnline ? 'online' : 'offline'}" title="${isOnline ? 'Online' : (m.last_seen_text || 'Offline')}"></span>
                            </div>
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--color-text-main); display: flex; align-items: center; gap: 6px;">
                                    ${escapeHtml(m.name)} ${adminBadge}
                                    <span class="status-pill ${isOnline ? 'online' : 'offline'}" style="font-size: 9.5px; padding: 1px 6px;"><span class="dot"></span> ${statusText}</span>
                                </div>
                                <div style="font-size: 11px; color: var(--color-text-muted);">${escapeHtml(m.designation || ucfirst(m.role))} • Joined: ${m.joined_formatted}</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <span class="chat-role-badge ${roleClass}">${m.role}</span>
                            ${removeBtn}
                        </div>
                    </div>
                `;
            });
            document.getElementById('view-group-members-list').innerHTML = html;

            if (data.can_delete_group) {
                document.getElementById('group-delete-container').innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline" onclick="closeViewGroupMembersModal(); confirmDeleteGroup(${data.room_id});" style="color: var(--color-danger); border-color: rgba(239, 68, 68, 0.4); font-size: 11px;">
                        <i class="fa-solid fa-trash"></i> Delete Group
                    </button>
                `;
            }
        } else {
            document.getElementById('view-group-members-list').innerHTML = `<div style="color: var(--color-danger); padding: 15px; font-size: 12px;">${escapeHtml(data.message || 'Failed to load group members.')}</div>`;
        }
    });
}

function removeMemberFromGroup(roomId, userId, userName) {
    if (!confirm(`Are you sure you want to remove "${userName}" from this group?`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=remove_group_member&room_id=${roomId}&user_id=${userId}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            openViewGroupMembersModal(roomId);
            loadRooms();
            loadMessages(roomId);
        } else {
            alert(data.message || 'Failed to remove member.');
        }
    });
}

function addMemberToGroup(roomId) {
    const select = document.getElementById('group-add-member-select');
    const userId = parseInt(select.value);
    if (!userId) {
        alert('Please select a team member to add.');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_group_member&room_id=${roomId}&user_id=${userId}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            openViewGroupMembersModal(roomId);
            loadRooms();
            loadMessages(roomId);
        } else {
            alert(data.message || 'Failed to add member.');
        }
    });
}

function closeViewGroupMembersModal() {
    document.getElementById('view-group-members-modal').style.display = 'none';
}

function confirmDeleteGroup(roomId = null) {
    const targetRoomId = roomId || currentRoomId;
    if (!targetRoomId || targetRoomId === 1) return;

    const confirmed = confirm('WARNING: Are you sure you want to permanently delete this group? All group conversations, messages, files, and memberships will be removed from the database.');
    if (!confirmed) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_group&room_id=${targetRoomId}&csrf_token=${csrfToken}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Group deleted successfully.');
            currentRoomId = 1;
            loadRooms();
            setTimeout(() => switchRoom(1), 300);
        } else {
            alert(data.message || 'Failed to delete group.');
        }
    });
}

// ---------------- @MENTIONS SYSTEM ----------------

function handleInputForMentions(e) {
    const input = document.getElementById('chat-text-input');
    const val = input.value;
    const cursorPos = input.selectionStart;
    
    // Find last @ before cursor
    const lastAt = val.lastIndexOf('@', cursorPos - 1);
    if (lastAt !== -1 && (lastAt === 0 || val.charAt(lastAt - 1) === ' ')) {
        const query = val.substring(lastAt + 1, cursorPos).toLowerCase();
        showMentionPopover(query, lastAt);
    } else {
        hideMentionPopover();
    }
}

function showMentionPopover(query, atIndex) {
    const popover = document.getElementById('mention-popover');
    const filtered = usersData.filter(u => u.name.toLowerCase().includes(query) || u.role.toLowerCase().includes(query));
    
    if (filtered.length === 0) {
        hideMentionPopover();
        return;
    }
    
    mentionSelectedIndex = 0;
    let html = '';
    filtered.forEach((u, idx) => {
        const isSelected = (idx === 0) ? 'selected' : '';
        const roleClass = 'badge-role-' + u.role;
        html += `
            <div class="mention-item ${isSelected}" onclick="selectMentionUser('${escapeAttr(u.name)}', ${atIndex})">
                <div class="mention-avatar">${u.initials}</div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 12px; color: var(--color-text-main);">${escapeHtml(u.name)}</div>
                    <div style="font-size: 10px; color: var(--color-text-muted);">${escapeHtml(u.designation || u.role)}</div>
                </div>
                <span class="chat-role-badge ${roleClass}">${u.role}</span>
            </div>
        `;
    });
    
    popover.innerHTML = html;
    popover.style.display = 'block';
}

function hideMentionPopover() {
    const popover = document.getElementById('mention-popover');
    if (popover) popover.style.display = 'none';
}

function selectMentionUser(name, atIndex) {
    const input = document.getElementById('chat-text-input');
    const val = input.value;
    const cursorPos = input.selectionStart;
    
    const before = val.substring(0, atIndex);
    const after = val.substring(cursorPos);
    
    input.value = before + '@' + name + ' ' + after;
    input.focus();
    const newPos = (before + '@' + name + ' ').length;
    input.setSelectionRange(newPos, newPos);
    
    hideMentionPopover();
}

function handleInputKeydown(e) {
    const popover = document.getElementById('mention-popover');
    if (popover && popover.style.display === 'block') {
        const items = popover.querySelectorAll('.mention-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionSelectedIndex = (mentionSelectedIndex + 1) % items.length;
            updateMentionSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionSelectedIndex = (mentionSelectedIndex - 1 + items.length) % items.length;
            updateMentionSelection(items);
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            if (items[mentionSelectedIndex]) {
                items[mentionSelectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            hideMentionPopover();
        }
    } else if (e.key === 'Escape' && editingMessageId) {
        cancelEditMessage();
    }
}

function updateMentionSelection(items) {
    items.forEach((item, idx) => {
        if (idx === mentionSelectedIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected');
        }
    });
}

// ---------------- FILE ATTACHMENTS ----------------

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

// ---------------- GROUP & DM MODALS ----------------

function openNewDMModal() {
    document.getElementById('new-dm-modal').style.display = 'flex';
}

function closeNewDMModal() {
    document.getElementById('new-dm-modal').style.display = 'none';
}

function openCreateGroupModal() {
    document.getElementById('new-group-modal').style.display = 'flex';
}

function closeCreateGroupModal() {
    document.getElementById('new-group-modal').style.display = 'none';
}

function populateNewDMUsers(users, currentUserId) {
    const listEl = document.getElementById('new-dm-user-list');
    let html = '';
    
    users.forEach(u => {
        if (u.id === currentUserId) return;
        const roleClass = 'badge-role-' + u.role;
        const isOnline = !!u.is_online;
        const statusText = isOnline ? 'Online' : (u.last_seen_text || 'Offline');
        html += `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--color-border); cursor: pointer;" onclick="startDMWithUser(${u.id})">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="chat-room-avatar" style="width: 32px; height: 32px; font-size: 11px;">
                        ${u.initials}
                        <span class="user-status-dot ${isOnline ? 'online' : 'offline'}" title="${isOnline ? 'Online' : (u.last_seen_text || 'Offline')}"></span>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <strong style="font-size: 13px; color: var(--color-text-main);">${escapeHtml(u.name)}</strong>
                            <span class="chat-role-badge ${roleClass}">${u.role}</span>
                            <span class="status-pill ${isOnline ? 'online' : 'offline'}" style="font-size: 9.5px; padding: 1px 6px;"><span class="dot"></span> ${statusText}</span>
                        </div>
                        <div class="text-muted" style="font-size: 11px; margin-top: 2px;">${escapeHtml(u.designation || ucfirst(u.role))}</div>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" style="font-size: 11px;"><i class="fa-solid fa-comments"></i> Message</button>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

function populateGroupMembers(users, currentUserId) {
    const container = document.getElementById('group-member-checkboxes');
    let html = '';
    
    users.forEach(u => {
        if (u.id === currentUserId) return;
        const roleClass = 'badge-role-' + u.role;
        html += `
            <label style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 4px; cursor: pointer; transition: background 0.15s;" onmouseover="this.style.background='rgba(79, 110, 247, 0.1)'" onmouseout="this.style.background='none'">
                <input type="checkbox" name="group_members[]" value="${u.id}" style="accent-color: var(--color-primary); width: 16px; height: 16px;">
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 12.5px; font-weight: 500;">${escapeHtml(u.name)}</span>
                    <span class="chat-role-badge ${roleClass}">${u.role}</span>
                </div>
            </label>
        `;
    });
    container.innerHTML = html;
}

function submitCreateGroup(e) {
    e.preventDefault();
    const groupName = document.getElementById('group-name-input').value.trim();
    const checkboxes = document.querySelectorAll('input[name="group_members[]"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

    if (!groupName) {
        alert('Please enter a group name.');
        return;
    }
    if (selectedIds.length === 0) {
        alert('Please select at least 1 team member to add to the group.');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData();
    formData.append('action', 'create_group');
    formData.append('group_name', groupName);
    formData.append('members', JSON.stringify(selectedIds));
    formData.append('csrf_token', csrfToken);

    fetch(window.BASE_URL + '/api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeCreateGroupModal();
            document.getElementById('group-name-input').value = '';
            loadRooms();
            setTimeout(() => switchRoom(data.room_id), 300);
        } else {
            alert(data.message || 'Failed to create group.');
        }
    });
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

function escapeAttr(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, ' ');
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function checkEmbedLayout() {
    if (window.innerWidth < 650) {
        document.body.classList.add('narrow-embed');
        const backBtn = document.getElementById('mobile-chat-back-btn');
        if (backBtn && currentRoomId) {
            backBtn.style.display = 'inline-flex';
        }
    } else {
        document.body.classList.remove('narrow-embed');
        const backBtn = document.getElementById('mobile-chat-back-btn');
        if (backBtn && window.innerWidth > 768) {
            backBtn.style.display = 'none';
        }
    }
}

window.addEventListener('resize', checkEmbedLayout);
document.addEventListener('DOMContentLoaded', checkEmbedLayout);
</script>

<?php if ($isEmbed): ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/app.js') ?: time(); ?>"></script>
</body>
</html>
<?php else: ?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
