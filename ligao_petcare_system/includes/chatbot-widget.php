<!-- ═══════════════════ CHATBOT WIDGET ═══════════════════ -->
<style>
#petey-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    font-size: 28px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(13,148,136,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: transform .2s, box-shadow .2s;
}
#petey-btn:hover { transform: scale(1.1); box-shadow: 0 6px 24px rgba(13,148,136,.6); }
#petey-btn .petey-badge {
    position: absolute;
    top: -4px; right: -4px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    width: 18px; height: 18px;
    display: none;
    align-items: center;
    justify-content: center;
}
#petey-window {
    position: fixed;
    bottom: 100px;
    right: 28px;
    width: 360px;
    max-height: 520px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 40px rgba(0,0,0,.18);
    display: flex;
    flex-direction: column;
    z-index: 9998;
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px) scale(.96);
    pointer-events: none;
    transition: opacity .25s, transform .25s;
}
#petey-window.open {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
}
.petey-head {
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.petey-head .petey-avatar { font-size: 26px; }
.petey-head .petey-info { flex: 1; }
.petey-head .petey-name { font-weight: 700; font-size: 15px; }
.petey-head .petey-sub  { font-size: 11px; opacity: .8; }
.petey-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    opacity: .8;
}
.petey-close:hover { opacity: 1; }
.petey-msgs {
    flex: 1;
    overflow-y: auto;
    padding: 16px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f0fdfa;
}
.petey-msgs::-webkit-scrollbar { width: 4px; }
.petey-msgs::-webkit-scrollbar-thumb { background: #99f6e4; border-radius: 4px; }
.petey-msg {
    max-width: 82%;
    padding: 9px 13px;
    border-radius: 14px;
    font-size: 13.5px;
    line-height: 1.55;
    word-break: break-word;
    white-space: pre-wrap;
}
.petey-msg.bot {
    background: #fff;
    color: #1f2937;
    border-bottom-left-radius: 4px;
    align-self: flex-start;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.petey-msg.user {
    background: #0d9488;
    color: #fff;
    border-bottom-right-radius: 4px;
    align-self: flex-end;
}
.petey-msg.typing { color: #6b7280; font-style: italic; font-size: 13px; }
.petey-quick-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}
.petey-quick-btn {
    background: #e0f7fa;
    border: 1px solid #0d9488;
    color: #0f766e;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: background .15s;
}
.petey-quick-btn:hover { background: #0d9488; color: #fff; }
.petey-bar {
    display: flex;
    gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
.petey-bar input {
    flex: 1;
    border: 1px solid #d1d5db;
    border-radius: 24px;
    padding: 8px 14px;
    font-size: 13.5px;
    outline: none;
    transition: border .2s;
}
.petey-bar input:focus { border-color: #0d9488; }
.petey-send {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: #0d9488;
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
    flex-shrink: 0;
}
.petey-send:hover { background: #0f766e; }
.petey-send:disabled { background: #9ca3af; cursor: not-allowed; }
@media (max-width: 420px) {
    #petey-window { width: calc(100vw - 24px); right: 12px; bottom: 88px; }
    #petey-btn    { bottom: 16px; right: 16px; }
}
</style>

<button id="petey-btn" onclick="peteyToggle()" title="Chat with Petey">
    🐾
    <span class="petey-badge" id="petey-badge">1</span>
</button>

<div id="petey-window">
    <div class="petey-head">
        <div class="petey-avatar">🐾</div>
        <div class="petey-info">
            <div class="petey-name">Petey — Ligao Petcare</div>
            <div class="petey-sub">Your pet health assistant</div>
        </div>
        <button class="petey-close" onclick="peteyToggle()">✕</button>
    </div>
    <div class="petey-msgs" id="petey-msgs"></div>
    <div class="petey-bar">
        <input
            type="text"
            id="petey-input"
            placeholder="Ask Petey anything…"
            onkeydown="if(event.key==='Enter') peteySend()"
            autocomplete="off"
        >
        <button class="petey-send" id="petey-send-btn" onclick="peteySend()">➤</button>
    </div>
</div>

<script>
(function () {
    var peteyHistory  = [];
    var peteyIsOpen   = false;
    var peteyOpened   = false;
    var peteyCounter  = 0;

    // ── Detect correct root path automatically ────────────────
    // Works whether page is at /admin/x.php or /user/x.php or /x.php
    var peteyRoot = (function() {
        var path = window.location.pathname;
        if (path.indexOf('/admin/') !== -1 || path.indexOf('/user/') !== -1) {
            return '../';
        }
        return '/';
    })();

    var QUICK = [
        '📋 Services & prices',
        '🏠 Home service info',
        '📅 How to book',
        '🛒 Products available',
        '🚨 Emergency signs',
        '📍 Clinic location'
    ];

    window.peteyToggle = function () {
        peteyIsOpen = !peteyIsOpen;
        document.getElementById('petey-window').classList.toggle('open', peteyIsOpen);
        document.getElementById('petey-badge').style.display = 'none';

        if (peteyIsOpen && !peteyOpened) {
            peteyOpened = true;
            peteyAppend('bot',
                "Hi there! 👋 I'm **Petey**, the virtual assistant for **Ligao Petcare & Veterinary Clinic**.\n\nHow can I help you and your furry friend today? 🐶🐱",
                QUICK
            );
        }
    };

    window.peteySend = function (text) {
        var input = document.getElementById('petey-input');
        var msg   = (text !== undefined ? text : input.value).trim();
        if (!msg) return;
        input.value = '';

        peteyAppend('user', msg);
        peteyHistory.push({ role: 'user', content: msg });

        var typingId = peteyAppend('bot', '…typing', null, true);
        document.getElementById('petey-send-btn').disabled = true;

        fetch(peteyRoot + 'chatbot.php', {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({
                message : msg,
                history : peteyHistory.slice(-10)
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            peteyRemove(typingId);
            document.getElementById('petey-send-btn').disabled = false;
            var reply = data.reply || data.error || 'Sorry, something went wrong.';
            // Clean up markdown asterisks Gemini sometimes returns
            reply = reply.replace(/\*\*(.*?)\*\*/g, '$1').replace(/\*(.*?)\*/g, '$1');
            peteyAppend('bot', reply);
            peteyHistory.push({ role: 'assistant', content: reply });
        })
        .catch(function() {
            peteyRemove(typingId);
            document.getElementById('petey-send-btn').disabled = false;
            peteyAppend('bot', '⚠️ Could not reach the assistant. Please check your connection.');
        });
    };

    function peteyAppend(role, text, quickReplies, isTyping) {
        var container = document.getElementById('petey-msgs');
        var id = 'pm-' + (++peteyCounter);

        var div = document.createElement('div');
        div.id = id;
        div.className = 'petey-msg ' + role + (isTyping ? ' typing' : '');

        // Render **bold** markdown
        var safe = text
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
        div.innerHTML = safe;

        if (quickReplies && quickReplies.length) {
            var row = document.createElement('div');
            row.className = 'petey-quick-btns';
            quickReplies.forEach(function(q) {
                var btn = document.createElement('button');
                btn.className   = 'petey-quick-btn';
                btn.textContent = q;
                btn.onclick     = function() { row.remove(); window.peteySend(q); };
                row.appendChild(btn);
            });
            div.appendChild(row);
        }

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return id;
    }

    function peteyRemove(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    }

    // Show notification badge after 3s if chat not opened
    setTimeout(function() {
        if (!peteyOpened) {
            var badge = document.getElementById('petey-badge');
            badge.style.display = 'flex';
        }
    }, 3000);
})();
</script>
<!-- ═══════════════════ END CHATBOT ═══════════════════════ -->