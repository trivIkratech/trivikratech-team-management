<?php
/**
 * Global Sales Target Management Modal
 * 
 * Allows Founder & Manager to set or edit monthly sales targets (revenue, enrollments, leads)
 * for any sales counsellor / employee with live month & counsellor switching.
 */

$targetCounsellorsList = function_exists('getSalesCounsellorsList') ? getSalesCounsellorsList() : [];
$currentMonthVal = date('Y-m');
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<!-- ======================================================== -->
<!-- 🎯 GLOBAL MONTHLY SALES TARGET MODAL -->
<!-- ======================================================== -->
<div id="globalSalesTargetModal" class="modal-overlay" style="z-index: 999999; display: none;">
    <div class="modal fade-in" style="max-width: 580px; width: 92vw; background: var(--color-bg-card); border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: 0 20px 60px rgba(0,0,0,0.5); overflow: hidden; margin: auto; position: relative;">
        
        <!-- Header -->
        <div style="padding: 16px 22px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.18), rgba(16, 185, 129, 0.12)); border-bottom: 1px solid rgba(99, 102, 241, 0.25); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: var(--color-primary); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--color-text-main);" id="salesTargetModalTitle">Set Monthly Sales Target</h3>
                    <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                        Assign monthly revenue & admission targets for sales counsellors
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeSalesTargetModal()" style="background: none; border: none; font-size: 20px; color: var(--color-text-muted); cursor: pointer; line-height: 1; padding: 4px 8px;" title="Close">✕</button>
        </div>

        <!-- Form Body -->
        <form id="salesTargetForm" onsubmit="submitSalesTargetForm(event)" style="margin: 0;">
            <input type="hidden" name="action" value="save_sales_target">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

            <div style="padding: 22px 24px; max-height: 75vh; overflow-y: auto;">
                
                <!-- Target Month & Counsellor Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); display: block; margin-bottom: 6px;">
                            <i class="fa-solid fa-calendar-days" style="color: var(--color-primary);"></i> Target Month *
                        </label>
                        <input type="month" id="targetModalMonth" name="target_month" class="form-control" 
                               value="<?php echo $currentMonthVal; ?>" required onchange="onTargetUserOrMonthChange()"
                               style="width: 100%; font-size: 13.5px; font-weight: 600; padding: 8px 12px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                    </div>

                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); display: block; margin-bottom: 6px;">
                            <i class="fa-solid fa-user-tie" style="color: var(--color-primary);"></i> Sales Counsellor *
                        </label>
                        <select id="targetModalUserId" name="user_id" class="form-select" required onchange="onTargetUserOrMonthChange()"
                                style="width: 100%; font-size: 13.5px; font-weight: 600; padding: 8px 12px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                            <option value="">-- Select Counsellor --</option>
                            <?php foreach ($targetCounsellorsList as $tc): ?>
                                <option value="<?php echo $tc['id']; ?>" <?php echo ($tc['role'] === 'sales_counsellor') ? 'selected' : ''; ?>>
                                    <?php echo e($tc['name']); ?> (<?php echo e(ucwords(str_replace('_', ' ', $tc['role']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Current Target Status Callout Banner (Dynamic) -->
                <div id="targetModalStatusBanner" style="display: none; margin-bottom: 16px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); color: var(--color-text-main);">
                    <i class="fa-solid fa-circle-info" style="color: var(--color-primary); margin-right: 6px;"></i>
                    <span id="targetModalStatusText">Existing target found for this month.</span>
                </div>

                <!-- 1. Revenue Target -->
                <div class="form-group" style="margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 13px; font-weight: 700; color: var(--color-text-main); display: flex; align-items: center; gap: 6px; margin: 0;">
                            <i class="fa-solid fa-indian-rupee-sign" style="color: #10b981;"></i> Monthly Revenue Target (₹) *
                        </label>
                        <span style="font-size: 11px; color: var(--color-text-muted);">Course/Service fees goal</span>
                    </div>
                    <input type="number" id="targetModalRevenue" name="target_revenue" class="form-control" 
                           placeholder="e.g. 150000" min="0" step="1000" required
                           style="width: 100%; font-size: 16px; font-weight: 700; padding: 10px 14px; background: var(--color-bg-secondary); border: 1.5px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-md); color: var(--color-text-main);">
                    
                    <!-- Quick Presets -->
                    <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
                        <span style="font-size: 11px; color: var(--color-text-secondary); align-self: center;">Presets:</span>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetRevenuePreset(50000)" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--color-border);">₹50k</button>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetRevenuePreset(100000)" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--color-border);">₹1.0 Lakh</button>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetRevenuePreset(150000)" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--color-border);">₹1.5 Lakh</button>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetRevenuePreset(200000)" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--color-border);">₹2.0 Lakh</button>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetRevenuePreset(300000)" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--color-border);">₹3.0 Lakh</button>
                    </div>
                </div>

                <!-- 2. Target Enrollments & Target Leads Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
                    <div>
                        <label style="font-size: 12.5px; font-weight: 700; color: var(--color-text-main); display: block; margin-bottom: 6px;">
                            <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Target Admissions *
                        </label>
                        <input type="number" id="targetModalEnrollments" name="target_enrollments" class="form-control" 
                               placeholder="e.g. 5" min="0" step="1" required
                               style="width: 100%; font-size: 14.5px; font-weight: 600; padding: 9px 12px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                        <div style="display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetEnrollPreset(3)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">3</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetEnrollPreset(5)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">5</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetEnrollPreset(8)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">8</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetEnrollPreset(10)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">10</button>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 12.5px; font-weight: 700; color: var(--color-text-main); display: block; margin-bottom: 6px;">
                            <i class="fa-solid fa-phone-volume" style="color: #f59e0b;"></i> Target Leads Contacted
                        </label>
                        <input type="number" id="targetModalLeads" name="target_leads" class="form-control" 
                               placeholder="e.g. 50" min="0" step="5"
                               style="width: 100%; font-size: 14.5px; font-weight: 600; padding: 9px 12px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                        <div style="display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetLeadsPreset(30)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">30</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetLeadsPreset(50)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">50</button>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="setTargetLeadsPreset(100)" style="padding: 1px 6px; font-size: 10.5px; border: 1px solid var(--color-border);">100</button>
                        </div>
                    </div>
                </div>

                <!-- 3. Incentive / Performance Notes -->
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fa-solid fa-gift" style="color: #ec4899;"></i> Incentive / Bonus Notes (Optional)
                    </label>
                    <textarea id="targetModalNotes" name="notes" rows="2" class="form-textarea"
                              placeholder="e.g. Bonus: ₹5,000 cash bonus + Certificate on achieving 100% target!"
                              style="width: 100%; font-family: inherit; font-size: 12.5px; padding: 10px 12px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); resize: vertical;"></textarea>
                </div>

            </div>

            <!-- Footer Actions -->
            <div style="padding: 14px 24px; background: var(--color-bg-tertiary); border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-ghost" onclick="closeSalesTargetModal()" style="font-size: 13.5px;">Cancel</button>
                <button type="submit" id="saveTargetSubmitBtn" class="btn btn-primary" style="font-size: 13.5px; font-weight: 600; padding: 8px 20px;">
                    <i class="fa-solid fa-check"></i> Save & Assign Target
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.openSalesTargetModal = function(userId, month, targetRev, targetEnr, targetLeads, notes) {
    const modal = document.getElementById('globalSalesTargetModal');
    if (!modal) {
        console.error('globalSalesTargetModal element not found');
        return;
    }

    const userSelect = document.getElementById('targetModalUserId');
    const monthInput = document.getElementById('targetModalMonth');
    const revInput = document.getElementById('targetModalRevenue');
    const enrInput = document.getElementById('targetModalEnrollments');
    const leadsInput = document.getElementById('targetModalLeads');
    const notesInput = document.getElementById('targetModalNotes');
    const banner = document.getElementById('targetModalStatusBanner');

    if (month) {
        monthInput.value = month;
    } else if (!monthInput.value) {
        monthInput.value = '<?php echo $currentMonthVal; ?>';
    }

    if (userId) {
        userSelect.value = userId;
    } else if (!userSelect.value && userSelect.options.length > 1) {
        userSelect.selectedIndex = 1;
        userId = userSelect.value;
    }

    const effectiveUserId = userSelect.value || userId;

    if (targetRev !== undefined && targetRev !== null && targetRev !== '') {
        revInput.value = targetRev;
        enrInput.value = targetEnr || 0;
        leadsInput.value = targetLeads || 0;
        notesInput.value = notes || '';
        if (banner) banner.style.display = 'none';
    } else if (effectiveUserId) {
        loadTargetForUserAndMonth(effectiveUserId, monthInput.value);
    } else {
        revInput.value = '';
        enrInput.value = '';
        leadsInput.value = '';
        notesInput.value = '';
        if (banner) banner.style.display = 'none';
    }

    modal.style.display = 'flex';
    modal.classList.add('active');
};

window.closeSalesTargetModal = function() {
    const modal = document.getElementById('globalSalesTargetModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
};

window.onTargetUserOrMonthChange = function() {
    const userId = document.getElementById('targetModalUserId').value;
    const month = document.getElementById('targetModalMonth').value;
    if (userId && month) {
        loadTargetForUserAndMonth(userId, month);
    }
};

window.loadTargetForUserAndMonth = function(userId, month) {
    const banner = document.getElementById('targetModalStatusBanner');
    const bannerText = document.getElementById('targetModalStatusText');
    const revInput = document.getElementById('targetModalRevenue');
    const enrInput = document.getElementById('targetModalEnrollments');
    const leadsInput = document.getElementById('targetModalLeads');
    const notesInput = document.getElementById('targetModalNotes');

    fetch(`<?php echo BASE_URL; ?>/api/leads.php?action=get_sales_target&user_id=${encodeURIComponent(userId)}&month=${encodeURIComponent(month)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data;
                if (d.has_target) {
                    revInput.value = d.target_revenue || '';
                    enrInput.value = d.target_enrollments || '';
                    leadsInput.value = d.target_leads || '';
                    notesInput.value = d.notes || '';
                    if (banner && bannerText) {
                        bannerText.innerHTML = `<strong>Existing Target:</strong> ₹${Number(d.target_revenue).toLocaleString('en-IN')} (${d.target_enrollments} Admissions) set by <strong>${d.set_by_name || 'Management'}</strong>.`;
                        banner.style.display = 'block';
                    }
                } else {
                    revInput.value = '';
                    enrInput.value = '';
                    leadsInput.value = '';
                    notesInput.value = '';
                    if (banner) banner.style.display = 'none';
                }
            }
        })
        .catch(err => {
            console.error('Error fetching sales target:', err);
        });
};

window.setTargetRevenuePreset = function(val) {
    const input = document.getElementById('targetModalRevenue');
    if (input) input.value = val;
};

window.setTargetEnrollPreset = function(val) {
    const input = document.getElementById('targetModalEnrollments');
    if (input) input.value = val;
};

window.setTargetLeadsPreset = function(val) {
    const input = document.getElementById('targetModalLeads');
    if (input) input.value = val;
};

window.submitSalesTargetForm = function(event) {
    event.preventDefault();
    const btn = document.getElementById('saveTargetSubmitBtn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const form = document.getElementById('salesTargetForm');
    const formData = new FormData(form);

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.success) {
            closeSalesTargetModal();
            if (typeof showToast === 'function') {
                showToast(data.message || 'Sales target assigned successfully!', 'success');
            } else {
                alert(data.message || 'Sales target assigned successfully!');
            }
            setTimeout(() => {
                window.location.reload();
            }, 700);
        } else {
            alert(data.message || 'Failed to save sales target.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        console.error('Error saving sales target:', err);
        alert('Network or server error while saving sales target.');
    });
};

// Close on backdrop click or ESC
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('globalSalesTargetModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeSalesTargetModal();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSalesTargetModal();
    });
});
</script>
