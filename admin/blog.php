<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

$admin_name = $_SESSION['user_name'] ?? 'Admin';
$tab = $_GET['tab'] ?? 'stats';
$stats = [];

$r = $conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE status = 'published'");
$stats['published'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE status = 'pending'");
$stats['pending'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE status = 'rejected'");
$stats['rejected'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM blog_comments WHERE status = 'active'");
$stats['comments_active'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM blog_comments WHERE status = 'hidden'");
$stats['comments_hidden'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COALESCE(SUM(likes_count),0) AS c FROM blog_posts");
$stats['total_likes'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COALESCE(SUM(views_count),0) AS c FROM blog_posts");
$stats['total_views'] = $r->fetch_assoc()['c'];
$posts = [];
if (in_array($tab, ['posts', 'pending'])) {
    $filter_status = $tab === 'pending' ? 'pending' : ($_GET['status'] ?? 'all');
    $page     = max(1, intval($_GET['page'] ?? 1));
    $per_page = 15;
    $offset   = ($page - 1) * $per_page;

    $where = $filter_status !== 'all' ? "WHERE p.status = '$filter_status'" : "";

    $total_r     = $conn->query("SELECT COUNT(*) AS c FROM blog_posts p $where");
    $total_posts = $total_r->fetch_assoc()['c'];
    $total_pages = ceil($total_posts / $per_page);

    $posts_q = "
        SELECT p.post_id, p.title, p.status, p.likes_count, p.views_count, p.created_at,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name,
               (SELECT COUNT(*) FROM blog_comments c WHERE c.post_id = p.post_id) AS comment_count
        FROM blog_posts p
        JOIN users u ON p.user_id = u.user_id
        $where
        ORDER BY p.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $posts = $conn->query($posts_q)->fetch_all(MYSQLI_ASSOC);
}
$comments = [];
if ($tab === 'comments') {
    $filter_status = $_GET['status'] ?? 'all';
    $page     = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset   = ($page - 1) * $per_page;

    $where = $filter_status !== 'all' ? "WHERE c.status = '$filter_status'" : "";

    $total_r        = $conn->query("SELECT COUNT(*) AS c FROM blog_comments c $where");
    $total_comments = $total_r->fetch_assoc()['c'];
    $total_pages    = ceil($total_comments / $per_page);

    $comments_q = "
        SELECT c.comment_id, c.content, c.status, c.likes_count, c.created_at, c.parent_id,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name,
               p.title AS post_title, p.post_id
        FROM blog_comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN blog_posts p ON c.post_id = p.post_id
        $where
        ORDER BY c.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $comments = $conn->query($comments_q)->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderación Blog - Admin Whirlpool</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-dark   : #003C64;
            --blue-darker : #002A47;
            --accent      : #0096DC;
            --light-accent: #E5F4FC;
            --bg          : #F0F4F8;
            --white       : #ffffff;
            --neutral-100 : #F1F5F9;
            --neutral-200 : #E2E8F0;
            --neutral-600 : #475569;
            --neutral-700 : #334155;
            --neutral-900 : #0F172A;
            --success     : #059669;
            --warning     : #D97706;
            --danger      : #DC2626;
            --shadow-sm   : 0 1px 3px rgba(0,0,0,.08);
            --shadow-md   : 0 4px 16px rgba(0,0,0,.10);
            --radius      : 10px;
            --radius-lg   : 16px;
            --transition  : 0.2s ease;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background: var(--bg);
            color: var(--neutral-900);
            min-height: 100vh;
        }

        .admin-main { margin-left: 260px; min-height: 100vh; }

        .admin-topbar {
            background: var(--white);
            border-bottom: 1px solid var(--neutral-200);
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
        }

        .topbar-title {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.25rem; font-weight: 800;
            color: var(--blue-dark);
        }

        .topbar-subtitle { font-size: .85rem; color: var(--neutral-600); margin-top: .2rem; }

        .admin-content { padding: 2rem; flex: 1; }

        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .stat-icon {
            width: 44px; height: 44px; border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon svg { width: 22px; height: 22px; }

        .stat-icon.blue    { background: var(--light-accent); color: var(--accent); }
        .stat-icon.green   { background: #D1FAE5; color: var(--success); }
        .stat-icon.yellow  { background: #FEF3C7; color: var(--warning); }
        .stat-icon.red     { background: #FEE2E2; color: var(--danger); }
        .stat-icon.purple  { background: #EDE9FE; color: #7C3AED; }

        .stat-value {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 1.75rem; font-weight: 800;
            color: var(--neutral-900); line-height: 1;
        }

        .stat-label { font-size: .78rem; color: var(--neutral-600); margin-top: .3rem; }

        
        .tabs-bar {
            display: flex;
            gap: .25rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: .375rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            width: fit-content;
        }

        .tab-btn {
            padding: .55rem 1.25rem;
            border-radius: var(--radius);
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700; font-size: .85rem;
            color: var(--neutral-600);
            text-decoration: none;
            transition: all var(--transition);
            display: flex; align-items: center; gap: .4rem;
        }

        .tab-btn:hover  { background: var(--neutral-100); color: var(--neutral-900); }
        .tab-btn.active { background: var(--blue-dark); color: white; }

        .tab-badge {
            background: rgba(255,255,255,.25);
            font-size: .7rem;
            padding: .1rem .45rem;
            border-radius: 99px;
        }

        .tab-btn:not(.active) .tab-badge {
            background: var(--neutral-200);
            color: var(--neutral-700);
        }

        
        .table-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
            overflow: hidden;
        }

        .table-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--neutral-200);
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
        }

        .table-card-title {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800; font-size: 1rem;
            color: var(--blue-dark);
        }

        .filter-select {
            padding: .45rem .875rem;
            border: 1.5px solid var(--neutral-200);
            border-radius: var(--radius);
            font-family: 'Open Sans', sans-serif;
            font-size: .825rem; color: var(--neutral-700);
            background: white; cursor: pointer; outline: none;
            transition: border-color var(--transition);
        }

        .filter-select:focus { border-color: var(--accent); }

        table { width: 100%; border-collapse: collapse; }

        th {
            text-align: left;
            padding: .75rem 1.25rem;
            font-family: 'Nunito Sans', sans-serif;
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--neutral-600);
            background: var(--neutral-100);
            border-bottom: 1px solid var(--neutral-200);
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--neutral-200);
            font-size: .865rem;
            color: var(--neutral-700);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--neutral-100); }

        .td-title {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700; color: var(--neutral-900);
            max-width: 300px;
        }

        .td-title a { color: inherit; text-decoration: none; }
        .td-title a:hover { color: var(--accent); }

        .td-excerpt {
            max-width: 280px;
            font-size: .8rem;
            color: var(--neutral-600);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* Badges de estado */
        .badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .25rem .7rem; border-radius: 99px;
            font-family: 'Nunito Sans', sans-serif;
            font-size: .72rem; font-weight: 700;
        }

        .badge-published { background: #D1FAE5; color: #065F46; }
        .badge-pending   { background: #FEF3C7; color: #92400E; }
        .badge-rejected  { background: #FEE2E2; color: #991B1B; }
        .badge-active    { background: #D1FAE5; color: #065F46; }
        .badge-hidden    { background: #F3F4F6; color: #6B7280; }
        .badge-reply     { background: var(--light-accent); color: var(--accent); }

        /* Acciones */
        .actions { display: flex; gap: .5rem; align-items: center; }

        .btn-action {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .35rem .75rem; border-radius: 6px;
            font-family: 'Nunito Sans', sans-serif;
            font-size: .75rem; font-weight: 700;
            cursor: pointer; border: none;
            transition: all var(--transition); text-decoration: none;
        }

        .btn-action svg { width: 13px; height: 13px; }

        .btn-view     { background: var(--light-accent); color: var(--accent); }
        .btn-view:hover { background: #C0E8F5; }

        .btn-approve  { background: #D1FAE5; color: var(--success); }
        .btn-approve:hover { background: #A7F3D0; }

        .btn-reject   { background: #FEE2E2; color: var(--danger); }
        .btn-reject:hover { background: #FECACA; }

        .btn-restore  { background: #EDE9FE; color: #7C3AED; }
        .btn-restore:hover { background: #DDD6FE; }

        .btn-hide     { background: #F3F4F6; color: #6B7280; }
        .btn-hide:hover { background: #E5E7EB; }

        .btn-delete   { background: #FEE2E2; color: var(--danger); }
        .btn-delete:hover { background: #FECACA; }

        /* Paginación */
        .pagination {
            display: flex; justify-content: center; gap: .5rem;
            padding: 1.25rem;
            border-top: 1px solid var(--neutral-200);
        }

        .page-btn {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius); text-decoration: none;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700; font-size: .825rem;
            color: var(--neutral-600);
            background: white; border: 1px solid var(--neutral-200);
            transition: all var(--transition);
        }

        .page-btn:hover    { border-color: var(--accent); color: var(--accent); }
        .page-btn.current  { background: var(--blue-dark); color: white; border-color: var(--blue-dark); }

        /* Empty state */
        .empty-row td {
            text-align: center; padding: 3rem;
            color: var(--neutral-600); font-size: .9rem;
        }

        /* Toast de confirmación */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem;
            background: var(--neutral-900); color: white;
            padding: .875rem 1.5rem; border-radius: var(--radius);
            font-size: .875rem; font-weight: 600;
            font-family: 'Nunito Sans', sans-serif;
            box-shadow: var(--shadow-md);
            transform: translateY(100px); opacity: 0;
            transition: all .3s ease; z-index: 9999;
        }

        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error   { background: var(--danger); }
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ACTIVE = "blog";
    </script>
    <script src="/admin/js/admin-navbar.js" defer></script>
</head>
<body>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="topbar-title">Moderación del Blog</div>
            <div class="topbar-subtitle">Gestiona publicaciones, comentarios y estadísticas de la comunidad</div>
        </div>
        <a href="../blog.php" target="_blank"
           style="display:inline-flex; align-items:center; gap:.4rem; background:var(--light-accent); color:var(--accent); padding:.55rem 1.1rem; border-radius:var(--radius); font-family:'Nunito Sans',sans-serif; font-weight:700; font-size:.825rem; text-decoration:none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/>
            </svg>
            Ver Blog
        </a>
    </div>

    <div class="admin-content">

                <div class="tabs-bar">
            <a href="?tab=stats"    class="tab-btn <?php echo $tab === 'stats'    ? 'active' : ''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 20V10M12 20V4M6 20v-6"/>
                </svg>
                Estadísticas
            </a>
            <a href="?tab=posts"    class="tab-btn <?php echo $tab === 'posts'    ? 'active' : ''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z"/>
                </svg>
                Publicaciones
                <span class="tab-badge"><?php echo $stats['published']; ?></span>
            </a>
            <a href="?tab=pending"  class="tab-btn <?php echo $tab === 'pending'  ? 'active' : ''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                Pendientes
                <?php if ($stats['pending'] > 0): ?>
                    <span class="tab-badge" style="background:#EF4444; color:white;"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=comments" class="tab-btn <?php echo $tab === 'comments' ? 'active' : ''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                Comentarios
                <span class="tab-badge"><?php echo $stats['comments_active']; ?></span>
            </a>
        </div>
        <?php if ($tab === 'stats'): ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['published']; ?></div>
                        <div class="stat-label">Publicaciones activas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pendientes de revisión</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M15 9l-6 6M9 9l6 6"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                        <div class="stat-label">Publicaciones rechazadas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['comments_active']; ?></div>
                        <div class="stat-label">Comentarios activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_likes']); ?></div>
                        <div class="stat-label">Total de likes</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                        <div class="stat-label">Vistas totales</div>
                    </div>
                </div>
            </div>

            <?php if ($stats['pending'] > 0): ?>
                <div style="background:#FEF3C7; border:1px solid #FDE68A; border-radius:var(--radius); padding:1rem 1.25rem; display:flex; align-items:center; gap:.875rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span style="font-size:.875rem; color:#92400E; font-weight:600; font-family:'Nunito Sans',sans-serif;">
                        Hay <strong><?php echo $stats['pending']; ?></strong> publicación(es) pendientes de aprobación.
                        <a href="?tab=pending" style="color:#D97706; margin-left:.5rem;">Revisar ahora →</a>
                    </span>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        <?php if (in_array($tab, ['posts', 'pending'])): ?>

            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">
                        <?php echo $tab === 'pending' ? 'Publicaciones pendientes de aprobación' : 'Todas las publicaciones'; ?>
                    </div>
                    <?php if ($tab === 'posts'): ?>
                        <select class="filter-select" onchange="window.location='?tab=posts&status='+this.value">
                            <option value="all"       <?php echo ($filter_status ?? 'all') === 'all'       ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="published" <?php echo ($filter_status ?? '') === 'published' ? 'selected' : ''; ?>>Publicados</option>
                            <option value="pending"   <?php echo ($filter_status ?? '') === 'pending'   ? 'selected' : ''; ?>>Pendientes</option>
                            <option value="rejected"  <?php echo ($filter_status ?? '') === 'rejected'  ? 'selected' : ''; ?>>Rechazados</option>
                        </select>
                    <?php endif; ?>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Publicación</th>
                            <th class="col-hide-mobile">Autor</th>
                            <th>Estado</th>
                            <th class="col-hide-mobile">Stats</th>
                            <th class="col-hide-mobile">Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr class="empty-row"><td colspan="6">No hay publicaciones para mostrar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $p): ?>
                                <tr>
                                    <td>
                                        <div class="td-title">
                                            <a href="../blog-post.php?id=<?php echo $p['post_id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($p['title']); ?>
                                            </a>
                                        </div>
                                        <div style="font-size:.75rem; color:var(--neutral-600); margin-top:.25rem;">
                                            <?php echo $p['comment_count']; ?> comentarios
                                        </div>
                                    </td>
                                    <td class="col-hide-mobile"><?php echo htmlspecialchars($p['author_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $p['status']; ?>">
                                            <?php echo ['published' => 'Publicado', 'pending' => 'Pendiente', 'rejected' => 'Rechazado'][$p['status']] ?? $p['status']; ?>
                                        </span>
                                    </td>
                                    <td class="col-hide-mobile" style="white-space:nowrap; font-size:.8rem;">
                                        ♥ <?php echo $p['likes_count']; ?>
                                        &nbsp;👁 <?php echo $p['views_count']; ?>
                                    </td>
                                    <td class="col-hide-mobile" style="white-space:nowrap; font-size:.8rem;">
                                        <?php echo date('d M Y', strtotime($p['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($p['status'] === 'pending'): ?>
                                                <button class="btn-action btn-approve"
                                                        onclick="moderatePost(<?php echo $p['post_id']; ?>, 'publish', this)">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <path d="M20 6L9 17l-5-5"/>
                                                    </svg>
                                                    Aprobar
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($p['status'] === 'published'): ?>
                                                <button class="btn-action btn-hide"
                                                        onclick="moderatePost(<?php echo $p['post_id']; ?>, 'unpublish', this)">
                                                    Despublicar
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($p['status'] !== 'published'): ?>
                                                <button class="btn-action btn-approve"
                                                        onclick="moderatePost(<?php echo $p['post_id']; ?>, 'publish', this)">
                                                    Publicar
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete"
                                                    onclick="deletePost(<?php echo $p['post_id']; ?>, this)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>
                                                </svg>
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                                <?php if (($total_pages ?? 1) > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>"
                               class="page-btn <?php echo $i === $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        <?php if ($tab === 'comments'): ?>

            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">Comentarios de la comunidad</div>
                    <select class="filter-select" onchange="window.location='?tab=comments&status='+this.value">
                        <option value="all"    <?php echo ($filter_status ?? 'all') === 'all'    ? 'selected' : ''; ?>>Todos</option>
                        <option value="active" <?php echo ($filter_status ?? '') === 'active' ? 'selected' : ''; ?>>Activos</option>
                        <option value="hidden" <?php echo ($filter_status ?? '') === 'hidden' ? 'selected' : ''; ?>>Ocultos</option>
                    </select>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Comentario</th>
                            <th class="col-hide-mobile">Autor</th>
                            <th>Publicación</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th class="col-hide-mobile">Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comments)): ?>
                            <tr class="empty-row"><td colspan="7">No hay comentarios para mostrar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                                <tr id="comment-row-<?php echo $c['comment_id']; ?>">
                                    <td class="td-excerpt">
                                        <?php echo htmlspecialchars(substr($c['content'], 0, 100)); ?>
                                        <?php echo strlen($c['content']) > 100 ? '…' : ''; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['author_name']); ?></td>
                                    <td>
                                        <a href="../blog-post.php?id=<?php echo $c['post_id']; ?>#comments"
                                           target="_blank"
                                           style="color:var(--accent); text-decoration:none; font-size:.8rem;">
                                            <?php echo htmlspecialchars(substr($c['post_title'], 0, 40)); ?>…
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($c['parent_id']): ?>
                                            <span class="badge badge-reply">Respuesta</span>
                                        <?php else: ?>
                                            <span style="font-size:.78rem; color:var(--neutral-600);">Comentario</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $c['status']; ?>">
                                            <?php echo $c['status'] === 'active' ? 'Activo' : 'Oculto'; ?>
                                        </span>
                                    </td>
                                    <td class="col-hide-mobile" style="white-space:nowrap; font-size:.8rem;">
                                        <?php echo date('d M Y', strtotime($c['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($c['status'] === 'active'): ?>
                                                <button class="btn-action btn-hide"
                                                        onclick="moderateComment(<?php echo $c['comment_id']; ?>, 'hide', this)">
                                                    Ocultar
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-restore"
                                                        onclick="moderateComment(<?php echo $c['comment_id']; ?>, 'restore', this)">
                                                    Restaurar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (($total_pages ?? 1) > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?tab=comments&page=<?php echo $i; ?>"
                               class="page-btn <?php echo $i === $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div><!-- /admin-content -->
</main>

<div class="toast" id="toast"></div>

<script>

function moderatePost(postId, action, btn) {
    const labels = { publish: 'publicar', unpublish: 'despublicar', delete: 'eliminar' };
    if (action === 'delete' && !confirm('¿Eliminar esta publicación permanentemente?')) return;

    fetch('../process/blog_admin_process.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ action, post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Acción aplicada correctamente.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Error al procesar.', 'error');
        }
    });
}

function deletePost(postId, btn) {
    moderatePost(postId, 'delete', btn);
}

function moderateComment(commentId, action, btn) {
    fetch('../process/blog_moderate.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ comment_id: commentId, action })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Comentario actualizado.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error al procesar.', 'error');
        }
    });
}

function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className   = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3000);
}
</script>
</body>
</html>