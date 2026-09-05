/**
 * Team Management System — Client-Side JavaScript
 * 
 * Handles: sidebar toggle, modals, AJAX requests, notifications,
 * form validation, confirmation dialogs, and interactivity.
 */

document.addEventListener('DOMContentLoaded', function() {

    // =============================================
    // Sidebar Backdrop & Auto-Close Handlers
    // =============================================
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (backdrop) {
        backdrop.addEventListener('click', function(e) {
            if (typeof window.closeSidebarNav === 'function') {
                window.closeSidebarNav(e);
            }
        });
    }

    // Auto close mobile/tablet drawer when nav link is clicked
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav-item').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 && typeof window.closeSidebarNav === 'function') {
                    window.closeSidebarNav(e);
                }
            });
        });
    }

    // =============================================
    // Modal System
    // =============================================
    window.openModal = function(modalId) {
        const overlay = document.getElementById(modalId);
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            // Focus first input
            setTimeout(() => {
                const firstInput = overlay.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }, 200);
        }
    };

    window.closeModal = function(modalId) {
        const overlay = document.getElementById(modalId);
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(overlay => {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });

    // =============================================
    // Flash Message Auto-Dismiss
    // =============================================
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateY(-10px)';
            flashMessage.style.transition = 'all 0.3s ease';
            setTimeout(() => flashMessage.remove(), 300);
        }, 5000);
    }

    // =============================================
    // AJAX Helper
    // =============================================
    window.ajaxRequest = function(url, method, data, callback) {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    callback(null, response);
                } catch (e) {
                    callback('Invalid response from server', null);
                }
            }
        };

        xhr.onerror = function() {
            callback('Network error. Please try again.', null);
        };

        // Build form data string
        if (typeof data === 'object') {
            const params = [];
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                }
            }
            xhr.send(params.join('&'));
        } else {
            xhr.send(data);
        }
    };

    // =============================================
    // Toast Notifications
    // =============================================
    window.showToast = function(message, type = 'success', duration = 4000) {
        // Remove existing toasts
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        
        const icons = {
            success: '<i class="fa-solid fa-check"></i>',
            error: '✕',
            warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
            info: 'ℹ'
        };

        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        // Add toast styles if not present
        if (!document.querySelector('#toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                .toast-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 12px 20px;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 500;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                    animation: toastSlideIn 0.3s ease-out;
                    max-width: 400px;
                }
                .toast-success { background: #065f46; color: #a7f3d0; border: 1px solid #10b981; }
                .toast-error { background: #7f1d1d; color: #fecaca; border: 1px solid #ef4444; }
                .toast-warning { background: #78350f; color: #fde68a; border: 1px solid #f59e0b; }
                .toast-info { background: #1e3a5f; color: #bfdbfe; border: 1px solid #4f6ef7; }
                .toast-icon { font-size: 16px; flex-shrink: 0; }
                .toast-message { flex: 1; }
                .toast-close { background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; opacity: 0.7; padding: 0 4px; }
                .toast-close:hover { opacity: 1; }
                @keyframes toastSlideIn {
                    from { opacity: 0; transform: translateX(30px); }
                    to { opacity: 1; transform: translateX(0); }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    // =============================================
    // Confirmation Dialog
    // =============================================
    window.confirmAction = function(message, callback) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal" style="max-width: 400px;">
                <div class="modal-body">
                    <div class="confirm-dialog">
                        <div class="confirm-dialog-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="confirm-dialog-title">Are you sure?</div>
                        <div class="confirm-dialog-text">${message}</div>
                        <div class="form-actions" style="justify-content: center;">
                            <button class="btn btn-outline" id="confirm-cancel">Cancel</button>
                            <button class="btn btn-danger" id="confirm-yes">Yes, Continue</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        overlay.querySelector('#confirm-cancel').addEventListener('click', function() {
            overlay.remove();
            document.body.style.overflow = '';
        });

        overlay.querySelector('#confirm-yes').addEventListener('click', function() {
            overlay.remove();
            document.body.style.overflow = '';
            callback();
        });

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.remove();
                document.body.style.overflow = '';
            }
        });
    };

    // =============================================
    // Attendance Check-In / Check-Out
    // =============================================
    const checkInBtn = document.getElementById('btn-check-in');
    const checkOutBtn = document.getElementById('btn-check-out');

    if (checkInBtn) {
        checkInBtn.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Checking In...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/attendance.php',
                'POST',
                { action: 'check_in', csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-circle" style="color: var(--color-success);"></i> Check In';
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-circle" style="color: var(--color-success);"></i> Check In';
                    }
                }
            );
        });
    }

    if (checkOutBtn) {
        checkOutBtn.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Checking Out...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/attendance.php',
                'POST',
                { action: 'check_out', csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-power-off"></i> Check Out';
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-power-off"></i> Check Out';
                    }
                }
            );
        });
    }

    const startBreakBtn = document.getElementById('btn-start-break');
    const endBreakBtn = document.getElementById('btn-end-break');

    if (startBreakBtn) {
        startBreakBtn.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Starting Break...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/attendance.php',
                'POST',
                { action: 'start_break', csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-mug-hot"></i> Start Break';
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-mug-hot"></i> Start Break';
                    }
                }
            );
        });
    }

    if (endBreakBtn) {
        endBreakBtn.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Ending Break...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/attendance.php',
                'POST',
                { action: 'end_break', csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-play"></i> End Break';
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.message, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-play"></i> End Break';
                    }
                }
            );
        });
    }

    // =============================================
    // Task Status Update
    // =============================================
    document.querySelectorAll('.task-status-select').forEach(select => {
        select.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const newStatus = this.value;
            const originalValue = this.dataset.originalValue;

            const container = this.closest('.task-item, tr');
            const commentInput = container ? container.querySelector('.task-comment-input') : null;
            const commentText = commentInput ? commentInput.value : undefined;

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            const payload = { 
                action: 'update_status', 
                task_id: taskId, 
                status: newStatus, 
                csrf_token: token 
            };
            if (commentText !== undefined) {
                payload.comments = commentText;
            }

            ajaxRequest(
                window.BASE_URL + '/api/tasks.php',
                'POST',
                payload,
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        select.value = originalValue;
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        select.dataset.originalValue = newStatus;
                        
                        // Update status badge if present
                        if (container) {
                            container.dataset.status = newStatus;
                            const badge = container.querySelector('.task-status-badge');
                            if (badge && response.badge_class) {
                                badge.className = 'badge task-status-badge ' + response.badge_class;
                                badge.textContent = response.status_label;
                            }
                            
                            // Update completion text if element exists
                            const doneElem = container.querySelector('.task-completed-at');
                            if (doneElem) {
                                if (response.completed_at_formatted) {
                                    doneElem.textContent = 'Done: ' + response.completed_at_formatted;
                                    doneElem.style.display = 'inline-block';
                                } else {
                                    doneElem.style.display = 'none';
                                }
                            }
                        }
                    } else {
                        showToast(response.message, 'error');
                        select.value = originalValue;
                    }
                }
            );
        });
    });

    // =============================================
    // Save Task Comment / Status Note
    // =============================================
    document.querySelectorAll('.btn-save-comment').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.taskId;
            const container = this.closest('.task-item, tr');
            const commentInput = container ? container.querySelector('.task-comment-input') : null;
            const newComment = commentInput ? commentInput.value : '';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            const origHtml = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            this.disabled = true;

            ajaxRequest(
                window.BASE_URL + '/api/tasks.php',
                'POST',
                { action: 'save_comment', task_id: taskId, comments: newComment, csrf_token: token },
                (error, response) => {
                    this.disabled = false;
                    this.innerHTML = origHtml;

                    if (error) {
                        showToast(error, 'error');
                        return;
                    }
                    if (response.success) {
                        showToast(response.message || 'Task note saved successfully', 'success');
                        // Update display preview if present
                        if (container) {
                            const preview = container.querySelector('.task-comment-preview-text');
                            if (preview) {
                                preview.textContent = newComment;
                                const previewBox = container.querySelector('.task-comment-display');
                                if (previewBox) {
                                    previewBox.style.display = newComment.trim() ? 'block' : 'none';
                                }
                            }
                        }
                    } else {
                        showToast(response.message, 'error');
                    }
                }
            );
        });
    });

    document.querySelectorAll('.task-comment-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const saveBtn = this.closest('.task-item, tr')?.querySelector('.btn-save-comment');
                if (saveBtn) saveBtn.click();
            }
        });
    });

    // =============================================
    // Form Validation
    // =============================================
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                const error = field.parentElement.querySelector('.form-error');
                if (error) error.remove();

                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'var(--color-danger)';
                    const errorEl = document.createElement('div');
                    errorEl.className = 'form-error';
                    errorEl.textContent = 'This field is required';
                    field.parentElement.appendChild(errorEl);
                } else {
                    field.style.borderColor = '';
                }
            });

            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                    valid = false;
                    field.style.borderColor = 'var(--color-danger)';
                    let error = field.parentElement.querySelector('.form-error');
                    if (!error) {
                        error = document.createElement('div');
                        error.className = 'form-error';
                        field.parentElement.appendChild(error);
                    }
                    error.textContent = 'Please enter a valid email address';
                }
            });

            if (!valid) {
                e.preventDefault();
            }
        });
    });

    // Clear validation on input
    document.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.style.borderColor = '';
            const error = this.parentElement.querySelector('.form-error');
            if (error) error.remove();
        });
    });

    // =============================================
    // Delete Confirmation on Forms
    // =============================================
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.dataset.confirm;
            const href = this.getAttribute('href');
            const form = this.closest('form');

            confirmAction(message, function() {
                if (href) {
                    window.location.href = href;
                } else if (form) {
                    form.submit();
                }
            });
        });
    });

    // =============================================
    // Live Clock (for attendance page)
    // =============================================
    const liveClock = document.getElementById('live-clock');
    if (liveClock) {
        function updateClock() {
            const now = new Date();
            liveClock.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // =============================================
    // Password Visibility Eye Toggle
    // =============================================
    window.togglePasswordVisibility = function(inputId, btn) {
        const input = typeof inputId === 'string' ? document.getElementById(inputId) : inputId;
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.className = 'fa-regular fa-eye-slash';
            } else {
                btn.innerText = '🙈';
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.className = 'fa-regular fa-eye';
            } else {
                btn.innerText = '👁️';
            }
        }
    };
    window.togglePassword = window.togglePasswordVisibility;

    // Auto-attach Eye buttons to all input[type="password"] fields with perfect alignment
    function initPasswordToggles() {
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
            if (input.dataset.hasToggle) return;

            // Check if already inside a custom wrapper with toggle button
            const parent = input.parentElement;
            if (parent && (parent.classList.contains('password-input-wrapper') || parent.querySelector('.btn-toggle-password-auto, button[onclick*="togglePassword"]'))) {
                input.dataset.hasToggle = 'true';
                return;
            }

            input.dataset.hasToggle = 'true';

            // Wrap input in a dedicated container to guarantee perfect vertical centering
            const wrapper = document.createElement('div');
            wrapper.className = 'password-input-wrapper';
            wrapper.style.cssText = 'position: relative; width: 100%; display: flex; align-items: center;';

            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            input.style.paddingRight = '44px';
            input.style.width = '100%';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-toggle-password-auto';
            btn.setAttribute('title', 'Toggle Visibility');
            btn.setAttribute('tabindex', '-1');
            btn.style.cssText = 'position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 16px; padding: 4px; display: inline-flex; align-items: center; justify-content: center; z-index: 10; line-height: 1; opacity: 0.7; transition: opacity 0.2s, color 0.2s;';
            btn.innerHTML = '<i class="fa-regular fa-eye"></i>';

            btn.onmouseenter = function() { btn.style.opacity = '1'; btn.style.color = 'var(--color-primary)'; };
            btn.onmouseleave = function() { btn.style.opacity = '0.7'; btn.style.color = 'var(--color-text-secondary)'; };

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                togglePasswordVisibility(input, btn);
            });

            wrapper.appendChild(btn);
        });
    }

    initPasswordToggles();

    // Re-run for dynamically opened modals or DOM changes
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn, button, [onclick*="Modal"], [data-modal]')) {
            setTimeout(initPasswordToggles, 100);
            setTimeout(initPasswordToggles, 300);
        }
    });

    // =============================================
    // Real-Time Notification Sound Synthesizer (Web Audio API)
    // =============================================
    let appAudioCtx = null;

    function getAppAudioContext() {
        if (!appAudioCtx || appAudioCtx.state === 'closed') {
            const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
            if (AudioCtxClass) {
                appAudioCtx = new AudioCtxClass();
            }
        }
        return appAudioCtx;
    }

    // Unlock Audio Context on any user interaction to bypass browser autoplay restrictions
    function unlockAppAudio() {
        const ctx = getAppAudioContext();
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
    }
    ['click', 'keydown', 'touchstart', 'mousedown'].forEach(evt => {
        document.addEventListener(evt, unlockAppAudio, { passive: true });
    });

    function playChimeNotes(ctx) {
        try {
            const now = ctx.currentTime;
            
            // Crystal Clear Bell Chime: 3 resonant harmonic chord notes (880Hz -> 1174Hz -> 1760Hz)
            const notes = [
                { freq: 880.00, start: 0, dur: 0.18, vol: 0.35 },   // A5
                { freq: 1174.66, start: 0.08, dur: 0.22, vol: 0.40 }, // D6
                { freq: 1760.00, start: 0.16, dur: 0.60, vol: 0.45 }  // A6
            ];

            notes.forEach(note => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(note.freq, now + note.start);

                gain.gain.setValueAtTime(0.0001, now + note.start);
                gain.gain.linearRampToValueAtTime(note.vol, now + note.start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + note.start + note.dur);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now + note.start);
                osc.stop(now + note.start + note.dur + 0.05);
            });
        } catch(e) {
            console.warn('Audio chime play error:', e);
        }
    }

    let lastChimeTime = 0;
    window.playNotificationSound = function() {
        const nowMs = Date.now();
        if (nowMs - lastChimeTime < 1000) return; // Throttle to at most 1 chime per second
        lastChimeTime = nowMs;

        try {
            const ctx = getAppAudioContext();
            if (!ctx) return;

            if (ctx.state === 'suspended') {
                ctx.resume().then(() => {
                    playChimeNotes(ctx);
                }).catch(() => {});
            } else {
                playChimeNotes(ctx);
            }
        } catch(e) {
            console.warn('Audio chime could not be played:', e);
        }
    };

    // =============================================
    // Top-Right Popup Toast Notifications System (Strictly Deduplicated)
    // =============================================
    const recentToasts = new Map(); // key -> timestamp

    window.showToastNotification = function(notif) {
        if (!notif || !notif.title) return;

        const now = Date.now();
        // Unique deduplication key based on ID or (title + message)
        const dedupKey = notif.id ? ('id_' + notif.id) : ('txt_' + notif.title + '_' + (notif.message || ''));
        if (recentToasts.has(dedupKey) && (now - recentToasts.get(dedupKey)) < 4000) {
            return; // Strict deduplication: ignore duplicate within 4 seconds
        }
        recentToasts.set(dedupKey, now);

        // Prune older cached keys
        if (recentToasts.size > 50) {
            for (const [k, ts] of recentToasts.entries()) {
                if (now - ts > 10000) recentToasts.delete(k);
            }
        }

        // Play crystal chime sound with toast notification
        window.playNotificationSound();

        let container = document.getElementById('toast-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-notification-container';
            container.className = 'toast-notification-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast-notification-card fade-slide-right';
        toast.dataset.id = notif.id || '';

        // Category accent styling
        let categoryColor = '#3b82f6';
        let categoryLabel = 'Notification';
        const cat = (notif.category || notif.type || 'info').toLowerCase();

        if (cat === 'chat') {
            categoryColor = '#3b82f6';
            categoryLabel = 'Team Chat';
        } else if (cat === 'meeting') {
            categoryColor = '#8b5cf6';
            categoryLabel = 'Meeting';
        } else if (cat === 'ticket') {
            categoryColor = '#ec4899';
            categoryLabel = 'Support Ticket';
        } else if (cat === 'leave') {
            categoryColor = '#10b981';
            categoryLabel = 'Leave Request';
        } else if (cat === 'announcement') {
            categoryColor = '#f59e0b';
            categoryLabel = 'Announcement';
        } else if (cat === 'auth') {
            categoryColor = '#06b6d4';
            categoryLabel = 'Team Activity';
        } else if (cat === 'task') {
            categoryColor = '#6366f1';
            categoryLabel = 'Task Alert';
        }

        toast.style.borderLeft = `4px solid ${categoryColor}`;

        const safeTitle = (notif.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const safeMsg = (notif.message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const safeIcon = notif.icon || 'fa-solid fa-bell';

        toast.innerHTML = `
            <div class="toast-notif-icon" style="background: ${categoryColor}20; color: ${categoryColor}; border: 1px solid ${categoryColor}40;">
                <i class="${safeIcon}"></i>
            </div>
            <div class="toast-notif-body">
                <div class="toast-notif-header">
                    <span class="toast-notif-tag" style="color: ${categoryColor};">${categoryLabel}</span>
                    <span class="toast-notif-time">${notif.time_ago || 'Just now'}</span>
                    <button type="button" class="toast-notif-close" title="Dismiss">×</button>
                </div>
                <div class="toast-notif-title">${safeTitle}</div>
                <div class="toast-notif-message">${safeMsg}</div>
            </div>
            <div class="toast-notif-progress">
                <div class="toast-notif-progress-bar" style="background: ${categoryColor}; animation: toastProgress 6s linear forwards;"></div>
            </div>
        `;

        // Direct click navigation
        toast.addEventListener('click', function(e) {
            if (e.target.closest('.toast-notif-close')) {
                e.stopPropagation();
                dismissToast(toast);
                return;
            }
            
            // Mark read via API
            if (notif.id) {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch((window.BASE_URL || '') + '/api/notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read&id=' + notif.id + '&csrf_token=' + token
                }).catch(() => {});
            }

            if (notif.link) {
                window.location.href = notif.link;
            } else {
                dismissToast(toast);
            }
        });

        const closeBtn = toast.querySelector('.toast-notif-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dismissToast(toast);
            });
        }

        container.prepend(toast);

        // Auto dismiss after 6 seconds
        let timeoutId = setTimeout(() => {
            dismissToast(toast);
        }, 6000);

        // Pause countdown on hover
        const progressBar = toast.querySelector('.toast-notif-progress-bar');
        toast.addEventListener('mouseenter', () => {
            clearTimeout(timeoutId);
            if (progressBar) progressBar.style.animationPlayState = 'paused';
        });

        toast.addEventListener('mouseleave', () => {
            if (progressBar) progressBar.style.animationPlayState = 'running';
            timeoutId = setTimeout(() => {
                dismissToast(toast);
            }, 3000);
        });
    };

    function dismissToast(toast) {
        if (!toast || toast.classList.contains('dismissing')) return;
        toast.classList.add('dismissing');
        toast.style.animation = 'toastSlideOut 0.25s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 250);
    }

    function updateHeaderBellCount(count) {
        const notifSummary = document.querySelector('.notif-dropdown summary');
        if (!notifSummary) return;

        let badge = notifSummary.querySelector('span');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.style.cssText = 'position: absolute; top: -2px; right: -2px; background: #ef4444; color: #fff; font-size: 10px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--color-bg-card);';
                notifSummary.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = 'flex';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }

    window.updateChatNotificationBadges = function(count) {
        const num = parseInt(count || 0, 10);
        const text = num > 99 ? '99+' : num;

        document.querySelectorAll('.sidebar-chat-badge').forEach(badge => {
            if (num > 0) {
                badge.textContent = text;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        });

        const floatingBadge = document.getElementById('floating-chat-badge');
        if (floatingBadge) {
            if (num > 0) {
                floatingBadge.textContent = text;
                floatingBadge.style.display = 'flex';
            } else {
                floatingBadge.style.display = 'none';
            }
        }
    };

    // Listen for postMessage from embedded chat iframe (sound, toasts & badge sync)
    window.addEventListener('message', function(event) {
        if (!event.data) return;
        if (event.data.type === 'play_sound') {
            if (typeof window.playNotificationSound === 'function') {
                window.playNotificationSound();
            }
        }
        if (event.data.type === 'show_toast' && event.data.notif) {
            if (typeof window.showToastNotification === 'function') {
                window.showToastNotification(event.data.notif);
            }
        }
        if (event.data.type === 'chat_unread_updated') {
            window.updateChatNotificationBadges(event.data.unread_count);
        }
    });

    // =============================================
    // Background Poller for All Notifications & Real-Time Sync
    // =============================================
    function initRealtimeNotificationPoller() {
        const uid = parseInt(window.CURRENT_USER_ID || '0', 10);
        if (!uid) return;
        const baseUrl = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : '';

        const userPrefix = 'u_' + uid + '_';

        // Daily Reset Check: If the date changes, clear previous session polling state
        const todayStr = new Date().toISOString().slice(0, 10);
        const storedPollDate = sessionStorage.getItem(userPrefix + 'notif_poll_date');
        if (storedPollDate !== todayStr) {
            sessionStorage.setItem(userPrefix + 'notif_poll_date', todayStr);
            sessionStorage.removeItem(userPrefix + 'last_polled_notif_id');
            sessionStorage.removeItem(userPrefix + 'notif_poller_initialized');
        }

        let lastNotifId = parseInt(sessionStorage.getItem(userPrefix + 'last_polled_notif_id') || '0', 10);
        let isInitialized = sessionStorage.getItem(userPrefix + 'notif_poller_initialized') === 'true';
        let pollerTimer = null;

        function pollNotifications() {
            // Check midnight transition
            const currentDay = new Date().toISOString().slice(0, 10);
            if (sessionStorage.getItem(userPrefix + 'notif_poll_date') !== currentDay) {
                sessionStorage.setItem(userPrefix + 'notif_poll_date', currentDay);
                lastNotifId = 0;
                isInitialized = false;
                sessionStorage.removeItem(userPrefix + 'last_polled_notif_id');
                sessionStorage.removeItem(userPrefix + 'notif_poller_initialized');
            }

            const url = baseUrl + '/api/notifications.php?action=poll&last_id=' + lastNotifId + (!isInitialized ? '&init=1' : '');

            fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;

                if (!isInitialized) {
                    lastNotifId = data.max_id || 0;
                    sessionStorage.setItem(userPrefix + 'last_polled_notif_id', lastNotifId);
                    sessionStorage.setItem(userPrefix + 'notif_poller_initialized', 'true');
                    isInitialized = true;
                } else if (data.new_notifications && data.new_notifications.length > 0) {
                    // Play notification chime sound
                    window.playNotificationSound();

                    data.new_notifications.forEach(notif => {
                        window.showToastNotification(notif);
                    });

                    lastNotifId = data.max_id;
                    sessionStorage.setItem(userPrefix + 'last_polled_notif_id', lastNotifId);
                }

                updateHeaderBellCount(data.unread_count);
                if (typeof data.chat_unread_count !== 'undefined') {
                    window.updateChatNotificationBadges(data.chat_unread_count);
                }
            })
            .catch(() => {});
        }

        // Run initial sync and poll every 1.5 seconds for real-time responsiveness across all panels
        pollNotifications();
        
        function restartPoller() {
            if (pollerTimer) clearInterval(pollerTimer);
            const interval = document.visibilityState === 'visible' ? 1500 : 3000;
            pollerTimer = setInterval(pollNotifications, interval);
        }
        
        restartPoller();
        document.addEventListener('visibilitychange', restartPoller);

        // Cross-tab broadcast listener to trigger instant sync across all open panels
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                const globalChatBus = new BroadcastChannel('tms_realtime_chat_bus');
                globalChatBus.onmessage = function(event) {
                    if (event.data) {
                        pollNotifications();
                    }
                };
            } catch(e) {}
        }
        window.addEventListener('storage', function(e) {
            if (e.key === 'tms_realtime_chat_event') {
                pollNotifications();
            }
        });
    }

    // Start Realtime Notification Poller
    initRealtimeNotificationPoller();

});
