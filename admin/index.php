<?php
session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

$admin_name = $_SESSION['user_name'] ?? 'Administrador';

$metrics_query = "
    SELECT
        (SELECT COUNT(*) FROM users WHERE status = 'active') AS total_users,
        (SELECT COUNT(*) FROM courses WHERE status = 'published') AS published_courses,
        (SELECT COUNT(*) FROM courses WHERE status = 'draft') AS draft_courses,
        (SELECT COUNT(*) FROM course_enrollments) AS total_enrollments,
        (SELECT COUNT(*) FROM course_enrollments WHERE status = 'completed') AS completed_enrollments,
        (SELECT COALESCE(AVG(progress_percentage), 0) FROM course_enrollments) AS avg_progress
";
$metrics = $conn->query($metrics_query)->fetch_assoc();

$popular_query = "
    SELECT c.course_id, c.course_title, c.status, c.enrollment_count,
           cat.category_name, cat.color AS category_color
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    ORDER BY c.enrollment_count DESC
    LIMIT 5
";
$popular_courses = $conn->query($popular_query)->fetch_all(MYSQLI_ASSOC);

$recent_users_query = "
    SELECT user_id, first_name, last_name, email, department, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
";
$recent_users = $conn->query($recent_users_query)->fetch_all(MYSQLI_ASSOC);

$cat_stats_query = "
    SELECT cat.category_name, cat.color,
           COUNT(e.enrollment_id) AS total_enrollments
    FROM course_categories cat
    LEFT JOIN courses c ON c.category_id = cat.category_id
    LEFT JOIN course_enrollments e ON e.course_id = c.course_id
    WHERE cat.status = 'active'
    GROUP BY cat.category_id
    ORDER BY total_enrollments DESC
";
$cat_stats = $conn->query($cat_stats_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Whirlpool Learning</title>
    <link rel="stylesheet" href="../css/styles.css?v=4.3">
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Open Sans', sans-serif;
            background: #F0F4F8;
            color: #2D2D2D;
        }

        .admin-topbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #E8E8E8;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-title {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            color: #003C64;
        }

        .topbar-subtitle {
            font-size: 0.8rem;
            color: #6B6B6B;
            margin-top: 0.1rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #003C64, #0096DC);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.875rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,60,100,0.25);
        }

        .btn-primary svg { width: 16px; height: 16px; }

        .admin-content {
            padding: 2rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: 1px solid #F0F0F0;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .metric-icon svg { width: 22px; height: 22px; }

        .metric-icon-blue { background: #E6F0FA; }
        .metric-icon-blue svg { stroke: #003C64; }

        .metric-icon-accent { background: #E0F4FF; }
        .metric-icon-accent svg { stroke: #0096DC; }

        .metric-icon-green { background: #E6FAF0; }
        .metric-icon-green svg { stroke: #00875A; }

        .metric-icon-orange { background: #FFF4E6; }
        .metric-icon-orange svg { stroke: #D97706; }

        .metric-value {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: #003C64;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .metric-label {
            font-size: 0.8rem;
            color: #6B6B6B;
            font-weight: 600;
        }

        .metric-sub {
            font-size: 0.75rem;
            color: #ADADAD;
            margin-top: 0.25rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
        }

        .panel-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: 1px solid #F0F0F0;
            overflow: hidden;
        }

        .panel-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #F0F0F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #003C64;
        }

        .panel-link {
            font-size: 0.8rem;
            color: #0096DC;
            text-decoration: none;
            font-weight: 600;
        }

        .panel-link:hover { text-decoration: underline; }

        /* Tabla de cursos populares */
        .data-table {
            width: 100%;
        }

        .data-row {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            border-bottom: 1px solid #F8F8F8;
            gap: 1rem;
            transition: background 0.15s;
        }

        .data-row:last-child { border-bottom: none; }
        .data-row:hover { background: #FAFAFA; }

        .course-rank {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #E6F0FA;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #003C64;
            flex-shrink: 0;
        }

        .course-row-info { flex: 1; min-width: 0; }

        .course-row-title {
            font-weight: 600;
            font-size: 0.875rem;
            color: #2D2D2D;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .course-row-cat {
            font-size: 0.75rem;
            color: #8C8C8C;
            margin-top: 0.15rem;
        }

        .course-row-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .enrollments-badge {
            font-size: 0.75rem;
            font-weight: 700;
            color: #003C64;
            background: #E6F0FA;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
        }

        .status-pill {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
        }

        .status-published { background: #E6FAF0; color: #00875A; }
        .status-draft { background: #FFF4E6; color: #D97706; }

        /* Usuarios recientes */
        .user-row {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1.5rem;
            border-bottom: 1px solid #F8F8F8;
            transition: background 0.15s;
        }

        .user-row:last-child { border-bottom: none; }
        .user-row:hover { background: #FAFAFA; }

        .user-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #003C64, #0096DC);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            font-family: 'Nunito Sans', sans-serif;
            flex-shrink: 0;
        }

        .user-row-info { flex: 1; min-width: 0; }

        .user-row-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: #2D2D2D;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-row-email {
            font-size: 0.75rem;
            color: #8C8C8C;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-dept {
            font-size: 0.7rem;
            background: #F0F4F8;
            color: #4A4A4A;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
            font-weight: 600;
            flex-shrink: 0;
        }

        /* Estadísticas por categoría */
        .cat-stat-item {
            padding: 0.875rem 1.5rem;
            border-bottom: 1px solid #F8F8F8;
        }

        .cat-stat-item:last-child { border-bottom: none; }

        .cat-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .cat-stat-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #2D2D2D;
        }

        .cat-stat-count {
            font-size: 0.8rem;
            font-weight: 700;
            color: #003C64;
        }

        .cat-progress-track {
            height: 6px;
            background: #F0F0F0;
            border-radius: 99px;
            overflow: hidden;
        }

        .cat-progress-fill {
            height: 100%;
            border-radius: 99px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            text-decoration: none;
            border: 1px solid #F0F0F0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,60,100,0.1);
            border-color: #0096DC;
        }

        .quick-action-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, #003C64, #0096DC);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .quick-action-icon svg {
            width: 20px;
            height: 20px;
            stroke: white;
        }

        .quick-action-label {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            color: #003C64;
        }

        .quick-action-desc {
            font-size: 0.75rem;
            color: #8C8C8C;
            margin-top: 0.15rem;
        }
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ROLE   = "<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'admin'); ?>";
        window.ADMIN_ACTIVE = "dashboard";
    </script>
    <script src="js/admin-navbar.js" defer></script>
</head>
<body>
<main class="admin-main">
        <div class="admin-topbar">
            <div>
                <div class="topbar-title">Dashboard</div>
                <div class="topbar-subtitle">Resumen general de la plataforma</div>
            </div>
            <a href="course-form.php" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo Curso
            </a>
        </div>

        <div class="admin-content">

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon metric-icon-blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value"><?php echo number_format($metrics['total_users']); ?></div>
                        <div class="metric-label">Usuarios Activos</div>
                        <div class="metric-sub">En la plataforma</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon metric-icon-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value"><?php echo $metrics['published_courses']; ?></div>
                        <div class="metric-label">Cursos Publicados</div>
                        <div class="metric-sub"><?php echo $metrics['draft_courses']; ?> en borrador</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon metric-icon-green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value"><?php echo number_format($metrics['total_enrollments']); ?></div>
                        <div class="metric-label">Inscripciones Total</div>
                        <div class="metric-sub"><?php echo $metrics['completed_enrollments']; ?> completadas</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon metric-icon-orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value"><?php echo round($metrics['avg_progress']); ?>%</div>
                        <div class="metric-label">Progreso Promedio</div>
                        <div class="metric-sub">Entre todos los usuarios</div>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="course-form.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="quick-action-label">Crear Curso</div>
                        <div class="quick-action-desc">Añadir nuevo contenido</div>
                    </div>
                </a>
                <a href="courses.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="quick-action-label">Gestionar Cursos</div>
                        <div class="quick-action-desc">Editar y publicar</div>
                    </div>
                </a>
                <a href="../courses.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="quick-action-label">Ver Plataforma</div>
                        <div class="quick-action-desc">Vista del estudiante</div>
                    </div>
                </a>
            </div>

            <!-- Grid de tablas -->
            <div class="dashboard-grid">
                <!-- Cursos más populares -->
                <div class="panel-card">
                    <div class="panel-header">
                        <div class="panel-title">Cursos más populares</div>
                        <a href="courses.php" class="panel-link">Ver todos →</a>
                    </div>
                    <div class="data-table">
                        <?php if (empty($popular_courses)): ?>
                            <div style="padding: 2rem; text-align: center; color: #8C8C8C; font-size: 0.875rem;">
                                No hay cursos registrados aún
                            </div>
                        <?php else: ?>
                            <?php foreach ($popular_courses as $i => $course): ?>
                                <div class="data-row">
                                    <div class="course-rank"><?php echo $i + 1; ?></div>
                                    <div class="course-row-info">
                                        <div class="course-row-title"><?php echo htmlspecialchars($course['course_title']); ?></div>
                                        <div class="course-row-cat"><?php echo htmlspecialchars($course['category_name']); ?></div>
                                    </div>
                                    <div class="course-row-meta">
                                        <span class="enrollments-badge"><?php echo $course['enrollment_count']; ?> inscritos</span>
                                        <span class="status-pill status-<?php echo $course['status']; ?>">
                                            <?php echo $course['status'] === 'published' ? 'Publicado' : 'Borrador'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                    <!-- Usuarios recientes -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-title">Usuarios recientes</div>
                        </div>
                        <?php foreach ($recent_users as $user): ?>
                            <div class="user-row">
                                <div class="user-avatar-sm">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                                <div class="user-row-info">
                                    <div class="user-row-name">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="user-row-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <span class="user-dept"><?php echo htmlspecialchars($user['department']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Inscripciones por categoría -->
                    <div class="panel-card">
                        <div class="panel-header">
                            <div class="panel-title">Inscripciones por categoría</div>
                        </div>
                        <?php
                        $max_enrollments = max(array_column($cat_stats, 'total_enrollments') ?: [1]);
                        foreach ($cat_stats as $cat):
                            $pct = $max_enrollments > 0 ? ($cat['total_enrollments'] / $max_enrollments) * 100 : 0;
                        ?>
                            <div class="cat-stat-item">
                                <div class="cat-stat-header">
                                    <span class="cat-stat-name"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                                    <span class="cat-stat-count"><?php echo $cat['total_enrollments']; ?></span>
                                </div>
                                <div class="cat-progress-track">
                                    <div class="cat-progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo htmlspecialchars($cat['color']); ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>