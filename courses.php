<?php

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';
$is_instructor = ($_SESSION['user_role'] ?? '') === 'instructor';

$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$categories_query = "SELECT * FROM course_categories WHERE status = 'active' ORDER BY display_order ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$courses_query = "
    SELECT
        c.*,
        cat.category_name,
        cat.color as category_color,
        COALESCE(e.enrollment_id, 0) as is_enrolled,
        COALESCE(e.progress_percentage, 0) as user_progress,
        COALESCE(e.status, '') as enrollment_status
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    LEFT JOIN course_enrollments e ON c.course_id = e.course_id AND e.user_id = ?
    WHERE c.status = 'published'
";

$params = [$user_id];
$types = "i";

if ($selected_category !== 'all') {
    $courses_query .= " AND cat.category_slug = ?";
    $params[] = $selected_category;
    $types .= "s";
}

if (!empty($search_query)) {
    $courses_query .= " AND (c.course_title LIKE ? OR c.course_description LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$courses_query .= " ORDER BY c.is_featured DESC, c.created_at DESC";

$stmt = $conn->prepare($courses_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}
$stmt->close();

$stats_query = "
    SELECT
        COUNT(DISTINCT e.course_id) as enrolled_courses,
        COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) as completed_courses,
        COALESCE(AVG(e.progress_percentage), 0) as avg_progress
    FROM course_enrollments e
    WHERE e.user_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$user_stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$xp_query = "SELECT COALESCE(SUM(xp_points), 0) AS total_xp FROM user_xp WHERE user_id = ?";
$xp_stmt  = $conn->prepare($xp_query);
$xp_stmt->bind_param("i", $user_id);
$xp_stmt->execute();
$user_xp  = (int) $xp_stmt->get_result()->fetch_assoc()['total_xp'];
$xp_stmt->close();

$conn->query("CALL sp_update_streak($user_id)");

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=3.91">
    <link rel="stylesheet" href="css/courses.css?v=1.11.02">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01.01">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="preconnect" href="https:
    <link rel="preconnect" href="https:
    <link href="https:
</head>
<body>
    <!-- Navegación -->
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="brand-logo">
            </div>
<div class="nav-menu" id="navMenu">
                <a href="news.php" class="nav-link">Noticias</a>
                <a href="courses.php" class="nav-link active">Cursos</a>
                <a href="blog.php" class="nav-link">Comunidad</a>
                <a href="friends.php" class="nav-link">Amigos</a>
            </div>
            <div class="nav-user">
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <div class="user-dropdown">
                        <a href="profile.php">Mi Perfil</a>
                        <?php if ($is_admin || $is_instructor): ?>
                            <a href="admin/index.php">Moderación</a>
                        <?php endif; ?>
                        <a href="process/logout_process.php">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="courses-main">
        <!-- Header de cursos -->
        <section class="courses-header">
            <div class="container">
                <div class="header-content">
                    <div class="header-text">
                        <h1>Explora Nuestros Cursos</h1>
                        <p>Desarrolla nuevas habilidades con contenido diseñado por expertos de la industria</p>
                    </div>
                    <div class="header-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $user_stats['enrolled_courses']; ?></div>
                            <div class="stat-label">Cursos Inscritos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $user_stats['completed_courses']; ?></div>
                            <div class="stat-label">Completados</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo round($user_stats['avg_progress']); ?>%</div>
                            <div class="stat-label">Progreso Promedio</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($user_xp); ?></div>
                            <div class="stat-label">XP Total</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros y búsqueda -->
        <section class="courses-filters">
            <div class="container">
                <form method="GET" action="courses.php" class="filters-form">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                            <path d="M8 15A7 7 0 108 1a7 7 0 000 14zM15 15l3.5 3.5" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <input
                            type="text"
                            name="search"
                            class="search-input"
                            placeholder="Buscar cursos..."
                            value="<?php echo htmlspecialchars($search_query); ?>"
                        >
                    </div>
                    <div class="category-filters">
                        <button
                            type="submit"
                            name="category"
                            value="all"
                            class="category-btn <?php echo $selected_category === 'all' ? 'active' : ''; ?>"
                        >
                            Todos
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button
                                type="submit"
                                name="category"
                                value="<?php echo htmlspecialchars($category['category_slug']); ?>"
                                class="category-btn <?php echo $selected_category === $category['category_slug'] ? 'active' : ''; ?>"
                                style="--category-color: <?php echo $category['color']; ?>"
                            >
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Grid de cursos -->
        <section class="courses-grid-section">
            <div class="container">
                <?php if (empty($courses)): ?>
                    <div class="no-results">
                        <svg class="no-results-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="2"/>
                        </svg>
                        <h3>No se encontraron cursos</h3>
                        <p>Intenta ajustar tus filtros o búsqueda</p>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <?php if ($course['is_featured']): ?>
                                    <div class="course-badge">Destacado</div>
                                <?php endif; ?>

                                <div class="course-thumbnail">
                                    <?php if (!empty($course['thumbnail_url'])): ?>
                                        <img
                                            src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>"
                                            alt="<?php echo htmlspecialchars($course['course_title']); ?>"
                                        >
                                    <?php else: ?>
                                        <svg viewBox="0 0 400 200" xmlns="http:
                                            <defs>
                                                <linearGradient id="courseGrad<?php echo $course['course_id']; ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                    <stop offset="0%" style="stop-color:<?php echo $course['category_color']; ?>;stop-opacity:0.8" />
                                                    <stop offset="100%" style="stop-color:<?php echo $course['category_color']; ?>;stop-opacity:0.5" />
                                                </linearGradient>
                                            </defs>
                                            <rect width="400" height="200" fill="url(#courseGrad<?php echo $course['course_id']; ?>)"/>
                                            <g transform="translate(200, 100)">
                                                <circle cx="0" cy="0" r="30" fill="white" opacity="0.3"/>
                                                <path d="M-15,-15 L-15,15 L15,15 L15,-15 Z M-10,-10 L-10,10 L10,10 L10,-10 Z" fill="white" opacity="0.6"/>
                                                <circle cx="0" cy="0" r="8" fill="white"/>
                                            </g>
                                        </svg>
                                    <?php endif; ?>
                                    <div class="course-category-tag" style="background-color: <?php echo htmlspecialchars($course['category_color']); ?>">
                                        <?php echo htmlspecialchars($course['category_name']); ?>
                                    </div>
                                </div>

                                <div class="course-content">
                                    <h3 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                                    <p class="course-description"><?php echo htmlspecialchars(substr($course['course_description'], 0, 120)) . '...'; ?></p>

                                    <div class="course-meta">
                                        <div class="meta-item">
                                            <svg viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/>
                                            </svg>
                                            <span><?php echo ucfirst($course['difficulty_level']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <svg viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v-6h2v6zm0-8H9V3h2v2z"/>
                                            </svg>
                                            <span><?php echo $course['estimated_hours']; ?> horas</span>
                                        </div>
                                    </div>

                                    <?php if ($course['is_enrolled']): ?>
                                        <div class="course-progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $course['user_progress']; ?>%"></div>
                                        </div>
                                        <?php if ($course['enrollment_status'] === 'completed'): ?>
                                        <a href="course-view.php?id=<?php echo $course['course_id']; ?>" class="btn-course btn-continue"
                                           style="background:linear-gradient(135deg,#004976,#0099D8);display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                                             Curso Completado
                                        </a>
                                        <?php else: ?>
                                        <a href="course-view.php?id=<?php echo $course['course_id']; ?>" class="btn-course btn-continue">
                                            Continuar Curso
                                        </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="course-detail.php?id=<?php echo $course['course_id']; ?>" class="btn-course btn-enroll">
                                            Ver Detalles
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="/js/chatbot-widget.js?v=1.12.11"></script>
    <?php endif; ?>
    <script src="/js/faq-widget.js?v=1.0"></script>
    <script src="/js/theme.js?v=1.01.05"></script>
    <script src="/js/leaderboard-widget.js?v=1.0.0"></script>
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