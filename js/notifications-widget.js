(function () {
    'use strict';

    function injectBell() {
        const navUser = document.querySelector('.nav-user');
        if (!navUser || document.getElementById('notif-bell')) return;

        const wrapper = document.createElement('div');
        wrapper.id        = 'notif-bell';
        wrapper.className = 'notif-bell-wrapper';
        wrapper.innerHTML = `
            <button class="notif-bell-btn" id="notifBellBtn" aria-label="Notificaciones">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-badge" id="notifBadge" style="display:none">0</span>
            </button>
            <div class="notif-dropdown" id="notifDropdown" style="display:none">
                <div class="notif-header">
                    <span>Notificaciones</span>
                    <button class="notif-mark-all" id="notifMarkAll">Marcar todo como leído</button>
                </div>
                <div class="notif-list" id="notifList">
                    <p class="notif-empty">Cargando…</p>
                </div>
            </div>
        `;

        navUser.insertBefore(wrapper, navUser.firstChild);
        bindEvents();
    }

    function bindEvents() {
        const btn      = document.getElementById('notifBellBtn');
        const dropdown = document.getElementById('notifDropdown');
        const markAll  = document.getElementById('notifMarkAll');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = dropdown.style.display === 'block';
            dropdown.style.display = open ? 'none' : 'block';
            if (!open) loadNotifications();
        });

        document.addEventListener('click', () => {
            if (dropdown) dropdown.style.display = 'none';
        });

        dropdown.addEventListener('click', (e) => e.stopPropagation());

        markAll.addEventListener('click', () => markRead(0));
    }

    function loadNotifications() {
        fetch('api/notifications_api.php?action=list')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('notifList');
                if (!data.success || !data.notifications.length) {
                    list.innerHTML = '<p class="notif-empty">No tienes notificaciones nuevas.</p>';
                    return;
                }
                list.innerHTML = data.notifications.map(renderItem).join('');

                list.querySelectorAll('.notif-item').forEach(el => {
                    el.addEventListener('click', () => {
                        const nid  = parseInt(el.dataset.id);
                        const link = el.dataset.link;
                        markRead(nid);
                        if (link) window.location.href = link;
                    });
                });

                updateCount();
            })
            .catch(() => {
                document.getElementById('notifList').innerHTML =
                    '<p class="notif-empty">Error al cargar.</p>';
            });
    }

    function renderItem(n) {
        const icon  = iconForType(n.type);
        const unread = n.is_read == 0 ? ' unread' : '';
        const time  = relativeTime(n.created_at);
        return `
            <div class="notif-item${unread}" data-id="${n.notification_id}" data-link="${n.link || ''}">
                <span class="notif-icon">${icon}</span>
                <div class="notif-body">
                    <p class="notif-title">${escHtml(n.title)}</p>
                    <p class="notif-msg">${escHtml(n.message)}</p>
                    <span class="notif-time">${time}</span>
                </div>
            </div>
        `;
    }

    function iconForType(type) {
        const icons = {
            blog_comment : '💬',
            friend_course: '🎓',
            new_course   : '📚',
            reminder     : '⏰',
        };
        return icons[type] || '🔔';
    }

    function markRead(notifId) {
        fetch('api/notifications_api.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ action: 'mark_read', notification_id: notifId || 0 })
        }).then(() => {
            if (notifId) {
                const el = document.querySelector(`.notif-item[data-id="${notifId}"]`);
                if (el) el.classList.remove('unread');
            } else {
                document.querySelectorAll('.notif-item.unread')
                        .forEach(el => el.classList.remove('unread'));
            }
            updateCount();
        });
    }

    function updateCount() {
        fetch('api/notifications_api.php?action=count')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('notifBadge');
                if (!badge) return;
                const n = data.count || 0;
                badge.textContent  = n > 9 ? '9+' : n;
                badge.style.display = n > 0 ? 'flex' : 'none';
            });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function relativeTime(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)   return 'Hace un momento';
        if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
        if (diff < 86400)return `Hace ${Math.floor(diff / 3600)} h`;
        return `Hace ${Math.floor(diff / 86400)} días`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        injectBell();
        updateCount();
        setInterval(updateCount, 60000);
    });
})();