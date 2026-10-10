<?php
/**
 * Reusable Modal: Safe Lead Deletion with Mandatory Google Sheet Backup
 * Accessible to: Founder, HR, Manager
 */
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$userRole = $currentUser['role'] ?? '';
$baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($userRole) : $userRole;

if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
    return;
}
?>

<!-- ================= SAFE LEAD DELETION MODAL ================= -->
<div class="modal-overlay" id="leadDeleteModal">
    <div class="modal" style="max-width: 540px; border-radius: var(--radius-lg); box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.5); overflow: hidden; border: 1px solid var(--color-border);">
        <div class="modal-header" style="background: rgba(239, 68, 68, 0.08); border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding: var(--space-4) var(--space-5);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: var(--color-danger); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 class="modal-title" style="color: var(--color-danger); margin: 0; font-size: 16px; font-weight: 700;">
                        Delete Lead(s) — Mandatory Backup
                    </h3>
                    <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                        Data protection & safety protocol
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeLeadDeleteModal()" style="font-size: 20px;">&times;</button>
        </div>

        <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-4);">
            <!-- Safety Notice -->
            <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 13px; color: var(--color-text-main);">
                <div style="font-weight: 700; color: #f59e0b; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Backup Mandatory Before Deleting
                </div>
                <div style="font-size: 12.5px; color: var(--color-text-secondary); line-height: 1.45;">
                    Lead records delete hone se pehle unka Google Sheet / Excel backup download karna zaroori hai. Backup download hone ke baad hi <strong>Delete</strong> button active hoga.
                </div>
            </div>

            <!-- Target Summary -->
            <div style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px 16px;">
                <div style="font-size: 12px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 6px;">
                    Selected for Deletion:
                </div>
                <div style="font-size: 15px; font-weight: 700; color: var(--color-text-main); display: flex; align-items: center; gap: 8px;" id="lead-delete-summary-title">
                    <span class="badge badge-danger" id="lead-delete-count-badge">1 Lead</span>
                    <span id="lead-delete-name-preview" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;"></span>
                </div>
                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 6px;" id="lead-delete-subtext">
                    All associated calling notes and activity trails will also be permanently removed.
                </div>
            </div>

            <!-- Step 1: Download Google Sheet Backup -->
            <div style="border: 1px dashed var(--color-border); border-radius: var(--radius-md); padding: 14px; background: rgba(99, 102, 241, 0.04); text-align: center;">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--color-text-main);">
                    Step 1: Download Google Sheet / CSV Backup
                </div>
                <button type="button" id="btn-download-delete-backup" class="btn btn-primary" onclick="downloadLeadDeleteBackup()" style="width: 100%; justify-content: center; padding: 10px 16px; font-size: 13.5px; font-weight: 700; background: #0284c7; border-color: #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                    <i class="fa-solid fa-file-excel" style="margin-right: 6px; font-size: 16px;"></i>
                    <span id="btn-backup-text">Download Backup (Google Sheet Format)</span>
                </button>
            </div>

            <!-- Backup Status Notification Box -->
            <div id="delete-backup-status" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 12.5px; align-items: center; gap: 8px; font-weight: 600;">
            </div>
        </div>

        <div class="modal-footer" style="padding: var(--space-4) var(--space-5); background: var(--color-bg-secondary); border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="closeLeadDeleteModal()" style="font-size: 13px;">
                Cancel
            </button>

            <!-- Step 2: Delete Button (Initially Disabled/Locked) -->
            <button type="button" id="btn-confirm-lead-delete" class="btn btn-danger" onclick="executeLeadDeletion()" disabled style="opacity: 0.45; cursor: not-allowed; font-weight: 700; font-size: 13.5px; padding: 8px 18px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" title="Please download backup first to enable deletion">
                <i class="fa-solid fa-lock" id="btn-delete-icon"></i>
                <span id="btn-delete-label">Delete Locked (Backup Required)</span>
            </button>
        </div>
    </div>
</div>

<script>
let leadDeleteState = {
    isDeleteAll: false,
    scope: 'custom', // 'all', 'filtered', 'custom'
    filterOptions: {},
    leadIds: [],
    leadNames: [],
    backupDownloaded: false,
    isProcessing: false,
    onSuccessCallback: null
};

/**
 * Open Safe Lead Deletion Modal for a Single Lead
 */
function confirmDeleteSingleLead(leadId, leadName, callback = null) {
    if (!leadId) return;
    leadDeleteState.isDeleteAll = false;
    leadDeleteState.scope = 'custom';
    leadDeleteState.filterOptions = {};
    leadDeleteState.leadIds = [parseInt(leadId)];
    leadDeleteState.leadNames = [leadName || ('Lead #' + leadId)];
    leadDeleteState.backupDownloaded = false;
    leadDeleteState.isProcessing = false;
    leadDeleteState.onSuccessCallback = callback;

    updateLeadDeleteModalUI();
    const modal = document.getElementById('leadDeleteModal');
    if (modal) modal.classList.add('active');
}

/**
 * Open Safe Lead Deletion Modal for Bulk Selection
 */
function confirmBulkDeleteLeads(customIds = null, callback = null) {
    let ids = [];
    if (Array.isArray(customIds) && customIds.length > 0) {
        ids = customIds.map(id => parseInt(id)).filter(id => id > 0);
    } else {
        const checkedBoxes = document.querySelectorAll('.lead-row-checkbox:checked');
        ids = Array.from(checkedBoxes).map(cb => parseInt(cb.value)).filter(id => id > 0);
    }

    if (ids.length === 0) {
        alert('Please select at least one lead to delete.');
        return;
    }

    leadDeleteState.isDeleteAll = false;
    leadDeleteState.scope = 'custom';
    leadDeleteState.filterOptions = {};
    leadDeleteState.leadIds = ids;
    leadDeleteState.leadNames = [];
    leadDeleteState.backupDownloaded = false;
    leadDeleteState.isProcessing = false;
    leadDeleteState.onSuccessCallback = callback;

    updateLeadDeleteModalUI();
    const modal = document.getElementById('leadDeleteModal');
    if (modal) modal.classList.add('active');
}

/**
 * Open Safe Lead Deletion Modal for ALL Leads (Founder Only)
 */
function confirmDeleteAllLeads(scope = 'all', filterOptions = {}, callback = null) {
    leadDeleteState.isDeleteAll = true;
    leadDeleteState.scope = scope || 'all';
    leadDeleteState.filterOptions = filterOptions || {};
    leadDeleteState.leadIds = [];
    leadDeleteState.leadNames = [];
    leadDeleteState.backupDownloaded = false;
    leadDeleteState.isProcessing = false;
    leadDeleteState.onSuccessCallback = callback;

    updateLeadDeleteModalUI();
    const modal = document.getElementById('leadDeleteModal');
    if (modal) modal.classList.add('active');
}

/**
 * Update Modal Content and Reset Button States
 */
function updateLeadDeleteModalUI() {
    const isAll = leadDeleteState.isDeleteAll;
    const count = leadDeleteState.leadIds.length;
    const badge = document.getElementById('lead-delete-count-badge');
    const preview = document.getElementById('lead-delete-name-preview');
    const deleteSubtext = document.getElementById('lead-delete-subtext');
    const deleteBtn = document.getElementById('btn-confirm-lead-delete');
    const deleteIcon = document.getElementById('btn-delete-icon');
    const deleteLabel = document.getElementById('btn-delete-label');
    const backupStatus = document.getElementById('delete-backup-status');
    const backupBtn = document.getElementById('btn-download-delete-backup');
    const backupBtnText = document.getElementById('btn-backup-text');

    if (badge) {
        if (isAll) {
            badge.textContent = (leadDeleteState.scope === 'filtered') ? 'ALL FILTERED LEADS' : 'ALL CRM LEADS';
            badge.className = 'badge badge-danger';
            badge.style.background = '#dc2626';
            badge.style.color = '#fff';
        } else {
            badge.textContent = count === 1 ? '1 Lead' : `${count} Leads`;
            badge.className = 'badge badge-danger';
        }
    }

    if (preview) {
        if (isAll) {
            preview.textContent = (leadDeleteState.scope === 'filtered')
                ? 'All leads matching active filter criteria' 
                : 'All Master Leads in Database (Entire CRM Pipeline)';
            preview.style.display = 'inline';
            preview.style.color = 'var(--color-danger)';
        } else if (count === 1 && leadDeleteState.leadNames.length > 0) {
            preview.textContent = leadDeleteState.leadNames[0];
            preview.style.display = 'inline';
            preview.style.color = 'inherit';
        } else {
            preview.textContent = `(${count} records selected)`;
            preview.style.display = 'inline';
            preview.style.color = 'inherit';
        }
    }

    if (deleteSubtext) {
        deleteSubtext.textContent = isAll
            ? '⚠️ WARNING: Every lead, calling note, activity trail, and installment record will be permanently deleted.'
            : 'All associated calling notes and activity trails will also be permanently removed.';
    }

    // Reset backup button
    if (backupBtn) {
        backupBtn.disabled = false;
        backupBtn.style.background = '#0284c7';
        backupBtn.style.borderColor = '#0284c7';
    }
    if (backupBtnText) {
        if (isAll) {
            backupBtnText.textContent = (leadDeleteState.scope === 'filtered')
                ? 'Download Filtered Leads Backup (Google Sheet Format)'
                : 'Download Full Backup (All Leads in Google Sheet Format)';
        } else {
            backupBtnText.textContent = `Download Backup (${count} Leads in Google Sheet Format)`;
        }
    }

    // Reset status box
    if (backupStatus) {
        backupStatus.style.display = 'none';
        backupStatus.className = '';
        backupStatus.innerHTML = '';
    }

    // Lock delete button initially
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.style.opacity = '0.45';
        deleteBtn.style.cursor = 'not-allowed';
        deleteBtn.title = 'Please download backup first to enable deletion';
    }
    if (deleteIcon) {
        deleteIcon.className = 'fa-solid fa-lock';
    }
    if (deleteLabel) {
        deleteLabel.textContent = isAll ? 'Delete All Locked (Backup Required)' : 'Delete Locked (Backup Required)';
    }
}

/**
 * Close Modal
 */
function closeLeadDeleteModal() {
    const modal = document.getElementById('leadDeleteModal');
    if (modal) modal.classList.remove('active');
    leadDeleteState.isDeleteAll = false;
    leadDeleteState.scope = 'custom';
    leadDeleteState.filterOptions = {};
    leadDeleteState.leadIds = [];
    leadDeleteState.leadNames = [];
    leadDeleteState.backupDownloaded = false;
    leadDeleteState.isProcessing = false;
}

/**
 * Step 1: Trigger Google Sheet CSV Backup Download & Unlock Delete
 */
function downloadLeadDeleteBackup() {
    let exportUrl = '<?php echo BASE_URL; ?>/api/leads.php?action=export_leads_csv';

    if (leadDeleteState.isDeleteAll) {
        exportUrl += '&scope=' + encodeURIComponent(leadDeleteState.scope);
        if (leadDeleteState.filterOptions) {
            Object.keys(leadDeleteState.filterOptions).forEach(k => {
                if (leadDeleteState.filterOptions[k] !== undefined && leadDeleteState.filterOptions[k] !== null && leadDeleteState.filterOptions[k] !== '') {
                    exportUrl += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(leadDeleteState.filterOptions[k]);
                }
            });
        }
    } else {
        if (leadDeleteState.leadIds.length === 0) return;
        const idsParam = leadDeleteState.leadIds.join(',');
        exportUrl += '&lead_ids=' + encodeURIComponent(idsParam);
    }

    // Create a temporary hidden link to initiate direct download without leaving page
    const link = document.createElement('a');
    link.href = exportUrl;
    link.setAttribute('download', 'Leads_Backup_' + Date.now() + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Mark backup as downloaded and unlock delete button
    leadDeleteState.backupDownloaded = true;

    const backupStatus = document.getElementById('delete-backup-status');
    const deleteBtn = document.getElementById('btn-confirm-lead-delete');
    const deleteIcon = document.getElementById('btn-delete-icon');
    const deleteLabel = document.getElementById('btn-delete-label');
    const backupBtnText = document.getElementById('btn-backup-text');

    if (backupStatus) {
        backupStatus.style.display = 'flex';
        backupStatus.style.background = 'rgba(16, 185, 129, 0.12)';
        backupStatus.style.color = '#10b981';
        backupStatus.style.border = '1px solid rgba(16, 185, 129, 0.3)';
        backupStatus.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size: 16px;"></i> <span>Backup downloaded successfully! Deletion action is now <strong>UNLOCKED</strong>.</span>';
    }

    if (backupBtnText) {
        backupBtnText.textContent = 'Backup Downloaded (Download Again)';
    }

    if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
        deleteBtn.title = 'Click to permanently delete leads';
        deleteBtn.style.boxShadow = '0 4px 14px rgba(239, 68, 68, 0.4)';
    }
    if (deleteIcon) {
        deleteIcon.className = 'fa-solid fa-trash-can';
    }
    if (deleteLabel) {
        if (leadDeleteState.isDeleteAll) {
            deleteLabel.textContent = (leadDeleteState.scope === 'filtered')
                ? 'Confirm & Permanently Delete All Filtered Leads'
                : 'Confirm & Permanently Delete ALL Master Leads';
        } else {
            deleteLabel.textContent = `Confirm & Permanently Delete (${leadDeleteState.leadIds.length})`;
        }
    }
}

/**
 * Step 2: Execute Permanent Lead Deletion via API
 */
async function executeLeadDeletion() {
    if (!leadDeleteState.backupDownloaded) {
        alert('Please download the Google Sheet backup first before deleting.');
        return;
    }

    if (leadDeleteState.isProcessing) return;

    const isAll = leadDeleteState.isDeleteAll;
    const count = leadDeleteState.leadIds.length;
    const deleteBtn = document.getElementById('btn-confirm-lead-delete');
    const deleteLabel = document.getElementById('btn-delete-label');
    const deleteIcon = document.getElementById('btn-delete-icon');

    if (isAll) {
        const doubleConfirm = confirm('🚨 FINAL CONFIRMATION:\nAre you absolutely sure you want to PERMANENTLY DELETE ALL leads?\nThis action CANNOT be undone.');
        if (!doubleConfirm) return;
    }

    leadDeleteState.isProcessing = true;
    if (deleteBtn) deleteBtn.disabled = true;
    if (deleteIcon) deleteIcon.className = 'fa-solid fa-spinner fa-spin';
    if (deleteLabel) deleteLabel.textContent = 'Deleting...';

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    if (isAll) {
        formData.append('action', 'delete_all_leads');
        formData.append('scope', leadDeleteState.scope);
        if (leadDeleteState.filterOptions) {
            Object.keys(leadDeleteState.filterOptions).forEach(k => {
                if (leadDeleteState.filterOptions[k] !== undefined && leadDeleteState.filterOptions[k] !== null && leadDeleteState.filterOptions[k] !== '') {
                    formData.append(k, leadDeleteState.filterOptions[k]);
                }
            });
        }
    } else if (count === 1) {
        formData.append('action', 'delete_lead');
        formData.append('lead_id', leadDeleteState.leadIds[0]);
    } else {
        formData.append('action', 'bulk_delete_leads');
        leadDeleteState.leadIds.forEach(id => formData.append('lead_ids[]', id));
    }

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            closeLeadDeleteModal();
            
            if (typeof leadDeleteState.onSuccessCallback === 'function') {
                leadDeleteState.onSuccessCallback(data);
            } else {
                alert(data.message || (isAll ? 'All leads deleted successfully.' : (count === 1 ? 'Lead deleted successfully.' : `${count} leads deleted successfully.`)));
                window.location.reload();
            }
        } else {
            alert(data.message || 'Error occurred while deleting leads.');
            leadDeleteState.isProcessing = false;
            if (deleteBtn) deleteBtn.disabled = false;
            if (deleteIcon) deleteIcon.className = 'fa-solid fa-trash-can';
            if (deleteLabel) deleteLabel.textContent = isAll ? 'Confirm & Permanently Delete ALL Leads' : `Confirm & Permanently Delete (${count})`;
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected network error occurred.');
        leadDeleteState.isProcessing = false;
        if (deleteBtn) deleteBtn.disabled = false;
        if (deleteIcon) deleteIcon.className = 'fa-solid fa-trash-can';
        if (deleteLabel) deleteLabel.textContent = isAll ? 'Confirm & Permanently Delete ALL Leads' : `Confirm & Permanently Delete (${count})`;
    }
}

// Close modal when clicking backdrop
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('leadDeleteModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLeadDeleteModal();
            }
        });
    }
});
</script>
