<!-- ═══════════════════════════════════════════════════════════
     Ligao Petcare & Veterinary Clinic
     File: includes/whiteboard-widget.php
     Purpose: Digital Whiteboard — Compact Dashboard Card

     VISIBILITY RULES:
       Admin → full name + pet + owner on every card
               + Arrived/Waiting/Ongoing/Done action buttons
       User  → sees ALL today's appointments (waiting-room view)
               Every card shows: #ID · time · service · queue pill
               Their OWN card is highlighted with a "Your appt" badge
               Queue buttons are hidden for users (read-only)
     ═══════════════════════════════════════════════════════════ -->




     

<style>

/* ── Done drawer responsive fix ─────────────────────────────── */
.wb-done-inner {
    max-height: 320px;
    overflow-y: auto;
    padding: 6px 12px 10px;
}
.wb-done-inner .wb-card {
    opacity: .82;
}
.wb-done-empty {
    text-align: center;
    padding: 14px;
    color: #9ca3af;
    font-size: 11px;
}
@media (max-width: 480px) {
    .wb-done-inner {
        max-height: 220px;
    }
    .wb-done-toggle {
        padding: 8px 10px;
        font-size: 10px;
    }
}

/* ── Panel shell ─────────────────────────────────────────────── */
#wb-panel {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(13,148,136,.10);
    border: 1.5px solid #e0f2f1;
    overflow: hidden;
    margin: 0;
    font-family: 'Segoe UI', system-ui, sans-serif;
    font-size: 13px;
    width: 100%;
    display: block;
    position: relative;
}
/* ── Header ─────────────────────────────────────────────────── */
.wb-header {
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wb-header-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.wb-live-dot {
    width: 7px; height: 7px; flex-shrink: 0;
    background: #4ade80; border-radius: 50%;
    animation: wb-pulse 1.6s ease-in-out infinite;
}
@keyframes wb-pulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.5; transform:scale(1.4); }
}
.wb-title    { font-size: 13px; font-weight: 700; white-space: nowrap; }
.wb-subtitle { font-size: 10px; opacity: .78; }

.wb-clock-wrap { text-align: right; flex-shrink: 0; }
.wb-clock    { font-size: 14px; font-weight: 800; letter-spacing: .8px; font-variant-numeric: tabular-nums; }
.wb-date-str { font-size: 9px; opacity: .72; }

.wb-refresh-btn {
    background: rgba(255,255,255,.18);
    border: 1px solid rgba(255,255,255,.28);
    color: #fff; border-radius: 6px;
    padding: 4px 8px; font-size: 11px;
    cursor: pointer; display: flex; align-items: center; gap: 4px;
    transition: background .15s; flex-shrink: 0;
    white-space: nowrap;
}
.wb-refresh-btn:hover { background: rgba(255,255,255,.28); }
.wb-refresh-btn.spinning .wb-refresh-icon { animation: wb-spin .6s linear infinite; }
@keyframes wb-spin { to { transform: rotate(360deg); } }
.wb-refresh-icon { display: inline-block; font-size: 13px; }
.wb-refresh-label { display: inline; }

@media (max-width: 480px) {
    .wb-refresh-label { display: none; }
    .wb-refresh-btn { padding: 4px 7px; }
    .wb-header { gap: 6px; padding: 8px 10px; }
    .wb-clock { font-size: 12px; letter-spacing: .4px; }
    .wb-title { font-size: 12px; }
    .wb-subtitle { display: none; }
}
/* ── Stat Row ────────────────────────────────────────────────── */
.wb-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-bottom: 1px solid #e0f2f1;
}
.wb-stat {
    padding: 8px 6px;
    text-align: center;
    border-right: 1px solid #e0f2f1;
    transition: background .15s, box-shadow .15s;
    cursor: pointer;
    user-select: none;
}
.wb-stat:last-child { border-right: none; }
.wb-stat:hover { background: #f0fdfa; }
.wb-stat.active-stat { background: #f0fdfa; box-shadow: inset 0 -3px 0 currentColor; }
.wb-stat.pending.active-stat   { box-shadow: inset 0 -3px 0 #f59e0b; }
.wb-stat.confirmed.active-stat { box-shadow: inset 0 -3px 0 #3b82f6; }
.wb-stat.completed.active-stat { box-shadow: inset 0 -3px 0 #10b981; }
.wb-stat.cancelled.active-stat { box-shadow: inset 0 -3px 0 #ef4444; }
/* ── Body ────────────────────────────────────────────────────── */
.wb-body { padding: 10px 12px; }

.wb-filters {
    display: flex; gap: 4px; margin-bottom: 8px; flex-wrap: wrap;
}
.wb-filter-btn {
    font-size: 10px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px;
    border: 1.5px solid #e5e7eb;
    background: #fff; color: #6b7280;
    cursor: pointer; transition: all .15s;
}
.wb-filter-btn:hover,
.wb-filter-btn.active { background: #25c2e9; color: #fff; border-color: #0d9488; }

/* ── Queue cards ─────────────────────────────────────────────── */
.wb-flow { display: flex; flex-direction: column; gap: 6px; }

.wb-card {
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    transition: box-shadow .15s, border-color .15s;
    position: relative; overflow: hidden;
}
.wb-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.09); border-color: #0d9488; }

/* Own appointment highlight (user view) */
.wb-card.is-mine {
    border-color: #0d9488;
    background: #f0fdfa;
    box-shadow: 0 0 0 2px rgba(13,148,136,.15);
}

/* Left accent stripe — appointment status */
.wb-card::before {
    content: ''; position: absolute;
    left: 0; top: 0; bottom: 0; width: 4px;
    border-radius: 10px 0 0 10px;
}
.wb-card.status-pending::before   { background: #f59e0b; }
.wb-card.status-confirmed::before { background: #3b82f6; }
.wb-card.status-completed::before { background: #10b981; }
.wb-card.status-cancelled::before { background: #ef4444; }

/* ── Card top row ────────────────────────────────────────────── */
.wb-card-main {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px 6px 14px;
}
.wb-card-time { min-width: 44px; text-align: center; flex-shrink: 0; }
.wb-time-val  { font-size: 12px; font-weight: 800; color: #0f766e; font-variant-numeric: tabular-nums; line-height: 1.1; }
.wb-time-ampm { font-size: 9px; color: #9ca3af; font-weight: 600; }

.wb-card-body { flex: 1; min-width: 0; }
.wb-card-top  { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.wb-pet-name  { font-size: 12px; font-weight: 700; color: #111827;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.wb-owner     { font-size: 10px; color: #9ca3af;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Anonymous ID (user view) */
.wb-appt-id {
    font-size: 12px; font-weight: 800;
    color: #0f766e; letter-spacing: .4px;
}

/* "Your appointment" badge — own card only */
.wb-mine-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 9px; font-weight: 800; letter-spacing: .3px;
    padding: 2px 7px; border-radius: 20px;
    background: #0d9488; color: #fff;
    flex-shrink: 0;
}

/* Other user label */
.wb-other-note {
    font-size: 10px; color: #9ca3af; font-style: italic;
}

.wb-badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 3px; }
.wb-badge  {
    font-size: 9px; font-weight: 700;
    padding: 1px 6px; border-radius: 20px; letter-spacing: .2px;
}
.wb-badge.service           { background: #e0f2f1; color: #0f766e; }
.wb-badge.type-clinic       { background: #eff6ff; color: #2563eb; }
.wb-badge.type-home_service { background: #fef3c7; color: #92400e; }
.wb-badge.st-pending   { background: #fef9c3; color: #854d0e; }
.wb-badge.st-confirmed { background: #dbeafe; color: #1e40af; }
.wb-badge.st-completed { background: #d1fae5; color: #065f46; }
.wb-badge.st-cancelled { background: #fee2e2; color: #991b1b; }

.wb-species-icon { font-size: 18px; flex-shrink: 0; }

/* ── Queue status pill (read-only, shown to everyone) ────────── */
.wb-queue-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 9px; font-weight: 800; letter-spacing: .4px;
    padding: 2px 8px; border-radius: 20px;
    text-transform: uppercase;
    flex-shrink: 0;
}
.wb-queue-pill.qs-none     { background: #f3f4f6; color: #9ca3af; }
.wb-queue-pill.qs-arrived  { background: #ede9fe; color: #6d28d9; }
.wb-queue-pill.qs-waiting  { background: #fef9c3; color: #92400e; }
.wb-queue-pill.qs-ongoing  { background: #dbeafe; color: #1d4ed8; }
.wb-queue-pill.qs-done     { background: #d1fae5; color: #065f46; }

/* ── Queue action buttons (ADMIN ONLY) ───────────────────────── */
.wb-queue-actions {
    display: flex; gap: 4px; flex-wrap: wrap;
    padding: 7px 10px 8px 14px;
    border-top: 1px dashed #f0f0f0;
}

.wb-qbtn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700;
    padding: 4px 10px; border-radius: 6px;
    border: 1.5px solid transparent;
    cursor: pointer; transition: all .15s;
    white-space: nowrap; line-height: 1;
    font-family: 'Segoe UI', system-ui, sans-serif;
}
.wb-qbtn:hover  { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,.12); }
.wb-qbtn:active { transform: translateY(0); }

.wb-qbtn.arrived              { background: #ede9fe; color: #6d28d9; border-color: #c4b5fd; }
.wb-qbtn.arrived:hover        { background: #ddd6fe; }
.wb-qbtn.arrived.active-qs   { background: #7c3aed; color: #fff; border-color: #7c3aed; box-shadow: 0 2px 8px rgba(124,58,237,.35); }

.wb-qbtn.waiting              { background: #fef9c3; color: #854d0e; border-color: #fde68a; }
.wb-qbtn.waiting:hover        { background: #fef08a; }
.wb-qbtn.waiting.active-qs   { background: #d97706; color: #fff; border-color: #d97706; box-shadow: 0 2px 8px rgba(217,119,6,.35); }

.wb-qbtn.ongoing              { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
.wb-qbtn.ongoing:hover        { background: #bfdbfe; }
.wb-qbtn.ongoing.active-qs   { background: #2563eb; color: #fff; border-color: #2563eb; box-shadow: 0 2px 8px rgba(37,99,235,.35); }

.wb-qbtn.done                 { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
.wb-qbtn.done:hover           { background: #a7f3d0; }
.wb-qbtn.done.active-qs      { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 8px rgba(5,150,105,.35); }

.wb-qbtn.saving { opacity: .65; pointer-events: none; }
.wb-qbtn .wb-qbtn-spin {
    display: none; width: 10px; height: 10px;
    border: 2px solid currentColor; border-top-color: transparent;
    border-radius: 50%; animation: wb-spin .55s linear infinite;
}
.wb-qbtn.saving .wb-qbtn-spin { display: inline-block; }

/* ── Home service multi-pet sub-rows ─────────────────────────── */
.wb-home-pets { margin-top: 4px; display: flex; flex-direction: column; gap: 2px; }
.wb-home-pet-row {
    font-size: 10px; color: #6b7280;
    display: flex; gap: 4px; align-items: center;
}
.wb-home-pet-row::before { content: '•'; color: #0d9488; font-weight: 900; }

/* ── Empty / error ────────────────────────────────────────────── */
.wb-empty { text-align: center; padding: 20px 12px; color: #9ca3af; }
.wb-empty-icon { font-size: 28px; margin-bottom: 5px; }
.wb-empty-text { font-size: 12px; font-weight: 600; }
.wb-empty-sub  { font-size: 10px; margin-top: 2px; }

/* ── Show-more toggle ────────────────────────────────────────── */
.wb-toggle {
    width: 100%; background: none; border: none;
    border-top: 1px dashed #e5e7eb;
    color: #0d9488; font-size: 11px; font-weight: 700;
    cursor: pointer; padding: 6px; margin-top: 4px;
    text-align: center; transition: background .15s;
}
.wb-toggle:hover { background: #f0fdfa; }

/* ── Footer ──────────────────────────────────────────────────── */
.wb-footer {
    border-top: 1px solid #e5e7eb;
    padding: 6px 12px;
    display: flex; align-items: center; justify-content: space-between;
    background: #f9fafb; font-size: 10px; color: #9ca3af;
    flex-wrap: wrap; gap: 4px;
}
.wb-upcoming { font-weight: 600; color: #0f766e; }

/* ── Done drawer (admin only) ────────────────────────────────── */
#wb-done-drawer {
    border-top: 1px solid #e0f2f1;
}
.wb-done-toggle {
    width: 100%; background: none; border: none;
    border-top: none;
    color: #0f766e; font-size: 11px; font-weight: 700;
    cursor: pointer; padding: 8px 14px; margin-top: 0;
    text-align: left; transition: background .15s;
    display: flex; align-items: center; justify-content: space-between;
    font-family: 'Segoe UI', system-ui, sans-serif;
}
.wb-done-toggle:hover { background: #f0fdfa; }
.wb-done-count-pill {
    background: #d1fae5; color: #065f46;
    font-size: 10px; font-weight: 700;
    padding: 1px 8px; border-radius: 20px;
    margin-left: 6px;
}
.wb-done-chevron { font-size: 13px; transition: transform .2s; display: inline-block; }
.wb-done-chevron.open { transform: rotate(180deg); }
.wb-done-inner { padding: 6px 12px 10px; }
.wb-done-header-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: #9ca3af; margin-bottom: 6px;
}

/* ── Toast ────────────────────────────────────────────────────── */
#wb-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 99999;
    background: #111827; color: #fff;
    padding: 10px 16px; border-radius: 8px;
    font-size: 12px; font-weight: 600;
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    display: flex; align-items: center; gap: 8px;
    opacity: 0; transform: translateY(8px);
    transition: opacity .2s, transform .2s;
    pointer-events: none;
    font-family: 'Segoe UI', system-ui, sans-serif;
    max-width: 280px;
}
#wb-toast.show    { opacity: 1; transform: translateY(0); }
#wb-toast.success { border-left: 3px solid #10b981; }
#wb-toast.error   { border-left: 3px solid #ef4444; }
</style>

<!-- ── Markup ────────────────────────────────────────────────── -->
<div id="wb-panel">

    <div class="wb-header">
        <div class="wb-header-left">
            <div class="wb-live-dot"></div>
            <div>
                <div class="wb-title">🏥 Patient Whiteboard</div>
                <div class="wb-subtitle">Live appointment flow</div>
            </div>
        </div>
        <div class="wb-clock-wrap">
            <div class="wb-clock" id="wb-clock">--:--</div>
            <div class="wb-date-str" id="wb-date-str">–</div>
        </div>
       <button class="wb-refresh-btn" onclick="wbRefresh()" id="wb-refresh-btn">
            <span class="wb-refresh-icon" id="wb-refresh-icon">↻</span>
            <span class="wb-refresh-label">Refresh</span>
        </button>
    </div>

    <div class="wb-stats">
        <div class="wb-stat pending" onclick="wbStatClick('pending')" title="Show pending appointments">
            <div class="wb-stat-num" id="wb-cnt-pending">–</div>
            <div class="wb-stat-label">⏳ Pending</div>
        </div>
        <div class="wb-stat confirmed" onclick="wbStatClick('confirmed')" title="Show confirmed appointments">
            <div class="wb-stat-num" id="wb-cnt-confirmed">–</div>
            <div class="wb-stat-label">✅ Confirmed</div>
        </div>
        <div class="wb-stat completed" onclick="wbStatClick('completed')" title="Show completed appointments">
            <div class="wb-stat-num" id="wb-cnt-completed">–</div>
            <div class="wb-stat-label">🎉 Done</div>
        </div>
        <div class="wb-stat cancelled" onclick="wbStatClick('cancelled')" title="Show cancelled appointments">
            <div class="wb-stat-num" id="wb-cnt-cancelled">–</div>
            <div class="wb-stat-label">❌ Cancelled</div>
        </div>
    </div>
    <div class="wb-body">
        <div class="wb-filters">
            <button class="wb-filter-btn active" onclick="wbFilter('all',this)">All</button>
            <button class="wb-filter-btn" onclick="wbFilter('pending',this)">⏳ Pending</button>
            <button class="wb-filter-btn" onclick="wbFilter('confirmed',this)">✅ Confirmed</button>
            <button class="wb-filter-btn" onclick="wbFilter('completed',this)">🎉 Done</button>
            <button class="wb-filter-btn" onclick="wbFilter('clinic',this)">🏥 Clinic</button>
            <button class="wb-filter-btn" onclick="wbFilter('home_service',this)">🏠 Home</button>
        </div>
        <div class="wb-flow" id="wb-flow">
            <div class="wb-empty">
                <div class="wb-empty-icon">🔄</div>
                <div class="wb-empty-text">Loading…</div>
            </div>
        </div>
    </div>

    <!-- Done drawer — admin only, hidden until there are "done" cards -->
    <div id="wb-done-drawer" style="display:none;">
        <button class="wb-done-toggle" onclick="wbToggleDone(this)">
            <span style="display:flex;align-items:center;gap:6px;">
                ✅ Done today
                <span class="wb-done-count-pill" id="wb-done-count">0 patients</span>
            </span>
            <span class="wb-done-chevron" id="wb-done-chevron">&#8964;</span>
        </button>
        <div id="wb-done-inner" class="wb-done-inner" style="display:none;">
            <!-- Populated by wbRenderDone() -->
        </div>
    </div>

    <div class="wb-footer">
        <span>Auto-refreshes every 60s · <span id="wb-last-updated">–</span></span>
        <span class="wb-upcoming">📅 <span id="wb-upcoming-7d">–</span> upcoming (7d)</span>
    </div>

<div id="wb-toast"></div>

<script>
(function () {
    /* ── Config ─────────────────────────────────────────────── */
    var WB_ROOT = (function() {
        var p = window.location.pathname;
        return (p.indexOf('/admin/') !== -1 || p.indexOf('/user/') !== -1) ? '../' : '/';
    })();
    var WB_API    = WB_ROOT + 'whiteboard-api.php';
    var WB_UPDATE = WB_ROOT + 'whiteboard-queue-update.php';

    var wbData       = [];
    var wbFilter_    = 'all';
    var wbExpanded   = false;
    var wbMaxVisible = 5;
    var wbIsAdmin    = false;

    /* ── Queue meta ─────────────────────────────────────────── */
    var QS_META = {
        none:    { label: '—',       icon: '',   cls: 'qs-none'    },
        arrived: { label: 'Arrived', icon: '👋', cls: 'qs-arrived' },
        waiting: { label: 'Waiting', icon: '⏳', cls: 'qs-waiting' },
        ongoing: { label: 'Ongoing', icon: '🔵', cls: 'qs-ongoing' },
        done:    { label: 'Done',    icon: '✅', cls: 'qs-done'    },
    };

    var QB_DEF = [
        { key: 'arrived', icon: '👋', label: 'Arrived'  },
        { key: 'waiting', icon: '⏳', label: 'Waiting'  },
        { key: 'ongoing', icon: '🔵', label: 'On-going' },
        { key: 'done',    icon: '✅', label: 'Done'     },
    ];

    /* ── Clock ──────────────────────────────────────────────── */
    function wbTick() {
        var n  = new Date();
        var el = document.getElementById('wb-clock');
        if (el) el.textContent = pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
        var D  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var M  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var ds = document.getElementById('wb-date-str');
        if (ds) ds.textContent = D[n.getDay()]+', '+M[n.getMonth()]+' '+n.getDate();
    }
    setInterval(wbTick, 1000); wbTick();

    /* ── Fetch ──────────────────────────────────────────────── */
    function wbFetch() {
        var btn = document.getElementById('wb-refresh-btn');
        if (btn) btn.classList.add('spinning');
        fetch(WB_API, { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (btn) btn.classList.remove('spinning');
                if (!d.success) { wbErr(); return; }
                wbData    = d.appointments || [];
                wbIsAdmin = d.is_admin || false;
                var c = d.counts;
                setText('wb-cnt-pending',   c.pending);
                setText('wb-cnt-confirmed', c.confirmed);
                setText('wb-cnt-completed', c.completed);
                setText('wb-cnt-cancelled', c.cancelled);
                setText('wb-upcoming-7d',   d.upcoming_7d);
                var n = new Date();
                setText('wb-last-updated', 'Updated '+pad(n.getHours())+':'+pad(n.getMinutes()));
                wbRender();
            })
            .catch(function(){ if (btn) btn.classList.remove('spinning'); wbErr(); });
    }

    /* ── Queue update (admin only) ──────────────────────────── */
    function wbSetQueue(apptId, newQs, btnEl) {
        wbData.forEach(function(a){ if (a.id === apptId) a.queue_status = newQs; });
        wbRender();
        if (btnEl) btnEl.classList.add('saving');
        fetch(WB_UPDATE, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ appt_id: apptId, queue_status: newQs }),
            cache:   'no-store',
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (btnEl) btnEl.classList.remove('saving');
            if (d.success) {
                var m = QS_META[newQs] || {};
                wbToast((m.icon||'') + ' Status → <strong>'+(m.label||newQs)+'</strong>', 'success');
            } else {
                wbFetch();
                wbToast('⚠️ Could not update status', 'error');
            }
        })
        .catch(function(){
            if (btnEl) btnEl.classList.remove('saving');
            wbFetch();
            wbToast('⚠️ Network error', 'error');
        });
    }

    /* ── Render ─────────────────────────────────────────────── */
    function wbRender() {
        var f = wbFilter_;
     var rows = wbData.filter(function(a){
    // "completed/done" cards only show when explicitly filtering for them
    if (a.queue_status === 'done' || a.status === 'completed') {
        return f === 'completed'; // only show in Done filter, never in All/Pending/Confirmed/etc.
    }
    if (f === 'all')          return true;
    if (f === 'clinic')       return a.appointment_type === 'clinic';
    if (f === 'home_service') return a.appointment_type === 'home_service';
    return a.status === f;
});

        var box = document.getElementById('wb-flow');
        if (!box) return;

        if (!rows.length) {
            box.innerHTML =
                '<div class="wb-empty">'+
                    '<div class="wb-empty-icon">📋</div>'+
                    '<div class="wb-empty-text">No appointments today</div>'+
                    '<div class="wb-empty-sub">Queue is clear!</div>'+
                '</div>';
            return;
        }

        var visible = wbExpanded ? rows : rows.slice(0, wbMaxVisible);
        var html = '';

        visible.forEach(function(a){
            var t      = wbTime(a.appointment_time);
            var qs     = a.queue_status || 'none';
            var qsMeta = QS_META[qs] || QS_META.none;
            var isMine = !!a.is_mine;

            /* Species icon */
            var icon = a.species === 'cat' ? '🐱' : a.species === 'dog' ? '🐶' : '🐾';
            if (a.appointment_type === 'home_service' && !a.pet_name) icon = '🏠';

            var svc  = a.service_name || (a.appointment_type === 'home_service' ? 'Home Service' : '—');
            var type = a.appointment_type === 'home_service' ? 'Home' : 'Clinic';

            /* ── Identity row ────────────────────────────────── */
            var identityHtml = '';
            if (wbIsAdmin) {
                /* Admin: full name + pet */
                var pet   = a.pet_name || '(Multi-pet)';
                var owner = a.owner_name || '?';
                identityHtml =
                    '<div class="wb-card-top">'+
                        '<span class="wb-pet-name">'+esc(pet)+'</span>'+
                        '<span class="wb-owner">— '+esc(owner)+'</span>'+
                    '</div>';
            } else {
                /* User: anonymous ID + date + "Your appt" badge if own */
                identityHtml =
                    '<div class="wb-card-top">'+
                        '<span class="wb-appt-id">#'+esc(String(a.id))+'</span>'+
                        '<span style="font-size:10px;color:#6b7280;">'+esc(a.appointment_date)+'</span>'+
                        (isMine
                            ? '<span class="wb-mine-badge">⭐ Your appt</span>'
                            : '<span class="wb-other-note">Customer</span>')+
                    '</div>';
            }

            /* ── Home-service pets ───────────────────────────── */
            var hpHtml = '';
            if (a.home_pets && a.home_pets.length) {
                hpHtml = '<div class="wb-home-pets">';
                a.home_pets.forEach(function(hp){
                    if (wbIsAdmin) {
                        hpHtml += '<div class="wb-home-pet-row">'+
                            '<strong>'+esc(hp.pet_name||'')+'</strong>'+
                            (hp.service_name ? ' · '+esc(hp.service_name) : '')+
                        '</div>';
                    } else {
                        var hpIcon = hp.species==='cat'?'🐱':hp.species==='dog'?'🐶':'🐾';
                        hpHtml += '<div class="wb-home-pet-row">'+hpIcon+
                            (hp.service_name ? ' '+esc(hp.service_name) : '')+
                        '</div>';
                    }
                });
                hpHtml += '</div>';
            }

            /* ── Queue pill (everyone) ───────────────────────── */
            var pillHtml =
                '<span class="wb-queue-pill '+qsMeta.cls+'" title="Queue status">'+
                    (qsMeta.icon ? qsMeta.icon+' ' : '')+qsMeta.label+
                '</span>';

            /* ── Queue action buttons (ADMIN ONLY) ───────────── */
            var actionsHtml = '';
            if (wbIsAdmin) {
                actionsHtml = '<div class="wb-queue-actions">';
                QB_DEF.forEach(function(btn){
                    var isActive = (qs === btn.key);
                    actionsHtml +=
                        '<button class="wb-qbtn '+btn.key+(isActive?' active-qs':'')+'"'+
                            ' data-appt-id="'+a.id+'"'+
                            ' data-qs="'+btn.key+'"'+
                            ' onclick="wbQueueClick(this)"'+
                            ' title="Mark as '+btn.label+'">'+
                            '<span class="wb-qbtn-spin"></span>'+
                            btn.icon+' '+btn.label+
                        '</button>';
                });
                actionsHtml += '</div>';
            }

            html +=
                '<div class="wb-card status-'+a.status+(isMine && !wbIsAdmin ? ' is-mine' : '')+'">'+
                    '<div class="wb-card-main">'+
                        '<div class="wb-card-time">'+
                            '<div class="wb-time-val">'+t.time+'</div>'+
                            '<div class="wb-time-ampm">'+t.ampm+'</div>'+
                        '</div>'+
                        '<div class="wb-card-body">'+
                            identityHtml+
                            '<div class="wb-badges">'+
                                '<span class="wb-badge service">'+esc(svc)+'</span>'+
                                '<span class="wb-badge type-'+a.appointment_type+'">'+type+'</span>'+
                                '<span class="wb-badge st-'+a.status+'">'+cap(a.status)+'</span>'+
                            '</div>'+
                            hpHtml+
                        '</div>'+
                        pillHtml+
                        '<span class="wb-species-icon" style="margin-left:4px;">'+icon+'</span>'+
                    '</div>'+
                    actionsHtml+
                '</div>';
        });

        if (rows.length > wbMaxVisible) {
            var rem = rows.length - wbMaxVisible;
            html += wbExpanded
                ? '<button class="wb-toggle" onclick="wbToggleExpand()">▲ Show less</button>'
                : '<button class="wb-toggle" onclick="wbToggleExpand()">▼ '+rem+' more</button>';
        }

        box.innerHTML = html;
        wbRenderDone();

function wbRenderDone() {
    var drawer = document.getElementById('wb-done-drawer');
    if (!drawer) return;

    // Only shown to admin
    if (!wbIsAdmin) { drawer.style.display = 'none'; return; }

    var doneRows = wbData.filter(function(a){
        return a.queue_status === 'done' || a.status === 'completed';
    });

    if (!doneRows.length) {
        drawer.style.display = 'none';
        return;
    }

    drawer.style.display = '';
    var countEl = document.getElementById('wb-done-count');
    if (countEl) countEl.textContent = doneRows.length + ' patient' + (doneRows.length !== 1 ? 's' : '');

    var inner = document.getElementById('wb-done-inner');
    if (!inner) return;

    var html = '';
    doneRows.forEach(function(a) {
        var t    = wbTime(a.appointment_time);
        var svc  = a.service_name || a.svc_name || 'Service';
        var type = a.appointment_type === 'home_service' ? '🏠 Home' : '🏥 Clinic';
        var icon = a.species === 'cat' ? '🐱' : a.species === 'dog' ? '🐶' : '🐾';
        if (a.appointment_type === 'home_service' && !a.pet_name) icon = '🏠';

        // Home service sub-pets
        var hpHtml = '';
        if (a.home_pets && a.home_pets.length) {
            hpHtml = '<div class="wb-home-pets">';
            a.home_pets.forEach(function(hp){
                hpHtml += '<div class="wb-home-pet-row">' +
                    '<strong>' + esc(hp.pet_name || '') + '</strong>' +
                    (hp.service_name ? ' · ' + esc(hp.service_name) : '') +
                    '</div>';
            });
            hpHtml += '</div>';
        }

        html +=
            '<div class="wb-card status-' + a.status + ' wb-done-card">' +
                '<div class="wb-card-main">' +
                    '<div class="wb-card-time">' +
                        '<div class="wb-time-val">' + t.time + '</div>' +
                        '<div class="wb-time-ampm">' + t.ampm + '</div>' +
                    '</div>' +
                    '<div class="wb-card-body">' +
                        '<div class="wb-card-top">' +
                            '<span class="wb-pet-name">' + esc(a.pet_name || '(Multi-pet)') + '</span>' +
                            '<span class="wb-owner">— ' + esc(a.owner_name || '?') + '</span>' +
                        '</div>' +
                        '<div class="wb-badges">' +
                            '<span class="wb-badge service">' + esc(svc) + '</span>' +
                            '<span class="wb-badge type-' + a.appointment_type + '">' + type + '</span>' +
                            '<span class="wb-badge st-completed">Completed</span>' +
                        '</div>' +
                        hpHtml +
                    '</div>' +
                    '<span class="wb-queue-pill qs-done">✅ Done</span>' +
                    '<span class="wb-species-icon" style="margin-left:4px;">' + icon + '</span>' +
                '</div>' +
            '</div>';
    });

    if (!html) html = '<div class="wb-done-empty">No completed patients yet today.</div>';
    inner.innerHTML = html;
}
    }

    /* ── Helpers ────────────────────────────────────────────── */
    function wbTime(t) {
        if (!t) return { time: '--:--', ampm: '' };
        var p = t.split(':'), h = parseInt(p[0], 10), m = p[1] || '00';
        return { time: pad(h % 12 || 12) + ':' + m, ampm: h >= 12 ? 'PM' : 'AM' };
    }
    function pad(x) { return String(x).padStart(2, '0'); }
    function esc(s) {
        return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
    }
    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }
    function wbErr() {
        var box = document.getElementById('wb-flow');
        if (box) box.innerHTML =
            '<div class="wb-empty">'+
                '<div class="wb-empty-icon">⚠️</div>'+
                '<div class="wb-empty-text">Could not load data</div>'+
            '</div>';
    }

    /* ── Toast ──────────────────────────────────────────────── */
    var toastTimer;
    function wbToast(msg, type) {
        var el = document.getElementById('wb-toast');
        if (!el) return;
        el.innerHTML = msg;
        el.className = 'show ' + (type || '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function(){ el.classList.remove('show'); }, 2600);
    }

    /* ── Public API ─────────────────────────────────────────── */
    window.wbRefresh = function(){ wbFetch(); };

    window.wbFilter = function(f, btn){
        wbFilter_  = f;
        wbExpanded = false;
        document.querySelectorAll('.wb-filter-btn').forEach(function(b){ b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        // sync stat card highlight
        document.querySelectorAll('.wb-stat').forEach(function(s){ s.classList.remove('active-stat'); });
        if (f === 'pending')   document.querySelector('.wb-stat.pending')  ?.classList.add('active-stat');
        if (f === 'confirmed') document.querySelector('.wb-stat.confirmed')?.classList.add('active-stat');
        if (f === 'completed') document.querySelector('.wb-stat.completed')?.classList.add('active-stat');
        if (f === 'cancelled') document.querySelector('.wb-stat.cancelled')?.classList.add('active-stat');
        wbRender();
    };

    window.wbStatClick = function(status) {
        // If already active, clicking again resets to 'all'
        var isAlreadyActive = document.querySelector('.wb-stat.' + status)?.classList.contains('active-stat');
        var targetFilter = isAlreadyActive ? 'all' : status;

        // sync filter buttons
        document.querySelectorAll('.wb-filter-btn').forEach(function(b){ b.classList.remove('active'); });
        if (targetFilter !== 'all') {
            document.querySelectorAll('.wb-filter-btn').forEach(function(b){
                if (b.getAttribute('onclick') && b.getAttribute('onclick').indexOf("'"+targetFilter+"'") !== -1) {
                    b.classList.add('active');
                }
            });
        } else {
            // activate the "All" button
            var allBtn = document.querySelector('.wb-filter-btn');
            if (allBtn) allBtn.classList.add('active');
        }

        // sync stat highlights
        document.querySelectorAll('.wb-stat').forEach(function(s){ s.classList.remove('active-stat'); });
        if (targetFilter !== 'all') {
            document.querySelector('.wb-stat.' + status)?.classList.add('active-stat');
        }

// For completed — open done drawer AND show cards in main flow
        if (targetFilter === 'completed') {
            if (wbIsAdmin) {
                var inner = document.getElementById('wb-done-inner');
                var chev  = document.getElementById('wb-done-chevron');
                if (inner && inner.style.display === 'none') {
                    inner.style.display = 'block';
                    if (chev) chev.classList.add('open');
                }
            }
            // No scroll needed — cards now appear inline in main flow
        }

        wbFilter_  = targetFilter;
        wbExpanded = false;
        wbRender();
    };
   window.wbToggleExpand = function(){ wbExpanded = !wbExpanded; wbRender(); };

    window.wbToggleDone = function(btn) {
        var inner = document.getElementById('wb-done-inner');
        var chev  = document.getElementById('wb-done-chevron');
        var isOpen = inner.style.display !== 'none';
        inner.style.display = isOpen ? 'none' : 'block';
        chev.classList.toggle('open', !isOpen);
    };

    window.wbQueueClick = function(btnEl) {
        var apptId = parseInt(btnEl.getAttribute('data-appt-id'), 10);
        var newQs  = btnEl.getAttribute('data-qs');
        var curQs  = null;
        wbData.forEach(function(a){ if (a.id === apptId) curQs = a.queue_status || 'none'; });
        if (curQs === newQs) newQs = 'none'; // toggle off
        wbSetQueue(apptId, newQs, btnEl);
    };

    /* ── Boot ───────────────────────────────────────────────── */
    wbFetch();
    setInterval(wbFetch, 60000);
})();
</script>
<!-- ═══════════════════ END WHITEBOARD ════════════════════════ -->