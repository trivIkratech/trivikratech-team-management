            </main>
        </div><!-- /.main-wrapper -->
    </div><!-- /.app-layout -->

    <?php 
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $isChatDashboard = (strpos($currentScript, 'chat/index.php') !== false);
    ?>

    <?php if (isLoggedIn() && !$isChatDashboard): ?>
        <!-- FLOATING SYSTEM-WIDE CHAT WIDGET (Loads Full-Featured Team Chat) -->
        <button id="floating-chat-trigger" onclick="toggleFloatingChat()" style="position: fixed; bottom: 24px; right: 24px; z-index: 9998; background: var(--color-primary); color: #ffffff; border: none; border-radius: 30px; padding: 12px 20px; font-weight: 600; box-shadow: 0 8px 24px rgba(79, 110, 247, 0.4); display: flex; align-items: center; gap: 8px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fa-solid fa-comments" style="font-size: 18px;"></i>
            <span>Team Chat</span>
        </button>

        <div id="floating-chat-box" style="display: none; position: fixed; bottom: 84px; right: 24px; width: 440px; height: 600px; max-width: calc(100vw - 32px); max-height: calc(100vh - 100px); z-index: 9999; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: 0 16px 48px rgba(0,0,0,0.5); flex-direction: column; overflow: hidden;" class="fade-in">
            <!-- Header -->
            <div style="padding: 10px 14px; background: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; user-select: none;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: var(--color-primary); font-size: 15px;"></i>
                    <strong style="font-size: 13.5px; color: var(--color-text-main);">Quick Team Chat</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" onclick="toggleFloatingChatSize()" title="Toggle Compact / Expanded View" style="background: none; border: none; color: var(--color-text-muted); font-size: 12px; cursor: pointer; padding: 4px; display: flex; align-items: center;">
                        <i class="fa-solid fa-up-right-and-down-left-from-center" id="floating-size-icon"></i>
                    </button>
                    <a href="<?php echo BASE_URL; ?>/chat/index.php" title="Open Fullscreen Page" style="color: var(--color-text-muted); font-size: 12px; text-decoration: none; padding: 4px; display: flex; align-items: center;">
                        <i class="fa-solid fa-expand"></i>
                    </a>
                    <button type="button" onclick="toggleFloatingChat()" title="Close Widget" style="background: none; border: none; color: var(--color-text-muted); font-size: 18px; cursor: pointer; line-height: 1; padding: 0 4px;">×</button>
                </div>
            </div>

            <!-- Full-Featured Chat Iframe -->
            <div style="flex: 1; position: relative; background: var(--color-bg-card); overflow: hidden;">
                <iframe id="floating-chat-iframe" src="" style="width: 100%; height: 100%; border: none; display: block;" title="Quick Team Chat"></iframe>
            </div>
        </div>

        <script>
        let isFloatingOpen = false;
        let isFloatingExpanded = false;

        function toggleFloatingChat() {
            const box = document.getElementById('floating-chat-box');
            const iframe = document.getElementById('floating-chat-iframe');
            isFloatingOpen = !isFloatingOpen;
            box.style.display = isFloatingOpen ? 'flex' : 'none';
            
            if (isFloatingOpen) {
                const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('app_theme') || 'dark';
                if (!iframe.getAttribute('src')) {
                    iframe.src = window.BASE_URL + '/chat/index.php?embed=1&theme=' + encodeURIComponent(currentTheme);
                } else if (iframe.contentDocument) {
                    iframe.contentDocument.documentElement.setAttribute('data-theme', currentTheme);
                    if (iframe.contentDocument.body) iframe.contentDocument.body.setAttribute('data-theme', currentTheme);
                    if (iframe.contentWindow) iframe.contentWindow.postMessage({ theme: currentTheme }, '*');
                }
            }
        }

        function toggleFloatingChatSize() {
            const box = document.getElementById('floating-chat-box');
            const icon = document.getElementById('floating-size-icon');
            isFloatingExpanded = !isFloatingExpanded;
            if (isFloatingExpanded) {
                box.style.width = '740px';
                box.style.height = '680px';
                icon.className = 'fa-solid fa-down-left-and-up-right-to-center';
            } else {
                box.style.width = '440px';
                box.style.height = '600px';
                icon.className = 'fa-solid fa-up-right-and-down-left-from-center';
            }
            
            // Trigger resize in iframe for layout adjustments
            const iframe = document.getElementById('floating-chat-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.dispatchEvent(new Event('resize'));
            }
        }
        </script>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
