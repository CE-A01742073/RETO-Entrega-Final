// Sidebar compartido del panel admin.
// Requiere window.ADMIN_NAME y window.ADMIN_ACTIVE definidos antes de cargar.

(function () {
  const NAV_ADMIN = [
    {
      section: "General",
      items: [
        {
          key: "dashboard",
          label: "Dashboard",
          href: "index.php",
          icon: `<rect x="3" y="3" width="7" height="7" rx="1"/>
                 <rect x="14" y="3" width="7" height="7" rx="1"/>
                 <rect x="3" y="14" width="7" height="7" rx="1"/>
                 <rect x="14" y="14" width="7" height="7" rx="1"/>`,
        },
      ],
    },
    {
      section: "Contenido",
      items: [
        {
          key: "courses",
          label: "Gestionar Cursos",
          href: "courses.php",
          icon: `<path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>`,
        },
        {
          key: "course-form",
          label: "Nuevo Curso",
          href: "course-form.php",
          icon: `<path d="M12 4v16m8-8H4"/>`,
        },
      ],
    },
    {
      section: "Recursos",
      items: [
        {
          key: "blog",
          label: "Blog",
          href: "blog.php",
          icon: `<path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z"/>
                 <path d="M17 20v-8H7v8M7 4v4h8"/>`,
        },
      ],
    },
    {
      section: "Usuarios",
      items: [
        {
          key: "users_stats",
          label: "Estadísticas de Usuarios",
          href: "users_stats.php",
          icon: `<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                 <circle cx="9" cy="7" r="4"/>
                 <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>`,
        },
        {
          key: "users",
          label: "Información de Usuarios",
          href: "users.php",
          icon: `<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                 <circle cx="9" cy="7" r="4"/>
                 <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>`,
        },
        {
          key: "dropout",
          label: "Predicción de Abandono",
          href: "dropout_predictions.php",
          icon: `<path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
        },
      ],
    },
    {
      section: "Sistema",
      items: [
        {
          key: "__back",
          label: "Volver a la Plataforma",
          href: "../courses.php",
          icon: `<path d="M10 19l-7-7m0 0l7-7m-7 7h18"/>`,
        },
      ],
    },
  ];

  // Menú para instructores
  const NAV_INSTRUCTOR = [
    {
      section: "General",
      items: [
        {
          key: "dashboard",
          label: "Dashboard",
          href: "index.php",
          icon: `<rect x="3" y="3" width="7" height="7" rx="1"/>
                 <rect x="14" y="3" width="7" height="7" rx="1"/>
                 <rect x="3" y="14" width="7" height="7" rx="1"/>
                 <rect x="14" y="14" width="7" height="7" rx="1"/>`,
        },
      ],
    },
    {
      section: "Contenido",
      items: [
        {
          key: "courses",
          label: "Gestionar Cursos",
          href: "courses.php",
          icon: `<path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>`,
        },
        {
          key: "course-form",
          label: "Nuevo Curso",
          href: "course-form.php",
          icon: `<path d="M12 4v16m8-8H4"/>`,
        },
      ],
    },
    {
      section: "Sistema",
      items: [
        {
          key: "__back",
          label: "Volver a la Plataforma",
          href: "../courses.php",
          icon: `<path d="M10 19l-7-7m0 0l7-7m-7 7h18"/>`,
        },
      ],
    },
  ];

  const NAV = (window.ADMIN_ROLE === 'instructor') ? NAV_INSTRUCTOR : NAV_ADMIN;

  const CSS = `
    .admin-sidebar {
      position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
      background: linear-gradient(180deg, #003C64 0%, #002A47 100%);
      display: flex; flex-direction: column; z-index: 100;
      box-shadow: 4px 0 20px rgba(0,0,0,.15);
    }
    .admin-main { margin-left: 260px; min-height: 100vh; }

    .sidebar-header { padding: 1.75rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.1); }
    .sidebar-logo { height: 36px; width: auto; object-fit: contain; margin-bottom: .5rem;
                    filter: brightness(0) invert(1); }
    .sidebar-badge { display: inline-block; background: #0096DC; color: white;
                     font-size: .65rem; font-weight: 700; font-family: 'Nunito Sans', sans-serif;
                     letter-spacing: .08em; text-transform: uppercase;
                     padding: .2rem .6rem; border-radius: 99px; }

    .sidebar-nav { flex: 1; padding: 1.5rem 0; overflow-y: auto; }
    .nav-section-label { font-size: .65rem; font-weight: 700; letter-spacing: .12em;
                         text-transform: uppercase; color: rgba(255,255,255,.4);
                         padding: 0 1.5rem; margin-bottom: .5rem; margin-top: 1.25rem; }
    .nav-section-label:first-child { margin-top: 0; }

    .sidebar-link { display: flex; align-items: center; gap: .875rem; padding: .75rem 1.5rem;
                    color: rgba(255,255,255,.7); text-decoration: none; font-size: .9rem;
                    font-weight: 600; font-family: 'Nunito Sans', sans-serif;
                    transition: all .2s; border-left: 3px solid transparent; }
    .sidebar-link:hover, .sidebar-link.active { background: rgba(255,255,255,.08);
                                                color: white; border-left-color: #0096DC; }
    .sidebar-link svg { width: 18px; height: 18px; flex-shrink: 0; opacity: .8; }
    .sidebar-link.active svg, .sidebar-link:hover svg { opacity: 1; }

    .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,.1); }
    .admin-user-info { display: flex; align-items: center; gap: .75rem; margin-bottom: .75rem; }
    .admin-avatar { width: 36px; height: 36px; border-radius: 50%; background: #0096DC;
                    display: flex; align-items: center; justify-content: center;
                    font-weight: 800; font-family: 'Nunito Sans', sans-serif;
                    color: white; font-size: .85rem; flex-shrink: 0; }
    .admin-name { font-size: .85rem; font-weight: 700; color: white;
                  font-family: 'Nunito Sans', sans-serif; }
    .admin-role-label { font-size: .7rem; color: rgba(255,255,255,.5); }
    .sidebar-logout { display: block; width: 100%; text-align: center; padding: .5rem;
                      background: rgba(255,255,255,.08); color: rgba(255,255,255,.6);
                      border-radius: 6px; text-decoration: none; font-size: .8rem;
                      font-weight: 600; transition: all .2s; }
    .sidebar-logout:hover { background: rgba(255,255,255,.15); color: white; }
    .sidebar-theme-toggle { display: flex; align-items: center; justify-content: space-between;
                             padding: .5rem 1.5rem; margin-bottom: .5rem;
                             color: rgba(255,255,255,.6); font-size: .8rem; font-weight: 600;
                             font-family: 'Nunito Sans', sans-serif; cursor: pointer;
                             border: none; background: transparent; width: 100%; transition: all .2s; }
    .sidebar-theme-toggle:hover { background: rgba(255,255,255,.08); color: white; }
    .toggle-track { width: 36px; height: 20px; background: rgba(255,255,255,.2);
                    border-radius: 10px; position: relative; flex-shrink: 0; }
    .toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px;
                    background: white; border-radius: 50%; transition: transform .25s ease; }
    [data-theme="dark"] .toggle-track { background: #0096DC; }
    [data-theme="dark"] .toggle-thumb { transform: translateX(16px); }

    /* ── Mobile responsive ───────────────────────────────────────────── */
    .admin-sidebar-overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 150;
    }
    .admin-sidebar-overlay.open { display: block; }

    .admin-mobile-toggle {
      display: none; position: fixed; top: 12px; left: 12px; z-index: 200;
      width: 40px; height: 40px; border-radius: 8px; border: none; cursor: pointer;
      background: #003C64; align-items: center; justify-content: center;
      flex-direction: column; gap: 5px; padding: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.3);
    }
    .admin-mobile-toggle span {
      display: block; width: 20px; height: 2px; background: white; border-radius: 2px;
      transition: transform .3s, opacity .3s;
    }
    .admin-mobile-toggle.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .admin-mobile-toggle.open span:nth-child(2) { opacity: 0; }
    .admin-mobile-toggle.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    @media (max-width: 900px) {
      /* Sidebar oculto por defecto */
      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform .3s ease;
        z-index: 160;
      }
      .admin-sidebar.sidebar-open { transform: translateX(0); }
      .admin-main { margin-left: 0 !important; }
      .admin-mobile-toggle { display: flex; }

      /* Topbar */
      .topbar, .admin-topbar {
        padding: 0 8px 0 60px !important;
        height: auto !important;
        min-height: 56px !important;
        flex-wrap: nowrap !important;
        gap: 6px !important;
        overflow: hidden !important;
      }
      .topbar-title, .topbar-subtitle {
        font-size: 14px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 160px !important;
      }
      /* Ocultar texto de botones del topbar, dejar solo icono */
      .topbar .btn, .admin-topbar .btn {
        padding: 6px 8px !important;
        font-size: 0 !important;
        gap: 0 !important;
        min-width: 34px !important;
      }
      .topbar .btn i, .admin-topbar .btn i {
        font-size: 14px !important;
        margin: 0 !important;
      }

      /* Contenido */
      .content, .admin-content { padding: 10px !important; }

      /* ── TABLAS RESPONSIVAS: ocultar columnas marcadas ── */
      .col-hide-mobile { display: none !important; }
      /* Tablas: layout normal, sin romper palabras */
      table { width: 100% !important; table-layout: auto !important; }
      td, th {
        word-break: normal !important;
        white-space: normal !important;
        overflow: visible !important;
      }

      /* Tabs con scroll si necesario */
      .tabs-bar { overflow-x: auto !important; white-space: nowrap !important; flex-wrap: nowrap !important; }
      .tab-btn { flex-shrink: 0 !important; }

      /* Grids de KPIs y charts */
      .kpi-grid { grid-template-columns: repeat(2,1fr) !important; }
      .chart-grid-main, .chart-grid-2, .list-grid, .stats-grid {
        grid-template-columns: 1fr !important;
      }

      /* Formularios en course-form */
      .form-grid, .form-row-2col { grid-template-columns: 1fr !important; }
    }

    @media (max-width: 480px) {
      .kpi-grid { grid-template-columns: 1fr !important; }
      .topbar-title { max-width: 100px !important; }
    }
  `;

  function svg(paths) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${paths}</svg>`;
  }

  function injectCSS() {
    if (document.getElementById("admin-navbar-css")) return;
    const style = document.createElement("style");
    style.id = "admin-navbar-css";
    style.textContent = CSS;
    document.head.appendChild(style);
  }

  function buildSidebar() {
    const activeKey = window.ADMIN_ACTIVE || "";
    const adminName = window.ADMIN_NAME || "Administrador";
    const initial   = adminName.charAt(0).toUpperCase();
    const isInstructor = window.ADMIN_ROLE === 'instructor';
    const badgeLabel   = isInstructor ? 'Panel Instructor' : 'Panel Admin';
    const roleLabel    = isInstructor ? 'Instructor' : 'Administrador';

    const navHTML = NAV.map(({ section, items }) => {
      const links = items
        .map(({ key, label, href, icon }) => {
          const isActive = key === activeKey ? " active" : "";
          return `<a href="${href}" class="sidebar-link${isActive}">${svg(icon)}${label}</a>`;
        })
        .join("");
      return `<div class="nav-section-label">${section}</div>${links}`;
    }).join("");

    const aside = document.createElement("aside");
    aside.className = "admin-sidebar";
    aside.innerHTML = `
      <div class="sidebar-header">
        <img src="../assets/images/logo_whirlpool.png" alt="Whirlpool" class="sidebar-logo">
        <span class="sidebar-badge">${badgeLabel}</span>
      </div>
      <nav class="sidebar-nav">${navHTML}</nav>
      <div class="sidebar-footer">
        <div class="admin-user-info">
          <div class="admin-avatar">${initial}</div>
          <div>
            <div class="admin-name">${adminName}</div>
            <div class="admin-role-label">${roleLabel}</div>
          </div>
        </div>
        <button class="sidebar-theme-toggle" onclick="(function(){var t=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('wl_theme',t);}catch(e){}})()">
          <span>Modo oscuro</span>
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </button>
        <a href="../process/logout_process.php" class="sidebar-logout">Cerrar Sesión</a>
      </div>`;

    document.body.prepend(aside);

    if (!document.getElementById('admin-sidebar-overlay')) {
      var overlay = document.createElement('div');
      overlay.id = 'admin-sidebar-overlay';
      overlay.className = 'admin-sidebar-overlay';
      overlay.onclick = function() { closeSidebar(); };
      document.body.appendChild(overlay);

      var toggle = document.createElement('button');
      toggle.id = 'admin-mobile-toggle';
      toggle.className = 'admin-mobile-toggle';
      toggle.setAttribute('aria-label', 'Abrir menú');
      toggle.innerHTML = '<span></span><span></span><span></span>';
      toggle.onclick = function() {
        var isOpen = aside.classList.toggle('sidebar-open');
        toggle.classList.toggle('open', isOpen);
        overlay.classList.toggle('open', isOpen);
      };
      document.body.appendChild(toggle);

      aside.querySelectorAll('.sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
          if (window.innerWidth <= 900) closeSidebar();
        });
      });
    }

    function closeSidebar() {
      aside.classList.remove('sidebar-open');
      var t = document.getElementById('admin-mobile-toggle');
      var o = document.getElementById('admin-sidebar-overlay');
      if(t) t.classList.remove('open');
      if(o) o.classList.remove('open');
    }

    var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    var sidebarLogo = aside.querySelector('.sidebar-logo');
    if (sidebarLogo) {
      var logoFile = currentTheme === 'dark' ? 'logo_whirlpool_oscuro.png' : 'logo_whirlpool_claro.png';
      sidebarLogo.src = '../assets/images/' + logoFile;
    }

    const main = document.querySelector("main");
    if (main && !main.classList.contains("admin-main")) {
      main.classList.add("admin-main");
    }
  }

  (function initTheme() {
    var stored = null;
    try { stored = localStorage.getItem('wl_theme'); } catch(e) {}
    var preferred = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', preferred);
    if (!document.getElementById('wl-dark-mode-css')) {
      var link = document.createElement('link');
      link.id   = 'wl-dark-mode-css';
      link.rel  = 'stylesheet';
      link.href = '../css/dark-mode.css';
      document.head.appendChild(link);
    }
  })();

  injectCSS();
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", buildSidebar);
  } else {
    buildSidebar();
  }
})();