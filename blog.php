<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';

$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 9;
$offset   = ($page - 1) * $per_page;

$total_result = $conn->query("SELECT COUNT(*) as total FROM blog_posts WHERE status = 'published'");
$total_posts  = $total_result->fetch_assoc()['total'];
$total_pages  = ceil($total_posts / $per_page);

$posts_query = "
    SELECT
        p.post_id,
        p.title,
        p.content,
        p.cover_image,
        p.likes_count,
        p.views_count,
        p.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS author_name,
        u.user_id AS author_id,
        (SELECT COUNT(*) FROM blog_comments c WHERE c.post_id = p.post_id AND c.status = 'active') AS comment_count,
        EXISTS(SELECT 1 FROM blog_reactions r WHERE r.user_id = ? AND r.target_type = 'post' AND r.target_id = p.post_id) AS user_liked
    FROM blog_posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.status = 'published'
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($posts_query);
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Comunidad - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="css/courses.css?v=1.12">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
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
            --shadow-lg         : 0 8px 32px rgba(0,0,0,.12);
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

        .brand-icon { width: 28px; height: 28px; }

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
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: all var(--transition);
            border: 1px solid var(--neutral-200);
            overflow: hidden;
        }

        .nav-user:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: block;
            padding: 0.75rem 1.25rem;
            color: var(--neutral-700);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background var(--transition);
        }

        .user-dropdown a:hover { background: var(--neutral-100); }

        .blog-hero {
            background: linear-gradient(135deg, var(--accomplished-blue) 0%, #003560 100%);
            padding: 3.5rem 2rem;
            text-align: center;
            color: white;
        }

        .blog-hero h1 {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }

        .blog-hero p {
            font-size: 1rem;
            opacity: 0.85;
            max-width: 520px;
            margin: 0 auto 2rem;
        }

        .btn-new-post {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--accent-blue);
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 99px;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all var(--transition);
            box-shadow: 0 4px 16px rgba(0,153,216,.4);
        }

        .btn-new-post:hover {
            background: white;
            color: var(--accomplished-blue);
            transform: translateY(-2px);
        }

        .blog-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.75rem;
        }

        .post-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            display: flex;
            flex-direction: column;
            transition: all var(--transition);
        }

        .post-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .post-cover {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--light-accent), #C8E8F5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .post-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .post-cover-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--light-accent) 0%, #C8E8F5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .post-cover-placeholder svg {
            width: 48px; height: 48px;
            color: var(--accent-blue);
            opacity: 0.5;
        }

        .post-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
        }

        .author-avatar {
            width: 32px; height: 32px;
            background: var(--accomplished-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .author-meta { line-height: 1.3; }

        .author-name {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--neutral-700);
        }

        .post-date {
            font-size: 0.72rem;
            color: var(--neutral-600);
        }

        .post-title {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--neutral-900);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            text-decoration: none;
            display: block;
            transition: color var(--transition);
        }

        .post-title:hover { color: var(--accent-blue); }

        .post-excerpt {
            font-size: 0.875rem;
            color: var(--neutral-600);
            line-height: 1.65;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-footer {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--neutral-200);
        }

        .reaction-btn {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--neutral-600);
            padding: 0.35rem 0.6rem;
            border-radius: 6px;
            transition: all var(--transition);
            font-family: 'Open Sans', sans-serif;
        }

        .reaction-btn:hover      { background: var(--light-accent); color: var(--accent-blue); }
        .reaction-btn.liked      { color: var(--accent-blue); }
        .reaction-btn svg        { width: 16px; height: 16px; }

        .post-read-more {
            margin-left: auto;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--accent-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: gap var(--transition);
        }

        .post-read-more:hover { gap: 0.5rem; }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            grid-column: 1 / -1;
        }

        .empty-state svg {
            width: 64px; height: 64px;
            color: var(--neutral-200);
            margin-bottom: 1.25rem;
        }

        .empty-state h3 {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--neutral-700);
            margin-bottom: 0.5rem;
        }

        .empty-state p { font-size: 0.9rem; color: var(--neutral-600); }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-btn {
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--neutral-600);
            background: white;
            border: 1px solid var(--neutral-200);
            transition: all var(--transition);
        }

        .page-btn:hover   { border-color: var(--accent-blue); color: var(--accent-blue); }
        .page-btn.current { background: var(--accomplished-blue); color: white; border-color: var(--accomplished-blue); }

        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .nav-menu.open { display: flex; }
            .nav-hamburger { display: flex; }
        }
    </style>
</head>
<body>

<!-- ====================================================
     Navbar
==================================================== -->
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
                <?php echo htmlspecialchars($user_name); ?>
            </div>
            <div class="user-dropdown">
                <a href="profile.php">Mi Perfil</a>
                <?php if ($is_admin): ?>
                    <a href="admin/index.php">Moderación</a>
                <?php endif; ?>
                <a href="process/logout_process.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</nav>

<!-- ====================================================
     Hero
==================================================== -->
<section class="blog-hero">
    <h1>Blog de la Comunidad</h1>
    <p>Comparte conocimiento, experiencias y reflexiones con tus compañeros de Whirlpool.</p>
    <a href="blog-new.php" class="btn-new-post">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 4v16m8-8H4"/>
        </svg>
        Nueva Publicación
    </a>
</section>

<!-- ====================================================
     Grid de publicaciones
==================================================== -->
<div class="blog-container">
    <div class="blog-grid">
        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z"/>
                    <path d="M17 20v-8H7v8M7 4v4h8"/>
                </svg>
                <h3>Todavía no hay publicaciones</h3>
                <p>Sé el primero en compartir algo con la comunidad.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <!-- Imagen de portada -->
                    <?php if ($post['cover_image']): ?>
                        <div class="post-cover">
                            <img src="<?php echo htmlspecialchars($post['cover_image']); ?>"
                                 alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 loading="lazy">
                        </div>
                    <?php else: ?>
                        <div class="post-cover-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z"/>
                                <path d="M17 20v-8H7v8M7 4v4h8"/>
                            </svg>
                        </div>
                    <?php endif; ?>

                    <!-- Cuerpo -->
                    <div class="post-body">
                        <div class="post-author">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                            </div>
                            <div class="author-meta">
                                <div class="author-name"><?php echo htmlspecialchars($post['author_name']); ?></div>
                                <div class="post-date"><?php echo date('d M Y', strtotime($post['created_at'])); ?></div>
                            </div>
                        </div>

                        <a href="blog-post.php?id=<?php echo $post['post_id']; ?>" class="post-title">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>

                        <p class="post-excerpt">
                            <?php echo htmlspecialchars(strip_tags(substr($post['content'], 0, 220))); ?>…
                        </p>

                        <div class="post-footer">
                            <!-- Like -->
                            <button class="reaction-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                    onclick="toggleLike(this, 'post', <?php echo $post['post_id']; ?>)"
                                    data-count="<?php echo $post['likes_count']; ?>">
                                <svg viewBox="0 0 24 24" fill="<?php echo $post['user_liked'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                </svg>
                                <span><?php echo $post['likes_count']; ?></span>
                            </button>

                            <!-- Comentarios -->
                            <a href="blog-post.php?id=<?php echo $post['post_id']; ?>#comments" class="reaction-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                                </svg>
                                <span><?php echo $post['comment_count']; ?></span>
                            </a>

                            <!-- Vistas -->
                            <span class="reaction-btn" style="cursor:default;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <span><?php echo $post['views_count']; ?></span>
                            </span>

                            <a href="blog-post.php?id=<?php echo $post['post_id']; ?>" class="post-read-more">
                                Leer
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>"
                   class="page-btn <?php echo $i === $page ? 'current' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>

function toggleLike(btn, type, id) {
    fetch('process/blog_reaction.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ type, id })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;

        const svg   = btn.querySelector('svg');
        const count = btn.querySelector('span');

        btn.classList.toggle('liked', data.liked);
        svg.setAttribute('fill', data.liked ? 'currentColor' : 'none');
        count.textContent = data.likes_count;
    });
}
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