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
$post_id   = intval($_GET['id'] ?? 0);

if (!$post_id) {
    header("Location: blog.php");
    exit;
}

$post_query = "
    SELECT
        p.*,
        CONCAT(u.first_name, ' ', u.last_name) AS author_name,
        u.user_id AS author_id,
        EXISTS(
            SELECT 1 FROM blog_reactions r
            WHERE r.user_id = ? AND r.target_type = 'post' AND r.target_id = p.post_id
        ) AS user_liked
    FROM blog_posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.post_id = ? AND p.status = 'published'
";

$stmt = $conn->prepare($post_query);
$stmt->bind_param("ii", $user_id, $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    header("Location: blog.php");
    exit;
}

$view_key = "viewed_post_$post_id";
if (!isset($_SESSION[$view_key])) {
    $conn->query("UPDATE blog_posts SET views_count = views_count + 1 WHERE post_id = $post_id");
    $_SESSION[$view_key] = true;
}

$comments_query = "
    SELECT
        c.*,
        CONCAT(u.first_name, ' ', u.last_name) AS author_name,
        EXISTS(
            SELECT 1 FROM blog_reactions r
            WHERE r.user_id = ? AND r.target_type = 'comment' AND r.target_id = c.comment_id
        ) AS user_liked,
        (SELECT COUNT(*) FROM blog_comments r WHERE r.parent_id = c.comment_id AND r.status = 'active') AS reply_count
    FROM blog_comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.post_id = ? AND c.parent_id IS NULL AND c.status = 'active'
    ORDER BY c.created_at ASC
";

$stmt = $conn->prepare($comments_query);
$stmt->bind_param("ii", $user_id, $post_id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="css/courses.css?v=1.11.01">
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
            position: fixed; top: 0; left: 0; right: 0;
            background: white;
            border-bottom: 1px solid var(--neutral-200);
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .nav-container {
            max-width: 1400px; margin: 0 auto; padding: 0 2rem;
            height: 72px;
            display: grid; grid-template-columns: auto 1fr auto;
            align-items: center; gap: 2rem;
        }

        .nav-brand {
            display: flex; align-items: center; gap: 0.75rem;
            font-family: 'Nunito Sans', sans-serif; font-weight: 800;
            font-size: 1.15rem; color: var(--accomplished-blue); text-decoration: none;
        }

        .nav-menu { display: flex; gap: 0.25rem; justify-content: center; }

        .nav-link {
            color: var(--neutral-600); text-decoration: none;
            font-family: 'Nunito Sans', sans-serif; font-weight: 600; font-size: 0.9rem;
            padding: 0.5rem 1rem; border-radius: var(--radius-md);
            transition: all var(--transition);
        }

        .nav-link:hover  { background: var(--light-accent); color: var(--accent-blue); }
        .nav-link.active { background: var(--light-accent); color: var(--accomplished-blue); }

        .nav-user { position: relative; cursor: pointer; }

        .user-name {
            font-family: 'Nunito Sans', sans-serif; font-weight: 700;
            color: var(--accomplished-blue); font-size: 0.9rem;
            display: flex; align-items: center; gap: 0.5rem;
        }

        .user-avatar {
            width: 36px; height: 36px; background: var(--accomplished-blue);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Nunito Sans', sans-serif; font-weight: 800; font-size: 0.85rem;
        }

        .user-dropdown {
            position: absolute; top: calc(100% + 12px); right: 0;
            background: white; border-radius: var(--radius-md);
            box-shadow: var(--shadow-md); min-width: 180px;
            opacity: 0; visibility: hidden; transform: translateY(-6px);
            transition: all var(--transition);
            border: 1px solid var(--neutral-200); overflow: hidden;
        }

        .nav-user:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .user-dropdown a { display: block; padding: 0.75rem 1.25rem; color: var(--neutral-700); text-decoration: none; font-size: 0.875rem; }
        .user-dropdown a:hover { background: var(--neutral-100); }

        .post-layout {
            max-width: 820px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .breadcrumb {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.8rem; color: var(--neutral-600); margin-bottom: 1.5rem;
        }

        .breadcrumb a { color: var(--accent-blue); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .post-article {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .post-cover {
            width: 100%; max-height: 380px; object-fit: cover;
        }

        .post-content-wrap { padding: 2.5rem; }

        .post-meta {
            display: flex; align-items: center; gap: 1rem;
            margin-bottom: 1.5rem; flex-wrap: wrap;
        }

        .author-row {
            display: flex; align-items: center; gap: 0.6rem;
        }

        .author-avatar {
            width: 40px; height: 40px; background: var(--accomplished-blue);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Nunito Sans', sans-serif; font-weight: 800; font-size: 0.85rem;
        }

        .author-info { line-height: 1.3; }
        .author-name { font-family: 'Nunito Sans', sans-serif; font-weight: 700; font-size: 0.9rem; color: var(--neutral-900); }
        .post-date   { font-size: 0.78rem; color: var(--neutral-600); }

        .post-stats {
            display: flex; align-items: center; gap: 0.75rem;
            margin-left: auto; flex-wrap: wrap;
        }

        .stat-item {
            display: flex; align-items: center; gap: 0.3rem;
            font-size: 0.8rem; color: var(--neutral-600);
        }

        .stat-item svg { width: 14px; height: 14px; }

        .post-actions-bar {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 1.5rem; padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--neutral-200);
        }

        .h1-post {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 2rem; font-weight: 800;
            color: var(--neutral-900); line-height: 1.25;
            margin-bottom: 1.5rem;
        }

        .post-body-text {
            font-size: 0.975rem;
            line-height: 1.85;
            color: var(--neutral-700);
            white-space: pre-wrap;
        }

        .post-reactions {
            display: flex; align-items: center; gap: 1rem;
            margin-top: 2.5rem; padding-top: 1.5rem;
            border-top: 1px solid var(--neutral-200);
        }

        .reaction-btn {
            display: flex; align-items: center; gap: 0.375rem;
            background: none; border: none; cursor: pointer;
            font-size: 0.85rem; font-weight: 600; color: var(--neutral-600);
            padding: 0.5rem 0.875rem; border-radius: 99px;
            transition: all var(--transition); font-family: 'Open Sans', sans-serif;
            border: 1.5px solid var(--neutral-200);
        }

        .reaction-btn svg        { width: 17px; height: 17px; }
        .reaction-btn:hover      { border-color: var(--accent-blue); color: var(--accent-blue); }
        .reaction-btn.liked      { border-color: var(--accent-blue); color: var(--accent-blue); background: var(--light-accent); }

        .comments-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            padding: 2.5rem;
        }

        .comments-title {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.2rem; font-weight: 800;
            color: var(--accomplished-blue);
            margin-bottom: 2rem;
            display: flex; align-items: center; gap: 0.5rem;
        }

        .comments-count {
            background: var(--light-accent);
            color: var(--accent-blue);
            font-size: 0.78rem; font-weight: 700;
            padding: 0.2rem 0.6rem; border-radius: 99px;
        }

        .comment-form { margin-bottom: 2.5rem; }

        .comment-input-row {
            display: flex; gap: 0.875rem; align-items: flex-start;
        }

        .comment-input-row .user-avatar {
            flex-shrink: 0; margin-top: 0.2rem;
        }

        .comment-input-wrap { flex: 1; }

        .comment-textarea {
            width: 100%;
            padding: 0.875rem 1.1rem;
            border: 1.5px solid var(--neutral-200);
            border-radius: var(--radius-md);
            font-family: 'Open Sans', sans-serif;
            font-size: 0.9rem; color: var(--neutral-900);
            resize: vertical; min-height: 90px;
            transition: border-color var(--transition); outline: none;
            line-height: 1.6;
        }

        .comment-textarea:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0,153,216,.1);
        }

        .comment-submit-row {
            display: flex; justify-content: flex-end; margin-top: 0.75rem;
        }

        .btn-submit-comment {
            background: var(--accomplished-blue); color: white;
            border: none; cursor: pointer;
            font-family: 'Nunito Sans', sans-serif; font-weight: 700; font-size: 0.875rem;
            padding: 0.6rem 1.4rem; border-radius: 99px;
            transition: all var(--transition);
            display: flex; align-items: center; gap: 0.4rem;
        }

        .btn-submit-comment:hover { background: #003a60; transform: translateY(-1px); }

        .comment-list { display: flex; flex-direction: column; gap: 1.5rem; }

        .comment-item { display: flex; gap: 0.875rem; }

        .comment-bubble {
            flex: 1;
            background: var(--neutral-50);
            border: 1px solid var(--neutral-200);
            border-radius: 0 var(--radius-lg) var(--radius-lg) var(--radius-lg);
            padding: 1rem 1.25rem;
        }

        .comment-header {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 0.5rem; flex-wrap: wrap;
        }

        .comment-author {
            font-family: 'Nunito Sans', sans-serif; font-weight: 700;
            font-size: 0.85rem; color: var(--neutral-900);
        }

        .comment-time {
            font-size: 0.75rem; color: var(--neutral-600);
            margin-left: auto;
        }

        .comment-text {
            font-size: 0.875rem; color: var(--neutral-700);
            line-height: 1.7; white-space: pre-wrap;
        }

        .comment-footer {
            display: flex; align-items: center; gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .btn-comment-action {
            background: none; border: none; cursor: pointer;
            font-size: 0.78rem; font-weight: 700;
            color: var(--neutral-600); font-family: 'Nunito Sans', sans-serif;
            padding: 0.25rem 0.5rem; border-radius: 6px;
            transition: all var(--transition);
            display: flex; align-items: center; gap: 0.3rem;
        }

        .btn-comment-action:hover      { background: var(--light-accent); color: var(--accent-blue); }
        .btn-comment-action.liked      { color: var(--accent-blue); }
        .btn-comment-action svg        { width: 13px; height: 13px; }

        .replies-container {
            margin-top: 1rem;
            margin-left: 1.5rem;
            padding-left: 1rem;
            border-left: 2px solid var(--neutral-200);
            display: none;
        }

        .replies-container.open { display: block; }

        .replies-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem; }

        .reply-item { display: flex; gap: 0.75rem; }

        .reply-avatar {
            width: 28px; height: 28px; flex-shrink: 0;
            background: var(--accent-blue); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-family: 'Nunito Sans', sans-serif; font-weight: 800; font-size: 0.7rem;
        }

        .reply-bubble {
            flex: 1; background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 0 var(--radius-md) var(--radius-md) var(--radius-md);
            padding: 0.75rem 1rem;
        }

        .reply-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; }

        .reply-author {
            font-family: 'Nunito Sans', sans-serif; font-weight: 700;
            font-size: 0.8rem; color: var(--neutral-900);
        }

        .reply-time { font-size: 0.72rem; color: var(--neutral-600); margin-left: auto; }

        .reply-text { font-size: 0.835rem; color: var(--neutral-700); line-height: 1.65; }

        .reply-form-wrap { margin-top: 0.75rem; display: none; }
        .reply-form-wrap.open { display: block; }

        .reply-form-row { display: flex; gap: 0.75rem; align-items: flex-start; }

        .reply-textarea {
            flex: 1; padding: 0.65rem 0.875rem;
            border: 1.5px solid var(--neutral-200); border-radius: var(--radius-md);
            font-family: 'Open Sans', sans-serif; font-size: 0.855rem;
            resize: none; min-height: 70px; outline: none;
            transition: border-color var(--transition);
        }

        .reply-textarea:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0,153,216,.1);
        }

        .btn-send-reply {
            background: var(--accent-blue); color: white; border: none;
            cursor: pointer; padding: 0.55rem 1.1rem; border-radius: 99px;
            font-family: 'Nunito Sans', sans-serif; font-weight: 700; font-size: 0.8rem;
            transition: background var(--transition); flex-shrink: 0; margin-top: 0.2rem;
        }

        .btn-send-reply:hover { background: #007db0; }

        .btn-hide-comment {
            margin-left: auto;
            background: none; border: none; cursor: pointer;
            font-size: 0.75rem; color: #DC2626;
            font-family: 'Nunito Sans', sans-serif; font-weight: 700;
            padding: 0.2rem 0.4rem; border-radius: 4px;
            transition: background var(--transition);
        }

        .btn-hide-comment:hover { background: #FEF2F2; }

        .no-comments {
            text-align: center; padding: 2.5rem 1rem;
            color: var(--neutral-600); font-size: 0.9rem;
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

<div class="post-layout">

    <div class="breadcrumb">
        <a href="blog.php">Blog</a>
        <span>›</span>
        <span><?php echo htmlspecialchars(substr($post['title'], 0, 60)); ?>…</span>
    </div>

    <article class="post-article">
        <?php if ($post['cover_image']): ?>
            <img class="post-cover"
                 src="<?php echo htmlspecialchars($post['cover_image']); ?>"
                 alt="<?php echo htmlspecialchars($post['title']); ?>">
        <?php endif; ?>

        <div class="post-content-wrap">
            <!-- Meta del autor -->
            <div class="post-meta">
                <div class="author-row">
                    <div class="author-avatar">
                        <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                    </div>
                    <div class="author-info">
                        <div class="author-name"><?php echo htmlspecialchars($post['author_name']); ?></div>
                        <div class="post-date"><?php echo date('d \d\e F \d\e Y', strtotime($post['created_at'])); ?></div>
                    </div>
                </div>

                <div class="post-stats">
                    <span class="stat-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <?php echo $post['views_count']; ?> vistas
                    </span>
                    <span class="stat-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                        <?php echo count($comments); ?> comentarios
                    </span>
                </div>

                <!-- Opciones de autor y admin -->
                <?php if ($user_id === $post['author_id'] || $is_admin): ?>
                    <div style="margin-left: auto; display:flex; gap:0.5rem;">
                        <?php if ($user_id === $post['author_id']): ?>
                            <a href="blog-edit.php?id=<?php echo $post['post_id']; ?>"
                               style="font-size:0.78rem; color:var(--accent-blue); text-decoration:none; font-weight:700; font-family:'Nunito Sans',sans-serif;">
                                Editar
                            </a>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <button onclick="deletePost(<?php echo $post['post_id']; ?>)"
                                    style="font-size:0.78rem; color:#DC2626; background:none; border:none; cursor:pointer; font-weight:700; font-family:'Nunito Sans',sans-serif;">
                                Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Título y contenido -->
            <h1 class="h1-post"><?php echo htmlspecialchars($post['title']); ?></h1>

            <div class="post-body-text">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>

            <!-- Reacciones de la publicación -->
            <div class="post-reactions">
                <button class="reaction-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                        id="post-like-btn"
                        onclick="toggleLike(this, 'post', <?php echo $post['post_id']; ?>)">
                    <svg viewBox="0 0 24 24"
                         fill="<?php echo $post['user_liked'] ? 'currentColor' : 'none'; ?>"
                         stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                    </svg>
                    <span id="post-like-count"><?php echo $post['likes_count']; ?></span> Me gusta
                </button>
            </div>
        </div>
    </article>

    <section class="comments-section" id="comments">
        <h2 class="comments-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            Comentarios
            <span class="comments-count" id="global-comment-count"><?php echo count($comments); ?></span>
        </h2>

        <!-- Nuevo comentario -->
        <div class="comment-form">
            <div class="comment-input-row">
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="comment-input-wrap">
                    <textarea class="comment-textarea"
                              id="new-comment-textarea"
                              placeholder="Escribe un comentario…"></textarea>
                    <div class="comment-submit-row">
                        <button class="btn-submit-comment" onclick="submitComment()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
                            </svg>
                            Comentar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de comentarios -->
        <div class="comment-list" id="comment-list">
            <?php if (empty($comments)): ?>
                <div class="no-comments" id="no-comments-msg">
                    Todavía no hay comentarios. ¡Sé el primero en participar!
                </div>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <?php $initial = strtoupper(substr($c['author_name'], 0, 1)); ?>
                    <div class="comment-item" id="comment-<?php echo $c['comment_id']; ?>">
                        <div class="user-avatar"><?php echo $initial; ?></div>
                        <div style="flex:1;">
                            <div class="comment-bubble">
                                <div class="comment-header">
                                    <span class="comment-author"><?php echo htmlspecialchars($c['author_name']); ?></span>
                                    <span class="comment-time"><?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?></span>
                                </div>
                                <div class="comment-text"><?php echo nl2br(htmlspecialchars($c['content'])); ?></div>
                                <div class="comment-footer">
                                    <!-- Like comentario -->
                                    <button class="btn-comment-action <?php echo $c['user_liked'] ? 'liked' : ''; ?>"
                                            onclick="toggleLike(this, 'comment', <?php echo $c['comment_id']; ?>)">
                                        <svg viewBox="0 0 24 24"
                                             fill="<?php echo $c['user_liked'] ? 'currentColor' : 'none'; ?>"
                                             stroke="currentColor" stroke-width="2">
                                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                        </svg>
                                        <span><?php echo $c['likes_count']; ?></span>
                                    </button>

                                    <!-- Responder -->
                                    <button class="btn-comment-action"
                                            onclick="toggleReplyForm(<?php echo $c['comment_id']; ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 14H4a1 1 0 01-1-1V4a1 1 0 011-1h16a1 1 0 011 1v9a1 1 0 01-1 1h-1l-5 4v-4z"/>
                                        </svg>
                                        Responder
                                        <?php if ($c['reply_count'] > 0): ?>
                                            (<?php echo $c['reply_count']; ?>)
                                        <?php endif; ?>
                                    </button>

                                    <!-- Admin: ocultar -->
                                    <?php if ($is_admin): ?>
                                        <button class="btn-hide-comment"
                                                onclick="hideComment(<?php echo $c['comment_id']; ?>, this)">
                                            Ocultar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Hilo de respuestas -->
                            <div class="replies-container" id="replies-<?php echo $c['comment_id']; ?>">
                                <div class="replies-list" id="replies-list-<?php echo $c['comment_id']; ?>">
                                    <!-- Las respuestas se cargan dinámicamente -->
                                </div>
                                <!-- Formulario de respuesta -->
                                <div class="reply-form-wrap" id="reply-form-<?php echo $c['comment_id']; ?>">
                                    <div class="reply-form-row">
                                        <textarea class="reply-textarea"
                                                  placeholder="Escribe una respuesta…"
                                                  id="reply-textarea-<?php echo $c['comment_id']; ?>"></textarea>
                                        <button class="btn-send-reply"
                                                onclick="submitReply(<?php echo $c['comment_id']; ?>)">
                                            Enviar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
const POST_ID    = <?php echo $post_id; ?>;
const USER_INIT  = '<?php echo strtoupper(substr($user_name, 0, 1)); ?>';
const USER_NAME  = '<?php echo htmlspecialchars($user_name, ENT_QUOTES); ?>';
const IS_ADMIN   = <?php echo $is_admin ? 'true' : 'false'; ?>;

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
        if (count) count.textContent = data.likes_count;
    });
}

function submitComment() {
    const textarea = document.getElementById('new-comment-textarea');
    const content  = textarea.value.trim();
    if (!content) return;

    fetch('process/blog_comment_process.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ post_id: POST_ID, content, parent_id: null })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return alert(data.message || 'Error al comentar.');

        textarea.value = '';

        const noMsg = document.getElementById('no-comments-msg');
        if (noMsg) noMsg.remove();

        const counter = document.getElementById('global-comment-count');
        counter.textContent = parseInt(counter.textContent) + 1;

        const list = document.getElementById('comment-list');
        list.insertAdjacentHTML('beforeend', buildCommentHTML(data.comment));
    });
}

function toggleReplyForm(commentId) {
    const container = document.getElementById(`replies-${commentId}`);
    const form      = document.getElementById(`reply-form-${commentId}`);
    const list      = document.getElementById(`replies-list-${commentId}`);

    const wasOpen = container.classList.contains('open');

    if (!wasOpen) {
        container.classList.add('open');
        form.classList.add('open');

        if (!container.dataset.loaded) {
            loadReplies(commentId, list);
            container.dataset.loaded = 'true';
        }
    } else {
        form.classList.toggle('open');
    }
}

function loadReplies(commentId, listEl) {
    fetch(`process/blog_replies_fetch.php?comment_id=${commentId}&post_id=${POST_ID}`)
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.replies.length) return;
        listEl.innerHTML = data.replies.map(buildReplyHTML).join('');
    });
}

function submitReply(parentId) {
    const textarea = document.getElementById(`reply-textarea-${parentId}`);
    const content  = textarea.value.trim();
    if (!content) return;

    fetch('process/blog_comment_process.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ post_id: POST_ID, content, parent_id: parentId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return alert(data.message || 'Error al responder.');

        textarea.value = '';
        const list = document.getElementById(`replies-list-${parentId}`);
        list.insertAdjacentHTML('beforeend', buildReplyHTML(data.comment));
    });
}

function hideComment(commentId, btn) {
    if (!confirm('¿Ocultar este comentario?')) return;

    fetch('process/blog_moderate.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ comment_id: commentId, action: 'hide' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`comment-${commentId}`)?.remove();
        }
    });
}

function deletePost(postId) {
    if (!confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')) return;

    fetch('process/blog_post_process.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ action: 'delete', post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) window.location.href = 'blog.php';
    });
}

function buildCommentHTML(c) {
    const adminBtn = IS_ADMIN
        ? `<button class="btn-hide-comment" onclick="hideComment(${c.comment_id}, this)">Ocultar</button>`
        : '';

    return `
    <div class="comment-item" id="comment-${c.comment_id}">
        <div class="user-avatar">${USER_INIT}</div>
        <div style="flex:1;">
            <div class="comment-bubble">
                <div class="comment-header">
                    <span class="comment-author">${USER_NAME}</span>
                    <span class="comment-time">Ahora</span>
                </div>
                <div class="comment-text">${nl2br(escapeHtml(c.content))}</div>
                <div class="comment-footer">
                    <button class="btn-comment-action" onclick="toggleLike(this, 'comment', ${c.comment_id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                        </svg>
                        <span>0</span>
                    </button>
                    <button class="btn-comment-action" onclick="toggleReplyForm(${c.comment_id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                            <path d="M9 14H4a1 1 0 01-1-1V4a1 1 0 011-1h16a1 1 0 011 1v9a1 1 0 01-1 1h-1l-5 4v-4z"/>
                        </svg>
                        Responder
                    </button>
                    ${adminBtn}
                </div>
            </div>
            <div class="replies-container" id="replies-${c.comment_id}">
                <div class="replies-list" id="replies-list-${c.comment_id}"></div>
                <div class="reply-form-wrap open" id="reply-form-${c.comment_id}">
                    <div class="reply-form-row">
                        <textarea class="reply-textarea" placeholder="Escribe una respuesta…" id="reply-textarea-${c.comment_id}"></textarea>
                        <button class="btn-send-reply" onclick="submitReply(${c.comment_id})">Enviar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function buildReplyHTML(r) {
    const initial = r.author_name ? r.author_name.charAt(0).toUpperCase() : '?';
    return `
    <div class="reply-item">
        <div class="reply-avatar">${initial}</div>
        <div class="reply-bubble">
            <div class="reply-header">
                <span class="reply-author">${escapeHtml(r.author_name)}</span>
                <span class="reply-time">${r.created_at}</span>
            </div>
            <div class="reply-text">${nl2br(escapeHtml(r.content))}</div>
        </div>
    </div>`;
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function nl2br(str) {
    return str.replace(/\n/g, '<br>');
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