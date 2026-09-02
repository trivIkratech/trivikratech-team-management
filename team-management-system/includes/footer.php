            </main>
        </div><!-- /.main-wrapper -->
    </div><!-- /.app-layout -->

    <?php 
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $isChatDashboard = (strpos($currentScript, 'chat/index.php') !== false);
    ?>

    <?php if (isLoggedIn() && !$isChatDashboard): ?>
        <!-- FLOATING SYSTEM-WIDE CHAT WIDGET (Hidden on Chat Dashboard) -->
        <button id="floating-chat-trigger" onclick="toggleFloatingChat()" style="position: fixed; bottom: 24px; right: 24px; z-index: 9998; background: var(--color-primary); color: #ffffff; border: none; border-radius: 30px; padding: 12px 20px; font-weight: 600; box-shadow: 0 8px 24px rgba(79, 110, 247, 0.4); display: flex; align-items: center; gap: 8px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fa-solid fa-comments" style="font-size: 18px;"></i>
            <span>Team Chat</span>
        </button>

        <div id="floating-chat-box" style="display: none; position: fixed; bottom: 84px; right: 24px; width: 360px; height: 500px; z-index: 9999; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: 0 16px 48px rgba(0,0,0,0.5); flex-direction: column; overflow: hidden;" class="fade-in">
            <!-- Header -->
            <div style="padding: 12px 16px; background: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: var(--color-primary);"></i>
                    <strong style="font-size: 14px; color: var(--color-text-main);">Quick Team Chat</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="<?php echo BASE_URL; ?>/chat/index.php" title="Open Fullscreen Chat" style="color: var(--color-text-muted); font-size: 13px; text-decoration: none;">
                        <i class="fa-solid fa-expand"></i>
                    </a>
                    <button onclick="toggleFloatingChat()" style="background: none; border: none; color: var(--color-text-muted); font-size: 18px; cursor: pointer;">×</button>
                </div>
            </div>

            <!-- Mini Message Area -->
            <div id="floating-chat-messages" style="flex: 1; padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: var(--color-bg-card);">
                <div style="text-align: center; color: var(--color-text-muted); font-size: 12px; margin: auto;">
                    Loading messages...
                </div>
            </div>

            <!-- Mini Input Bar -->
            <div style="padding: 10px 14px; background: var(--color-bg-secondary); border-top: 1px solid var(--color-border);">
                <form id="floating-chat-form" onsubmit="sendFloatingMessage(event)">
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <input type="text" id="floating-chat-input" class="form-input" placeholder="Type message or paste URL..." style="flex: 1; font-size: 12px; height: 36px;" autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-sm" style="height: 36px; padding: 0 12px;">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        let isFloatingOpen = false;
        let floatingPollTimer = null;

        function toggleFloatingChat() {
            const box = document.getElementById('floating-chat-box');
            isFloatingOpen = !isFloatingOpen;
            box.style.display = isFloatingOpen ? 'flex' : 'none';
            if (isFloatingOpen) {
                loadFloatingMessages();
                if (!floatingPollTimer) {
                    floatingPollTimer = setInterval(loadFloatingMessages, 3000);
                }
            }
        }

        function loadFloatingMessages() {
            if (!isFloatingOpen) return;
            fetch(window.BASE_URL + '/api/chat.php?action=get_messages&room_id=1')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderFloatingMessages(data.messages);
                }
            });
        }

        function renderFloatingMessages(messages) {
            const area = document.getElementById('floating-chat-messages');
            if (!messages || messages.length === 0) {
                area.innerHTML = '<div style="margin: auto; text-align: center; color: var(--color-text-muted); font-size: 12px;">Welcome to Team Chat!</div>';
                return;
            }

            let html = '';
            messages.slice(-20).forEach(m => {
                const isSelf = m.is_self;
                const bg = isSelf ? 'var(--color-primary)' : 'var(--color-bg-tertiary)';
                const color = isSelf ? '#ffffff' : 'var(--color-text-main)';
                const align = isSelf ? 'flex-end' : 'flex-start';

                let fileHtml = '';
                if (m.file_path) {
                    if (m.is_image) {
                        fileHtml = `<br><a href="${m.file_url}" target="_blank"><img src="${m.file_url}" style="max-width: 180px; border-radius: 6px; margin-top: 4px;"></a>`;
                    } else {
                        fileHtml = `<br><a href="${m.file_url}" target="_blank" style="font-size: 11px; color: ${color}; text-decoration: underline;"><i class="fa-solid fa-paperclip"></i> ${m.file_name}</a>`;
                    }
                }

                const isEdited = (m.is_edited == 1) ? '<span style="font-size: 8px; opacity: 0.7; font-style: italic; margin-left: 4px;">(edited)</span>' : '';

                html += `
                    <div style="align-self: ${align}; max-width: 85%;">
                        <div style="background: ${bg}; color: ${color}; padding: 8px 12px; border-radius: 10px; font-size: 12px; line-height: 1.35; word-break: break-word;">
                            ${!isSelf ? `<strong style="display: block; font-size: 10px; color: var(--color-primary); margin-bottom: 2px;">${m.sender_name}</strong>` : ''}
                            ${m.formatted_html || ''}
                            ${fileHtml}
                        </div>
                        <div style="font-size: 9px; color: var(--color-text-muted); text-align: ${isSelf ? 'right' : 'left'}; margin-top: 2px;">
                            ${m.time} ${isEdited}
                        </div>
                    </div>
                `;
            });
            const isAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;
            area.innerHTML = html;
            if (isAtBottom || area.scrollTop === 0) {
                area.scrollTop = area.scrollHeight;
            }
        }

        function sendFloatingMessage(e) {
            e.preventDefault();
            const input = document.getElementById('floating-chat-input');
            const msg = input.value.trim();
            if (!msg) return;

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch(window.BASE_URL + '/api/chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=send_message&room_id=1&message=${encodeURIComponent(msg)}&csrf_token=${token}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadFloatingMessages();
                }
            });
        }
        </script>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
