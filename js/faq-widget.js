(function () {
    'use strict';

    const faqs = [
        { q: '¿Qué es esta plataforma?', c: 'general', a: 'Es una plataforma digital de aprendizaje donde los usuarios pueden acceder a cursos, lecciones y contenido educativo en línea.' },
        { q: '¿Necesito registrarme para usar la plataforma?', c: 'cuenta', a: 'Sí, es necesario crear una cuenta para poder acceder a los cursos y guardar tu progreso.' },
        { q: '¿Cómo puedo registrarme?', c: 'cuenta', a: 'Debes completar el formulario de registro con tus datos básicos como nombre, correo electrónico y contraseña.' },
        { q: '¿Cómo inicio sesión en mi cuenta?', c: 'cuenta', a: 'Ingresa tu correo electrónico y contraseña en la página de inicio de sesión.' },
        { q: '¿Qué hago si olvidé mi contraseña?', c: 'cuenta', a: 'Puedes utilizar la opción de recuperación de contraseña para restablecerla.' },
        { q: '¿Dónde puedo ver los cursos disponibles?', c: 'cursos', a: 'En la sección de cursos de la plataforma podrás ver todos los cursos disponibles.' },
        { q: '¿Los cursos tienen diferentes niveles?', c: 'cursos', a: 'Dependiendo del curso, pueden existir diferentes niveles o módulos que se deben completar.' },
        { q: '¿Cómo empiezo un curso?', c: 'cursos', a: 'Solo debes seleccionar el curso y comenzar con la primera lección.' },
        { q: '¿Los cursos están divididos en partes?', c: 'cursos', a: 'Sí, cada curso está organizado en módulos y cada módulo contiene varias lecciones.' },
        { q: '¿Debo completar las lecciones en orden?', c: 'cursos', a: 'Generalmente sí, ya que algunas lecciones dependen del contenido anterior.' },
        { q: '¿La plataforma guarda mi progreso automáticamente?', c: 'progreso', a: 'Sí, el sistema guarda tu progreso conforme avanzas en las lecciones.' },
        { q: '¿Puedo continuar un curso después de salir?', c: 'progreso', a: 'Sí, puedes regresar en cualquier momento y continuar desde donde te quedaste.' },
        { q: '¿Puedo repetir una lección?', c: 'progreso', a: 'Sí, puedes revisar cualquier lección nuevamente cuando lo necesites.' },
        { q: '¿Puedo tomar varios cursos al mismo tiempo?', c: 'progreso', a: 'Sí, puedes estar inscrito en varios cursos y avanzar en ellos cuando quieras.' },
        { q: '¿Hay categorías de cursos?', c: 'cursos', a: 'Sí, los cursos pueden estar organizados por categorías o temas.' },
        { q: '¿Cuánto tiempo tengo para terminar un curso?', c: 'cursos', a: 'Normalmente puedes avanzar a tu propio ritmo, sin un límite de tiempo específico.' },
        { q: '¿Necesito descargar algo para usar la plataforma?', c: 'general', a: 'No, puedes acceder directamente desde tu navegador web.' },
        { q: '¿Puedo usar la plataforma desde mi celular?', c: 'general', a: 'Sí, la plataforma puede utilizarse desde dispositivos móviles si el navegador lo permite.' },
        { q: '¿Puedo interactuar con otros usuarios?', c: 'comunidad', a: 'Sí, en algunas secciones como el blog, comentarios y la sección de amigos puedes interactuar con otros usuarios.' },
        { q: '¿Puedo comentar en las publicaciones del blog?', c: 'comunidad', a: 'Sí, los usuarios pueden dejar comentarios en las publicaciones disponibles.' },
        { q: '¿Puedo reaccionar a las publicaciones?', c: 'comunidad', a: 'Sí, puedes usar reacciones para mostrar tu opinión sobre el contenido.' },
        { q: '¿Qué tipo de contenido hay en el blog?', c: 'comunidad', a: 'El blog contiene artículos, información educativa y contenido relacionado con los cursos.' },
        { q: '¿Cómo sé qué lecciones ya completé?', c: 'progreso', a: 'El sistema muestra visualmente tu progreso dentro de cada curso.' },
        { q: '¿La plataforma guarda mi historial de aprendizaje?', c: 'progreso', a: 'Sí, el sistema registra las lecciones que has completado.' },
        { q: '¿Puedo ver mi perfil de usuario?', c: 'cuenta', a: 'Sí, puedes acceder a tu perfil donde se muestra tu información y progreso.' },
        { q: '¿Puedo actualizar mis datos personales?', c: 'cuenta', a: 'Sí, desde tu perfil puedes modificar tu información si es necesario.' },
        { q: '¿Puedo cambiar mi contraseña?', c: 'cuenta', a: 'Sí, desde la configuración de tu cuenta puedes actualizar tu contraseña.' },
        { q: '¿Qué hago si tengo problemas técnicos?', c: 'soporte', a: 'Puedes utilizar el chatbot o contactar al administrador del sistema.' },
        { q: '¿La plataforma tiene un chatbot?', c: 'soporte', a: 'Sí, existe un chatbot que puede ayudarte con dudas básicas sobre cursos y la plataforma.' },
        { q: '¿Qué tipo de preguntas puede responder el chatbot?', c: 'soporte', a: 'Puede ayudarte a encontrar cursos, resolver dudas sobre el sistema o guiarte dentro de la plataforma.' },
        { q: '¿Quién administra la plataforma?', c: 'general', a: 'La plataforma es administrada por un equipo que gestiona usuarios, cursos y contenido.' },
        { q: '¿Los administradores pueden crear nuevos cursos?', c: 'general', a: 'Sí, los administradores pueden agregar y actualizar cursos dentro del sistema.' },
        { q: '¿Los comentarios son moderados?', c: 'comunidad', a: 'Sí, los administradores pueden revisar y moderar los comentarios.' },
        { q: '¿Puedo acceder a la plataforma en cualquier momento?', c: 'general', a: 'Sí, mientras tengas conexión a internet puedes acceder cuando quieras. La plataforma está disponible las 24 horas.' },
        { q: '¿Mis datos están protegidos?', c: 'soporte', a: 'Sí, la plataforma utiliza mecanismos de seguridad para proteger la información de los usuarios.' },
        { q: '¿Puedo ver qué cursos estoy tomando actualmente?', c: 'progreso', a: 'Sí, desde tu panel principal puedes ver tus cursos activos.' },
        { q: '¿Puedo dejar un curso y retomarlo después?', c: 'progreso', a: 'Sí, puedes regresar al curso en cualquier momento sin perder tu progreso.' },
        { q: '¿Puedo usar diferentes dispositivos para acceder a mi cuenta?', c: 'cuenta', a: 'Sí, puedes iniciar sesión desde distintos dispositivos y tu progreso se mantiene.' },
        { q: '¿Qué debo hacer si encuentro un error en la plataforma?', c: 'soporte', a: 'Puedes reportarlo al administrador o utilizar el chatbot de soporte disponible.' },
        { q: '¿Puedo agregar amigos en la plataforma?', c: 'comunidad', a: 'Sí, en la sección de Amigos puedes enviar solicitudes de amistad a otros usuarios y ver su progreso.' },
    ];

    const categories = {
        all:       { label: 'Todas',     icon: '◈' },
        general:   { label: 'General',   icon: '◉' },
        cuenta:    { label: 'Mi Cuenta', icon: '◎' },
        cursos:    { label: 'Cursos',    icon: '◐' },
        progreso:  { label: 'Progreso',  icon: '◑' },
        comunidad: { label: 'Comunidad', icon: '◒' },
        soporte:   { label: 'Soporte',   icon: '◓' },
    };

    const css = `
        @import url('https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Open+Sans:wght@400;500;600&display=swap');

        #wlp-faq-btn {
            position: fixed;
            bottom: 28px;
            left: 28px;
            z-index: 9998;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #004976;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,73,118,.18);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .2s, transform .2s, box-shadow .2s;
            color: #004976;
            font-family: 'Nunito Sans', sans-serif;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -.5px;
            gap: 3px;
            padding: 0 10px;
            white-space: nowrap;
            width: auto;
            border-radius: 22px;
        }
        #wlp-faq-btn:hover {
            background: #004976;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,73,118,.28);
        }
        #wlp-faq-btn svg {
            width: 15px; height: 15px;
            flex-shrink: 0;
            fill: currentColor;
        }

        /* ── Overlay ── */
        #wlp-faq-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,20,40,.45);
            backdrop-filter: blur(3px);
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }
        #wlp-faq-overlay.wlp-faq-open {
            opacity: 1;
            pointer-events: auto;
        }

        /* ── Modal ── */
        #wlp-faq-modal {
            position: fixed;
            bottom: 80px;
            left: 28px;
            z-index: 10000;
            width: 500px;
            max-height: 600px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0,20,50,.22);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(16px) scale(.97);
            opacity: 0;
            pointer-events: none;
            transition: transform .25s cubic-bezier(.34,1.4,.64,1), opacity .22s ease;
        }
        #wlp-faq-modal.wlp-faq-open {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: auto;
        }

        /* ── Modal header ── */
        #wlp-faq-header {
            background: linear-gradient(135deg, #003C64 0%, #0099D8 100%);
            padding: 18px 20px 14px;
            flex-shrink: 0;
            position: relative;
        }
        #wlp-faq-header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        #wlp-faq-title {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }
        #wlp-faq-subtitle {
            font-family: 'Open Sans', sans-serif;
            font-size: 12px;
            color: rgba(255,255,255,.7);
            margin-top: 3px;
        }
        #wlp-faq-close {
            background: rgba(255,255,255,.15);
            border: none;
            border-radius: 8px;
            width: 30px; height: 30px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: background .18s;
        }
        #wlp-faq-close:hover { background: rgba(255,255,255,.28); }
        #wlp-faq-close svg { width: 16px; height: 16px; fill: #fff; }

        /* Search */
        #wlp-faq-search-wrap {
            position: relative;
        }
        #wlp-faq-search-wrap svg {
            position: absolute;
            left: 11px; top: 50%;
            transform: translateY(-50%);
            width: 14px; height: 14px;
            fill: none; stroke: rgba(255,255,255,.6); stroke-width: 2;
            pointer-events: none;
        }
        #wlp-faq-search {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 10px;
            background: rgba(255,255,255,.12);
            font-family: 'Open Sans', sans-serif;
            font-size: 13px;
            color: #fff;
            outline: none;
            transition: border-color .18s, background .18s;
        }
        #wlp-faq-search::placeholder { color: rgba(255,255,255,.5); }
        #wlp-faq-search:focus { border-color: rgba(255,255,255,.55); background: rgba(255,255,255,.18); }

        /* Category chips */
        #wlp-faq-cats {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 12px 20px;
            flex-shrink: 0;
            border-bottom: 1px solid #eef1f5;
            background: #fafbfc;
        }
        
        .wlp-faq-cat {
            padding: 5px 12px;
            border-radius: 20px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            font-family: 'Nunito Sans', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            white-space: nowrap;
            transition: all .16s;
            display: flex; align-items: center; gap: 5px;
        }
        .wlp-faq-cat:hover { border-color: #0099D8; color: #0099D8; }
        .wlp-faq-cat.active {
            background: #004976;
            border-color: #004976;
            color: #fff;
        }

        /* ── FAQ list ── */
        #wlp-faq-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0 12px;
            scrollbar-width: thin;
            scrollbar-color: #cdd8e3 transparent;
        }
        #wlp-faq-list::-webkit-scrollbar { width: 4px; }
        #wlp-faq-list::-webkit-scrollbar-thumb { background: #cdd8e3; border-radius: 4px; }

        .wlp-faq-item {
            border-bottom: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .wlp-faq-item:last-child { border-bottom: none; }

        .wlp-faq-q {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 13px 20px;
            cursor: pointer;
            font-family: 'Nunito Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            transition: background .15s, color .15s;
            user-select: none;
        }
        .wlp-faq-q:hover { background: #f7f9fb; color: #004976; }
        .wlp-faq-q.open   { color: #004976; background: #f0f7ff; }

        .wlp-faq-chevron {
            width: 18px; height: 18px;
            flex-shrink: 0;
            fill: none; stroke: currentColor; stroke-width: 2.5;
            transition: transform .22s ease;
        }
        .wlp-faq-q.open .wlp-faq-chevron { transform: rotate(180deg); }

        .wlp-faq-a {
            max-height: 0;
            overflow: hidden;
            transition: max-height .28s cubic-bezier(.4,0,.2,1);
        }
        .wlp-faq-a-inner {
            padding: 0 20px 14px 20px;
            font-family: 'Open Sans', sans-serif;
            font-size: 13px;
            line-height: 1.65;
            color: #475569;
            border-left: 3px solid #0099D8;
            margin: 0 20px 0 20px;
            padding-left: 12px;
            border-radius: 0 0 0 3px;
        }

        /* highlight */
        .wlp-faq-hl { background: #fef08a; border-radius: 2px; padding: 0 1px; }

        /* No results */
        #wlp-faq-empty {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-family: 'Open Sans', sans-serif;
            font-size: 13px;
        }
        #wlp-faq-empty svg { width: 36px; height: 36px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        #wlp-faq-empty strong { display: block; font-family: 'Nunito Sans', sans-serif; font-size: 15px; color: #64748b; margin-bottom: 4px; }

        @media (max-width: 540px) {
            #wlp-faq-modal { width: calc(100vw - 24px); left: 12px; bottom: 72px; max-height: 70vh; }
            #wlp-faq-btn   { left: 12px; bottom: 12px; }
        }
    `;

    const html = `
        <style>${css}</style>

        <button id="wlp-faq-btn" aria-label="Preguntas frecuentes">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>
            FAQ
        </button>

        <div id="wlp-faq-overlay"></div>

        <div id="wlp-faq-modal" role="dialog" aria-label="Preguntas Frecuentes" aria-modal="true">
            <div id="wlp-faq-header">
                <div id="wlp-faq-header-top">
                    <div>
                        <div id="wlp-faq-title">Preguntas Frecuentes</div>
                        <div id="wlp-faq-subtitle">Encuentra respuestas rápidas sobre la plataforma</div>
                    </div>
                    <button id="wlp-faq-close" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>
                <div id="wlp-faq-search-wrap">
                    <svg viewBox="0 0 20 20"><path d="M8 15A7 7 0 108 1a7 7 0 000 14zM15 15l3.5 3.5" stroke-linecap="round"/></svg>
                    <input id="wlp-faq-search" type="text" placeholder="Buscar pregunta..." maxlength="80" autocomplete="off" aria-label="Buscar en FAQ">
                </div>
            </div>

            <div id="wlp-faq-cats"></div>

            <div id="wlp-faq-list" role="list"></div>
            <div id="wlp-faq-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <strong>Sin resultados</strong>
                Intenta con otra palabra clave
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const faqBtn    = document.getElementById('wlp-faq-btn');
    const overlay   = document.getElementById('wlp-faq-overlay');
    const modal     = document.getElementById('wlp-faq-modal');
    const closeBtn  = document.getElementById('wlp-faq-close');
    const searchEl  = document.getElementById('wlp-faq-search');
    const listEl    = document.getElementById('wlp-faq-list');
    const emptyEl   = document.getElementById('wlp-faq-empty');
    const catsEl    = document.getElementById('wlp-faq-cats');

    let isOpen       = false;
    let activecat    = 'all';
    let openIndex    = -1;

    Object.entries(categories).forEach(([key, val]) => {
        const btn = document.createElement('button');
        btn.className = 'wlp-faq-cat' + (key === 'all' ? ' active' : '');
        btn.dataset.cat = key;
        btn.innerHTML = `<span>${val.icon}</span>${val.label}`;
        btn.addEventListener('click', () => {
            activecat = key;
            document.querySelectorAll('.wlp-faq-cat').forEach(b => b.classList.toggle('active', b.dataset.cat === key));
            openIndex = -1;
            render();
        });
        catsEl.appendChild(btn);
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function highlight(text, query) {
        return escHtml(text);
    }

    function render() {
        const q = searchEl.value.trim().toLowerCase();
        const filtered = faqs.filter((f) => {
            const catOk  = activecat === 'all' || f.c === activecat;
            const termOk = !q || f.q.toLowerCase().includes(q);
            return catOk && termOk;
        });

        listEl.innerHTML = '';

        if (filtered.length === 0) {
            emptyEl.style.display = 'block';
            return;
        }
        emptyEl.style.display = 'none';

        filtered.forEach((f) => {
            const globalIdx = faqs.indexOf(f);
            const isOpen_ = openIndex === globalIdx;

            const item = document.createElement('div');
            item.className = 'wlp-faq-item';
            item.setAttribute('role', 'listitem');

            item.innerHTML = `
                <div class="wlp-faq-q${isOpen_ ? ' open' : ''}" tabindex="0" role="button" aria-expanded="${isOpen_}">
                    ${highlight(f.q, q)}
                    <svg class="wlp-faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="wlp-faq-a" style="max-height:${isOpen_ ? '300px' : '0'}">
                    <div class="wlp-faq-a-inner">${highlight(f.a, q)}</div>
                </div>
            `;

            const qEl = item.querySelector('.wlp-faq-q');
            const aEl = item.querySelector('.wlp-faq-a');

            function toggle() {
                const wasOpen = openIndex === globalIdx;
                openIndex = wasOpen ? -1 : globalIdx;

                document.querySelectorAll('.wlp-faq-q').forEach(el => {
                    el.classList.remove('open');
                    el.setAttribute('aria-expanded', 'false');
                });
                document.querySelectorAll('.wlp-faq-a').forEach(el => {
                    el.style.maxHeight = '0';
                });

                if (!wasOpen) {
                    qEl.classList.add('open');
                    qEl.setAttribute('aria-expanded', 'true');
                    aEl.style.maxHeight = '300px';
                    setTimeout(() => item.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
                }
            }

            qEl.addEventListener('click', toggle);
            qEl.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });

            listEl.appendChild(item);
        });
    }

    function openFaq() {
        isOpen = true;
        modal.classList.add('wlp-faq-open');
        overlay.classList.add('wlp-faq-open');
        render();
        setTimeout(() => searchEl.focus(), 200);
    }

    function closeFaq() {
        isOpen = false;
        modal.classList.remove('wlp-faq-open');
        overlay.classList.remove('wlp-faq-open');
    }

    faqBtn.addEventListener('click', () => isOpen ? closeFaq() : openFaq());
    closeBtn.addEventListener('click', closeFaq);
    overlay.addEventListener('click', closeFaq);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && isOpen) closeFaq();
    });

    searchEl.addEventListener('input', () => {
        openIndex = -1;
        render();
    });

    render();

})();