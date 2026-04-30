<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Publicación - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
    <link rel="stylesheet" href="css/courses.css?v=1.11">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="preconnect" href="https:
    <link rel="preconnect" href="https:
    <link href="https:
    <style>
        :root {
            --accomplished-blue : #004976;
            --accent-blue       : #0099D8;
            --light-accent      : #E5F4FC;
            --neutral-50        : #F8FAFC;
            --neutral-100       : #F1F5F9;
            --neutral-200       : #E2E8F0;
            --neutral-600       : #475569;
            --neutral-700       : #334155;
            --neutral-900       : #0F172A;
            --shadow-sm         : 0 1px 3px rgba(0,0,0,.08);
            --shadow-md         : 0 4px 16px rgba(0,0,0,.10);
            --radius-md         : 10px;
            --radius-lg         : 16px;
            --transition        : 0.2s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Open Sans', sans-serif;
            background: var(--neutral-50);
            color: var(--neutral-900);
            padding-top: 72px;
        }

        .main-nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: white;
            border-bottom: 1px solid var(--neutral-200);
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            height: 72px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 2rem;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--accomplished-blue);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        .nav-link {
            color: var(--neutral-600);
            text-decoration: none;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            transition: all var(--transition);
        }

        .nav-link:hover  { background: var(--light-accent); color: var(--accent-blue); }
        .nav-link.active { background: var(--light-accent); color: var(--accomplished-blue); }

        .nav-user { position: relative; cursor: pointer; }

        .user-name {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            color: var(--accomplished-blue);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 36px; height: 36px;
            background: var(--accomplished-blue);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 12px); right: 0;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            min-width: 180px;
            opacity: 0; visibility: hidden;
            transform: translateY(-6px);
            transition: all var(--transition);
            border: 1px solid var(--neutral-200);
            overflow: hidden;
        }

        .nav-user:hover .user-dropdown {
            opacity: 1; visibility: visible; transform: translateY(0);
        }

        .user-dropdown a {
            display: block;
            padding: 0.75rem 1.25rem;
            color: var(--neutral-700);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .user-dropdown a:hover { background: var(--neutral-100); }

        .form-wrapper {
            max-width: 820px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--neutral-600);
            margin-bottom: 1rem;
        }

        .breadcrumb a { color: var(--accent-blue); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .form-header h1 {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--accomplished-blue);
        }

        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            padding: 2.5rem;
        }

        .form-group { margin-bottom: 1.75rem; }

        label {
            display: block;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--neutral-700);
            margin-bottom: 0.5rem;
        }

        label span {
            font-weight: 400;
            color: var(--neutral-600);
            font-family: 'Open Sans', sans-serif;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--neutral-200);
            border-radius: var(--radius-md);
            font-family: 'Open Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--neutral-900);
            background: white;
            transition: border-color var(--transition);
            outline: none;
        }

        input[type="text"]:focus,
        textarea:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0,153,216,.12);
        }

        textarea { resize: vertical; min-height: 280px; line-height: 1.7; }

        .cover-dropzone {
            border: 2px dashed var(--neutral-200);
            border-radius: var(--radius-md);
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
            background: var(--neutral-50);
            position: relative;
        }

        .cover-dropzone:hover,
        .cover-dropzone.dragover {
            border-color: var(--accent-blue);
            background: var(--light-accent);
        }

        .cover-dropzone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
        }

        .cover-dropzone svg {
            width: 36px; height: 36px;
            color: var(--accent-blue);
            margin-bottom: 0.75rem;
        }

        .cover-dropzone p {
            font-size: 0.875rem;
            color: var(--neutral-600);
        }

        .cover-dropzone strong { color: var(--accent-blue); }

        #cover-preview {
            display: none;
            position: relative;
            margin-top: 1rem;
        }

        #cover-preview img {
            width: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: var(--radius-md);
        }

        #cover-preview button {
            position: absolute;
            top: 0.5rem; right: 0.5rem;
            background: rgba(0,0,0,.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px; height: 28px;
            cursor: pointer;
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
        }

        .char-count {
            text-align: right;
            font-size: 0.75rem;
            color: var(--neutral-600);
            margin-top: 0.35rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--neutral-200);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            border-radius: 99px;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--neutral-100);
            color: var(--neutral-700);
        }

        .btn-secondary:hover { background: var(--neutral-200); }

        .btn-primary {
            background: var(--accomplished-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(0,73,118,.3);
        }

        .btn-primary:hover {
            background: #003a60;
            transform: translateY(-1px);
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: none;
        }
    </style>
</head>
<body>

<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
                <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="brand-logo">
            </div>

        <div class="nav-menu" id="navMenu">
                <a href="news.php" class="nav-link">Noticias</a>
                <a href="courses.php" class="nav-link">Cursos</a>
                <a href="blog.php" class="nav-link active">Comunidad</a>
                <a href="friends.php" class="nav-link">Amigos</a>
        </div>

        <div class="nav-user">
            <div class="user-name">
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <?php echo htmlspecialchars($user_name); ?>
            </div>
            <div class="user-dropdown">
                <a href="profile.php">Mi Perfil</a>
                <a href="process/logout_process.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</nav>

<div class="form-wrapper">
    <div class="form-header">
        <div class="breadcrumb">
            <a href="blog.php">Blog</a>
            <span>›</span>
            <span>Nueva publicación</span>
        </div>
        <h1>Nueva Publicación</h1>
    </div>

    <div class="form-card">
        <div class="alert-error" id="alert-error"></div>

        <form id="blog-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_post">

            <!-- Título -->
            <div class="form-group">
                <label for="title">Título <span>(requerido)</span></label>
                <input type="text"
                       id="title"
                       name="title"
                       placeholder="Escribe un título claro y descriptivo"
                       maxlength="255"
                       required>
                <div class="char-count"><span id="title-count">0</span>/255</div>
            </div>

            <!-- Imagen de portada -->
            <div class="form-group">
                <label>Imagen de portada <span>(opcional)</span></label>
                <div class="cover-dropzone" id="cover-dropzone">
                    <input type="file" name="cover_image" id="cover-input"
                           accept="image/jpeg,image/png,image/webp">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <p><strong>Haz clic o arrastra</strong> una imagen aquí</p>
                    <p>JPG, PNG o WebP — máximo 5 MB</p>
                </div>
                <div id="cover-preview">
                    <img id="cover-img" src="" alt="Vista previa">
                    <button type="button" onclick="removeCover()" title="Quitar imagen">×</button>
                </div>
            </div>

            <!-- Contenido -->
            <div class="form-group">
                <label for="content">Contenido <span>(requerido)</span></label>
                <textarea id="content"
                          name="content"
                          placeholder="Comparte tu conocimiento, experiencia o reflexión..."
                          required></textarea>
            </div>

            <div class="form-actions">
                <a href="blog.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
                    </svg>
                    Publicar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const titleInput = document.getElementById('title');
titleInput.addEventListener('input', () => {
    document.getElementById('title-count').textContent = titleInput.value.length;
});

document.getElementById('cover-input').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showError('La imagen no debe superar los 5 MB.');
        this.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('cover-img').src = e.target.result;
        document.getElementById('cover-preview').style.display = 'block';
        document.getElementById('cover-dropzone').style.display = 'none';
    };
    reader.readAsDataURL(file);
});

const dropzone = document.getElementById('cover-dropzone');
dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('dragover'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    const fileInput = document.getElementById('cover-input');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

function removeCover() {
    document.getElementById('cover-input').value = '';
    document.getElementById('cover-preview').style.display = 'none';
    document.getElementById('cover-dropzone').style.display = 'block';
}

function showError(msg) {
    const el = document.getElementById('alert-error');
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.getElementById('blog-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Publicando…';

    const formData = new FormData(this);

    try {
        const res  = await fetch('process/blog_post_process.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            window.location.href = `blog-post.php?id=${data.post_id}`;
        } else {
            showError(data.message || 'Ocurrió un error al publicar. Intenta nuevamente.');
            btn.disabled = false;
            btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/></svg> Publicar`;
        }
    } catch (err) {
        showError('Error de conexión. Verifica tu red e intenta de nuevo.');
        btn.disabled = false;
    }
});
</script>
    <script src="/js/faq-widget.js?v=1.12.1"></script>
    <script src="/js/theme.js?v=1.01.04"></script>
    <script src="js/notifications-widget.js"></script>
<!-- JS menú móvil -->
<script>
(function(){
    function toggleMenu(e) {
        e.stopPropagation();
        var m = document.getElementById('navMenu');
        var h = document.getElementById('navHamburger');
        if (!m) return;
        var isOpen = m.classList.toggle('open');
        if (h) h.classList.toggle('open', isOpen);
    }
    function closeMenu() {
        var m = document.getElementById('navMenu');
        var h = document.getElementById('navHamburger');
        if (m) m.classList.remove('open');
        if (h) h.classList.remove('open');
    }

    var btn = document.createElement('button');
    btn.id = 'navHamburger';
    btn.className = 'nav-hamburger';
    btn.setAttribute('aria-label', 'Abrir menú');
    btn.innerHTML = '<span></span><span></span><span></span>';
    btn.addEventListener('click', toggleMenu);
    document.body.appendChild(btn);

    document.addEventListener('click', function(e) {
        var m = document.getElementById('navMenu');
        if (m && m.classList.contains('open')) {
            if (!m.contains(e.target)) {
                closeMenu();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var m = document.getElementById('navMenu');
        if (m) {
            m.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
})();
</script>
</body>
</html>