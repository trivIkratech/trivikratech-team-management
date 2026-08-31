/**
 * Team Management System — Client-Side JavaScript
 * 
 * Handles: sidebar toggle, modals, AJAX requests, notifications,
 * form validation, confirmation dialogs, and interactivity.
 */

document.addEventListener('DOMContentLoaded', function() {

    // =============================================
    // Sidebar Toggle (Mobile)
    // =============================================
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const menuBtn = document.querySelector('.mobile-menu-btn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
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

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/tasks.php',
                'POST',
                { action: 'update_status', task_id: taskId, status: newStatus, csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        select.value = originalValue;
                        return;
                    }

                    if (response.success) {
                        showToast(response.message, 'success');
                        select.dataset.originalValue = newStatus;
                        // Update badge if present
                        const badge = select.closest('.task-item, tr')?.querySelector('.task-status-badge');
                        if (badge && response.badge_class) {
                            badge.className = 'badge task-status-badge ' + response.badge_class;
                            badge.textContent = response.status_label;
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
    // Save Task Comment
    // =============================================
    document.querySelectorAll('.btn-save-comment').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.taskId;
            const commentInput = this.closest('.task-item, tr')?.querySelector('.task-comment-input');
            const newComment = commentInput ? commentInput.value : '';
            const statusSelect = this.closest('.task-item, tr')?.querySelector('.task-status-select');
            const currentStatus = statusSelect ? statusSelect.value : 'todo';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';

            ajaxRequest(
                window.BASE_URL + '/api/tasks.php',
                'POST',
                { action: 'update_status', task_id: taskId, status: currentStatus, comments: newComment, csrf_token: token },
                function(error, response) {
                    if (error) {
                        showToast(error, 'error');
                        return;
                    }
                    if (response.success) {
                        showToast('Task comment saved successfully', 'success');
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
                btn.innerText = '<i class="fa-solid fa-eye"></i>';
            }
        }
    };

    // Auto-attach Eye buttons to all input[type="password"] fields
    function initPasswordToggles() {
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
            if (input.dataset.hasToggle) return;
            input.dataset.hasToggle = 'true';
            
            const parent = input.parentElement;
            if (!parent) return;

            parent.style.position = 'relative';
            input.style.paddingRight = '44px';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-toggle-password-auto';
            btn.setAttribute('title', 'Toggle Visibility');
            btn.style.cssText = 'position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 15px; padding: 4px; z-index: 10;';
            btn.innerHTML = '<i class="fa-regular fa-eye"></i>';

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                togglePasswordVisibility(input, btn);
            });

            parent.appendChild(btn);
        });
    }

    initPasswordToggles();

    // Re-run for dynamically opened modals
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn, button, [onclick*="Modal"]')) {
            setTimeout(initPasswordToggles, 100);
            setTimeout(initPasswordToggles, 300);
        }
    });

});
