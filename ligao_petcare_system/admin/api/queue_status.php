<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/queue.php
// Purpose: Digital Patient Flow Whiteboard (Admin)
// ============================================================
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Whiteboard — Admin | Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f4f8; }

        .queue-header {
            background: linear-gradient(135deg, #0d9488, #0f766e);
            color: white;
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .queue-header h1 { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .queue-header .meta { font-size: 0.8rem; opacity: 0.85; }

        .board-wrap { padding: 24px; max-width: 1400px; margin: 0 auto; }

        .board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 900px) { .board { grid-template-columns: 1fr; } }

        .lane {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }

        .lane-header {
            padding: 14px 18px;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .lane-header .badge {
            background: rgba(255,255,255,0.3);
            border-radius: 99px;
            padding: 2px 12px;
            font-size: 0.85rem;
        }

        .lane[data-status="waiting"]     .lane-header { background: #64748b; color: white; }
        .lane[data-status="in_progress"] .lane-header { background: #d97706; color: white; }
        .lane[data-status="done"]        .lane-header { background: #16a34a; color: white; }

        .lane-body { padding: 14px; flex: 1; overflow-y: auto; }

        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #94a3b8;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s, transform 0.15s;
            animation: fadeIn 0.25s ease;
        }
        .card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); transform: translateY(-1px); }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        .lane[data-status="in_progress"] .card { border-left-color: #d97706; }
        .lane[data-status="done"]        .card { border-left-color: #16a34a; opacity: 0.72; }

        .card-top { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; }
        .q-num {
            font-size: 1.6rem; font-weight: 900; color: #0d9488;
            min-width: 44px; text-align: center; line-height: 1;
        }
        .card-info { flex: 1; }
        .card-name  { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
        .card-sub   { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
        .card-time  { font-size: 0.77rem; color: #94a3b8; margin-top: 2px; }

        .card-actions {
            display: flex; gap: 6px; flex-wrap: wrap;
            margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;
        }

        .btn-sm {
            padding: 5px 13px; border: none; border-radius: 6px;
            font-size: 0.75rem; font-weight: 700; cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
        }
        .btn-sm:hover { opacity: 0.88; transform: scale(1.02); }
        .btn-progress { background: #d97706; color: white; }
        .btn-done     { background: #16a34a; color: white; }
        .btn-back     { background: #e2e8f0; color: #475569; }

        .lane-empty {
            text-align: center; padding: 50px 20px;
            color: #cbd5e1; font-size: 0.9rem;
        }
        .lane-empty .empty-icon { font-size: 2rem; display: block; margin-bottom: 8px; }

        #toast {
            position: fixed; bottom: 28px; right: 28px;
            background: #1e293b; color: white;
            padding: 12px 22px; border-radius: 10px;
            font-size: 0.88rem; font-weight: 600;
            opacity: 0; transition: opacity 0.3s;
            z-index: 9999; pointer-events: none;
        }
        #toast.show { opacity: 1; }
    </style>
</head>
<body>

<div class="queue-header">
    <h1>🏥 Patient Queue Whiteboard</h1>
    <div class="meta">
        <?= date('l, F j, Y') ?> &nbsp;|&nbsp;
        Auto-refreshes every 15s &nbsp;|&nbsp;
        <span id="last-updated">Loading…</span>
    </div>
</div>

<div class="board-wrap">
    <div class="board">
        <div class="lane" data-status="waiting">
            <div class="lane-header">
                ⏳ Waiting
                <span class="badge" id="count-waiting">0</span>
            </div>
            <div class="lane-body" id="lane-waiting">
                <div class="lane-empty"><span class="empty-icon">🐾</span>No patients waiting</div>
            </div>
        </div>

        <div class="lane" data-status="in_progress">
            <div class="lane-header">
                🔵 In Progress
                <span class="badge" id="count-in_progress">0</span>
            </div>
            <div class="lane-body" id="lane-in_progress">
                <div class="lane-empty"><span class="empty-icon">🩺</span>None in progress</div>
            </div>
        </div>

        <div class="lane" data-status="done">
            <div class="lane-header">
                ✅ Done Today
                <span class="badge" id="count-done">0</span>
            </div>
            <div class="lane-body" id="lane-done">
                <div class="lane-empty"><span class="empty-icon">🎉</span>None completed yet</div>
            </div>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
const POLL_MS = 15000;

function toast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => t.classList.remove('show'), 2800);
}

function esc(str) {
    if (str === null || str === undefined) return '—';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtTime(ts) {
    if (!ts || ts === '0000-00-00 00:00:00' || ts === null) return '';
    const d = new Date(ts.replace(' ','T'));
    return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
}

function buildCard(e) {
    let actions = '';
    if (e.status === 'waiting') {
        actions = `<button class="btn-sm btn-progress" onclick="setStatus(${e.id},'in_progress')">▶ Call Patient</button>`;
    } else if (e.status === 'in_progress') {
        actions = `
            <button class="btn-sm btn-done" onclick="setStatus(${e.id},'done')">✓ Mark Done</button>
            <button class="btn-sm btn-back" onclick="setStatus(${e.id},'waiting')">← Back to Waiting</button>`;
    } else {
        actions = `<button class="btn-sm btn-back" onclick="setStatus(${e.id},'in_progress')">↩ Undo</button>`;
    }

    const calledLine = e.called_at ? `<div class="card-time">📞 Called: ${fmtTime(e.called_at)}</div>` : '';
    const doneLine   = e.done_at   ? `<div class="card-time">✅ Done: ${fmtTime(e.done_at)}</div>`     : '';

    return `<div class="card" id="card-${e.id}">
        <div class="card-top">
            <div class="q-num">#${esc(e.queue_number)}</div>
            <div class="card-info">
                <div class="card-name">${esc(e.patient_name)}</div>
                <div class="card-sub">🐾 ${esc(e.pet_name)} &nbsp;·&nbsp; ${esc(e.service_name)}</div>
                <div class="card-time">⏰ Appt: ${esc(e.appt_time)}</div>
                ${calledLine}${doneLine}
            </div>
        </div>
        <div class="card-actions">${actions}</div>
    </div>`;
}

function renderBoard(entries) {
    const lanes = { waiting: [], in_progress: [], done: [] };
    entries.forEach(e => { if (lanes[e.status]) lanes[e.status].push(e); });

    const empties = {
        waiting:     '<span class="empty-icon">🐾</span>No patients waiting',
        in_progress: '<span class="empty-icon">🩺</span>None in progress',
        done:        '<span class="empty-icon">🎉</span>None completed yet'
    };

    ['waiting','in_progress','done'].forEach(status => {
        const body  = document.getElementById('lane-' + status);
        const count = document.getElementById('count-' + status);
        count.textContent = lanes[status].length;
        body.innerHTML = lanes[status].length
            ? lanes[status].map(buildCard).join('')
            : `<div class="lane-empty">${empties[status]}</div>`;
    });

    document.getElementById('last-updated').textContent =
        'Updated ' + new Date().toLocaleTimeString();
}

async function fetchQueue() {
    try {
        const r = await fetch('queue_ajax.php');
        const d = await r.json();
        if (d.success) renderBoard(d.entries);
        else console.error('Queue error:', d.message);
    } catch(err) { console.error('Fetch error:', err); }
}

async function setStatus(id, status) {
    try {
        const r = await fetch('queue_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id, status})
        });
        const d = await r.json();
        if (d.success) { toast('✓ Status updated!'); fetchQueue(); }
        else toast('Error: ' + (d.message || 'Unknown'));
    } catch(err) { toast('Network error'); }
}

fetchQueue();
setInterval(fetchQueue, POLL_MS);
</script>
</body>
</html>