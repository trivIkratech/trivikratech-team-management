<?php
/**
 * Reusable Course & Service Payment Plans & Installments / Milestones Modal
 * Allows Sales Counsellors, Managers, and Founders to configure:
 * 1. Standard Course Payment Plans (1-Time vs 2-3 Installments)
 * 2. Scope & Complexity Based Project Milestones (Custom Deliverable Splits) for IT Services
 * and record/update installment/milestone payments.
 */
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$userRole = $currentUser['role'] ?? '';
$baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($userRole) : $userRole;
$currentUserId = (int)($currentUser['id'] ?? 0);
?>

<!-- ================= MANAGE PAYMENTS & INSTALLMENTS / MILESTONES MODAL ================= -->
<div class="modal-overlay" id="managePaymentsModal" style="display: none; z-index: 9999;">
    <div class="modal" style="max-width: 780px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; border-radius: var(--radius-lg); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; border: 1px solid var(--color-border); background: var(--color-bg-card);">
        
        <!-- Modal Header -->
        <div class="modal-header" style="background: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div id="pm-header-icon-box" style="width: 42px; height: 42px; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 19px;">
                    <i class="fa-solid fa-credit-card" id="pm-header-icon"></i>
                </div>
                <div>
                    <h3 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--color-text-main); display: flex; align-items: center; gap: 8px;">
                        <span id="pm-header-title">Payment & Installments</span>
                        <span id="payment-modal-lead-status-badge"></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;" id="payment-modal-lead-subtitle">
                        <span id="pm-lead-role-label">Student</span>: <strong id="payment-modal-lead-name" style="color: var(--color-primary-light);"></strong> · <span id="payment-modal-course-name"></span>
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeManagePaymentsModal()" style="font-size: 22px; cursor: pointer; color: var(--color-text-muted); background: none; border: none;">&times;</button>
        </div>

        <!-- Financial Summary Bar -->
        <div style="background: var(--color-bg-tertiary); border-bottom: 1px solid var(--color-border); padding: 14px 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                <div style="background: var(--color-bg-card); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;" id="pm-total-fee-label">Total Fee / Quote</div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--color-text-main); margin-top: 2px;" id="pm-total-fee">₹0</div>
                </div>
                <div style="background: rgba(16, 185, 129, 0.08); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.25);">
                    <div style="font-size: 11px; color: #10b981; text-transform: uppercase; font-weight: 600;">Received Amount</div>
                    <div style="font-size: 16px; font-weight: 700; color: #10b981; margin-top: 2px;" id="pm-paid-amount">₹0</div>
                </div>
                <div style="background: rgba(239, 68, 68, 0.08); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.25);">
                    <div style="font-size: 11px; color: #ef4444; text-transform: uppercase; font-weight: 600;">Remaining Balance</div>
                    <div style="font-size: 16px; font-weight: 700; color: #ef4444; margin-top: 2px;" id="pm-balance-due">₹0</div>
                </div>
                <div style="background: var(--color-bg-card); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Payment Structure</div>
                    <div style="font-size: 12.5px; font-weight: 600; color: var(--color-primary-light); margin-top: 4px;" id="pm-plan-type">1-Time Full Payment</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div style="display: flex; gap: 8px; padding: 10px 20px 0 20px; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <button type="button" id="tab-pm-installments-btn" class="btn btn-sm btn-primary" onclick="switchPaymentModalTab('installments')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0; font-weight: 600;">
                <i class="fa-solid fa-list-check" style="margin-right: 4px;"></i> <span id="tab-pm-installments-text">Installments & Payments</span>
            </button>
            <button type="button" id="tab-pm-plan-btn" class="btn btn-sm btn-ghost" onclick="switchPaymentModalTab('plan')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0; font-weight: 600;">
                <i class="fa-solid fa-sliders" style="margin-right: 4px;"></i> <span id="tab-pm-plan-text">Configure Plan / Milestones</span>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="modal-body" style="padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px;">
            
            <!-- Loading Indicator -->
            <div id="pm-loading" style="text-align: center; padding: 40px 0; color: var(--color-primary);">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                <div style="font-size: 13px; color: var(--color-text-secondary); margin-top: 10px;">Loading payment details...</div>
            </div>

            <!-- TAB 1: INSTALLMENTS / MILESTONES & PAYMENTS LIST -->
            <div id="tab-pm-installments-content" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: var(--color-text-main);" id="pm-schedule-heading">Payment Schedule & Status</h4>
                        <div style="font-size: 12px; color: var(--color-text-secondary);" id="pm-schedule-subheading">Record and manage collections for this lead</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="switchPaymentModalTab('plan')" style="font-size: 12px;">
                        <i class="fa-solid fa-pen"></i> Edit Plan / Custom Split
                    </button>
                </div>

                <div id="pm-installments-container" style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Rendered dynamically -->
                </div>
            </div>

            <!-- TAB 2: CONFIGURE / SETUP PAYMENT PLAN -->
            <div id="tab-pm-plan-content" style="display: none;">
                <form id="pm-setup-plan-form" onsubmit="submitConfigurePaymentPlan(event)">
                    <input type="hidden" name="action" value="save_payment_plan">
                    <input type="hidden" name="lead_id" id="pm-setup-lead-id" value="">
                    <?php echo csrfField(); ?>

                    <!-- Dynamic Description Banner (Course vs Service) -->
                    <div id="pm-plan-banner" style="background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <!-- Injected via JS -->
                    </div>

                    <!-- Course Plan Selector (Only shown for courses) -->
                    <div id="pm-course-plan-selector" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 18px;">
                        <label class="payment-plan-option-card" style="display: block; cursor: pointer;">
                            <input type="radio" name="plan_type" value="one_time" id="pm-plan-radio-1time" onchange="onCoursePlanTypeChange('one_time')" style="display: none;">
                            <div class="plan-card-inner" id="plan-card-1time" style="padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-secondary); transition: all 0.2s;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main); display: flex; align-items: center; justify-content: space-between;">
                                    <span>1-Time Full Payment</span>
                                    <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                                </div>
                                <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 4px;">100% upfront course fee</div>
                            </div>
                        </label>

                        <label class="payment-plan-option-card" style="display: block; cursor: pointer;">
                            <input type="radio" name="plan_type" value="installment" id="pm-plan-radio-2inst" onchange="onCoursePlanTypeChange('installment', 2)" style="display: none;">
                            <div class="plan-card-inner" id="plan-card-2inst" style="padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-secondary); transition: all 0.2s;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main); display: flex; align-items: center; justify-content: space-between;">
                                    <span>2 Installments</span>
                                    <span class="badge badge-secondary" style="font-size: 10px;">2 Months</span>
                                </div>
                                <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 4px;">50% / 50% or custom split</div>
                            </div>
                        </label>

                        <label class="payment-plan-option-card" style="display: block; cursor: pointer;">
                            <input type="radio" name="plan_type" value="installment" id="pm-plan-radio-3inst" onchange="onCoursePlanTypeChange('installment', 3)" style="display: none;">
                            <div class="plan-card-inner" id="plan-card-3inst" style="padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-secondary); transition: all 0.2s;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main); display: flex; align-items: center; justify-content: space-between;">
                                    <span>3 Installments</span>
                                    <span class="badge badge-primary" style="font-size: 10px;">3 Months</span>
                                </div>
                                <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 4px;">Admission, 2nd, 3rd Month</div>
                            </div>
                        </label>
                    </div>

                    <!-- Service Scope-based Milestone Presets & Custom Controls (Only shown for services) -->
                    <div id="pm-service-milestone-presets" style="display: none; margin-bottom: 18px;">
                        <div style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 8px;">
                            Quick Preset Splits (based on project complexity):
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="applyServiceMilestonePreset(1)">
                                ⚡ 1-Time Full Project Fee (100%)
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="applyServiceMilestonePreset(2)">
                                📅 2 Milestones (50% Advance / 50% Handover)
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="applyServiceMilestonePreset(3)">
                                🚀 3 Milestones (40% Advance / 30% Beta / 30% Go-Live)
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="applyServiceMilestonePreset(4)">
                                🏢 4 Milestones (30% Kickoff / 30% Dev / 30% UAT / 10% Handover)
                            </button>
                        </div>
                    </div>

                    <!-- Installments / Milestones Breakdown Table / Inputs -->
                    <div style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main);" id="pm-breakdown-table-title">
                                <i class="fa-solid fa-list-ol" style="color: var(--color-primary); margin-right: 4px;"></i> Installment Amounts & Due Dates
                            </div>
                            <div style="font-size: 12px; font-weight: 600;" id="pm-split-sum-indicator">
                                Sum: <span id="pm-split-sum-val">₹0</span> / <span id="pm-split-target-val">₹0</span>
                            </div>
                        </div>

                        <div id="pm-plan-rows-container" style="display: flex; flex-direction: column; gap: 10px;">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <!-- Add Milestone Button (Only for Services or Custom Plans) -->
                        <div id="pm-add-milestone-wrapper" style="margin-top: 12px; text-align: right; display: none;">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPmCustomMilestoneRow()" style="font-size: 12px; font-weight: 600;">
                                <i class="fa-solid fa-plus-circle"></i> Add Custom Project Milestone / Phase
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="switchPaymentModalTab('installments')">Back to Schedule</button>
                        <button type="submit" class="btn btn-primary" id="pm-btn-save-plan" style="font-weight: 600;">
                            <i class="fa-solid fa-check"></i> <span id="pm-btn-save-plan-text">Save Payment Plan</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Modal Footer -->
        <div class="modal-footer" style="background: var(--color-bg-secondary); border-top: 1px solid var(--color-border); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 12px; color: var(--color-text-muted);">
                <i class="fa-solid fa-shield-halved" style="margin-right: 4px;"></i> Authorized Sales & Accounting Portal
            </div>
            <button type="button" class="btn btn-secondary" onclick="closeManagePaymentsModal()">Close</button>
        </div>
    </div>
</div>

<!-- ================= RECORD / UPDATE SINGLE INSTALLMENT / MILESTONE PAYMENT MODAL ================= -->
<div class="modal-overlay" id="recordPaymentSubModal" style="display: none; z-index: 10001;">
    <div class="modal" style="max-width: 520px; width: 92%; border-radius: var(--radius-lg); box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.5); overflow: hidden; border: 1px solid var(--color-border); background: var(--color-bg-card);">
        
        <div class="modal-header" style="background: rgba(16, 185, 129, 0.1); border-bottom: 1px solid rgba(16, 185, 129, 0.25); padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="modal-title" style="margin: 0; font-size: 15px; font-weight: 700; color: #10b981;">
                        Record Payment
                    </h3>
                    <div style="font-size: 11.5px; color: var(--color-text-secondary);" id="rpm-installment-title-badge">
                        Payment Collection
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeRecordPaymentSubModal()" style="font-size: 20px; cursor: pointer; color: var(--color-text-muted); background: none; border: none;">&times;</button>
        </div>

        <form id="recordPaymentForm" onsubmit="submitRecordPaymentForm(event)">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="lead_id" id="rpm-lead-id" value="">
            <input type="hidden" name="payment_id" id="rpm-payment-id" value="">
            <?php echo csrfField(); ?>

            <div class="modal-body" style="padding: 18px; display: flex; flex-direction: column; gap: 14px;">
                
                <!-- Installment / Milestone Info Box -->
                <div style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;">Scheduled Amount</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--color-text-main);" id="rpm-scheduled-amount">₹0</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;">Due Date</div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--color-text-secondary);" id="rpm-due-date">—</div>
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Paid Amount (₹) *</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="rpm-paid-amount" class="form-input" required placeholder="e.g. 15000">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Payment Date *</label>
                        <input type="date" name="paid_at" id="rpm-paid-at" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Payment Method *</label>
                        <select name="payment_method" id="rpm-payment-method" class="form-select" required>
                            <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                            <option value="bank_transfer">Bank Transfer (NEFT / IMPS)</option>
                            <option value="cash">Cash (In-office receipt)</option>
                            <option value="card">Debit / Credit Card (POS)</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Transaction / UTR Reference</label>
                        <input type="text" name="transaction_id" id="rpm-transaction-id" class="form-input" placeholder="e.g. 423982938472">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Payment Status</label>
                    <select name="status" id="rpm-status" class="form-select">
                        <option value="auto">Auto-detect based on Paid Amount</option>
                        <option value="paid">Paid (Fully Cleared)</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Notes / Remarks</label>
                    <input type="text" name="notes" id="rpm-notes" class="form-input" placeholder="e.g. Paid via QR scan, Receipt #102">
                </div>
            </div>

            <div class="modal-footer" style="padding: 12px 18px; background: var(--color-bg-secondary); border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeRecordPaymentSubModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="rpm-submit-btn" style="font-weight: 600;">
                    <i class="fa-solid fa-check"></i> Save Payment Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPmLeadId = 0;
let currentPmData = null;
let currentPmNumInstallments = 1;
let currentPmOfferingType = 'course';

/**
 * Open Manage Payments Modal
 */
function openPaymentModal(leadId) {
    currentPmLeadId = leadId;
    const modal = document.getElementById('managePaymentsModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
    switchPaymentModalTab('installments');
    loadPaymentModalData(leadId);
}

function closeManagePaymentsModal() {
    const modal = document.getElementById('managePaymentsModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

/**
 * Switch tabs inside Payment Modal
 */
function switchPaymentModalTab(tab) {
    const instBtn = document.getElementById('tab-pm-installments-btn');
    const planBtn = document.getElementById('tab-pm-plan-btn');
    const instContent = document.getElementById('tab-pm-installments-content');
    const planContent = document.getElementById('tab-pm-plan-content');

    if (tab === 'installments') {
        if (instBtn) { instBtn.className = 'btn btn-sm btn-primary'; }
        if (planBtn) { planBtn.className = 'btn btn-sm btn-ghost'; }
        if (instContent) instContent.style.display = 'block';
        if (planContent) planContent.style.display = 'none';
    } else {
        if (instBtn) { instBtn.className = 'btn btn-sm btn-ghost'; }
        if (planBtn) { planBtn.className = 'btn btn-sm btn-primary'; }
        if (instContent) instContent.style.display = 'none';
        if (planContent) planContent.style.display = 'block';
        if (currentPmData) {
            setupPlanFormUI(currentPmData);
        }
    }
}

/**
 * Fetch Payment Summary & Installments Data via API
 */
function loadPaymentModalData(leadId) {
    const loadingEl = document.getElementById('pm-loading');
    const instContent = document.getElementById('tab-pm-installments-content');
    if (loadingEl) loadingEl.style.display = 'block';
    if (instContent) instContent.style.display = 'none';

    fetch('<?php echo BASE_URL; ?>/api/leads.php?action=get_payment_info&lead_id=' + leadId)
    .then(res => res.json())
    .then(data => {
        if (loadingEl) loadingEl.style.display = 'none';
        if (!data.success || !data.lead) {
            alert(data.message || 'Error loading payment details.');
            closeManagePaymentsModal();
            return;
        }

        currentPmData = data;
        currentPmOfferingType = data.lead.offering_type || 'course';
        renderPaymentSummaryHeader(data);
        renderInstallmentsList(data);
        if (instContent) instContent.style.display = 'block';
    })
    .catch(err => {
        console.error(err);
        if (loadingEl) loadingEl.style.display = 'none';
        alert('Network error loading payment details.');
        closeManagePaymentsModal();
    });
}

/**
 * Render Header Summary (Supports Courses vs Services)
 */
function renderPaymentSummaryHeader(data) {
    const lead = data.lead;
    const summary = data.payment_summary || {};
    const isService = (lead.offering_type === 'service');
    
    // Header texts
    const headerTitleEl = document.getElementById('pm-header-title');
    const headerIconEl = document.getElementById('pm-header-icon');
    const roleLabelEl = document.getElementById('pm-lead-role-label');
    const totalFeeLabelEl = document.getElementById('pm-total-fee-label');
    const instTabTextEl = document.getElementById('tab-pm-installments-text');
    const planTabTextEl = document.getElementById('tab-pm-plan-text');
    const scheduleHeadingEl = document.getElementById('pm-schedule-heading');
    const scheduleSubheadingEl = document.getElementById('pm-schedule-subheading');

    if (isService) {
        headerTitleEl.textContent = 'Project Payment & Milestones';
        headerIconEl.className = 'fa-solid fa-diagram-project';
        roleLabelEl.textContent = 'Client';
        totalFeeLabelEl.textContent = 'Total Project Quote';
        instTabTextEl.textContent = 'Project Milestones & Payments';
        planTabTextEl.textContent = 'Configure Milestones (Custom Scope)';
        scheduleHeadingEl.textContent = 'Project Milestone Schedule & Status';
        scheduleSubheadingEl.textContent = 'Track and record milestone payments according to project deliverables';
    } else {
        headerTitleEl.textContent = 'Course Payment & Installments';
        headerIconEl.className = 'fa-solid fa-credit-card';
        roleLabelEl.textContent = 'Student';
        totalFeeLabelEl.textContent = 'Total Course Fee';
        instTabTextEl.textContent = 'Installments & Payments';
        planTabTextEl.textContent = 'Configure Plan (1-Time vs 2-3 Installments)';
        scheduleHeadingEl.textContent = 'Course Installment Schedule & Status';
        scheduleSubheadingEl.textContent = 'Record and manage course installment collections for this student';
    }

    document.getElementById('payment-modal-lead-name').textContent = lead.name;
    document.getElementById('payment-modal-course-name').textContent = lead.course_service_name || (isService ? 'IT Service Project' : 'Course Program');
    document.getElementById('pm-setup-lead-id').value = lead.id;

    const totalFee = parseFloat(summary.total_fee || lead.expected_revenue || 0);
    const paidAmount = parseFloat(summary.paid_amount || lead.paid_amount || 0);
    const balanceDue = parseFloat(summary.balance_due !== undefined ? summary.balance_due : Math.max(0, totalFee - paidAmount));
    
    document.getElementById('pm-total-fee').textContent = '₹' + totalFee.toLocaleString('en-IN');
    document.getElementById('pm-paid-amount').textContent = '₹' + paidAmount.toLocaleString('en-IN');
    document.getElementById('pm-balance-due').textContent = '₹' + balanceDue.toLocaleString('en-IN');

    // Status Badge
    const statusBadgeEl = document.getElementById('payment-modal-lead-status-badge');
    let pStatus = summary.payment_status || lead.payment_status || 'unpaid';
    if (paidAmount >= totalFee && totalFee > 0) {
        statusBadgeEl.innerHTML = '<span class="badge badge-success" style="font-size: 11px;"><i class="fa-solid fa-circle-check"></i> Fully Paid</span>';
    } else if (paidAmount > 0) {
        statusBadgeEl.innerHTML = '<span class="badge badge-warning" style="font-size: 11px;"><i class="fa-solid fa-hourglass-half"></i> Partially Paid</span>';
    } else {
        statusBadgeEl.innerHTML = '<span class="badge badge-danger" style="font-size: 11px;"><i class="fa-solid fa-clock"></i> Unpaid</span>';
    }

    // Plan label
    const planType = summary.payment_plan || lead.payment_plan || 'none';
    const totalInst = parseInt(summary.total_installments || lead.total_installments || 1);
    const planTypeEl = document.getElementById('pm-plan-type');
    
    if (planType === 'one_time') {
        planTypeEl.innerHTML = '<i class="fa-solid fa-bolt" style="color: #f59e0b; margin-right: 3px;"></i> 1-Time Full Payment';
    } else if (planType === 'installment' || planType === 'milestone') {
        if (isService) {
            planTypeEl.innerHTML = '<i class="fa-solid fa-diagram-project" style="color: var(--color-primary); margin-right: 3px;"></i> ' + totalInst + ' Custom Milestones';
        } else {
            planTypeEl.innerHTML = '<i class="fa-solid fa-calendar-days" style="color: var(--color-primary); margin-right: 3px;"></i> ' + totalInst + ' Installments Plan';
        }
    } else {
        planTypeEl.innerHTML = '<span style="color: var(--color-text-muted);">Not Configured</span>';
    }
}

/**
 * Render Installments / Milestones Cards List
 */
function renderInstallmentsList(data) {
    const container = document.getElementById('pm-installments-container');
    const installments = data.payment_summary?.installments || [];
    const lead = data.lead;
    const isService = (lead.offering_type === 'service');

    if (!installments || installments.length === 0) {
        container.innerHTML = `
            <div style="background: var(--color-bg-secondary); border: 2px dashed var(--color-border); border-radius: var(--radius-lg); padding: 30px; text-align: center;">
                <i class="fa-solid ${isService ? 'fa-diagram-project' : 'fa-receipt'}" style="font-size: 32px; color: var(--color-text-muted); margin-bottom: 10px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">${isService ? 'No Project Milestones Configured Yet' : 'No Payment Plan Configured Yet'}</div>
                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 4px; max-width: 420px; margin-left: auto; margin-right: auto;">
                    ${isService ? 'Services and client projects are custom-split into deliverable milestones based on scope and complexity.' : 'Setup a 1-Time Full Payment or 2-3 Installments structure with custom amounts and due dates.'}
                </div>
                <button type="button" class="btn btn-primary" onclick="switchPaymentModalTab('plan')" style="margin-top: 14px; font-weight: 600;">
                    <i class="fa-solid fa-plus-circle"></i> ${isService ? 'Configure Project Milestones Now' : 'Configure Payment Plan Now'}
                </button>
            </div>
        `;
        return;
    }

    let html = '';
    installments.forEach((inst, idx) => {
        const amt = parseFloat(inst.amount || 0);
        const paid = parseFloat(inst.paid_amount || 0);
        const bal = Math.max(0, amt - paid);
        const status = inst.status || 'pending';

        let badgeHtml = '<span class="badge badge-secondary" style="font-size: 11px;">Pending</span>';
        if (status === 'paid' || (paid >= amt && amt > 0)) {
            badgeHtml = '<span class="badge badge-success" style="font-size: 11px;"><i class="fa-solid fa-circle-check"></i> Paid</span>';
        } else if (status === 'partially_paid' || paid > 0) {
            badgeHtml = '<span class="badge badge-warning" style="font-size: 11px;"><i class="fa-solid fa-hourglass-half"></i> Partially Paid</span>';
        } else if (status === 'overdue' || (inst.due_date && new Date(inst.due_date) < new Date())) {
            badgeHtml = '<span class="badge badge-danger" style="font-size: 11px;"><i class="fa-solid fa-triangle-exclamation"></i> Overdue</span>';
        }

        const isPaid = (paid >= amt && amt > 0);
        const cardBorder = isPaid ? 'rgba(16, 185, 129, 0.4)' : (paid > 0 ? 'rgba(245, 158, 11, 0.4)' : 'var(--color-border)');
        const cardBg = isPaid ? 'rgba(16, 185, 129, 0.03)' : 'var(--color-bg-secondary)';
        const defaultTitle = isService ? ('Milestone ' + inst.installment_no) : ('Installment ' + inst.installment_no);

        html += `
            <div style="border: 1px solid ${cardBorder}; background: ${cardBg}; border-radius: var(--radius-md); padding: 14px 16px; transition: all 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">
                            ${escapeHtml(inst.title || defaultTitle)}
                        </span>
                        ${badgeHtml}
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: var(--color-text-main);">
                            ₹${amt.toLocaleString('en-IN')}
                        </span>
                        <button type="button" class="btn btn-sm ${isPaid ? 'btn-outline-success' : 'btn-primary'}" 
                                onclick="openRecordPaymentSubModal(${inst.id}, ${inst.installment_no}, '${escapeHtml(inst.title)}', ${amt}, ${paid}, '${inst.due_date || ''}', '${inst.payment_method || 'upi'}', '${escapeHtml(inst.transaction_id || '')}', '${escapeHtml(inst.notes || '')}')"
                                style="padding: 4px 10px; font-size: 12px; font-weight: 600;">
                            <i class="fa-solid ${isPaid ? 'fa-pen' : 'fa-hand-holding-dollar'}"></i> ${isPaid ? 'Update' : 'Record Payment'}
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; font-size: 12px; padding: 10px; background: var(--color-bg-tertiary); border-radius: var(--radius-sm);">
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 10.5px;">Paid Amount</span>
                        <strong style="color: ${paid > 0 ? '#10b981' : 'var(--color-text-muted)'}; font-size: 13px;">₹${paid.toLocaleString('en-IN')}</strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 10.5px;">Target Due Date</span>
                        <span>${inst.due_date ? new Date(inst.due_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'}) : '—'}</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 10.5px;">Payment Date</span>
                        <span>${inst.paid_at ? new Date(inst.paid_at).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'}) : '—'}</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 10.5px;">Method / Mode</span>
                        <span style="text-transform: uppercase; font-weight: 600;">${inst.payment_method || '—'}</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 10.5px;">UTR / Txn ID</span>
                        <span style="font-family: monospace; font-size: 11px;">${inst.transaction_id || '—'}</span>
                    </div>
                </div>

                ${inst.notes ? `<div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 8px;"><i class="fa-solid fa-note-sticky" style="color: var(--color-text-muted); margin-right: 4px;"></i>${escapeHtml(inst.notes)}</div>` : ''}
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Setup Plan Tab UI based on current data and Offering Type (Course vs Service)
 */
function setupPlanFormUI(data) {
    const lead = data.lead;
    const summary = data.payment_summary || {};
    const isService = (lead.offering_type === 'service');
    const totalFee = parseFloat(summary.total_fee || lead.expected_revenue || 0);
    const planType = summary.payment_plan || (lead.payment_plan !== 'none' ? lead.payment_plan : 'installment');
    const existingInst = summary.installments || [];

    const bannerEl = document.getElementById('pm-plan-banner');
    const courseSelectorEl = document.getElementById('pm-course-plan-selector');
    const servicePresetsEl = document.getElementById('pm-service-milestone-presets');
    const addMilestoneWrapperEl = document.getElementById('pm-add-milestone-wrapper');
    const breakdownTitleEl = document.getElementById('pm-breakdown-table-title');
    const saveBtnTextEl = document.getElementById('pm-btn-save-plan-text');

    if (isService) {
        // SERVICE CONFIGURATION
        bannerEl.innerHTML = `
            <div style="font-weight: 700; font-size: 13px; color: var(--color-primary-light); margin-bottom: 4px;">
                <i class="fa-solid fa-diagram-project" style="margin-right: 4px;"></i> Scope & Complexity-Based Project Milestones
            </div>
            <div style="font-size: 12px; color: var(--color-text-secondary);">
                Service & client projects are priced and divided into <strong>custom milestone installments</strong> based on project scope, complexity, and delivery phases.
            </div>
        `;
        courseSelectorEl.style.display = 'none';
        servicePresetsEl.style.display = 'block';
        addMilestoneWrapperEl.style.display = 'block';
        breakdownTitleEl.innerHTML = '<i class="fa-solid fa-list-check" style="color: var(--color-primary); margin-right: 4px;"></i> Project Deliverables & Milestone Schedule';
        saveBtnTextEl.textContent = 'Save Project Milestones';

        if (existingInst.length > 0) {
            renderServiceCustomMilestoneRows(existingInst);
        } else {
            // Default 3-milestone breakdown for service
            applyServiceMilestonePreset(3);
        }
    } else {
        // COURSE CONFIGURATION
        bannerEl.innerHTML = `
            <div style="font-weight: 700; font-size: 13px; color: var(--color-primary-light); margin-bottom: 4px;">
                <i class="fa-solid fa-calculator" style="margin-right: 4px;"></i> Course Fee Payment Structure
            </div>
            <div style="font-size: 12px; color: var(--color-text-secondary);">
                Choose between 1-Time Full Payment or structured Installments (Up to 3 Installments) for the student's enrolled course.
            </div>
        `;
        courseSelectorEl.style.display = 'grid';
        servicePresetsEl.style.display = 'none';
        addMilestoneWrapperEl.style.display = 'none';
        breakdownTitleEl.innerHTML = '<i class="fa-solid fa-list-ol" style="color: var(--color-primary); margin-right: 4px;"></i> Installment Amounts & Due Dates';
        saveBtnTextEl.textContent = 'Save Payment Plan';

        const count = existingInst.length > 0 ? existingInst.length : (planType === 'one_time' ? 1 : 3);
        onCoursePlanTypeChange(planType === 'one_time' ? 'one_time' : 'installment', count, existingInst);
    }
}

/**
 * Course Plan Type Selection Handler (1-time vs 2-inst vs 3-inst)
 */
function onCoursePlanTypeChange(planType, numInstallments = 1, existingData = null) {
    currentPmNumInstallments = (planType === 'one_time') ? 1 : numInstallments;

    document.querySelectorAll('.plan-card-inner').forEach(el => {
        el.style.borderColor = 'var(--color-border)';
        el.style.background = 'var(--color-bg-secondary)';
    });

    if (planType === 'one_time') {
        const card = document.getElementById('plan-card-1time');
        const radio = document.getElementById('pm-plan-radio-1time');
        if (card) { card.style.borderColor = 'var(--color-primary)'; card.style.background = 'rgba(99, 102, 241, 0.08)'; }
        if (radio) radio.checked = true;
    } else if (numInstallments === 2) {
        const card = document.getElementById('plan-card-2inst');
        const radio = document.getElementById('pm-plan-radio-2inst');
        if (card) { card.style.borderColor = 'var(--color-primary)'; card.style.background = 'rgba(99, 102, 241, 0.08)'; }
        if (radio) radio.checked = true;
    } else {
        const card = document.getElementById('plan-card-3inst');
        const radio = document.getElementById('pm-plan-radio-3inst');
        if (card) { card.style.borderColor = 'var(--color-primary)'; card.style.background = 'rgba(99, 102, 241, 0.08)'; }
        if (radio) radio.checked = true;
    }

    renderCoursePlanRows(planType, currentPmNumInstallments, existingData);
}

/**
 * Render Course Installment Rows
 */
function renderCoursePlanRows(planType, numInstallments, existingData = null) {
    const container = document.getElementById('pm-plan-rows-container');
    const totalFee = parseFloat(currentPmData?.payment_summary?.total_fee || currentPmData?.lead?.expected_revenue || 40000);
    
    document.getElementById('pm-split-target-val').textContent = '₹' + totalFee.toLocaleString('en-IN');

    let html = '';
    const today = new Date();

    for (let i = 1; i <= numInstallments; i++) {
        let title = '';
        let amount = 0;
        let dueDate = '';

        if (existingData && existingData[i - 1]) {
            title = existingData[i - 1].title || '';
            amount = parseFloat(existingData[i - 1].amount || 0);
            dueDate = existingData[i - 1].due_date || '';
        } else {
            if (planType === 'one_time') {
                title = '1-Time Full Payment';
                amount = totalFee;
                dueDate = today.toISOString().split('T')[0];
            } else if (numInstallments === 2) {
                title = (i === 1) ? '1st Installment (Admission Downpayment)' : '2nd Installment (Month 2)';
                amount = Math.round(totalFee / 2);
                if (i === 2) amount = totalFee - Math.round(totalFee / 2);
                
                const d = new Date();
                d.setMonth(d.getMonth() + (i - 1));
                dueDate = d.toISOString().split('T')[0];
            } else {
                title = (i === 1) ? '1st Installment (Admission Downpayment)' : ((i === 2) ? '2nd Installment (Month 2)' : '3rd Installment (Month 3)');
                if (totalFee === 40000) {
                    amount = (i === 1 || i === 2) ? 15000 : 10000;
                } else if (totalFee === 35000) {
                    amount = (i === 1) ? 15000 : 10000;
                } else {
                    amount = Math.round(totalFee / 3);
                    if (i === 3) amount = totalFee - (Math.round(totalFee / 3) * 2);
                }

                const d = new Date();
                d.setMonth(d.getMonth() + (i - 1));
                dueDate = d.toISOString().split('T')[0];
            }
        }

        html += `
            <div class="pm-plan-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; align-items: center; background: var(--color-bg-card); padding: 10px 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Installment ${i} Title</label>
                    <input type="text" class="form-input pm-row-title" value="${escapeHtml(title)}" style="padding: 6px 10px; font-size: 12px;" required>
                </div>
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Amount (₹) *</label>
                    <input type="number" step="0.01" min="0" class="form-input pm-row-amount" value="${amount}" oninput="recalcPlanSplitSum()" style="padding: 6px 10px; font-size: 12px; font-weight: 700;" required>
                </div>
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Due Date *</label>
                    <input type="date" class="form-input pm-row-date" value="${dueDate}" style="padding: 6px 8px; font-size: 12px;" required>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
    recalcPlanSplitSum();
}

/**
 * Service Milestone Preset Applier
 */
function applyServiceMilestonePreset(count) {
    const totalFee = parseFloat(currentPmData?.payment_summary?.total_fee || currentPmData?.lead?.expected_revenue || 50000);
    const presets = [];

    if (count === 1) {
        presets.push({
            title: '1-Time Full Project Advance',
            amount: totalFee,
            due_date: new Date().toISOString().split('T')[0]
        });
    } else if (count === 2) {
        const half = Math.round(totalFee / 2);
        const d2 = new Date(); d2.setDate(d2.getDate() + 30);
        presets.push(
            { title: 'Milestone 1: Project Kickoff & Advance (50%)', amount: half, due_date: new Date().toISOString().split('T')[0] },
            { title: 'Milestone 2: Final Delivery & Handover (50%)', amount: totalFee - half, due_date: d2.toISOString().split('T')[0] }
        );
    } else if (count === 3) {
        const p1 = Math.round(totalFee * 0.40);
        const p2 = Math.round(totalFee * 0.30);
        const p3 = totalFee - p1 - p2;
        const d2 = new Date(); d2.setDate(d2.getDate() + 20);
        const d3 = new Date(); d3.setDate(d3.getDate() + 45);
        presets.push(
            { title: 'Milestone 1: Project Advance & UI/UX Signoff (40%)', amount: p1, due_date: new Date().toISOString().split('T')[0] },
            { title: 'Milestone 2: Core Development & Beta Review (30%)', amount: p2, due_date: d2.toISOString().split('T')[0] },
            { title: 'Milestone 3: Final Deployment & Handover (30%)', amount: p3, due_date: d3.toISOString().split('T')[0] }
        );
    } else if (count === 4) {
        const p1 = Math.round(totalFee * 0.30);
        const p2 = Math.round(totalFee * 0.30);
        const p3 = Math.round(totalFee * 0.30);
        const p4 = totalFee - p1 - p2 - p3;
        const d2 = new Date(); d2.setDate(d2.getDate() + 15);
        const d3 = new Date(); d3.setDate(d3.getDate() + 35);
        const d4 = new Date(); d4.setDate(d4.getDate() + 60);
        presets.push(
            { title: 'Milestone 1: Advance / Kickoff & Architecture (30%)', amount: p1, due_date: new Date().toISOString().split('T')[0] },
            { title: 'Milestone 2: UI/UX & Backend Integration (30%)', amount: p2, due_date: d2.toISOString().split('T')[0] },
            { title: 'Milestone 3: Beta Testing & Client Review (30%)', amount: p3, due_date: d3.toISOString().split('T')[0] },
            { title: 'Milestone 4: Production Deployment & Handover (10%)', amount: p4, due_date: d4.toISOString().split('T')[0] }
        );
    }

    renderServiceCustomMilestoneRows(presets);
}

/**
 * Render Custom Service Milestone Rows with Dynamic Add/Remove
 */
function renderServiceCustomMilestoneRows(milestonesList) {
    const container = document.getElementById('pm-plan-rows-container');
    const totalFee = parseFloat(currentPmData?.payment_summary?.total_fee || currentPmData?.lead?.expected_revenue || 0);
    document.getElementById('pm-split-target-val').textContent = '₹' + totalFee.toLocaleString('en-IN');

    let html = '';
    milestonesList.forEach((m, idx) => {
        const num = idx + 1;
        const title = m.title || `Milestone ${num}: Phase Deliverable`;
        const amt = parseFloat(m.amount || 0);
        const dueDate = m.due_date || new Date().toISOString().split('T')[0];

        html += `
            <div class="pm-plan-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 8px; align-items: center; background: var(--color-bg-card); padding: 10px 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Milestone ${num} Title / Scope *</label>
                    <input type="text" class="form-input pm-row-title" value="${escapeHtml(title)}" placeholder="e.g. Milestone ${num}: Advance Kickoff" style="padding: 6px 10px; font-size: 12px;" required>
                </div>
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Amount (₹) *</label>
                    <input type="number" step="0.01" min="0" class="form-input pm-row-amount" value="${amt}" oninput="recalcPlanSplitSum()" style="padding: 6px 10px; font-size: 12px; font-weight: 700;" required>
                </div>
                <div>
                    <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Target Due Date *</label>
                    <input type="date" class="form-input pm-row-date" value="${dueDate}" style="padding: 6px 8px; font-size: 12px;" required>
                </div>
                <div style="padding-top: 14px;">
                    <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="removePmCustomMilestoneRow(this)" title="Remove Milestone" style="padding: 6px 8px;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    recalcPlanSplitSum();
}

/**
 * Add a new custom milestone row for Service projects
 */
function addPmCustomMilestoneRow() {
    const container = document.getElementById('pm-plan-rows-container');
    const existingRows = container.querySelectorAll('.pm-plan-row');
    const nextNum = existingRows.length + 1;
    const today = new Date();
    today.setDate(today.getDate() + (nextNum * 15));
    const nextDate = today.toISOString().split('T')[0];

    const rowDiv = document.createElement('div');
    rowDiv.className = 'pm-plan-row';
    rowDiv.style = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 8px; align-items: center; background: var(--color-bg-card); padding: 10px 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);';
    rowDiv.innerHTML = `
        <div>
            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Milestone ${nextNum} Title / Scope *</label>
            <input type="text" class="form-input pm-row-title" value="Milestone ${nextNum}: Deliverable Phase" style="padding: 6px 10px; font-size: 12px;" required>
        </div>
        <div>
            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Amount (₹) *</label>
            <input type="number" step="0.01" min="0" class="form-input pm-row-amount" value="0" oninput="recalcPlanSplitSum()" style="padding: 6px 10px; font-size: 12px; font-weight: 700;" required>
        </div>
        <div>
            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block;">Target Due Date *</label>
            <input type="date" class="form-input pm-row-date" value="${nextDate}" style="padding: 6px 8px; font-size: 12px;" required>
        </div>
        <div style="padding-top: 14px;">
            <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="removePmCustomMilestoneRow(this)" title="Remove Milestone" style="padding: 6px 8px;">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(rowDiv);
    recalcPlanSplitSum();
}

/**
 * Remove custom milestone row
 */
function removePmCustomMilestoneRow(btn) {
    const row = btn.closest('.pm-plan-row');
    const container = document.getElementById('pm-plan-rows-container');
    if (container.querySelectorAll('.pm-plan-row').length <= 1) {
        alert('At least one milestone / payment record is required.');
        return;
    }
    if (row) {
        row.remove();
        // Re-number labels
        container.querySelectorAll('.pm-plan-row').forEach((r, idx) => {
            const label = r.querySelector('label');
            if (label) label.textContent = `Milestone ${idx + 1} Title / Scope *`;
        });
        recalcPlanSplitSum();
    }
}

/**
 * Calculate & Validate Sum of Split Amounts
 */
function recalcPlanSplitSum() {
    const amountInputs = document.querySelectorAll('.pm-row-amount');
    let sum = 0;
    amountInputs.forEach(input => {
        sum += parseFloat(input.value || 0);
    });

    const totalFee = parseFloat(currentPmData?.payment_summary?.total_fee || currentPmData?.lead?.expected_revenue || 0);
    const sumValEl = document.getElementById('pm-split-sum-val');
    const indicatorEl = document.getElementById('pm-split-sum-indicator');

    if (sumValEl) sumValEl.textContent = '₹' + sum.toLocaleString('en-IN');
    if (indicatorEl) {
        if (Math.abs(sum - totalFee) < 0.01) {
            indicatorEl.style.color = '#10b981';
        } else {
            indicatorEl.style.color = '#ef4444';
        }
    }
}

/**
 * Submit Payment Plan / Milestones Configuration
 */
async function submitConfigurePaymentPlan(e) {
    e.preventDefault();
    const btn = document.getElementById('pm-btn-save-plan');
    const leadId = document.getElementById('pm-setup-lead-id').value;
    const isService = (currentPmOfferingType === 'service');
    
    // Collect rows
    const rows = document.querySelectorAll('.pm-plan-row');
    const installments = [];
    let sum = 0;

    rows.forEach((row, idx) => {
        const title = row.querySelector('.pm-row-title').value.trim();
        const amount = parseFloat(row.querySelector('.pm-row-amount').value || 0);
        const dueDate = row.querySelector('.pm-row-date').value;

        sum += amount;
        installments.push({
            installment_no: idx + 1,
            title: title || (isService ? ('Milestone ' + (idx + 1)) : ('Installment ' + (idx + 1))),
            amount: amount,
            due_date: dueDate
        });
    });

    if (installments.length === 0) {
        alert('Please configure at least 1 installment / milestone.');
        return;
    }

    const totalFee = parseFloat(currentPmData?.payment_summary?.total_fee || currentPmData?.lead?.expected_revenue || 0);
    if (totalFee > 0 && Math.abs(sum - totalFee) > 0.01) {
        const itemType = isService ? 'project quote' : 'course fee';
        if (!confirm(`Warning: The sum of ${isService ? 'milestones' : 'installments'} (₹${sum.toLocaleString('en-IN')}) does not match the total ${itemType} (₹${totalFee.toLocaleString('en-IN')}). Do you want to proceed with this plan?`)) {
            return;
        }
    }

    const planType = (installments.length === 1) ? 'one_time' : 'installment';

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving Plan...';

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');
    formData.append('action', 'save_payment_plan');
    formData.append('lead_id', leadId);
    formData.append('plan_type', planType);
    formData.append('installments_json', JSON.stringify(installments));

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            loadPaymentModalData(leadId);
            switchPaymentModalTab('installments');
            if (typeof window.refreshLeadsAfterPayment === 'function') {
                window.refreshLeadsAfterPayment();
            }
        } else {
            alert(data.message || 'Error saving payment plan.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + (isService ? 'Save Project Milestones' : 'Save Payment Plan');
    }
}

/**
 * Open Record Installment / Milestone Payment Sub-Modal
 */
function openRecordPaymentSubModal(paymentId, instNo, title, amount, paidAmount, dueDate, method, txnId, notes) {
    const isService = (currentPmOfferingType === 'service');
    document.getElementById('rpm-lead-id').value = currentPmLeadId;
    document.getElementById('rpm-payment-id').value = paymentId;
    
    const fallbackTitle = isService ? ('Milestone ' + instNo) : ('Installment ' + instNo);
    document.getElementById('rpm-installment-title-badge').textContent = title || fallbackTitle;
    document.getElementById('rpm-scheduled-amount').textContent = '₹' + parseFloat(amount).toLocaleString('en-IN');
    document.getElementById('rpm-due-date').textContent = dueDate ? new Date(dueDate).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'}) : '—';
    
    // Set paid amount defaults
    document.getElementById('rpm-paid-amount').value = (paidAmount > 0) ? paidAmount : amount;
    document.getElementById('rpm-payment-method').value = method || 'upi';
    document.getElementById('rpm-transaction-id').value = txnId || '';
    document.getElementById('rpm-notes').value = notes || '';
    document.getElementById('rpm-status').value = 'auto';

    const modal = document.getElementById('recordPaymentSubModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
}

function closeRecordPaymentSubModal() {
    const modal = document.getElementById('recordPaymentSubModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

/**
 * Submit Record Payment Form
 */
async function submitRecordPaymentForm(e) {
    e.preventDefault();
    const btn = document.getElementById('rpm-submit-btn');
    const form = document.getElementById('recordPaymentForm');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeRecordPaymentSubModal();
            loadPaymentModalData(currentPmLeadId);
            // If lead drawer is open, refresh it
            if (document.getElementById('leadDrawerModal')?.classList.contains('active')) {
                if (typeof viewLeadDrawer === 'function') {
                    viewLeadDrawer(currentPmLeadId);
                }
            }
            if (typeof window.refreshLeadsAfterPayment === 'function') {
                window.refreshLeadsAfterPayment();
            }
        } else {
            alert(data.message || 'Error recording payment.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Payment Record';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
