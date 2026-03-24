// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: assets/js/main.js
// Purpose: Global JavaScript utilities for all pages
// ============================================================

// ── Auto-dismiss alerts after 4 seconds ─────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });

    // Activate sidebar link for current page
    const currentFile = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href === currentFile) {
            link.classList.add('active');
        }
    });

    // Close any open modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                m.classList.remove('open');
            });
        }
    });

});

// ── Generic modal open/close helpers ────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

// Close modal when clicking overlay background
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ── Confirm delete helper ────────────────────────────────────
function confirmDelete(message, url) {
    if (confirm(message || 'Are you sure you want to delete this record? This cannot be undone.')) {
        window.location.href = url;
    }
}

// ── Live table search ────────────────────────────────────────
// Usage: <input oninput="liveSearch(this, 'myTableId')">
function liveSearch(input, tableId) {
    const filter = input.value.toLowerCase();
    const table  = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// ── Preview image before upload ──────────────────────────────
// Usage: <input type="file" onchange="previewImage(this, 'previewId')">
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Format peso display ──────────────────────────────────────
function formatPeso(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── Show/hide loading spinner on form submit ─────────────────
function showLoading(btnId, text) {
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.disabled = true;
        btn.dataset.original = btn.textContent;
        btn.textContent = text || 'Loading...';
    }
}

// ── Highlight table row on click ─────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('table tbody tr').forEach(function (row) {
        row.style.cursor = 'default';
    });
});

// ── Auto-calculate age from date of birth ───────────────────
// Usage: <input type="date" id="dob" oninput="calcAge('dob','ageField')">
function calcAge(dobId, ageId) {
    const dob   = document.getElementById(dobId);
    const age   = document.getElementById(ageId);
    if (!dob || !age || !dob.value) return;
    const today    = new Date();
    const birthDate = new Date(dob.value);
    let years = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) years--;
    age.value = years >= 0 ? years : 0;
}

// ── Topbar notification bell click ──────────────────────────
function toggleNotifDropdown() {
    const panel = document.getElementById('notifPanel');
    if (panel) {
        panel.classList.toggle('open');
    }
}