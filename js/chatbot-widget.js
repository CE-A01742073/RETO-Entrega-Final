(function () {
    'use strict';

    const CHAT_API        = '/api/chatbot_api.php';
    const FRIENDS_API     = '/api/friends_api.php';
    const HISTORY_API     = '/api/chat_history.php';

    const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf'
    ];
    const MAX_FILE_SIZE_MB = 10;

    const styles = `
        /* ── Floating button ── */
        #wlp-chatbot-btn {
            position: fixed; bottom: 28px; right: 28px; z-index: 9998;
            width: 56px; height: 56px; border-radius: 50%;
            background: #004976; border: none; cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,73,118,0.35);
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, transform 0.2s;
        }
        #wlp-chatbot-btn:hover { background: #0099D8; transform: scale(1.07); }
        #wlp-chatbot-btn svg   { width: 26px; height: 26px; fill: #fff; }
        #wlp-chatbot-badge {
            position: absolute; top: 0; right: 0; width: 16px; height: 16px;
            background: #e53e3e; border-radius: 50%; border: 2px solid #fff;
            display: none; font-size: 9px; font-weight: 700; color: #fff;
            align-items: center; justify-content: center;
        }

        /* ── Panel ── */
        #wlp-chatbot-panel {
            position: fixed; bottom: 96px; right: 28px; z-index: 9999;
            width: 370px; max-height: 580px;
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            display: flex; flex-direction: column; overflow: hidden;
            transform: scale(0.92) translateY(10px); opacity: 0; pointer-events: none;
            transition: transform 0.22s ease, opacity 0.22s ease, width 0.28s cubic-bezier(.4,0,.2,1);
        }
        #wlp-chatbot-panel.wlp-open { transform: scale(1) translateY(0); opacity: 1; pointer-events: auto; }
        #wlp-chatbot-panel.wlp-history-open { width: 610px; }

        /* ── Header ── */
        #wlp-chat-header {
            background: linear-gradient(135deg, #004976 0%, #0099D8 100%);
            padding: 14px 18px; display: flex; align-items: center; gap: 12px; flex-shrink: 0;
        }
        #wlp-chat-header .wlp-avatar {
            width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        #wlp-chat-header .wlp-avatar svg { width: 20px; height: 20px; fill: #fff; }
        #wlp-chat-header-text h4 { margin: 0; font-family: 'Nunito Sans', sans-serif; font-size: 15px; font-weight: 700; color: #fff; }
        #wlp-chat-header-text span { font-family: 'Open Sans', sans-serif; font-size: 12px; color: rgba(255,255,255,0.78); }
        #wlp-chat-header-actions { margin-left: auto; display: flex; align-items: center; gap: 4px; }
        #wlp-history-toggle-btn, #wlp-chat-close {
            background: none; border: none; cursor: pointer; padding: 5px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center; transition: background .18s;
        }
        #wlp-history-toggle-btn svg { width: 17px; height: 17px; fill: none; stroke: rgba(255,255,255,.8); stroke-width: 2; transition: stroke .18s; }
        #wlp-history-toggle-btn:hover, #wlp-history-toggle-btn.active { background: rgba(255,255,255,.2); }
        #wlp-history-toggle-btn:hover svg, #wlp-history-toggle-btn.active svg { stroke: #fff; }
        #wlp-chat-close svg { width: 18px; height: 18px; fill: rgba(255,255,255,.8); }
        #wlp-chat-close:hover { background: rgba(255,255,255,.15); }
        #wlp-chat-close:hover svg { fill: #fff; }

        /* ── Tabs ── */
        #wlp-tabs { display: flex; border-bottom: 1px solid #e8edf2; background: #fff; flex-shrink: 0; }
        .wlp-tab {
            flex: 1; padding: 10px 0; border: none; background: none;
            font-family: 'Nunito Sans', sans-serif; font-size: 13px; font-weight: 600;
            color: #9aaab8; cursor: pointer; border-bottom: 3px solid transparent;
            transition: color .18s, border-color .18s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .wlp-tab svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; }
        .wlp-tab.active { color: #004976; border-bottom-color: #0099D8; }

        /* ── Panes ── */
        .wlp-pane { display: none; flex-direction: column; flex: 1; overflow: hidden; min-height: 0; }
        .wlp-pane.active { display: flex; }
        #wlp-pane-chat { flex-direction: row !important; }

        /* ── History sidebar ── */
        #wlp-history-sidebar {
            width: 0; overflow: hidden; flex-shrink: 0;
            background: #f0f4f8; border-right: 1px solid #dde4ec;
            display: flex; flex-direction: column;
            transition: width 0.28s cubic-bezier(.4,0,.2,1);
        }
        #wlp-history-sidebar.open { width: 210px; }
        #wlp-history-sidebar-inner { width: 210px; display: flex; flex-direction: column; height: 100%; overflow: hidden; }

        #wlp-history-sidebar-header {
            padding: 12px 14px 8px;
            font-family: 'Nunito Sans', sans-serif; font-size: 11px; font-weight: 800;
            letter-spacing: .6px; text-transform: uppercase; color: #7a90a4;
            border-bottom: 1px solid #dde4ec; flex-shrink: 0;
            display: flex; align-items: center; gap: 6px;
        }
        #wlp-history-sidebar-header svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2.5; flex-shrink: 0; }

        #wlp-history-sessions { flex: 1; overflow-y: auto; padding: 6px 0; scrollbar-width: thin; scrollbar-color: #cdd8e3 transparent; }
        #wlp-history-sessions::-webkit-scrollbar { width: 3px; }
        #wlp-history-sessions::-webkit-scrollbar-thumb { background: #cdd8e3; border-radius: 3px; }

        .wlp-session-item {
            padding: 10px 14px; cursor: pointer;
            border-left: 3px solid transparent;
            transition: background .15s, border-color .15s;
        }
        .wlp-session-item:hover  { background: #e4ecf4; }
        .wlp-session-item.active { background: #dce8f5; border-left-color: #0099D8; }

        .wlp-session-date {
            font-family: 'Open Sans', sans-serif; font-size: 10px; color: #9aaab8;
            margin-bottom: 3px; display: flex; align-items: center; gap: 4px;
        }
        .wlp-session-date svg { width: 10px; height: 10px; fill: none; stroke: currentColor; stroke-width: 2; flex-shrink: 0; }
        .wlp-session-title {
            font-family: 'Nunito Sans', sans-serif; font-size: 12px; font-weight: 700; color: #2d4a62;
            line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .wlp-session-count { margin-top: 4px; font-family: 'Open Sans', sans-serif; font-size: 10px; color: #9aaab8; }

        .wlp-session-loading, .wlp-session-empty {
            padding: 20px 14px; font-family: 'Open Sans', sans-serif;
            font-size: 12px; color: #9aaab8; text-align: center; line-height: 1.5;
        }

        #wlp-new-chat-btn {
            margin: 8px 10px; padding: 7px 10px;
            border: 1.5px dashed #a0b4c8; border-radius: 8px;
            background: none; cursor: pointer;
            font-family: 'Nunito Sans', sans-serif; font-size: 11px; font-weight: 700; color: #7a90a4;
            display: flex; align-items: center; justify-content: center; gap: 5px;
            transition: border-color .18s, color .18s, background .18s; flex-shrink: 0;
        }
        #wlp-new-chat-btn:hover { border-color: #0099D8; color: #004976; background: #e8f3fb; }
        #wlp-new-chat-btn svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2.5; }

        /* ── Chat area ── */
        #wlp-chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

        /* History reading banner */
        #wlp-history-banner {
            display: none; padding: 7px 14px;
            background: #fff8e6; border-bottom: 1px solid #f5d78a;
            font-family: 'Open Sans', sans-serif; font-size: 11.5px; color: #92680a;
            align-items: center; gap: 8px; flex-shrink: 0;
        }
        #wlp-history-banner.visible { display: flex; }
        #wlp-history-banner svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2; flex-shrink: 0; }
        #wlp-back-to-chat {
            margin-left: auto; background: none; border: none; cursor: pointer;
            font-family: 'Nunito Sans', sans-serif; font-size: 11px; font-weight: 700; color: #004976;
            padding: 2px 8px; border-radius: 5px; transition: background .15s;
        }
        #wlp-back-to-chat:hover { background: #e8f0fa; }

        /* Messages */
        #wlp-chat-messages {
            flex: 1; overflow-y: auto; padding: 16px;
            display: flex; flex-direction: column; gap: 12px;
            background: #f7f9fb; scroll-behavior: smooth;
        }
        #wlp-chat-messages::-webkit-scrollbar { width: 5px; }
        #wlp-chat-messages::-webkit-scrollbar-thumb { background: #cdd8e3; border-radius: 4px; }

        .wlp-msg { display: flex; gap: 8px; max-width: 92%; }
        .wlp-msg.wlp-user { align-self: flex-end; flex-direction: row-reverse; }
        .wlp-msg-bubble {
            padding: 10px 14px; border-radius: 14px;
            font-family: 'Open Sans', sans-serif; font-size: 13px; line-height: 1.5;
            white-space: pre-wrap; word-break: break-word;
        }
        .wlp-msg.wlp-bot  .wlp-msg-bubble { background: #fff; color: #1a2533; border-bottom-left-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        .wlp-msg.wlp-user .wlp-msg-bubble { background: #004976; color: #fff; border-bottom-right-radius: 4px; }
        .wlp-msg.wlp-history-msg .wlp-msg-bubble { opacity: .82; }
        .wlp-msg-time { font-size: 10px; color: #9aaab8; align-self: flex-end; flex-shrink: 0; margin-bottom: 2px; }

        .wlp-date-sep { text-align: center; font-family: 'Open Sans', sans-serif; font-size: 10.5px; color: #9aaab8; position: relative; padding: 4px 0; }
        .wlp-date-sep::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background:#e2e8ef; }
        .wlp-date-sep span { position:relative; background:#f7f9fb; padding:0 10px; }

        /* Typing */
        .wlp-typing { display: flex; gap: 5px; padding: 10px 14px; background: #fff; border-radius: 14px; border-bottom-left-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.07); width: fit-content; }
        .wlp-typing span { width: 7px; height: 7px; background: #0099D8; border-radius: 50%; animation: wlp-bounce 1.2s infinite; }
        .wlp-typing span:nth-child(2) { animation-delay: 0.2s; }
        .wlp-typing span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes wlp-bounce { 0%,80%,100%{transform:translateY(0);opacity:.5} 40%{transform:translateY(-6px);opacity:1} }

        /* ── Input area ── */
        #wlp-chat-input-area {
            padding: 10px 12px; border-top: 1px solid #e8edf2;
            background: #fff; display: flex; flex-direction: column; gap: 6px; flex-shrink: 0;
        }
        #wlp-input-row { display: flex; gap: 7px; align-items: flex-end; }
        #wlp-chat-input {
            flex: 1; resize: none; border: 1.5px solid #dbe3eb; border-radius: 10px;
            padding: 8px 11px; font-family: 'Open Sans', sans-serif; font-size: 13px; color: #1a2533;
            background: #f7f9fb; outline: none; max-height: 100px; transition: border-color .18s; line-height: 1.45;
        }
        #wlp-chat-input:focus { border-color: #0099D8; background: #fff; }
        #wlp-chat-input::placeholder { color: #9aaab8; }
        #wlp-chat-input:disabled { opacity: .5; cursor: not-allowed; background: #f0f0f0; }

        /* Botón adjuntar */
        #wlp-attach-btn {
            width: 36px; height: 36px; background: none; border: 1.5px solid #dbe3eb;
            border-radius: 9px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: border-color .18s, background .18s; color: #7a90a4;
        }
        #wlp-attach-btn:hover:not(:disabled) { border-color: #0099D8; background: #e8f3fb; color: #004976; }
        #wlp-attach-btn:disabled { opacity: .4; cursor: not-allowed; }
        #wlp-attach-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; }
        #wlp-attach-btn.has-file { border-color: #0099D8; background: #e8f3fb; color: #004976; }

        #wlp-send-btn {
            width: 36px; height: 36px; background: #004976; border: none; border-radius: 9px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: background .18s;
        }
        #wlp-send-btn:hover    { background: #0099D8; }
        #wlp-send-btn:disabled { background: #c5d3de; cursor: not-allowed; }
        #wlp-send-btn svg { width: 16px; height: 16px; fill: #fff; }

        /* Preview del archivo adjunto */
        #wlp-file-preview {
            display: none; align-items: center; gap: 8px;
            padding: 7px 10px; background: #eef4fb; border-radius: 8px;
            border: 1px solid #c8dcf0;
        }
        #wlp-file-preview.visible { display: flex; }
        #wlp-file-preview-icon { flex-shrink: 0; display: flex; }
        #wlp-file-preview-icon svg { width: 20px; height: 20px; fill: none; stroke: #0099D8; stroke-width: 2; }
        #wlp-file-preview-thumb {
            width: 36px; height: 36px; border-radius: 5px;
            object-fit: cover; display: none; flex-shrink: 0;
            border: 1px solid #c8dcf0;
        }
        #wlp-file-preview-thumb.visible { display: block; }
        #wlp-file-preview-info { flex: 1; overflow: hidden; }
        #wlp-file-preview-name {
            display: block; font-family: 'Open Sans', sans-serif; font-size: 11.5px;
            color: #2d4a62; font-weight: 600;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        #wlp-file-preview-size {
            display: block; font-family: 'Open Sans', sans-serif; font-size: 10px; color: #7a90a4; margin-top: 1px;
        }
        #wlp-file-remove-btn {
            background: none; border: none; cursor: pointer; padding: 3px;
            color: #9aaab8; display: flex; align-items: center; justify-content: center;
            border-radius: 4px; transition: color .15s, background .15s; flex-shrink: 0;
        }
        #wlp-file-remove-btn:hover { color: #e53e3e; background: #fee2e2; }
        #wlp-file-remove-btn svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2.5; }

        /* Burbujas con adjunto */
        .wlp-msg-attachment { margin-top: 7px; }
        .wlp-msg-attachment img {
            max-width: 170px; max-height: 130px; border-radius: 8px;
            object-fit: cover; display: block; border: 1px solid rgba(255,255,255,.2);
        }
        .wlp-msg-attachment-file {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px; border-radius: 7px; font-family: 'Open Sans', sans-serif; font-size: 11.5px;
            max-width: 200px; overflow: hidden;
        }
        .wlp-msg.wlp-user .wlp-msg-attachment-file { background: rgba(255,255,255,.18); color: #e8f3fb; }
        .wlp-msg.wlp-bot  .wlp-msg-attachment-file { background: #f0f4f8; color: #2d4a62; }
        .wlp-msg-attachment-file svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; flex-shrink: 0; }
        .wlp-msg-attachment-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Friends pane ── */
        #wlp-friends-pane { flex: 1; overflow-y: auto; background: #f7f9fb; display: flex; flex-direction: column; }
        #wlp-friends-pane::-webkit-scrollbar { width: 5px; }
        #wlp-friends-pane::-webkit-scrollbar-thumb { background: #cdd8e3; border-radius: 4px; }
        .wlp-pending-section { background: #fff; border-bottom: 1px solid #e8edf2; padding: 12px 14px; }
        .wlp-pending-title { font-family: 'Nunito Sans', sans-serif; font-size: 12px; font-weight: 700; color: #004976; letter-spacing: .3px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .wlp-pending-badge { background: #e53e3e; color: #fff; font-size: 10px; font-weight: 700; border-radius: 20px; padding: 1px 6px; }
        .wlp-req-item { display: flex; align-items: center; gap: 8px; padding: 7px 0; border-top: 1px solid #f3f4f6; }
        .wlp-req-item:first-of-type { border-top: none; padding-top: 0; }
        .wlp-req-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#004976,#0099D8); color: #fff; font-family: 'Nunito Sans', sans-serif; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .wlp-req-name { flex: 1; font-size: 12.5px; font-weight: 600; color: #1a2533; }
        .wlp-req-accept, .wlp-req-reject { padding: 3px 10px; border-radius: 5px; border: none; font-size: 11px; font-weight: 600; cursor: pointer; }
        .wlp-req-accept { background: #16A34A; color: #fff; margin-right: 4px; }
        .wlp-req-accept:hover { background: #15803d; }
        .wlp-req-reject { background: #f3f4f6; color: #4B5563; }
        .wlp-req-reject:hover { background: #e5e7eb; }
        .wlp-friends-list-section { padding: 12px 14px; flex: 1; }
        .wlp-section-title { font-family: 'Nunito Sans', sans-serif; font-size: 12px; font-weight: 700; color: #9aaab8; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 8px; }
        .wlp-friend-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        .wlp-friend-item:last-child { border-bottom: none; }
        .wlp-friend-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,#16A34A,#4ade80); color: #fff; font-family: 'Nunito Sans', sans-serif; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .wlp-friend-info { flex: 1; }
        .wlp-friend-name { font-size: 13px; font-weight: 600; color: #1a2533; }
        .wlp-friend-stat { font-size: 11px; color: #9aaab8; }
        .wlp-empty { text-align: center; padding: 30px 16px; color: #9aaab8; font-size: 13px; font-family: 'Open Sans', sans-serif; }
        .wlp-empty svg { width: 36px; height: 36px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto; }
        .wlp-see-all-btn { display: block; margin: 12px 14px; padding: 9px; border-radius: 8px; background: #004976; color: #fff; text-align: center; text-decoration: none; font-family: 'Nunito Sans', sans-serif; font-size: 13px; font-weight: 700; transition: background .18s; flex-shrink: 0; }
        .wlp-see-all-btn:hover { background: #0099D8; }

        /* ── Responsive ── */
        @media (max-width: 660px) {
            #wlp-chatbot-panel, #wlp-chatbot-panel.wlp-history-open { width: calc(100vw - 24px) !important; right: 12px; bottom: 84px; }
            #wlp-history-sidebar.open { width: 150px; }
            #wlp-history-sidebar-inner { width: 150px; }
            #wlp-chatbot-btn { right: 16px; bottom: 16px; }
        }
    `;

    const html = `
        <style>${styles}</style>

        <button id="wlp-chatbot-btn" aria-label="Abrir asistente">
            <div id="wlp-chatbot-badge"></div>
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
        </button>

        <div id="wlp-chatbot-panel" role="dialog" aria-label="Asistente Whirlpool">

            <div id="wlp-chat-header">
                <div class="wlp-avatar">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                </div>
                <div id="wlp-chat-header-text">
                    <h4>Asistente Whirlpool</h4>
                    <span id="wlp-header-sub">Chat · Powered by Gemini</span>
                </div>
                <div id="wlp-chat-header-actions">
                    <button id="wlp-history-toggle-btn" aria-label="Ver historial" title="Conversaciones anteriores">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </button>
                    <button id="wlp-chat-close" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:rgba(255,255,255,.8)"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>
            </div>

            <div id="wlp-tabs">
                <button class="wlp-tab active" data-tab="chat" id="wlp-tab-chat">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" fill="currentColor" stroke="none"/></svg>
                    Chat IA
                </button>
                <button class="wlp-tab" data-tab="friends" id="wlp-tab-friends">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Amigos
                    <span id="wlp-friends-tab-badge" style="display:none;background:#e53e3e;color:#fff;font-size:10px;font-weight:700;border-radius:20px;padding:1px 6px;margin-left:2px;"></span>
                </button>
            </div>

            <!-- CHAT PANE -->
            <div class="wlp-pane active" id="wlp-pane-chat">

                <!-- History sidebar -->
                <div id="wlp-history-sidebar">
                    <div id="wlp-history-sidebar-inner">
                        <div id="wlp-history-sidebar-header">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Historial
                        </div>
                        <div id="wlp-history-sessions">
                            <div class="wlp-session-loading">Cargando…</div>
                        </div>
                        <button id="wlp-new-chat-btn">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Nueva conversación
                        </button>
                    </div>
                </div>

                <!-- Main chat area -->
                <div id="wlp-chat-area">
                    <div id="wlp-history-banner">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span id="wlp-banner-text">Conversación anterior</span>
                        <button id="wlp-back-to-chat">Volver al chat</button>
                    </div>
                    <div id="wlp-chat-messages" aria-live="polite"></div>
                    <div id="wlp-chat-input-area">
                        <!-- Preview del archivo seleccionado -->
                        <div id="wlp-file-preview">
                            <div id="wlp-file-preview-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <img id="wlp-file-preview-thumb" alt="preview" />
                            <div id="wlp-file-preview-info">
                                <span id="wlp-file-preview-name">archivo</span>
                                <span id="wlp-file-preview-size">0 KB</span>
                            </div>
                            <button id="wlp-file-remove-btn" aria-label="Quitar archivo">
                                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <!-- Fila de input -->
                        <div id="wlp-input-row">
                            <input type="file" id="wlp-file-input"
                                accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
                                style="display:none" aria-label="Adjuntar archivo" />
                            <button id="wlp-attach-btn" aria-label="Adjuntar archivo" title="Adjuntar imagen o PDF (máx. 10 MB)">
                                <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </button>
                            <textarea id="wlp-chat-input" placeholder="Pregunta sobre cursos, tu progreso..." rows="1" maxlength="1000" aria-label="Mensaje"></textarea>
                            <button id="wlp-send-btn" aria-label="Enviar">
                                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FRIENDS PANE -->
            <div class="wlp-pane" id="wlp-pane-friends">
                <div id="wlp-friends-pane">
                    <div id="wlp-pending-container"></div>
                    <div id="wlp-friends-list-container"></div>
                </div>
                <a href="/friends.php" class="wlp-see-all-btn">Ver todos los estudiantes →</a>
            </div>

        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const btn              = document.getElementById('wlp-chatbot-btn');
    const panel            = document.getElementById('wlp-chatbot-panel');
    const closeBtn         = document.getElementById('wlp-chat-close');
    const messages         = document.getElementById('wlp-chat-messages');
    const input            = document.getElementById('wlp-chat-input');
    const sendBtn          = document.getElementById('wlp-send-btn');
    const badge            = document.getElementById('wlp-chatbot-badge');
    const headerSub        = document.getElementById('wlp-header-sub');
    const tabBtns          = document.querySelectorAll('.wlp-tab');
    const panes            = document.querySelectorAll('.wlp-pane');
    const friendsBadge     = document.getElementById('wlp-friends-tab-badge');
    const historyToggleBtn = document.getElementById('wlp-history-toggle-btn');
    const historySidebar   = document.getElementById('wlp-history-sidebar');
    const historySessions  = document.getElementById('wlp-history-sessions');
    const historyBanner    = document.getElementById('wlp-history-banner');
    const bannerText       = document.getElementById('wlp-banner-text');
    const backToChatBtn    = document.getElementById('wlp-back-to-chat');
    const newChatBtn       = document.getElementById('wlp-new-chat-btn');
    // Adjunto
    const attachBtn        = document.getElementById('wlp-attach-btn');
    const fileInput        = document.getElementById('wlp-file-input');
    const filePreview      = document.getElementById('wlp-file-preview');
    const filePreviewIcon  = document.getElementById('wlp-file-preview-icon');
    const filePreviewThumb = document.getElementById('wlp-file-preview-thumb');
    const filePreviewName  = document.getElementById('wlp-file-preview-name');
    const filePreviewSize  = document.getElementById('wlp-file-preview-size');
    const fileRemoveBtn    = document.getElementById('wlp-file-remove-btn');

    let isOpen           = false;
    let isLoading        = false;
    let chatInitialized  = false;
    let currentTab       = 'chat';
    let historyOpen      = false;
    let historyLoaded    = false;
    let viewingHistory   = false;
    let activeSessionIdx = -1;
    let liveMessages     = [];
    let cachedSessions   = [];

    let attachedFile     = null;

    function togglePanel() {
        isOpen = !isOpen;
        panel.classList.toggle('wlp-open', isOpen);
        badge.style.display = 'none';
        if (isOpen) {
            if (currentTab === 'chat' && !chatInitialized) { addWelcomeMessage(); chatInitialized = true; }
            if (currentTab === 'friends') loadFriendsPane();
            setTimeout(() => { if (currentTab === 'chat') input.focus(); }, 250);
        }
    }

    tabBtns.forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const tab = tabBtn.dataset.tab;
            if (tab === currentTab) return;
            currentTab = tab;
            tabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
            panes.forEach(p => p.classList.toggle('active', p.id === 'wlp-pane-' + tab));
            historyToggleBtn.style.display = tab === 'chat' ? '' : 'none';
            if (tab === 'chat') {
                headerSub.textContent = 'Chat · Powered by Gemini';
                if (!chatInitialized) { addWelcomeMessage(); chatInitialized = true; }
                setTimeout(() => input.focus(), 100);
            } else {
                headerSub.textContent = 'Comunidad de estudiantes';
                if (historyOpen) closeHistorySidebar();
                loadFriendsPane();
            }
        });
    });

   historyToggleBtn.addEventListener('click', () => historyOpen ? closeHistorySidebar() : openHistorySidebar());

    function openHistorySidebar() {
        historyOpen = true;
        historySidebar.classList.add('open');
        panel.classList.add('wlp-history-open');
        historyToggleBtn.classList.add('active');
        if (!historyLoaded) loadHistory();
    }

    function closeHistorySidebar() {
        historyOpen = false;
        historySidebar.classList.remove('open');
        panel.classList.remove('wlp-history-open');
        historyToggleBtn.classList.remove('active');
    }

    async function loadHistory() {
        historySessions.innerHTML = '<div class="wlp-session-loading">Cargando…</div>';
        try {
            const r = await fetch(HISTORY_API);
            const d = await r.json();
            if (!d.success || !d.sessions || d.sessions.length === 0) {
                historySessions.innerHTML = '<div class="wlp-session-empty">Sin conversaciones<br>anteriores aún</div>';
                return;
            }
            cachedSessions = d.sessions;
            historyLoaded  = true;
            renderSessionList();
        } catch {
            historySessions.innerHTML = '<div class="wlp-session-empty">Error al cargar historial</div>';
        }
    }

    function renderSessionList() {
        historySessions.innerHTML = '';
        cachedSessions.forEach((s, idx) => {
            const item = document.createElement('div');
            item.className = 'wlp-session-item' + (activeSessionIdx === idx ? ' active' : '');
            item.innerHTML = `
                <div class="wlp-session-date">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ${escapeHtml(s.date)} · ${escapeHtml(s.time)}
                </div>
                <div class="wlp-session-title">${escapeHtml(s.title)}</div>
                <div class="wlp-session-count">${s.msg_count} mensaje${s.msg_count !== 1 ? 's' : ''}</div>
            `;
            item.addEventListener('click', () => loadSessionIntoChat(idx));
            historySessions.appendChild(item);
        });
    }

    function loadSessionIntoChat(idx) {
        const session = cachedSessions[idx];
        if (!session) return;
        activeSessionIdx = idx;
        viewingHistory   = true;
        renderSessionList();

        messages.innerHTML = '';
        const sep = document.createElement('div');
        sep.className = 'wlp-date-sep';
        sep.innerHTML = `<span>${escapeHtml(session.date)} · ${escapeHtml(session.time)}</span>`;
        messages.appendChild(sep);

        session.messages.forEach(m => {
            addMessageEl('user', m.user, m.time, true, null);
            addMessageEl('bot',  m.bot,  m.time, true, null);
        });

        bannerText.textContent = `${session.date} · ${session.msg_count} mensajes`;
        historyBanner.classList.add('visible');

        input.disabled    = true;
        sendBtn.disabled  = true;
        input.placeholder = 'Modo lectura — presiona "Volver al chat" para continuar';
        scrollToBottom();
    }

    function exitHistoryMode() {
        viewingHistory   = false;
        activeSessionIdx = -1;
        historyBanner.classList.remove('visible');
        input.disabled    = false;
        sendBtn.disabled  = false;
        input.placeholder = 'Pregunta sobre cursos, tu progreso...';
        if (historyOpen) renderSessionList();
        renderLiveMessages();
        input.focus();
    }

    function renderLiveMessages() {
        messages.innerHTML = '';
        liveMessages.forEach(m => addMessageEl(m.role, m.text, m.time, false, m.attachment || null));
        scrollToBottom();
    }

    backToChatBtn.addEventListener('click', exitHistoryMode);

    newChatBtn.addEventListener('click', () => {
        liveMessages     = [];
        chatInitialized  = false;
        historyLoaded    = false;
        activeSessionIdx = -1;
        clearAttachment();
        exitHistoryMode();
        addWelcomeMessage();
        chatInitialized = true;
    });

    attachBtn.addEventListener('click', () => {
        if (!isLoading && !viewingHistory) fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        fileInput.value = ''; // reset para reusar
        if (!file) return;

        if (!ALLOWED_MIME_TYPES.includes(file.type)) {
            alert('Tipo de archivo no permitido.\nFormatos aceptados: JPG, PNG, WebP, GIF, PDF.');
            return;
        }
        if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
            alert(`El archivo supera el límite de ${MAX_FILE_SIZE_MB} MB.`);
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl   = e.target.result;
            const base64    = dataUrl.split(',')[1];
            const isImage   = file.type.startsWith('image/');
            const objectUrl = isImage ? URL.createObjectURL(file) : null;
            attachedFile    = { base64, mimeType: file.type, name: file.name, size: file.size, isImage, objectUrl };
            showFilePreview();
        };
        reader.readAsDataURL(file);
    });

    function showFilePreview() {
        if (!attachedFile) return;
        const kb   = attachedFile.size / 1024;
        const sizeStr = kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB';

        filePreviewName.textContent = attachedFile.name;
        filePreviewSize.textContent = sizeStr;

        if (attachedFile.isImage && attachedFile.objectUrl) {
            filePreviewThumb.src = attachedFile.objectUrl;
            filePreviewThumb.classList.add('visible');
            filePreviewIcon.style.display = 'none';
        } else {
            filePreviewThumb.classList.remove('visible');
            filePreviewIcon.style.display = '';
        }

        filePreview.classList.add('visible');
        attachBtn.classList.add('has-file');
        input.focus();
    }

    function clearAttachment() {
        if (attachedFile && attachedFile.objectUrl) URL.revokeObjectURL(attachedFile.objectUrl);
        attachedFile = null;
        filePreview.classList.remove('visible');
        filePreviewThumb.classList.remove('visible');
        filePreviewThumb.src = '';
        filePreviewIcon.style.display = '';
        attachBtn.classList.remove('has-file');
    }

    fileRemoveBtn.addEventListener('click', clearAttachment);

    function nowTime() { return new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }); }

    function addWelcomeMessage() {
        const text = 'Hola, soy el asistente virtual de la plataforma de capacitación Whirlpool. Puedo ayudarte a encontrar cursos, revisar tu progreso y responder preguntas sobre el contenido disponible.\n\nTambién puedes adjuntar imágenes o PDFs para que los analice. ¿En qué puedo ayudarte hoy?';
        const time = nowTime();
        liveMessages.push({ role: 'bot', text, time });
        addMessageEl('bot', text, time, false, null);
    }

    function addBotMessage(text) {
        const time = nowTime();
        liveMessages.push({ role: 'bot', text, time });
        addMessageEl('bot', text, time, false, null);
    }

    function addUserMessage(text, attachment) {
        const time = nowTime();
        // Solo guardamos metadatos del adjunto en liveMessages (no el base64)
        liveMessages.push({ role: 'user', text, time, attachment: attachment || null });
        addMessageEl('user', text, time, false, attachment || null);
    }

    /**
     * attachment = { name, mimeType, isImage, objectUrl }
     */
    function addMessageEl(role, text, time, isHistory, attachment) {
        const el = document.createElement('div');
        el.className = `wlp-msg wlp-${role}${isHistory ? ' wlp-history-msg' : ''}`;

        let bubbleHtml = '';
        if (text) bubbleHtml += escapeHtml(text);

        if (attachment) {
            if (attachment.isImage && attachment.objectUrl) {
                bubbleHtml += `<div class="wlp-msg-attachment"><img src="${escapeHtml(attachment.objectUrl)}" alt="${escapeHtml(attachment.name)}" /></div>`;
            } else {
                const isPdf = (attachment.mimeType === 'application/pdf');
                const iconPaths = isPdf
                    ? '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'
                    : '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>';
                bubbleHtml += `<div class="wlp-msg-attachment"><div class="wlp-msg-attachment-file"><svg viewBox="0 0 24 24">${iconPaths}</svg><span>${escapeHtml(attachment.name)}</span></div></div>`;
            }
        }

        el.innerHTML = `<div class="wlp-msg-bubble">${bubbleHtml}</div><span class="wlp-msg-time">${escapeHtml(time)}</span>`;
        messages.appendChild(el);
        scrollToBottom();
    }

    function showTyping() {
        const el = document.createElement('div');
        el.className = 'wlp-msg wlp-bot'; el.id = 'wlp-typing-indicator';
        el.innerHTML = `<div class="wlp-typing"><span></span><span></span><span></span></div>`;
        messages.appendChild(el); scrollToBottom();
    }
    function hideTyping() { const el = document.getElementById('wlp-typing-indicator'); if (el) el.remove(); }
    function scrollToBottom() { messages.scrollTop = messages.scrollHeight; }
    function escapeHtml(t) {
        if (t == null) return '';
        const d = document.createElement('div'); d.appendChild(document.createTextNode(String(t))); return d.innerHTML;
    }

    async function sendMessage() {
        if (isLoading || viewingHistory) return;
        const text = input.value.trim();
        if (!text && !attachedFile) return;

        // Capturar adjunto antes de limpiar
        const currentFile = attachedFile ? { ...attachedFile } : null;
        const attachMeta  = currentFile
            ? { name: currentFile.name, mimeType: currentFile.mimeType, isImage: currentFile.isImage, objectUrl: currentFile.objectUrl }
            : null;

        addUserMessage(text, attachMeta);
        input.value = ''; input.style.height = 'auto';
        clearAttachment();

        isLoading = true; sendBtn.disabled = true; attachBtn.disabled = true;
        historyLoaded = false;
        showTyping();

        try {
            const payload = { message: text };
            if (currentFile) {
                payload.file_data = currentFile.base64;
                payload.file_mime = currentFile.mimeType;
                payload.file_name = currentFile.name;
            }

            const r = await fetch(CHAT_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const d = await r.json();
            hideTyping();
            addBotMessage(d.success && d.message
                ? d.message
                : 'Lo siento, ocurrió un error. Por favor, intenta de nuevo.');
        } catch {
            hideTyping();
            addBotMessage('No fue posible conectar con el asistente. Verifica tu conexión e intenta de nuevo.');
        } finally {
            isLoading = false; sendBtn.disabled = false; attachBtn.disabled = false; input.focus();
        }
    }

    let friendsLoaded = false;

    async function loadFriendsPane() {
        const pc = document.getElementById('wlp-pending-container');
        const fc = document.getElementById('wlp-friends-list-container');
        if (!friendsLoaded) { pc.innerHTML = '<div class="wlp-empty"><small>Cargando...</small></div>'; fc.innerHTML = ''; }
        try {
            const r = await fetch('/api/friends_widget_data.php');
            const d = await r.json();
            if (!d.success) return;
            friendsLoaded = true;
            pc.innerHTML = '';
            if (d.pending && d.pending.length > 0) {
                friendsBadge.textContent = d.pending.length; friendsBadge.style.display = 'inline';
                const sec = document.createElement('div');
                sec.className = 'wlp-pending-section';
                sec.innerHTML = `<div class="wlp-pending-title"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Solicitudes pendientes<span class="wlp-pending-badge">${d.pending.length}</span></div>`;
                d.pending.forEach(req => {
                    const item = document.createElement('div');
                    item.className = 'wlp-req-item'; item.id = 'wlp-req-' + req.friendship_id;
                    item.innerHTML = `<div class="wlp-req-avatar">${escapeHtml(req.name.substring(0,2).toUpperCase())}</div><div class="wlp-req-name">${escapeHtml(req.name)}</div><button class="wlp-req-accept">✓</button><button class="wlp-req-reject">✗</button>`;
                    item.querySelector('.wlp-req-accept').addEventListener('click', () => widgetRespondRequest(req.friendship_id, 'accept'));
                    item.querySelector('.wlp-req-reject').addEventListener('click', () => widgetRespondRequest(req.friendship_id, 'reject'));
                    sec.appendChild(item);
                });
                pc.appendChild(sec);
            } else { friendsBadge.style.display = 'none'; }
            fc.innerHTML = '';
            if (d.friends && d.friends.length > 0) {
                const sec = document.createElement('div');
                sec.className = 'wlp-friends-list-section';
                sec.innerHTML = `<div class="wlp-section-title">Mis amigos (${d.friends.length})</div>`;
                d.friends.forEach(f => {
                    const item = document.createElement('div');
                    item.className = 'wlp-friend-item';
                    item.innerHTML = `<div class="wlp-friend-avatar">${escapeHtml(f.name.substring(0,2).toUpperCase())}</div><div class="wlp-friend-info"><div class="wlp-friend-name">${escapeHtml(f.name)}</div><div class="wlp-friend-stat">${f.completed} cursos completados · ${f.avg_progress}% progreso</div></div>`;
                    sec.appendChild(item);
                });
                fc.appendChild(sec);
            } else if (!d.pending || d.pending.length === 0) {
                fc.innerHTML = `<div class="wlp-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Aún no tienes amigos.<br>¡Visita la comunidad!</div>`;
            }
        } catch { pc.innerHTML = '<div class="wlp-empty">Error al cargar amigos.</div>'; }
    }

    async function widgetRespondRequest(fid, action) {
        try {
            const r = await fetch(FRIENDS_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, friendship_id: fid }) });
            const d = await r.json();
            if (d.success) {
                const item = document.getElementById('wlp-req-' + fid);
                if (item) { item.style.opacity = '0'; item.style.transition = 'opacity .3s'; setTimeout(() => item.remove(), 300); }
                friendsLoaded = false;
                setTimeout(() => loadFriendsPane(), 350);
            }
        } catch { /* silent */ }
    }

    async function checkPendingCount() {
        try {
            const r = await fetch(FRIENDS_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'get_pending_count' }) });
            const d = await r.json();
            if (d.success && d.count > 0) {
                badge.style.display = 'flex'; badge.textContent = d.count > 9 ? '9+' : d.count;
                friendsBadge.textContent = d.count; friendsBadge.style.display = 'inline';
            }
        } catch { /* silent */ }
    }

    input.addEventListener('input', function () { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 100) + 'px'; });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
    btn.addEventListener('click', togglePanel);
    closeBtn.addEventListener('click', togglePanel);
    sendBtn.addEventListener('click', sendMessage);

    setTimeout(checkPendingCount, 2000);

})();