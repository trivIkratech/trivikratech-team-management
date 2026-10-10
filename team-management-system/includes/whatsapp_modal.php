<!-- ======================================================== -->
<!-- 💬 GLOBAL WHATSAPP OUTREACH MODAL WITH TEMPLATE SELECTOR -->
<!-- ======================================================== -->
<div id="globalWhatsAppModal" class="modal-overlay" style="z-index: 99999; display: none;">
    <div class="modal fade-in" style="max-width: 580px; width: 92vw; background: var(--color-bg-card); border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: 0 20px 60px rgba(0,0,0,0.4); overflow: hidden; margin: auto;">
        <!-- Header -->
        <div style="padding: 16px 20px; background: linear-gradient(135deg, rgba(37, 211, 102, 0.15), rgba(18, 140, 126, 0.08)); border-bottom: 1px solid rgba(37, 211, 102, 0.25); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: #25D366; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--color-text-main);">Send WhatsApp Message</h3>
                    <div id="globalWaRecipientInfo" style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                        To: <strong id="globalWaRecipientName" style="color: var(--color-text-main);">Candidate</strong> (<span id="globalWaRecipientPhone" style="font-family: monospace;">+91-XXXXX</span>)
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeGlobalWhatsAppModal()" style="background: none; border: none; font-size: 20px; color: var(--color-text-muted); cursor: pointer; line-height: 1; padding: 4px 8px;">✕</button>
        </div>

        <!-- Body -->
        <div style="padding: 20px;">
            <!-- Dropdown Template Selector -->
            <div class="form-group" style="margin-bottom: 14px;">
                <label style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                    <i class="fa-solid fa-list-check" style="color: #25D366;"></i> Select Message Template (Dropdown):
                </label>
                <select id="globalWaTemplateSelect" class="form-select" onchange="onGlobalWaTemplateChange()" style="width: 100%; font-size: 13.5px; font-weight: 600; padding: 8px 12px; border-radius: var(--radius-md); border: 1.5px solid rgba(37, 211, 102, 0.4); background: var(--color-bg-secondary); color: var(--color-text-main);">
                    <option value="1">Option 1: New Lead / Website Inflow (UI/UX + Gen-AI Reconnection & Upgrade)</option>
                    <option value="2">Option 2: Raw Lead / Cold Outreach (UI/UX Career Pitch with Gen-AI)</option>
                </select>
            </div>

            <!-- Message Preview & Editor -->
            <div class="form-group" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary);">Message Content (Editable):</label>
                    <button type="button" onclick="copyGlobalWaMessage()" class="btn btn-sm btn-ghost" style="padding: 2px 8px; font-size: 11px; color: var(--color-primary);" id="globalWaCopyBtn">
                        <i class="fa-regular fa-copy"></i> Copy Text
                    </button>
                </div>
                <textarea id="globalWaMessageText" class="form-textarea" rows="10" style="width: 100%; font-family: inherit; font-size: 12.5px; line-height: 1.5; padding: 12px; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); resize: vertical;" oninput="updateGlobalWaUrl()"></textarea>
            </div>

            <!-- Auto-Update Lead Status Option -->
            <div class="form-group" style="margin-bottom: 14px; background: rgba(2, 132, 199, 0.08); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid rgba(2, 132, 199, 0.25); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-arrows-rotate" style="color: #0284c7; font-size: 14px;"></i>
                    <label style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); margin: 0;">Auto-Update Status to:</label>
                </div>
                <select id="globalWaStatusUpdateSelect" class="form-select" style="font-size: 12.5px; font-weight: 600; padding: 5px 12px; width: auto; background: var(--color-bg-secondary); color: var(--color-text-main); border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                    <option value="interested" selected>➔ Mark as Interested (Contacted)</option>
                    <option value="keep">Keep Current Status</option>
                    <option value="enrolled">Mark as Enrolled</option>
                    <option value="uninterested">Mark as Uninterested</option>
                </select>
            </div>

            <!-- Footer Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; border-top: 1px solid var(--color-border); padding-top: 16px;">
                <button type="button" class="btn btn-secondary" onclick="closeGlobalWhatsAppModal()" style="padding: 8px 16px; font-size: 13px;">
                    Cancel
                </button>
                <div style="display: flex; gap: 8px;">
                    <a id="globalWaSendDirectBtn" href="#" target="_blank" onclick="onGlobalWaSendClick()" class="btn" style="background: #25D366; color: #ffffff; font-weight: 600; font-size: 13.5px; padding: 8px 18px; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35); text-decoration: none;">
                        <i class="fa-brands fa-whatsapp" style="font-size: 16px;"></i> Send on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.currentWaLead = {
    id: 0,
    name: '',
    phone: '',
    course: 'UI/UX Design',
    counsellor: <?php echo json_encode(getUserName() ?: 'Team'); ?>
};

function getTemplate1Content(name, counsellor, course) {
    const candidateName = name ? name.trim() : 'there';
    const courseTitle = course || 'UI/UX Design';
    return `Hi ${candidateName} 👋\n\n`
        + `I’m ${counsellor} from *Trivikra Tech*.\n\n`
        + `You had earlier shown interest in *${courseTitle}* with us. We’re reconnecting with our previous students/leads because we’ve recently updated our program with *Gen-AI & Agentic-AI tools*.\n\n`
        + `🎨 UI/UX Design + Gen-AI + Agentic-AI\n`
        + `💼 Industry Projects & Portfolio Building\n`
        + `🤖 Latest AI Tools & Workflows\n`
        + `🚀 Internship & Career Support\n\n`
        + `Are you currently looking to *start or upgrade your career in ${courseTitle}?*\n\n`
        + `Just reply *YES*, and I’ll share the updated course details with you. 🙂`;
}

function getTemplate2Content(name, counsellor, course) {
    const courseTitle = course || 'UI/UX Design';
    return `Hi 👋\n\n`
        + `This is ${counsellor} from *Trivikra Tech*.\n\n`
        + `Are you currently interested in building a career in *${courseTitle}*?\n\n`
        + `We’re currently offering a practical *${courseTitle} with Gen-AI & Agentic-AI* program designed to help students and professionals learn UI/UX along with the latest AI tools and industry workflows.\n\n`
        + `🎨 UI/UX Design\n`
        + `🤖 Gen-AI & Agentic-AI\n`
        + `💼 Live/Industry Projects\n`
        + `📁 Portfolio Building\n`
        + `🚀 Internship & Career Support\n\n`
        + `If you’re interested, just reply *“Interested”* and I’ll share the complete details with you. 😊`;
}

window.openWhatsAppModal = function(leadId, name, phone, defaultOption = 1, course = 'UI/UX Design') {
    window.currentWaLead.id = leadId || 0;
    window.currentWaLead.name = name || '';
    window.currentWaLead.phone = String(phone || '').replace(/[^0-9]/g, '');
    window.currentWaLead.course = course || 'UI/UX Design';

    const nameEl = document.getElementById('globalWaRecipientName');
    const phoneEl = document.getElementById('globalWaRecipientPhone');
    if (nameEl) nameEl.textContent = window.currentWaLead.name || 'Candidate';
    if (phoneEl) phoneEl.textContent = window.currentWaLead.phone ? ('+91 ' + window.currentWaLead.phone) : '—';
    
    const select = document.getElementById('globalWaTemplateSelect');
    if (select) select.value = String(defaultOption || 1);

    const statusSelect = document.getElementById('globalWaStatusUpdateSelect');
    if (statusSelect) statusSelect.value = 'interested';

    onGlobalWaTemplateChange();

    const modal = document.getElementById('globalWhatsAppModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
};

window.closeGlobalWhatsAppModal = function() {
    const modal = document.getElementById('globalWhatsAppModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
};

window.onGlobalWaTemplateChange = function() {
    const select = document.getElementById('globalWaTemplateSelect');
    const opt = select ? select.value : '1';
    const msgBox = document.getElementById('globalWaMessageText');
    
    if (msgBox) {
        if (opt === '2') {
            msgBox.value = getTemplate2Content(window.currentWaLead.name, window.currentWaLead.counsellor, window.currentWaLead.course);
        } else {
            msgBox.value = getTemplate1Content(window.currentWaLead.name, window.currentWaLead.counsellor, window.currentWaLead.course);
        }
    }
    
    updateGlobalWaUrl();
};

window.updateGlobalWaUrl = function() {
    let cleanPhone = window.currentWaLead.phone;
    if (cleanPhone.length === 10) {
        cleanPhone = '91' + cleanPhone;
    }
    const msgBox = document.getElementById('globalWaMessageText');
    const message = msgBox ? msgBox.value : '';
    const encoded = encodeURIComponent(message);
    const url = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encoded}`;
    const btn = document.getElementById('globalWaSendDirectBtn');
    if (btn) btn.href = url;
};

function copyGlobalWaMessage() {
    const msg = document.getElementById('globalWaMessageText').value;
    navigator.clipboard.writeText(msg).then(() => {
        const btn = document.getElementById('globalWaCopyBtn');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy Text';
        }, 2000);
    });
}

async function onGlobalWaSendClick() {
    const leadId = window.currentWaLead.id;
    if (leadId > 0) {
        const opt = document.getElementById('globalWaTemplateSelect').value;
        const optLabel = (opt === '2') ? 'Option 2 (Raw Lead Outreach)' : 'Option 1 (New Lead Reconnection)';
        const statusChoice = document.getElementById('globalWaStatusUpdateSelect').value;
        const newStatus = (statusChoice !== 'keep') ? statusChoice : '';
        
        try {
            const formData = new FormData();
            formData.append('action', 'log_whatsapp_outreach');
            formData.append('lead_id', leadId);
            formData.append('activity_type', 'whatsapp');
            formData.append('remarks', `Sent WhatsApp Message using ${optLabel}`);
            if (newStatus) {
                formData.append('new_status', newStatus);
            }
            
            const res = await fetch((window.BASE_URL || '') + '/api/leads.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success && data.status_badge) {
                // Update all matching status cells in the table/view dynamically without reload
                document.querySelectorAll(`[data-lead-status-id="${leadId}"]`).forEach(el => {
                    el.innerHTML = data.status_badge;
                });
                
                const singleStatusCell = document.getElementById(`status-cell-${leadId}`);
                if (singleStatusCell) singleStatusCell.innerHTML = data.status_badge;

                const dashboardStatusCell = document.getElementById(`lead-status-${leadId}`);
                if (dashboardStatusCell) dashboardStatusCell.innerHTML = data.status_badge;

                const freshStatusCell = document.getElementById(`fresh-status-${leadId}`);
                if (freshStatusCell) freshStatusCell.innerHTML = data.status_badge;
            }
        } catch (e) {
            console.error('Error logging WhatsApp outreach & updating status:', e);
        }
    }
    
    setTimeout(() => {
        closeGlobalWhatsAppModal();
    }, 300);
}

// Close when clicking outside
document.getElementById('globalWhatsAppModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeGlobalWhatsAppModal();
    }
});
</script>
