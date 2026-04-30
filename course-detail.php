<?php

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';

if ($course_id === 0) {
    header("Location: courses.php");
    exit();
}

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_json MEDIUMTEXT DEFAULT NULL");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_position INT DEFAULT 0");

$course_query = "
    SELECT
        c.*,
        cat.category_name,
        cat.color as category_color,
        u.first_name,
        u.last_name,
        COALESCE(e.enrollment_id, 0) as is_enrolled,
        COALESCE(e.progress_percentage, 0) as user_progress,
        (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) as module_count,
        (SELECT COUNT(*) FROM course_lessons cl
         INNER JOIN course_modules cm ON cl.module_id = cm.module_id
         WHERE cm.course_id = c.course_id) as lesson_count
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    LEFT JOIN course_enrollments e ON c.course_id = e.course_id AND e.user_id = ?
    WHERE c.course_id = ? AND c.status = 'published'
";

$stmt = $conn->prepare($course_query);
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: courses.php");
    exit();
}

$course = $result->fetch_assoc();
$stmt->close();

$modules_query = "
    SELECT
        m.*,
        (SELECT COUNT(*) FROM course_lessons WHERE module_id = m.module_id) as lesson_count,
        (SELECT SUM(duration_minutes) FROM course_lessons WHERE module_id = m.module_id) as total_duration
    FROM course_modules m
    WHERE m.course_id = ?
    ORDER BY m.module_order ASC
";

$stmt = $conn->prepare($modules_query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$modules_result = $stmt->get_result();
$modules = [];
while ($row = $modules_result->fetch_assoc()) {
    $modules[] = $row;
}
$stmt->close();

foreach ($modules as &$module) {
    $lessons_query = "
        SELECT
            l.*,
            COALESCE(lp.is_completed, 0) as is_completed
        FROM course_lessons l
        LEFT JOIN lesson_progress lp ON l.lesson_id = lp.lesson_id AND lp.user_id = ?
        WHERE l.module_id = ?
        ORDER BY l.lesson_order ASC
    ";

    $stmt = $conn->prepare($lessons_query);
    $stmt->bind_param("ii", $user_id, $module['module_id']);
    $stmt->execute();
    $lessons_result = $stmt->get_result();
    $module['lessons'] = [];
    while ($lesson = $lessons_result->fetch_assoc()) {
        $module['lessons'][] = $lesson;
    }
    $stmt->close();
}

$conn->close();

$slides_json   = null;
$slides_module = 0;

if (!empty($course['slides_json'])) {
    $slides_decoded = json_decode($course['slides_json'], true);

    if (isset($slides_decoded['slides'])) {
        $slides_arr  = $slides_decoded['slides'];
    } else {
        $slides_arr  = is_array($slides_decoded) ? $slides_decoded : [];
    }
    if (!empty($slides_arr)) {
        $slides_json   = $course['slides_json'];
        $slides_module = intval($course['slides_position'] ?? 0);
        $slides_count  = count($slides_arr);
    }
}

$learning_objectives = json_decode($course['learning_objectives'], true) ?? [];
$instructor_name = !empty($course['first_name']) ? $course['first_name'] . ' ' . $course['last_name'] : 'Instructor';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_title']); ?> - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="css/courses.css?v=1.11.01">
    <link rel="stylesheet" href="css/course-detail.css?v=1.11">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
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
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="user-dropdown">
                        <a href="profile.php">Mi Perfil</a>
                        <?php if ($is_admin): ?>
                            <a href="admin/index.php">Moderación</a>
                        <?php endif; ?>
                        <a href="process/logout_process.php">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="course-detail-main">
        <!-- Header del curso -->
        <section class="course-detail-header">
            <div class="container">
                <div class="breadcrumb">
                    <a href="courses.php">Cursos</a>
                    <span>/</span>
                    <a href="courses.php?category=<?php echo htmlspecialchars($course['category_slug'] ?? ''); ?>">
                        <?php echo htmlspecialchars($course['category_name']); ?>
                    </a>
                    <span>/</span>
                    <span><?php echo htmlspecialchars($course['course_title']); ?></span>
                </div>

                <div class="header-content">
                    <div class="header-info">
                        <div class="category-badge" style="background-color: <?php echo htmlspecialchars($course['category_color']); ?>">
                            <?php echo htmlspecialchars($course['category_name']); ?>
                        </div>
                        <h1><?php echo htmlspecialchars($course['course_title']); ?></h1>
                        <p class="course-intro"><?php echo htmlspecialchars($course['course_description']); ?></p>

                        <div class="course-stats-inline">
                            <div class="stat-inline">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                                </svg>
                                <span><?php echo ucfirst($course['difficulty_level']); ?></span>
                            </div>
                            <div class="stat-inline">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                </svg>
                                <span><?php echo $course['estimated_hours']; ?> horas</span>
                            </div>
                            <div class="stat-inline">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                                </svg>
                                <span><?php echo $lesson_count; ?> lecciones</span>
                            </div>
                            <div class="stat-inline">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                                </svg>
                                <span><?php echo $course['enrollment_count']; ?> estudiantes</span>
                            </div>
                        </div>
                    </div>

                    <div class="header-action">
                        <div class="action-card">
                            <?php if ($course['is_enrolled']): ?>
                                <div class="enrolled-status">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                    <span>Ya estás inscrito</span>
                                </div>
                                <div class="progress-info">
                                    <div class="progress-label">
                                        <span>Progreso del curso</span>
                                        <span class="progress-percentage"><?php echo round($course['user_progress']); ?>%</span>
                                    </div>
                                    <div class="progress-bar-large">
                                        <div class="progress-fill" style="width: <?php echo $course['user_progress']; ?>%"></div>
                                    </div>
                                </div>
                                <a href="course-view.php?id=<?php echo $course_id; ?>" class="btn-primary-large">
                                    Continuar Aprendiendo
                                </a>
                            <?php else: ?>
                                <button onclick="enrollCourse(<?php echo $course_id; ?>)" class="btn-primary-large">
                                    Inscribirse al Curso
                                </button>
                                <p class="enrollment-note">Acceso completo e ilimitado</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contenido del curso -->
        <div class="container">
            <div class="course-content-layout">
                <!-- Columna principal -->
                <div class="main-content">
                    <!-- Objetivos de aprendizaje -->
                    <?php if (!empty($learning_objectives)): ?>
                    <section class="content-section">
                        <h2>Lo que aprenderás</h2>
                        <div class="objectives-grid">
                            <?php foreach ($learning_objectives as $objective): ?>
                            <div class="objective-item">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                <span><?php echo htmlspecialchars($objective); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Contenido del curso -->
                    <section class="content-section">
                        <h2>Contenido del curso</h2>
                        <div class="course-curriculum">
                            <?php foreach ($modules as $index => $module): ?>
                            <div class="module-accordion">
                                <div class="module-header" onclick="toggleModule(<?php echo $index; ?>)">
                                    <div class="module-info">
                                        <svg class="expand-icon" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                        </svg>
                                        <div>
                                            <h3><?php echo htmlspecialchars($module['module_title']); ?></h3>
                                            <?php if (!empty($module['module_description'])): ?>
                                            <p><?php echo htmlspecialchars($module['module_description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="module-meta">
                                        <span><?php echo $module['lesson_count']; ?> lecciones</span>
                                        <span>•</span>
                                        <span><?php echo $module['total_duration']; ?> min</span>
                                    </div>
                                </div>
                                <div class="module-content" id="module-<?php echo $index; ?>">
                                    <div class="lessons-list">
                                        <?php foreach ($module['lessons'] as $lesson): ?>
                                        <div class="lesson-item <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>">
                                            <div class="lesson-icon">
                                                <?php if ($lesson['is_completed']): ?>
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                                    </svg>
                                                <?php elseif ($lesson['content_type'] === 'video'): ?>
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                                                    </svg>
                                                <?php elseif ($lesson['content_type'] === 'text'): ?>
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                                                    </svg>
                                                <?php elseif ($lesson['content_type'] === 'quiz'): ?>
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="lesson-info">
                                                <h4><?php echo htmlspecialchars($lesson['lesson_title']); ?></h4>
                                                <div class="lesson-meta">
                                                    <span class="lesson-type"><?php echo ucfirst($lesson['content_type']); ?></span>
                                                    <?php if ($lesson['duration_minutes'] > 0): ?>
                                                    <span>•</span>
                                                    <span><?php echo $lesson['duration_minutes']; ?> min</span>
                                                    <?php endif; ?>
                                                    <?php if ($lesson['is_preview']): ?>
                                                    <span class="preview-badge">Vista previa</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($course['is_enrolled'] || $lesson['is_preview']): ?>
                                            <a href="course-view.php?id=<?php echo $course_id; ?>&lesson=<?php echo $lesson['lesson_id']; ?>" class="lesson-action">
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                                                </svg>
                                            </a>
                                            <?php else: ?>
                                            <div class="lesson-locked">
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                                                </svg>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>

                                        <?php

                                        $show_slides_here = $slides_json && isset($module['module_id']) && intval($slides_module) === intval($module['module_id']);
                                        if ($show_slides_here):
                                        ?>
                                        <div class="lesson-item" style="border-left:3px solid #0099D8;background:linear-gradient(135deg,#f0f9ff,#e5f4fc);">
                                            <div class="lesson-icon" style="background:#0099D8;color:white;border-radius:8px;display:flex;align-items:center;justify-content:center;width:32px;height:32px;flex-shrink:0;">
                                                <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;">
                                                    <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm2 1v8h12V5H4z"/>
                                                </svg>
                                            </div>
                                            <div class="lesson-info">
                                                <h4 style="color:#004976;">Presentación del Curso</h4>
                                                <div class="lesson-meta">
                                                    <span class="lesson-type" style="background:#E5F4FC;color:#0099D8;">✨ Generada por IA</span>
                                                    <span>•</span>
                                                    <span><?php echo $slides_count ?? 8; ?> diapositivas</span>
                                                </div>
                                            </div>
                                            <?php if ($course['is_enrolled']): ?>
                                            <a href="course-slides-viewer.php?course_id=<?php echo $course_id; ?>" target="_blank" class="lesson-action" style="background:#0099D8;">
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                                                </svg>
                                            </a>
                                            <?php else: ?>
                                            <div class="lesson-locked">
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                                                </svg>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Prerequisitos -->
                    <?php if (!empty($course['prerequisites'])): ?>
                    <section class="content-section">
                        <h2>Requisitos previos</h2>
                        <div class="prerequisites-box">
                            <?php echo nl2br(htmlspecialchars($course['prerequisites'])); ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="sidebar-content">
                    <!-- Instructor -->
                    <div class="sidebar-card">
                        <h3>Instructor</h3>
                        <div class="instructor-info">
                            <div class="instructor-avatar">
                                <?php echo strtoupper(substr($instructor_name, 0, 1)); ?>
                            </div>
                            <div>
                                <h4><?php echo htmlspecialchars($instructor_name); ?></h4>
                                <p>Experto en <?php echo htmlspecialchars($course['category_name']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Información adicional -->
                    <div class="sidebar-card">
                        <h3>Este curso incluye</h3>
                        <ul class="includes-list">
                            <li>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                                </svg>
                                <span><?php echo $course['estimated_hours']; ?> horas de contenido en video</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                                </svg>
                                <span><?php echo $lesson_count; ?> lecciones</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z"/>
                                </svg>
                                <span>Recursos descargables</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                                </svg>
                                <span>Certificado de finalización</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                </svg>
                                <span>Acceso ilimitado</span>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </main>
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="/js/chatbot-widget.js?v=1.12"></script>
    <?php endif; ?>
    <script src="js/course-detail.js?v=1.1"></script>
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