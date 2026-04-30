(function () {
  'use strict';

  const AVATAR_COLORS = [
    { bg: '#E6F1FB', text: '#185FA5' },
    { bg: '#E1F5EE', text: '#0F6E56' },
    { bg: '#EEEDFE', text: '#3C3489' },
    { bg: '#FAEEDA', text: '#633806' },
    { bg: '#FAECE7', text: '#993C1D' },
    { bg: '#FBEAF0', text: '#72243E' },
  ];

  const DAYS_ES   = ['L','M','X','J','V','S','D'];
  const DAYS_FULL = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  const MEDALS    = ['🥇','🥈','🥉'];

  let currentTab = 'global';
  let isOpen     = false;
  let loaded     = false;
  let cachedData = { global: null, friends: null };

  function injectStyles() {
    const s = document.createElement('style');
    s.textContent = `

      /* ── FAB ── */
      #lb-fab {
        position: fixed;
        bottom: 100px; right: 24px;
        width: 52px; height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, #004976, #0099D8);
        border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 16px rgba(0,73,118,.35);
        z-index: 1100;
        transition: transform .2s, box-shadow .2s;
      }
      #lb-fab:hover { transform: scale(1.08); box-shadow: 0 6px 20px rgba(0,73,118,.45); }
      #lb-fab svg { width: 24px; height: 24px; color: #fff; }

      #lb-fab-badge {
        position: absolute; top: -4px; right: -4px;
        background: #E85D04; color: #fff;
        font-size: 10px; font-weight: 700;
        min-width: 18px; height: 18px; border-radius: 9px;
        display: none; align-items: center; justify-content: center;
        padding: 0 4px; border: 2px solid #fff; font-family: inherit;
      }
      #lb-fab-badge.visible { display: flex; }

      /* ── Overlay ── */
      #lb-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1101; opacity: 0; pointer-events: none;
        transition: opacity .25s;
      }
      #lb-overlay.open { opacity: 1; pointer-events: all; }

      /* ── Panel ── */
      #lb-panel {
        position: fixed; top: 0; right: 0;
        width: 380px; max-width: 100vw; height: 100%;
        background: var(--neutral-100, #f5f7fa);
        z-index: 1102;
        display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform .28s cubic-bezier(.4,0,.2,1);
        box-shadow: -4px 0 24px rgba(0,0,0,.18);
      }
      #lb-panel.open { transform: translateX(0); }

      .lb-panel-header {
        background: linear-gradient(135deg, #004976 0%, #0099D8 100%);
        padding: 1.25rem 1.5rem 1rem;
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0;
      }
      .lb-panel-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
      .lb-panel-subtitle { font-size: 12px; color: rgba(255,255,255,.75); margin-top: 2px; }
      .lb-close {
        background: rgba(255,255,255,.2); border: none; border-radius: 8px;
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; color: #fff; flex-shrink: 0; transition: background .15s;
      }
      .lb-close:hover { background: rgba(255,255,255,.35); }
      .lb-close svg { width: 16px; height: 16px; }

      .lb-panel-body {
        flex: 1; overflow-y: auto;
        padding: 1rem;
        display: flex; flex-direction: column; gap: 12px;
        background: var(--neutral-100, #f5f7fa);
      }

      /* ── Tarjeta racha ── */
      .lb-streak-card {
        background: var(--neutral-white, #fff);
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        box-shadow: var(--shadow-sm, 0 1px 4px rgba(0,0,0,.06));
        border: 1px solid var(--neutral-300, #eee);
      }
      .lb-streak-top { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
      .lb-flame { width: 36px; height: 36px; flex-shrink: 0; }
      .lb-streak-label {
        font-size: 11px; font-weight: 600; letter-spacing: .4px;
        color: var(--neutral-500, #888);
      }
      .lb-streak-value {
        font-size: 26px; font-weight: 700; line-height: 1.1;
        color: var(--whirlpool-accomplished-blue, #004976);
      }
      .lb-streak-unit { font-size: 13px; font-weight: 500; color: var(--neutral-500, #666); }
      .lb-streak-best { font-size: 12px; color: var(--neutral-500, #aaa); margin-bottom: 14px; }
      .lb-streak-best strong { color: var(--whirlpool-accomplished-blue, #004976); }

      .lb-streak-week { display: flex; gap: 5px; justify-content: space-between; margin-bottom: 4px; }
      .lb-day {
        flex: 1; aspect-ratio: 1; border-radius: 50%;
        border: 1.5px solid var(--neutral-300, #e8e8e8);
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700;
        color: var(--neutral-400, #ccc);
        background: var(--neutral-200, #fafafa);
      }
      .lb-day--done        { background: #004976 !important; color: #fff !important; border-color: #004976 !important; }
      .lb-day--today       { background: #0099D8 !important; color: #fff !important; border-color: #0099D8 !important; box-shadow: 0 0 0 3px rgba(0,153,216,.2); }
      .lb-day--today-empty { border-color: #0099D8 !important; color: #0099D8 !important; }
      .lb-day--future      { opacity: .35; }
      .lb-streak-week-label { font-size: 10px; color: var(--neutral-400, #bbb); text-align: center; margin-top: 3px; }
      .lb-streak-tip {
        margin-top: 10px; font-size: 12px;
        color: var(--accent-blue, #0099D8);
        background: var(--whirlpool-light-accent, #f0f8ff);
        border-radius: 8px; padding: 7px 10px; text-align: center;
      }

      /* ── Tarjeta ranking ── */
      .lb-ranking-card {
        background: var(--neutral-white, #fff);
        border-radius: 14px;
        box-shadow: var(--shadow-sm, 0 1px 4px rgba(0,0,0,.06));
        border: 1px solid var(--neutral-300, #eee);
        overflow: hidden;
      }
      .lb-ranking-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .9rem 1.25rem .75rem;
        border-bottom: 1px solid var(--neutral-300, #f0f0f0);
      }
      .lb-ranking-title {
        font-size: 13px; font-weight: 700;
        color: var(--whirlpool-accomplished-blue, #004976);
      }
      .lb-tabs {
        display: flex; gap: 2px;
        background: var(--neutral-200, #f0f2f5);
        border-radius: 7px; padding: 2px;
      }
      .lb-tab {
        padding: 4px 14px; border: none;
        background: transparent; border-radius: 5px;
        font-size: 11px; font-weight: 600;
        color: var(--neutral-500, #888);
        cursor: pointer; transition: all .15s; font-family: inherit;
      }
      .lb-tab--active {
        background: var(--neutral-white, #fff);
        color: var(--whirlpool-accomplished-blue, #004976);
        box-shadow: 0 1px 3px rgba(0,0,0,.1);
      }

      .lb-list { padding: .25rem 0; }
      .lb-row {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 1.25rem; transition: background .12s;
      }
      .lb-row:hover { background: var(--neutral-200, #f9f9f9); }
      .lb-row--me {
        background: var(--whirlpool-light-accent, #EBF5FD) !important;
        border-left: 3px solid #0099D8;
      }
      .lb-pos { font-size: 12px; font-weight: 700; color: var(--neutral-400, #bbb); width: 22px; text-align: center; flex-shrink: 0; }
      .lb-pos--medal { font-size: 17px; }
      .lb-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
      .lb-info { flex: 1; min-width: 0; }
      .lb-name { font-size: 13px; font-weight: 500; color: var(--neutral-800, #222); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .lb-name--me { color: var(--whirlpool-accomplished-blue, #004976); font-weight: 700; }
      .lb-you-tag { display: inline-block; font-size: 9px; background: #0099D8; color: #fff; border-radius: 4px; padding: 1px 4px; margin-left: 4px; vertical-align: middle; font-weight: 700; }
      .lb-meta { font-size: 11px; color: var(--neutral-500, #aaa); margin-top: 1px; }
      .lb-right { text-align: right; flex-shrink: 0; }
      .lb-xp { font-size: 13px; font-weight: 700; color: var(--whirlpool-accomplished-blue, #004976); }
      .lb-streak-badge { font-size: 10px; color: #E85D04; margin-top: 1px; }

      .lb-ranking-footer { padding: 8px 1.25rem 10px; border-top: 1px solid var(--neutral-300, #f5f5f5); }
      .lb-footer-note { font-size: 11px; color: var(--neutral-400, #bbb); }

      .lb-error, .lb-empty { padding: 1.5rem 1.25rem; text-align: center; font-size: 12px; color: var(--neutral-500, #bbb); line-height: 1.7; }

      /* ── Skeletons ── */
      .lb-sk {
        background: linear-gradient(90deg, var(--neutral-200, #f0f0f0) 25%, var(--neutral-300, #e8e8e8) 50%, var(--neutral-200, #f0f0f0) 75%);
        background-size: 200% 100%;
        animation: lb-shimmer 1.4s infinite;
        border-radius: 6px;
      }
      @keyframes lb-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
      .lb-streak-skeleton { display: flex; flex-direction: column; gap: 10px; padding: .5rem 0; }
      .lb-sk-title { height: 12px; width: 55%; }
      .lb-sk-val   { height: 28px; width: 35%; }
      .lb-sk-days  { height: 28px; width: 100%; border-radius: 14px; }
      .lb-list-skeleton { padding: .25rem 1.25rem; display: flex; flex-direction: column; gap: 8px; }
      .lb-sk-row { height: 38px; }

    `;
    document.head.appendChild(s);
  }

  function buildDOM() {
    const overlay = document.createElement('div');
    overlay.id = 'lb-overlay';
    overlay.addEventListener('click', closePanel);

    const panel = document.createElement('div');
    panel.id = 'lb-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Ranking y rachas');
    panel.innerHTML = `
      <div class="lb-panel-header">
        <div>
          <div class="lb-panel-title">Ranking y rachas</div>
          <div class="lb-panel-subtitle">Tu progreso vs. tus compañeros</div>
        </div>
        <button class="lb-close" id="lb-close-btn" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="lb-panel-body">
        <div class="lb-streak-card" id="lb-streak-card">
          <div class="lb-streak-skeleton">
            <div class="lb-sk lb-sk-title"></div>
            <div class="lb-sk lb-sk-val"></div>
            <div class="lb-sk lb-sk-days"></div>
          </div>
        </div>
        <div class="lb-ranking-card">
          <div class="lb-ranking-header">
            <span class="lb-ranking-title">Ranking de aprendizaje</span>
            <div class="lb-tabs" role="tablist">
              <button class="lb-tab lb-tab--active" data-tab="global" role="tab" aria-selected="true">Global</button>
              <button class="lb-tab" data-tab="friends" role="tab" aria-selected="false">Amigos</button>
            </div>
          </div>
          <div class="lb-list" id="lb-list">
            <div class="lb-list-skeleton">
              ${[1,2,3,4,5].map(() => '<div class="lb-sk lb-sk-row"></div>').join('')}
            </div>
          </div>
          <div class="lb-ranking-footer">
            <span class="lb-footer-note" id="lb-footer-note">Actualizado hoy</span>
          </div>
        </div>
      </div>
    `;

    const fab = document.createElement('button');
    fab.id = 'lb-fab';
    fab.setAttribute('aria-label', 'Abrir ranking y rachas');
    fab.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M8 21h8M12 17v4M7 4H5a1 1 0 00-1 1v3c0 3.3 2.7 6 6 6h4c3.3 0 6-2.7 6-6V5a1 1 0 00-1-1h-2"/>
        <path d="M7 4V2M17 4V2M7 4h10"/>
      </svg>
      <span id="lb-fab-badge"></span>
    `;
    fab.addEventListener('click', openPanel);

    document.body.appendChild(overlay);
    document.body.appendChild(panel);
    document.body.appendChild(fab);

    panel.querySelector('#lb-close-btn').addEventListener('click', closePanel);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && isOpen) closePanel(); });

    panel.querySelectorAll('.lb-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        panel.querySelectorAll('.lb-tab').forEach(b => {
          b.classList.remove('lb-tab--active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('lb-tab--active');
        btn.setAttribute('aria-selected', 'true');
        currentTab = btn.dataset.tab;
        loadData(currentTab);
      });
    });
  }

  function openPanel() {
    isOpen = true;
    document.getElementById('lb-panel').classList.add('open');
    document.getElementById('lb-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    if (!loaded) { loaded = true; loadData('global'); }
  }

  function closePanel() {
    isOpen = false;
    document.getElementById('lb-panel').classList.remove('open');
    document.getElementById('lb-overlay').classList.remove('open');
    document.body.style.overflow = '';
  }

  function loadData(tab) {
    if (cachedData[tab]) { renderAll(cachedData[tab], tab); return; }
    showListSkeleton();
    fetch(`/api/leaderboard.php?tab=${tab}&limit=5`)
      .then(r => r.json())
      .then(data => {
        cachedData[tab] = data;
        renderAll(data, tab);
        const streak = data.streak?.current ?? 0;
        const badge  = document.getElementById('lb-fab-badge');
        if (streak > 0) { badge.textContent = streak; badge.classList.add('visible'); }
      })
      .catch(() => {
        document.getElementById('lb-list').innerHTML = '<p class="lb-error">No se pudo cargar el ranking.</p>';
      });
  }

  function renderStreak(streak) {
    const card     = document.getElementById('lb-streak-card');
    const current  = streak.current ?? 0;
    const longest  = streak.longest ?? 0;
    const week     = streak.week_activity ?? Array(7).fill(false);
    const todayDow = (new Date().getDay() + 6) % 7;

    const daysHtml = DAYS_ES.map((d, i) => {
      let cls = 'lb-day';
      if      (i === todayDow && week[i]) cls += ' lb-day--today';
      else if (i === todayDow)            cls += ' lb-day--today-empty';
      else if (i > todayDow)              cls += ' lb-day--future';
      else if (week[i])                   cls += ' lb-day--done';
      return `<div class="${cls}" title="${DAYS_FULL[i]}">${d}</div>`;
    }).join('');

    const flameColor = current >= 7 ? '#E85D04' : current >= 3 ? '#F4A261' : '#ccc';

    card.innerHTML = `
      <div class="lb-streak-top">
        <svg class="lb-flame" viewBox="0 0 24 24" fill="${flameColor}">
          <path d="M12 2C12 2 7 7 7 13a5 5 0 0010 0C17 7 12 2 12 2zM12 20a3 3 0 01-3-3c0-2.5 3-7 3-7s3 4.5 3 7a3 3 0 01-3 3z"/>
        </svg>
        <div>
          <div class="lb-streak-label">RACHA ACTUAL</div>
          <div class="lb-streak-value">${current} <span class="lb-streak-unit">día${current !== 1 ? 's' : ''}</span></div>
        </div>
      </div>
      <div class="lb-streak-best">Mejor racha: <strong>${longest} días</strong></div>
      <div class="lb-streak-week">${daysHtml}</div>
      <div class="lb-streak-week-label">Esta semana</div>
      ${current === 0 ? '<div class="lb-streak-tip">¡Completa una lección hoy para iniciar tu racha!</div>' : ''}
    `;
  }

  function renderList(data) {
    const list    = document.getElementById('lb-list');
    const ranking = data.ranking ?? [];
    const myPos   = data.my_position;

    if (ranking.length === 0) {
      list.innerHTML = '<p class="lb-empty">Aún no hay datos.<br>¡Completa cursos para aparecer aquí!</p>';
      document.getElementById('lb-footer-note').textContent = '';
      return;
    }

    list.innerHTML = ranking.map((u, idx) => {
      const color    = AVATAR_COLORS[idx % AVATAR_COLORS.length];
      const posLabel = u.position <= 3 ? MEDALS[u.position - 1] : `${u.position}`;
      const xpFmt    = u.total_xp.toLocaleString('es-MX');
      return `
        <div class="lb-row ${u.is_me ? 'lb-row--me' : ''}">
          <span class="lb-pos ${u.position <= 3 ? 'lb-pos--medal' : ''}">${posLabel}</span>
          <div class="lb-avatar" style="background:${color.bg};color:${color.text}">${u.initials}</div>
          <div class="lb-info">
            <div class="lb-name ${u.is_me ? 'lb-name--me' : ''}">${escHtml(u.full_name)}${u.is_me ? ' <span class="lb-you-tag">tú</span>' : ''}</div>
            <div class="lb-meta">${u.completed_courses} curso${u.completed_courses !== 1 ? 's' : ''} · ${xpFmt} XP</div>
          </div>
          <div class="lb-right">
            <div class="lb-xp">${xpFmt}</div>
            ${u.current_streak > 0 ? `<div class="lb-streak-badge">🔥 ${u.current_streak}d</div>` : ''}
          </div>
        </div>`;
    }).join('');

    const note = document.getElementById('lb-footer-note');
    note.textContent = (myPos && myPos > ranking.length) ? `Tu posición global: #${myPos}` : 'Actualizado hoy';
  }

  function renderAll(data, tab) {
    if (tab !== currentTab) return;
    renderStreak(data.streak);
    renderList(data);
  }

  function showListSkeleton() {
    document.getElementById('lb-list').innerHTML = `
      <div class="lb-list-skeleton">
        ${[1,2,3,4,5].map(() => '<div class="lb-sk lb-sk-row"></div>').join('')}
      </div>`;
  }

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function init() { injectStyles(); buildDOM(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();