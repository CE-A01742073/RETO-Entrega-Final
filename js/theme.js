(function () {
    var STORAGE_KEY = 'wl_theme';

    var LOGO_LIGHT = 'logo_whirlpool_claro.png';
    var LOGO_DARK  = 'logo_whirlpool_oscuro.png';

    function getPreferred() {
        var stored = localStorage.getItem(STORAGE_KEY);
        if (stored) return stored;
        return 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
        updateLogos(theme);
        document.querySelectorAll('.theme-toggle').forEach(function(btn) {
            btn.title = theme === 'dark' ? 'Modo claro' : 'Modo oscuro';
        });
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    function updateLogos(theme) {
        var targetFile = theme === 'dark' ? LOGO_DARK : LOGO_LIGHT;

        /* Seleccionar todas las imágenes de logo en la página */
        var logos = document.querySelectorAll(
            'img.brand-logo, img.footer-logo, img.sidebar-logo, ' +
            '.panel-logo img, .auth-panel img'
        );

        logos.forEach(function(img) {
            var src = img.getAttribute('src') || '';

            /* Guardar la ruta base la primera vez */
            if (!img.dataset.logoBase) {
                img.dataset.logoBase = src.replace(/logo_whirlpool[^/]*\.png.*$/, '');
            }

            var newSrc = img.dataset.logoBase + targetFile;

            /* Solo actualizar si el src realmente cambia */
            if (src.indexOf(targetFile) === -1) {
                img.setAttribute('src', newSrc);
            }
        });
    }

    var ICON_MOON = '<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    var ICON_SUN  = '<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';

    function injectStudentToggle() {
        if (document.querySelector('.theme-toggle')) return;
        var container = document.querySelector('.nav-actions, .nav-user');
        if (!container) return;
        var btn = document.createElement('button');
        btn.className = 'theme-toggle';
        btn.type = 'button';
        btn.innerHTML = ICON_MOON + ICON_SUN;
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleTheme();
        });
        container.insertBefore(btn, container.firstChild);
    }

    function initDropdown() {
        var dropdown = document.querySelector('.user-dropdown');
        if (!dropdown) return;

        var trigger = dropdown.parentElement;
        var isOpen = false;

        function hide() {
            dropdown.style.cssText =
                'display:none!important;opacity:0;visibility:hidden;pointer-events:none;';
            isOpen = false;
        }

        function show() {
            dropdown.style.cssText =
                'display:block!important;opacity:1!important;visibility:visible!important;' +
                'pointer-events:auto!important;transform:translateY(0)!important;' +
                'position:absolute;z-index:9999;';
            isOpen = true;
        }

        hide(); 

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            isOpen ? hide() : show();
        });

        dropdown.addEventListener('click', function(e) {
            e.stopPropagation(); 
        });

        document.addEventListener('click', function() {
            if (isOpen) hide();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isOpen) hide();
        });
    }

    window.ThemeManager = {
        toggle: toggleTheme,
        apply: applyTheme,
        get: function() { return document.documentElement.getAttribute('data-theme') || 'light'; }
    };

    applyTheme(getPreferred());

    function onReady() {
        injectStudentToggle();
        initDropdown();
        updateLogos(getPreferred());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

})();