<?php

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id        = $_SESSION['user_id'];
$course_id      = isset($_GET['id'])     ? intval($_GET['id'])     : 0;
$lesson_id      = isset($_GET['lesson']) ? intval($_GET['lesson']) : 0;
$is_slides_view = isset($_GET['lesson']) && $_GET['lesson'] === 'slides';

if ($course_id === 0) {
    header("Location: courses.php");
    exit();
}

$enrollment_query = "SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$enrollment) {
    header("Location: course-detail.php?id=" . $course_id);
    exit();
}

$course_query = "
    SELECT c.*, cat.category_name, cat.color as category_color
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    WHERE c.course_id = ?
";
$stmt = $conn->prepare($course_query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

$modules_query = "
    SELECT DISTINCT m.module_id, m.course_id, m.module_title, m.module_description, m.module_order,
           (SELECT COUNT(*) FROM course_lessons WHERE module_id = m.module_id) as lesson_count
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
        SELECT l.*, COALESCE(lp.is_completed, 0) as is_completed
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
unset($module);

$current_lesson = null;
if ($lesson_id === 0) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if (!$lesson['is_completed']) {
                $current_lesson = $lesson;
                break 2;
            }
        }
    }
    if (!$current_lesson && !empty($modules) && !empty($modules[0]['lessons'])) {
        $current_lesson = $modules[0]['lessons'][0];
    }
} else {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if ($lesson['lesson_id'] == $lesson_id) {
                $current_lesson = $lesson;
                break 2;
            }
        }
    }
}

if (!$current_lesson && !$is_slides_view) {
    header("Location: course-detail.php?id=" . $course_id);
    exit();
}
if (!$current_lesson) $current_lesson = ['lesson_id'=>0,'lesson_title'=>'','content_type'=>'slides','duration_minutes'=>0,'lesson_description'=>'','is_completed'=>0,'resources'=>''];

$update_activity = $conn->prepare("UPDATE course_enrollments SET last_accessed = NOW() WHERE enrollment_id = ?");
$update_activity->bind_param("i", $enrollment['enrollment_id']);
$update_activity->execute();
$update_activity->close();

$resources = !empty($current_lesson['resources']) ? json_decode($current_lesson['resources'], true) : [];

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_json MEDIUMTEXT DEFAULT NULL");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_position INT DEFAULT 0");
$sl = $conn->prepare("SELECT slides_json, slides_position FROM courses WHERE course_id = ?");
$slides_json = null; $slides_module_id = 0; $slides_count = 0;
if ($sl) {
    $sl->bind_param('i', $course_id);
    $sl->execute();
    $sl_row = $sl->get_result()->fetch_assoc();
    $sl->close();
    if (!empty($sl_row['slides_json'])) {
        $slides_decoded = json_decode($sl_row['slides_json'], true);
        $slides_arr = isset($slides_decoded['slides']) ? $slides_decoded['slides'] : (is_array($slides_decoded) ? $slides_decoded : []);
        if (!empty($slides_arr)) {
            $slides_json      = $sl_row['slides_json'];
            $slides_module_id = intval($sl_row['slides_position'] ?? 0);
            $slides_count     = count($slides_arr);
        }
    }
}

$lesson_quiz         = null;
$quiz_block_complete = false;
if ($current_lesson && isset($current_lesson['lesson_id']) && $current_lesson['lesson_id'] !== 0 && $current_lesson['lesson_id'] !== 'slides') {
    $qz = $conn->prepare("SELECT quiz_id, is_required FROM lesson_quizzes WHERE lesson_id = ? LIMIT 1");
    if ($qz) {
        $qz->bind_param('i', $current_lesson['lesson_id']);
        $qz->execute();
        $qz_row = $qz->get_result()->fetch_assoc();
        $qz->close();
        if ($qz_row) {
            $lesson_quiz = $qz_row;
            if (intval($qz_row['is_required']) === 1) {
                $qa = $conn->prepare("SELECT COUNT(*) as cnt FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND passed=1 AND completed_at IS NOT NULL");
                $qa->bind_param('ii', $qz_row['quiz_id'], $user_id);
                $qa->execute();
                $qa_passed = intval($qa->get_result()->fetch_assoc()['cnt']) > 0;
                $qa->close();
                if (!$qa_passed && !$current_lesson['is_completed']) {
                    $quiz_block_complete = true;
                }
            }
        }
    }
}

$conn->close();

$flat_lessons = [];
foreach ($modules as $mod) {
    foreach ($mod['lessons'] as $les) {
        $les['_module_id'] = $mod['module_id'];
        $flat_lessons[] = $les;
    }
    if ($slides_json && $slides_module_id === intval($mod['module_id'])) {
        $flat_lessons[] = [
            'lesson_id'    => 'slides',
            'lesson_title' => 'Presentación del Curso',
            'content_type' => 'slides',
            '_module_id'   => $mod['module_id'],
        ];
    }
}

$prev_lesson = null;
$next_lesson = null;
$current_flat_index = -1;

if ($is_slides_view) {
    foreach ($flat_lessons as $fi => $fl) {
        if ($fl['lesson_id'] === 'slides') { $current_flat_index = $fi; break; }
    }
} else {
    foreach ($flat_lessons as $fi => $fl) {
        if ($fl['lesson_id'] == ($current_lesson['lesson_id'] ?? 0)) { $current_flat_index = $fi; break; }
    }
}

if ($current_flat_index > 0)                        $prev_lesson = $flat_lessons[$current_flat_index - 1];
if ($current_flat_index < count($flat_lessons) - 1) $next_lesson = $flat_lessons[$current_flat_index + 1];

function lessonUrl($lesson, $course_id) {
    $lid = $lesson['lesson_id'] === 'slides' ? 'slides' : $lesson['lesson_id'];
    return "course-view.php?id={$course_id}&lesson={$lid}";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_lesson['lesson_title']); ?> - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="css/courses.css?v=1.11.01">
    <link rel="stylesheet" href="css/course-view.css?v=1.13.2">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .pdf-toolbar { display:flex; align-items:center; gap:0.875rem; padding:0.75rem 1rem; background:#F8FAFB; border-radius:8px 8px 0 0; border:1px solid #E0E0E0; border-bottom:none; }
        .document-container { border:1px solid #E0E0E0; border-radius:0 0 8px 8px; overflow:hidden; }
        .document-container iframe { width:100%; height:600px; display:block; }
        .video-container { position:relative; padding-bottom:56.25%; height:0; overflow:hidden; background:#000; border-radius:8px; }
        .video-container iframe, .video-container video { position:absolute; top:0; left:0; width:100%; height:100%; border-radius:8px; }
        .btn-download { display:inline-flex; align-items:center; gap:0.4rem; background:#003C64; color:white; padding:0.4rem 0.875rem; border-radius:6px; text-decoration:none; font-size:0.8rem; font-weight:700; margin-left:auto; transition:background 0.2s; }
        .btn-download:hover { background:#0096DC; }
        .link-container { display:flex; justify-content:center; padding:2rem 0; }
        .link-preview-card { background:#F8FAFB; border:1.5px solid #E0E0E0; border-radius:12px; padding:2rem; text-align:center; max-width:480px; width:100%; }
        .btn-external-link { display:inline-flex; align-items:center; gap:0.5rem; background:linear-gradient(135deg,#003C64,#0096DC); color:white; padding:0.625rem 1.25rem; border-radius:8px; text-decoration:none; font-size:0.875rem; font-weight:700; transition:all 0.2s; }
        .btn-external-link:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,60,100,0.25); }
        .rich-text-content { font-size:0.95rem; line-height:1.8; color:#2D2D2D; padding:0.5rem 0; }
        .rich-text-content h3 { font-family:'Nunito Sans',sans-serif; font-size:1.1rem; font-weight:700; color:#003C64; margin:1.25rem 0 0.5rem; }
        .rich-text-content ul, .rich-text-content ol { padding-left:1.5rem; margin:0.5rem 0; }
        .rich-text-content li { margin-bottom:0.3rem; }
        .rich-text-content b, .rich-text-content strong { font-weight:700; color:#1A1A1A; }
        .quiz-container iframe { width:100%; min-height:600px; border-radius:8px; border:1px solid #E0E0E0; }
        .content-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:3rem; gap:0.75rem; color:#8C8C8C; font-size:0.9rem; text-align:center; background:#F8FAFB; border-radius:8px; border:1.5px dashed #D0D0D0; }
        /* Offline download bar */
        .offline-download-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-top: 1.25rem;
            font-size: 0.82rem;
            color: #4a5568;
        }
        .offline-download-bar svg { color: #0099D8; flex-shrink: 0; }
        .offline-download-bar span { flex: 1; }
        .btn-offline {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #004976;
            color: white;
            padding: 0.45rem 1rem;
            border-radius: 7px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 700;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-offline:hover { background: #0099D8; }
    </style>
<?php if ($is_slides_view): ?>
<style>
    .embed-slide[data-type="keypoints"] { background: linear-gradient(135deg,#004976 0%,#002d4a 100%) !important; }
    #slides-sidebar-panel {
        position: fixed;
        top: 80px;
        right: 0;
        width: 300px;
        height: calc(100vh - 80px);
        background: white;
        border-left: 1px solid #e5e7eb;
        box-shadow: -4px 0 16px rgba(0,0,0,0.10);
        overflow-y: auto;
        z-index: 999;
        display: flex;
        flex-direction: column;
    }
    body.slides-mode .viewer-layout {
        display: block !important;
        grid-template-columns: unset !important;
        padding: 80px 300px 0 0 !important;
        margin: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    body.slides-mode .viewer-content {
        padding: 0 !important;
        width: 100% !important;
        background: transparent !important;
    }
    body.slides-mode #slides-embed {
        margin: 0 !important;
        border-radius: 0 !important;
        width: 100% !important;
    }
    body.slides-mode .lesson-container {
        display: none !important;
    }
    @media (max-width: 700px) {
        #slides-sidebar-panel { display: none; }
        body.slides-mode .viewer-layout { padding-right: 0 !important; }
    }
</style>
<?php endif; ?>
</head>
<body class="course-viewer <?php echo $is_slides_view ? 'slides-mode' : ''; ?>">
    <header class="viewer-header">
    <div class="header-left">
        <a href="courses.php" class="back-button" title="Volver a cursos">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"/>
            </svg>
        </a>
        <div class="course-info">
            <h1><?php echo htmlspecialchars($course['course_title']); ?></h1>
                <div class="progress-indicator">
                    <div class="progress-bar-mini">
                        <div class="progress-fill" style="width: <?php echo $enrollment['progress_percentage']; ?>%"></div>
                    </div>
                    <span class="progress-text"><?php echo round($enrollment['progress_percentage']); ?>% completado</span>
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-toggle-sidebar" onclick="toggleSidebar()">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                </svg>
                <span>Contenido</span>
            </button>
        </div>
    </header>

    <div class="viewer-layout" id="<?php echo $is_slides_view ? 'slides-viewer-layout' : 'normal-viewer-layout'; ?>">
        <main class="viewer-content" id="<?php echo $is_slides_view ? 'slides-viewer-content' : ''; ?>">
            <div class="lesson-container" <?php echo $is_slides_view ? 'style="display:none"' : ''; ?>>
                <div class="lesson-header">
                    <div class="lesson-meta">
                        <?php if ($is_slides_view): ?>
                        <span class="lesson-type-badge" style="background:#E5F4FC;color:#0099D8;">✨ Presentación IA</span>
                        <?php else: ?>
                        <?php
                        $type_labels = [
                            'video'    => '🎬 Video',
                            'pdf'      => '📄 PDF',
                            'image'    => '🖼️ Imagen',
                            'text'     => '📝 Texto',
                            'link'     => '🔗 Enlace',
                            'quiz'     => '📋 Quiz',
                            'document' => '📋 Documento',
                        ];
                        $type_label = $type_labels[$current_lesson['content_type']] ?? ucfirst($current_lesson['content_type']);
                        ?>
                        <span class="lesson-type-badge"><?php echo $type_label; ?></span>
                        <?php if ($current_lesson['duration_minutes'] > 0): ?>
                        <span class="lesson-duration">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                            </svg>
                            <?php echo $current_lesson['duration_minutes']; ?> minutos
                        </span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo $is_slides_view ? 'Presentación del Curso' : htmlspecialchars($current_lesson['lesson_title']); ?></h2>
                    <?php if (!$is_slides_view && !empty($current_lesson['lesson_description'])): ?>
                    <p class="lesson-description"><?php echo htmlspecialchars($current_lesson['lesson_description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="lesson-content-area">
                    <?php if ($is_slides_view): ?>
                    <!-- slides se renderizan abajo, fuera del lesson-container -->
                    <?php else: ?>
                    <?php
                    $ctype = $current_lesson['content_type'];
                    $curl  = $current_lesson['content_url']  ?? '';
                    $ctext = $current_lesson['content_text'] ?? '';

                    $is_youtube = strpos($curl, 'youtube.com') !== false || strpos($curl, 'youtu.be') !== false;
                    $is_vimeo   = strpos($curl, 'vimeo.com') !== false;

                    $youtube_id = '';
                    if ($is_youtube) {
                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $curl, $m);
                        $youtube_id = $m[1] ?? '';
                    }

                    $vimeo_id = '';
                    if ($is_vimeo) {
                        preg_match('/vimeo\.com\/(\d+)/', $curl, $m);
                        $vimeo_id = $m[1] ?? '';
                    }
                    ?>

                    <?php if ($ctype === 'video'): ?>
                        <div class="video-container">
                            <?php if ($is_youtube && $youtube_id): ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen></iframe>

                            <?php elseif ($is_vimeo && $vimeo_id): ?>
                                <iframe src="https://player.vimeo.com/video/<?php echo $vimeo_id; ?>"
                                        frameborder="0"
                                        allow="autoplay; fullscreen; picture-in-picture"
                                        allowfullscreen></iframe>

                            <?php elseif (!empty($curl)): ?>
                                <video controls style="width:100%;max-height:520px;background:#000;border-radius:8px;">
                                    <source src="<?php echo htmlspecialchars($curl); ?>" type="video/mp4">
                                    Tu navegador no soporta el elemento de video.
                                </video>

                            <?php else: ?>
                                <div class="content-placeholder">
                                    <span style="font-size:2.5rem;">🎬</span>
                                    <p>No se ha cargado ningún video para esta lección.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($ctype === 'pdf'): ?>
                        <?php if (!empty($curl)): ?>
                            <div class="pdf-toolbar">
                                <span style="font-size:1.5rem;">📄</span>
                                <span style="font-weight:600;color:#003C64;">Documento PDF</span>
                                <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" download class="btn-download">
                                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                                    </svg>
                                    Descargar PDF
                                </a>
                            </div>
                            <div class="document-container">
                                <iframe src="<?php echo htmlspecialchars($curl); ?>#toolbar=1&view=FitH" frameborder="0"></iframe>
                            </div>
                        <?php else: ?>
                            <div class="content-placeholder">
                                <span style="font-size:2.5rem;">📄</span>
                                <p>No se ha adjuntado ningún PDF a esta lección.</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($ctype === 'image'): ?>
                        <?php if (!empty($curl)): ?>
                            <div class="image-container">
                                <img src="<?php echo htmlspecialchars($curl); ?>"
                                     alt="<?php echo htmlspecialchars($current_lesson['lesson_title']); ?>"
                                     style="max-width:100%;border-radius:8px;display:block;margin:0 auto;">
                                <div style="text-align:center;margin-top:1rem;">
                                    <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" download class="btn-download">
                                        <svg viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;">
                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                                        </svg>
                                        Descargar imagen
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="content-placeholder">
                                <span style="font-size:2.5rem;">🖼️</span>
                                <p>No se ha adjuntado ninguna imagen a esta lección.</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($ctype === 'text'): ?>
                        <div class="text-content rich-text-content">
                            <?php if (!empty($ctext)): ?>
                                <?php echo $ctext;?>
                            <?php elseif (!empty($curl)): ?>
                                <?php echo nl2br(htmlspecialchars($curl)); ?>
                            <?php else: ?>
                                <div class="content-placeholder">
                                    <span style="font-size:2.5rem;">📝</span>
                                    <p>Esta lección no tiene contenido de texto aún.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($ctype === 'link'): ?>
                        <?php if (!empty($curl)): ?>
                            <div class="link-container">
                                <div class="link-preview-card">
                                    <div style="font-size:2.5rem;margin-bottom:0.75rem;">🔗</div>
                                    <p style="font-size:0.95rem;color:#4A4A4A;margin-bottom:1.25rem;">Esta lección incluye un recurso externo. Haz clic para abrirlo en una nueva pestaña.</p>
                                    <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" rel="noopener noreferrer" class="btn-external-link">
                                        <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;">
                                            <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                        </svg>
                                        Abrir recurso externo
                                    </a>
                                    <div style="margin-top:0.75rem;font-size:0.78rem;color:#8C8C8C;word-break:break-all;"><?php echo htmlspecialchars($curl); ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="content-placeholder">
                                <span style="font-size:2.5rem;">🔗</span>
                                <p>No se ha configurado ningún link para esta lección.</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($ctype === 'quiz'): ?>
                        <?php if (!empty($curl)): ?>
                            <div class="quiz-container">
                                <iframe src="<?php echo htmlspecialchars($curl); ?>"
                                        frameborder="0"
                                        style="width:100%;min-height:600px;border-radius:8px;border:1px solid #E0E0E0;">
                                </iframe>
                                <div style="text-align:center;margin-top:0.75rem;">
                                    <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" class="btn-external-link" style="display:inline-flex;">
                                        <svg viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;">
                                            <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                        </svg>
                                        Abrir quiz en nueva pestaña
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="content-placeholder">
                                <span style="font-size:2.5rem;">📝</span>
                                <p>El quiz no está disponible aún.</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($ctype === 'document'): ?>
                        <?php if (!empty($curl)): ?>
                            <div class="pdf-toolbar">
                                <span style="font-size:1.5rem;">📋</span>
                                <span style="font-weight:600;color:#003C64;">Documento</span>
                                <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" download class="btn-download">
                                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                                    </svg>
                                    Descargar
                                </a>
                            </div>
                            <div class="document-container">
                                <iframe src="<?php echo htmlspecialchars($curl); ?>" frameborder="0"></iframe>
                            </div>
                        <?php else: ?>
                            <div class="content-placeholder">
                                <span style="font-size:2.5rem;">📋</span>
                                <p>Documento no disponible.</p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="content-placeholder">
                            <span style="font-size:2.5rem;">📁</span>
                            <p>Tipo de contenido no reconocido: <strong><?php echo htmlspecialchars($ctype); ?></strong></p>
                        </div>
                    <?php endif; ?>
                    <?php endif; // end !is_slides_view ?>
                </div>

                <?php if (!$is_slides_view && !empty($resources)): ?>
                <div class="lesson-resources">
                    <h3>Recursos adicionales</h3>
                    <div class="resources-list">
                        <?php foreach ($resources as $resource): ?>
                        <a href="<?php echo htmlspecialchars($resource['url'] ?? '#'); ?>" class="resource-item" target="_blank" rel="noopener">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z"/>
                            </svg>
                            <span><?php echo htmlspecialchars($resource['title'] ?? 'Recurso'); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$is_slides_view): ?>

                <?php if ($lesson_quiz): ?>
                <div id="quiz-container" style="margin:1.5rem 0;"></div>
                <?php endif; ?>

                <!-- ── Descarga offline ── -->
                <?php if ($current_lesson['lesson_id'] !== 0): ?>
                <div class="offline-download-bar">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                    </svg>
                    <span>Descarga esta lección para estudiarla sin conexión.</span>
                    <a href="/process/download_lesson.php?lesson_id=<?php echo intval($current_lesson['lesson_id']); ?>&course_id=<?php echo $course_id; ?>"
                       class="btn-offline">
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                        </svg>
                        Descargar offline
                    </a>
                </div>
                <?php endif; ?>

                <div class="lesson-actions">
                    <?php if ($enrollment['status'] === 'completed'): ?>
                    <div style="display:flex;align-items:center;gap:0.875rem;padding:0.875rem 1.125rem;background:linear-gradient(135deg,rgba(0,73,118,0.06),rgba(0,153,216,0.08));border:1.5px solid rgba(0,153,216,0.3);border-radius:12px;margin-bottom:1rem;">
                        <span style="font-size:1.5rem;">🏆</span>
                        <div style="flex:1;">
                            <div style="font-weight:700;color:#004976;font-size:0.9rem;">¡Curso completado!</div>
                            <div style="font-size:0.78rem;color:#5a7a8a;margin-top:0.1rem;">Has terminado todas las lecciones de este curso.</div>
                        </div>
                        <a href="courses.php" style="display:inline-flex;align-items:center;gap:0.4rem;background:#004976;color:white;padding:0.45rem 1rem;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:700;white-space:nowrap;transition:background 0.2s;"
                           onmouseover="this.style.background='#0099D8'" onmouseout="this.style.background='#004976'">
                            Ver mis cursos →
                        </a>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                        <?php if ($prev_lesson): ?>
                        <a href="<?php echo lessonUrl($prev_lesson, $course_id); ?>"
                           style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.1rem;background:#F5F7FA;border:1.5px solid #E0E0E0;border-radius:10px;text-decoration:none;color:#004976;font-size:0.82rem;font-weight:700;transition:all 0.2s;flex:1;max-width:220px;"
                           onmouseover="this.style.background='#E5F4FC';this.style.borderColor='#0099D8'" onmouseout="this.style.background='#F5F7FA';this.style.borderColor='#E0E0E0'">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;flex-shrink:0;">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"/>
                            </svg>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($prev_lesson['lesson_title']); ?></span>
                        </a>
                        <?php else: ?>
                        <div style="flex:1;max-width:220px;"></div>
                        <?php endif; ?>

                        <?php
                        $btn_disabled = $is_slides_view || $quiz_block_complete;
                        $btn_style    = 'flex:1.2;';
                        $btn_onclick  = '';
                        $btn_title    = '';
                        if ($is_slides_view) {
                            $btn_style .= 'opacity:0.4;cursor:not-allowed;';
                        } elseif ($quiz_block_complete) {
                            $btn_style .= 'opacity:0.5;cursor:not-allowed;background:#9CA3AF;border-color:#9CA3AF;';
                            $btn_title  = 'Debes aprobar el quiz para completar esta lección';
                        } else {
                            $btn_onclick = "toggleLessonComplete({$current_lesson['lesson_id']}, {$course_id})";
                        }
                        ?>
                        <button
                            class="btn-complete <?php echo (!$is_slides_view && $current_lesson['is_completed']) ? 'completed' : ''; ?>"
                            <?php if ($btn_disabled): ?>disabled<?php endif; ?>
                            onclick="<?php echo $btn_onclick; ?>"
                            id="complete-btn"
                            style="<?php echo $btn_style; ?>"
                            <?php if ($btn_title): ?>title="<?php echo $btn_title; ?>"<?php endif; ?>
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span id="complete-text">
                                <?php
                                if ($is_slides_view) echo 'Presentación';
                                elseif ($current_lesson['is_completed']) echo 'Completada';
                                elseif ($quiz_block_complete) echo '🔒 Aprueba el quiz primero';
                                else echo 'Marcar como completada';
                                ?>
                            </span>
                        </button>

                        <?php if ($next_lesson): ?>
                        <a href="<?php echo lessonUrl($next_lesson, $course_id); ?>"
                           style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.1rem;background:#004976;border:1.5px solid #004976;border-radius:10px;text-decoration:none;color:white;font-size:0.82rem;font-weight:700;transition:all 0.2s;flex:1;max-width:220px;justify-content:flex-end;"
                           onmouseover="this.style.background='#0099D8';this.style.borderColor='#0099D8'" onmouseout="this.style.background='#004976';this.style.borderColor='#004976'">
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($next_lesson['lesson_title']); ?></span>
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;flex-shrink:0;">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/>
                            </svg>
                        </a>
                        <?php else: ?>
                        <div id="next-lesson-slot" style="flex:1;max-width:220px;">
                        <?php if ($enrollment['status'] === 'completed'): ?>
                            <a href="courses.php"
                               style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.1rem;background:linear-gradient(135deg,#004976,#0099D8);border:none;border-radius:10px;text-decoration:none;color:white;font-size:0.82rem;font-weight:700;transition:all 0.2s;white-space:nowrap;"
                               onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
                                🏆 Ver mis cursos
                            </a>
                        <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; // !is_slides_view ?>
            </div>
                <?php if ($is_slides_view && $slides_json): ?>
                <?php
                    $slides_arr_view = json_decode($slides_json, true);
                    if (isset($slides_arr_view['slides'])) $slides_arr_view = $slides_arr_view['slides'];
                ?>
                <div id="slides-embed">
                    <div class="slides-header">
                        <div class="slides-header-left">
                            <div class="slides-header-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;">
                                    <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm2 1v8h12V5H4z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="slides-header-title">Presentación del Curso</div>
                                <div class="slides-header-sub">✨ Generada por IA · <?php echo count($slides_arr_view); ?> diapositivas</div>
                            </div>
                        </div>
                        <div class="slides-header-controls">
                            <span id="slide-counter-header" class="slides-counter"></span>
                            <button onclick="embedNav(-1)" class="slides-btn slides-btn-prev">←</button>
                            <button onclick="embedNav(1)"  class="slides-btn slides-btn-next">→</button>
                        </div>
                    </div>

                    <div id="slides-progress-track"><div id="slides-progress-bar"></div></div>

                    <div id="slides-container">
                        <?php foreach ($slides_arr_view as $si => $slide):
                            $stype = $slide['type'] ?? 'content';
                            $isDark = in_array($stype, ['title','closing','keypoints']);
                        ?>
                        <div class="embed-slide <?php echo $isDark ? 'slide-dark' : 'slide-light'; ?>"
                             data-index="<?php echo $si; ?>"
                             style="opacity:<?php echo $si===0?'1':'0'; ?>;
                                    transform:translateX(<?php echo $si===0?'0':'40px'; ?>);
                                    pointer-events:<?php echo $si===0?'all':'none'; ?>;">

                            <?php if ($stype === 'title'): ?>
                                <div style="text-align:center;">
                                    <div style="width:48px;height:3px;background:#0099D8;border-radius:2px;margin:0 auto 1.5rem;"></div>
                                    <div style="display:inline-block;font-size:0.7rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#0099D8;background:rgba(0,153,216,0.12);border:1px solid rgba(0,153,216,0.25);border-radius:99px;padding:0.3rem 0.875rem;margin-bottom:1.25rem;">✦ Whirlpool Training</div>
                                    <h2 style="font-family:'Nunito Sans',sans-serif;font-size:clamp(1.5rem,3vw,2.25rem);font-weight:900;color:white;line-height:1.2;margin-bottom:1rem;letter-spacing:-0.02em;"><?php echo htmlspecialchars($slide['title']??''); ?></h2>
                                    <p style="font-size:clamp(0.9rem,1.5vw,1.1rem);color:rgba(0,153,216,0.9);font-weight:600;"><?php echo htmlspecialchars($slide['subtitle']??''); ?></p>
                                </div>

                            <?php elseif ($stype === 'agenda'): ?>
                                <div style="display:grid;grid-template-columns:1fr 2fr;gap:3rem;align-items:center;">
                                    <div>
                                        <div style="font-size:0.7rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#0099D8;margin-bottom:0.5rem;">Contenido</div>
                                        <h2 style="font-family:'Nunito Sans',sans-serif;font-size:clamp(1.75rem,3vw,2.5rem);font-weight:900;color:#004976;line-height:1.1;letter-spacing:-0.02em;"><?php echo htmlspecialchars($slide['title']??'Agenda'); ?></h2>
                                        <div style="width:36px;height:3px;background:#0099D8;border-radius:2px;margin-top:0.75rem;"></div>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:0.6rem;">
                                        <?php foreach (($slide['points']??[]) as $pi=>$pt): ?>
                                        <div style="display:flex;align-items:center;gap:0.875rem;padding:0.75rem 1rem;background:#E5F4FC;border-radius:8px;border-left:3px solid #0099D8;">
                                            <span style="width:24px;height:24px;background:#0099D8;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;flex-shrink:0;"><?php echo $pi+1; ?></span>
                                            <span style="font-size:0.88rem;font-weight:600;color:#004976;"><?php echo htmlspecialchars($pt); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php elseif ($stype === 'content'): ?>
                                <div>
                                    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.75rem;padding-bottom:1rem;border-bottom:2px solid #E5F4FC;">
                                        <div style="width:40px;height:40px;background:linear-gradient(135deg,#004976,#0099D8);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-family:'Nunito Sans',sans-serif;font-weight:900;font-size:0.9rem;flex-shrink:0;"><?php echo sprintf('%02d', $si); ?></div>
                                        <h2 style="font-family:'Nunito Sans',sans-serif;font-size:clamp(1.1rem,2.5vw,1.6rem);font-weight:800;color:#004976;letter-spacing:-0.02em;"><?php echo htmlspecialchars($slide['title']??''); ?></h2>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.875rem;">
                                        <?php foreach (($slide['points']??[]) as $pt): ?>
                                        <div style="display:flex;gap:0.75rem;align-items:flex-start;padding:1rem 1.125rem;background:#f8fbfd;border:1.5px solid #E5F4FC;border-radius:10px;">
                                            <div style="width:7px;height:7px;background:#0099D8;border-radius:50%;margin-top:0.35rem;flex-shrink:0;"></div>
                                            <span style="font-size:0.85rem;color:#2D2D2D;line-height:1.55;"><?php echo htmlspecialchars($pt); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php elseif ($stype === 'keypoints'): ?>
                                <div>
                                    <h2 style="font-family:'Nunito Sans',sans-serif;font-size:clamp(1.1rem,2.5vw,1.6rem);font-weight:800;color:white;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;">⭐ <?php echo htmlspecialchars($slide['title']??'Puntos Clave'); ?></h2>
                                    <div style="display:flex;flex-direction:column;gap:0.875rem;">
                                        <?php $kicons=['💡','🎯','🚀','✅','🔑']; foreach (($slide['points']??[]) as $ki=>$pt): ?>
                                        <div style="display:flex;gap:1rem;align-items:flex-start;padding:1rem 1.25rem;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);border-radius:10px;">
                                            <div style="width:34px;height:34px;background:#0099D8;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?php echo $kicons[$ki%count($kicons)]; ?></div>
                                            <span style="font-size:0.88rem;color:rgba(255,255,255,0.9);line-height:1.55;padding-top:0.15rem;"><?php echo htmlspecialchars($pt); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php elseif ($stype === 'closing'): ?>
                                <div style="text-align:center;">
                                    <h2 style="font-family:'Nunito Sans',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:900;color:white;margin-bottom:1.25rem;letter-spacing:-0.02em;"><?php echo htmlspecialchars($slide['title']??'¡Gracias!'); ?></h2>
                                    <p style="font-size:clamp(0.9rem,1.5vw,1.1rem);color:rgba(255,255,255,0.8);max-width:500px;margin:0 auto 2rem;line-height:1.6;"><?php echo htmlspecialchars($slide['subtitle']??''); ?></p>
                                    <div style="display:flex;align-items:center;gap:0.75rem;justify-content:center;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.82rem;letter-spacing:0.05em;color:rgba(255,255,255,0.4);text-transform:uppercase;">
                                        <div style="width:60px;height:1px;background:rgba(255,255,255,0.15);"></div>
                                        Whirlpool Training
                                        <div style="width:60px;height:1px;background:rgba(255,255,255,0.15);"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer: dots + nav lecciones + descarga offline -->
                    <div id="slides-footer">
                        <?php if ($prev_lesson): ?>
                        <a href="<?php echo lessonUrl($prev_lesson, $course_id); ?>" class="slides-nav-btn slides-nav-btn-prev">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"/></svg>
                            <span><?php echo htmlspecialchars($prev_lesson['lesson_title']); ?></span>
                        </a>
                        <?php else: ?><div></div><?php endif; ?>

                        <div style="display:flex;flex-direction:column;align-items:center;gap:0.6rem;">
                            <div id="slides-dots"></div>
                            <a href="/process/download_lesson.php?lesson_id=slides&course_id=<?php echo $course_id; ?>"
                               class="btn-offline"
                               style="font-size:0.74rem;padding:0.35rem 0.85rem;opacity:0.85;"
                               title="Descargar presentación para uso offline">
                                <svg viewBox="0 0 20 20" fill="currentColor" style="width:13px;height:13px;">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                                </svg>
                                Descargar offline
                            </a>
                        </div>

                        <?php if ($next_lesson): ?>
                        <a href="<?php echo lessonUrl($next_lesson, $course_id); ?>" class="slides-nav-btn slides-nav-btn-next">
                            <span><?php echo htmlspecialchars($next_lesson['lesson_title']); ?></span>
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/></svg>
                        </a>
                        <?php else: ?><div></div><?php endif; ?>
                    </div>
                </div>

                <script>
                const embedSlides = document.querySelectorAll('.embed-slide');
                const embedTotal  = embedSlides.length;
                let embedCurrent  = 0;
                const dotsEl      = document.getElementById('slides-dots');
                const progressBar = document.getElementById('slides-progress-bar');
                const counterEl   = document.getElementById('slide-counter-header');

                for (let i = 0; i < embedTotal; i++) {
                    const d = document.createElement('button');
                    d.className = 'slides-dot' + (i===0?' active':'');
                    d.style.width = i===0 ? '18px' : '6px';
                    d.onclick = () => embedGoTo(i);
                    dotsEl.appendChild(d);
                }

                function embedUpdateUI() {
                    const dots = dotsEl.querySelectorAll('.slides-dot');
                    dots.forEach((d,i) => {
                        d.classList.toggle('active', i===embedCurrent);
                        d.style.width = i===embedCurrent ? '18px' : '6px';
                    });
                    progressBar.style.width = ((embedCurrent+1)/embedTotal*100)+'%';
                    counterEl.textContent = (embedCurrent+1)+' / '+embedTotal;
                }

                function embedGoTo(n) {
                    if (n<0||n>=embedTotal||n===embedCurrent) return;
                    const dir = n > embedCurrent ? 1 : -1;
                    embedSlides[embedCurrent].style.opacity   = '0';
                    embedSlides[embedCurrent].style.transform = `translateX(${-dir*40}px)`;
                    embedSlides[embedCurrent].style.pointerEvents = 'none';
                    embedCurrent = n;
                    embedSlides[embedCurrent].style.transform = `translateX(${dir*40}px)`;
                    embedSlides[embedCurrent].style.opacity   = '0';
                    embedSlides[embedCurrent].style.pointerEvents = 'all';
                    requestAnimationFrame(() => {
                        embedSlides[embedCurrent].style.opacity   = '1';
                        embedSlides[embedCurrent].style.transform = 'translateX(0)';
                    });
                    embedUpdateUI();
                }

                function embedNav(dir) { embedGoTo(embedCurrent + dir); }

                document.addEventListener('keydown', e => {
                    const slideArea = document.getElementById('slides-embed');
                    if (!slideArea) return;
                    if (e.key==='ArrowRight') embedNav(1);
                    if (e.key==='ArrowLeft')  embedNav(-1);
                });

                embedUpdateUI();
                </script>
                </div>                <?php endif; ?>

        </main>

        <aside class="viewer-sidebar" id="viewer-sidebar">
            <div class="sidebar-header">
                <h3>Contenido del Curso</h3>
                <button class="btn-close-sidebar" onclick="toggleSidebar()">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
                    </svg>
                </button>
            </div>
            <div class="sidebar-content">
                <?php foreach ($modules as $module): ?>
                <div class="sidebar-module">
                    <div class="module-title">
                        <h4><?php echo htmlspecialchars($module['module_title']); ?></h4>
                        <span class="module-count">
                            <?php 
                            $completed_count = 0;
                            foreach ($module['lessons'] as $lesson) {
                                if ($lesson['is_completed']) $completed_count++;
                            }
                            echo $completed_count . '/' . count($module['lessons']);
                            ?>
                        </span>
                    </div>
                    <div class="module-lessons">
                        <?php foreach ($module['lessons'] as $lesson): ?>
                        <a 
                            href="course-view.php?id=<?php echo $course_id; ?>&lesson=<?php echo $lesson['lesson_id']; ?>" 
                            class="lesson-link <?php echo $lesson['lesson_id'] == $current_lesson['lesson_id'] ? 'active' : ''; ?> <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>"
                        >
                            <div class="lesson-icon-small">
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
                                <?php else: ?>
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="lesson-info-small">
                                <span class="lesson-name"><?php echo htmlspecialchars($lesson['lesson_title']); ?></span>
                                <?php if ($lesson['duration_minutes'] > 0): ?>
                                <span class="lesson-duration-small"><?php echo $lesson['duration_minutes']; ?> min</span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>

                        <?php if ($slides_json && $slides_module_id === intval($module['module_id'])): ?>
                        <a href="course-view.php?id=<?php echo $course_id; ?>&lesson=slides"
                           class="lesson-link <?php echo $is_slides_view ? 'active' : ''; ?>"
                           style="border-left:3px solid #0099D8;<?php echo $is_slides_view ? '' : 'background:linear-gradient(135deg,rgba(0,153,216,0.06),rgba(229,244,252,0.5));'; ?>">
                            <div class="lesson-icon-small" style="color:#0099D8;">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V4zm2 1v8h12V5H4z"/>
                                </svg>
                            </div>
                            <div class="lesson-info-small">
                                <span class="lesson-name" style="color:#004976;font-weight:600;">Presentación del Curso</span>
                                <span class="lesson-duration-small" style="color:#0099D8;">✨ IA · <?php echo $slides_count; ?> slides</span>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="/js/chatbot-widget.js?v=1.11"></script>
    <?php endif; ?>
    <script src="js/course-view.js?=1.1"></script>
    <?php if ($is_slides_view): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('viewer-sidebar');
        if (!sidebar) return;
        var panel = document.createElement('div');
        panel.id = 'slides-sidebar-panel';
        while (sidebar.firstChild) {
            panel.appendChild(sidebar.firstChild);
        }
        sidebar.style.display = 'none';
        document.body.appendChild(panel);
    });
    </script>
    <?php endif; ?>
    <script src="/js/theme.js?v=1.01.04"></script>
    <script src="js/notifications-widget.js"></script>
</body>
    <script src="/js/faq-widget.js?v=1.12.1"></script>
    <script src="/js/quiz-widget.js?v=1.1"></script>
<?php if (!$is_slides_view && $lesson_quiz): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    QuizWidget.init(
        <?php echo intval($current_lesson['lesson_id']); ?>,
        <?php echo intval($course_id); ?>,
        'quiz-container'
    );
});
</script>
<?php endif; ?>

<script>
(function() {
    var _origToggle = window.toggleLessonComplete;
    function patchCompletedUI(data) {
        if (!data || !data.course_completed) return;

        var banner = document.createElement('a');
        banner.href = 'courses.php';
        banner.innerHTML = '🏆 ¡Curso completado! Ver mis cursos';
        banner.style.cssText = [
            'display:flex','align-items:center','gap:0.5rem',
            'padding:0.6rem 1.1rem',
            'background:linear-gradient(135deg,#004976,#0099D8)',
            'border-radius:10px','text-decoration:none','color:white',
            'font-size:0.82rem','font-weight:700','transition:all 0.2s',
            'white-space:nowrap','flex:1','max-width:280px'
        ].join(';');
        banner.onmouseover = function(){ this.style.opacity='0.88'; };
        banner.onmouseout  = function(){ this.style.opacity='1'; };

        var slot = document.getElementById('next-lesson-slot');
        if (slot) {
            slot.innerHTML = '';
            slot.appendChild(banner);
            return;
        }
        var actions = document.querySelector('.lesson-actions');
        if (!actions) return;
        var links = actions.querySelectorAll('a[href*="course-view.php"]');
        var nextLink = null;
        links.forEach(function(l) {
            if (l.innerHTML.includes('14.707')) nextLink = l;
        });
        if (nextLink) {
            nextLink.parentNode.replaceChild(banner, nextLink);
        } else {
            var row = actions.querySelector('div[style*="display:flex"]');
            if (row) row.appendChild(banner);
        }
    }

    if (typeof toggleLessonComplete === 'function') {
        window.toggleLessonComplete = function(lessonId, courseId) {
            var origFetch = window.fetch;
            window.fetch = function(url, opts) {
                return origFetch.apply(this, arguments).then(function(resp) {
                    var cloned = resp.clone();
                    cloned.json().then(function(data) {
                        if (data && data.course_completed) {
                            patchCompletedUI(data);
                        }
                    }).catch(function(){});
                    window.fetch = origFetch; // restaurar
                    return resp;
                });
            };
            return _origToggle(lessonId, courseId);
        };
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            var _orig2 = window.toggleLessonComplete;
            if (!_orig2) return;
            window.toggleLessonComplete = function(lessonId, courseId) {
                var origFetch = window.fetch;
                window.fetch = function(url, opts) {
                    return origFetch.apply(this, arguments).then(function(resp) {
                        var cloned = resp.clone();
                        cloned.json().then(function(data) {
                            if (data && data.course_completed) patchCompletedUI(data);
                        }).catch(function(){});
                        window.fetch = origFetch;
                        return resp;
                    });
                };
                return _orig2(lessonId, courseId);
            };
        });
    }
})();
</script>
</html>