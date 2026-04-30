<?php
session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

$admin_name = $_SESSION['user_name'] ?? 'Administrador';
if (isset($_POST['toggle_status']) && isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'published') ? 'draft' : 'published';

    $stmt = $conn->prepare("UPDATE courses SET status = ? WHERE course_id = ?");
    $stmt->bind_param("si", $new_status, $course_id);
    $stmt->execute();
    $stmt->close();

    header("Location: courses.php?msg=status_updated");
    exit();
}
if (isset($_POST['delete_course']) && isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM course_enrollments WHERE course_id = ?");
    $check->bind_param("i", $course_id);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();

    if ($cnt > 0) {
        header("Location: courses.php?error=has_enrollments");
        exit();
    }
    $conn->query("DELETE cl FROM course_lessons cl
                  INNER JOIN course_modules cm ON cl.module_id = cm.module_id
                  WHERE cm.course_id = {$course_id}");
    $conn->query("DELETE FROM course_modules WHERE course_id = {$course_id}");
    $conn->query("DELETE FROM courses WHERE course_id = {$course_id}");

    header("Location: courses.php?msg=deleted");
    exit();
}
$filter_status   = $_GET['status']   ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search          = trim($_GET['search'] ?? '');
$where_clauses = ['1=1'];
$params = [];
$types  = '';

if ($filter_status !== 'all') {
    $where_clauses[] = 'c.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_category !== 'all') {
    $where_clauses[] = 'c.category_id = ?';
    $params[] = intval($filter_category);
    $types .= 'i';
}
if ($search !== '') {
    $where_clauses[] = '(c.course_title LIKE ? OR c.course_description LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}

$where_sql = implode(' AND ', $where_clauses);

$courses_query = "
    SELECT c.*,
           cat.category_name, cat.color AS category_color,
           (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) AS module_count,
           (SELECT COUNT(*) FROM course_lessons cl
            INNER JOIN course_modules cm ON cl.module_id = cm.module_id
            WHERE cm.course_id = c.course_id) AS lesson_count
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    WHERE {$where_sql}
    ORDER BY c.created_at DESC
";

if ($types) {
    $stmt = $conn->prepare($courses_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $courses = $conn->query($courses_query)->fetch_all(MYSQLI_ASSOC);
}
$categories = $conn->query("SELECT * FROM course_categories WHERE status = 'active' ORDER BY display_order")->fetch_all(MYSQLI_ASSOC);

$conn->close();
$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Cursos - Admin Whirlpool</title>
    <link rel="stylesheet" href="../css/styles.css?v=4.3">
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; background: #F0F4F8; color: #2D2D2D; }
        .admin-topbar { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E8E8E8; position: sticky; top: 0; z-index: 50; }
        .topbar-title { font-family: 'Nunito Sans', sans-serif; font-weight: 800; font-size: 1.25rem; color: #003C64; }
        .topbar-subtitle { font-size: 0.8rem; color: #6B6B6B; margin-top: 0.1rem; }
        .admin-content { padding: 2rem; }

        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #003C64, #0096DC); color: white; padding: 0.625rem 1.25rem; border-radius: 8px; text-decoration: none; font-family: 'Nunito Sans', sans-serif; font-weight: 700; font-size: 0.875rem; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,60,100,0.25); }
        .btn-primary svg { width: 16px; height: 16px; }

        
        .feedback-alert {
            padding: 0.875rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-success { background: #E6FAF0; color: #00875A; border: 1px solid #A8EDCC; }
        .alert-error { background: #FFF0F0; color: #C0392B; border: 1px solid #F5AEAE; }

        
        .filters-bar {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            border: 1px solid #F0F0F0;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 160px; }
        .filter-label { font-size: 0.75rem; font-weight: 700; color: #6B6B6B; text-transform: uppercase; letter-spacing: 0.06em; }

        .filter-input, .filter-select {
            padding: 0.5rem 0.875rem;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: 'Open Sans', sans-serif;
            color: #2D2D2D;
            background: white;
            transition: border-color 0.2s;
        }
        .filter-input:focus, .filter-select:focus { outline: none; border-color: #0096DC; }

        .btn-filter { padding: 0.5rem 1.25rem; background: #003C64; color: white; border: none; border-radius: 8px; font-family: 'Nunito Sans', sans-serif; font-weight: 700; font-size: 0.875rem; cursor: pointer; transition: background 0.2s; }
        .btn-filter:hover { background: #0096DC; }

        .btn-reset { padding: 0.5rem 1rem; background: #F0F4F8; color: #6B6B6B; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 0.875rem; cursor: pointer; text-decoration: none; font-family: 'Open Sans', sans-serif; }
        .btn-reset:hover { background: #E8EEF5; }

        
        .results-summary {
            font-size: 0.875rem;
            color: #6B6B6B;
            margin-bottom: 1rem;
        }
        .results-summary strong { color: #003C64; }

        
        .courses-table-wrap {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            border: 1px solid #F0F0F0;
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #F8FAFB;
            padding: 0.875rem 1.25rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6B6B6B;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid #EFEFEF;
        }
        tbody td { padding: 1rem 1.25rem; border-bottom: 1px solid #F5F5F5; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #FAFAFA; }

        /* Celda de thumbnail */
        .thumb-cell { width:52px; height:36px; border-radius:6px; overflow:hidden; cursor:pointer; flex-shrink:0; transition:opacity 0.15s; }
        .thumb-cell:hover { opacity:0.8; }
        .thumb-cell-img { width:52px; height:36px; object-fit:cover; display:block; border-radius:6px; }
        .thumb-cell-empty { width:52px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center; }

        /* Celda de nombre del curso */
        .course-name-cell { display: flex; align-items: center; gap: 0.875rem; }

        .course-color-dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }

        .course-cell-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1A1A1A;
            font-family: 'Nunito Sans', sans-serif;
            margin-bottom: 0.15rem;
        }

        .course-cell-meta {
            font-size: 0.75rem;
            color: #8C8C8C;
        }

        /* Pills y badges */
        .status-pill { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 0.25rem 0.7rem; border-radius: 99px; }
        .status-published { background: #E6FAF0; color: #00875A; }
        .status-draft { background: #FFF4E6; color: #D97706; }

        .level-pill { display: inline-block; font-size: 0.72rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 99px; background: #F0F4F8; color: #4A4A4A; }

        .featured-star { color: #F59E0B; font-size: 1rem; }

        /* Columna de acciones */
        .actions-cell { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

        .btn-action {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.78rem;
            font-weight: 600; font-family: 'Open Sans', sans-serif; cursor: pointer;
            border: none; text-decoration: none; transition: all 0.15s;
        }
        .btn-action svg { width: 13px; height: 13px; }

        .btn-edit { background: #E6F0FA; color: #003C64; }
        .btn-edit:hover { background: #003C64; color: white; }

        .btn-publish { background: #E6FAF0; color: #00875A; }
        .btn-publish:hover { background: #00875A; color: white; }

        .btn-draft { background: #FFF4E6; color: #D97706; }
        .btn-draft:hover { background: #D97706; color: white; }

        .btn-delete { background: #FFF0F0; color: #C0392B; }
        .btn-delete:hover { background: #C0392B; color: white; }

        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #8C8C8C;
        }
        .empty-state svg { width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.4; display: block; }
        .empty-state h3 { font-family: 'Nunito Sans', sans-serif; color: #4A4A4A; margin-bottom: 0.5rem; }

        
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .modal-icon {
            width: 52px; height: 52px; background: #FFF0F0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
        }
        .modal-icon svg { width: 24px; height: 24px; stroke: #C0392B; }
        .modal-title { font-family: 'Nunito Sans', sans-serif; font-weight: 800; font-size: 1.1rem; color: #1A1A1A; text-align: center; margin-bottom: 0.5rem; }
        .modal-desc { font-size: 0.875rem; color: #6B6B6B; text-align: center; line-height: 1.6; margin-bottom: 1.5rem; }

        .modal-actions { display: flex; gap: 0.75rem; }
        .btn-cancel { flex: 1; padding: 0.75rem; background: #F0F4F8; color: #4A4A4A; border: none; border-radius: 8px; font-weight: 700; font-family: 'Nunito Sans', sans-serif; cursor: pointer; font-size: 0.9rem; }
        .btn-cancel:hover { background: #E0E8F0; }
        .btn-confirm-delete { flex: 1; padding: 0.75rem; background: #C0392B; color: white; border: none; border-radius: 8px; font-weight: 700; font-family: 'Nunito Sans', sans-serif; cursor: pointer; font-size: 0.9rem; }
        .btn-confirm-delete:hover { background: #A93226; }
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ROLE   = "<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'admin'); ?>";
        window.ADMIN_ACTIVE = "courses";
    </script>
    <script src="js/admin-navbar.js" defer></script>
</head>
<body>
<main class="admin-main">
        <div class="admin-topbar">
            <div>
                <div class="topbar-title">Gestionar Cursos</div>
                <div class="topbar-subtitle"><?php echo count($courses); ?> cursos encontrados</div>
            </div>
            <a href="course-form.php" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                Nuevo Curso
            </a>
        </div>

        <div class="admin-content">

            <!-- Feedback -->
            <?php if ($msg === 'status_updated'): ?>
                <div class="feedback-alert alert-success">✓ Estado del curso actualizado correctamente.</div>
            <?php elseif ($msg === 'deleted'): ?>
                <div class="feedback-alert alert-success">✓ Curso eliminado correctamente.</div>
            <?php elseif ($error === 'has_enrollments'): ?>
                <div class="feedback-alert alert-error">✗ No se puede eliminar: el curso tiene inscripciones activas.</div>
            <?php elseif ($msg === 'saved'): ?>
                <div class="feedback-alert alert-success">✓ Curso guardado correctamente.</div>
            <?php endif; ?>

            <!-- Filtros -->
            <form method="GET" action="courses.php" class="filters-bar">
                <div class="filter-group" style="flex: 2;">
                    <label class="filter-label">Buscar</label>
                    <input type="text" name="search" class="filter-input" placeholder="Nombre del curso..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Estado</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="published" <?php echo $filter_status === 'published' ? 'selected' : ''; ?>>Publicado</option>
                        <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Categoría</label>
                    <select name="category" class="filter-select">
                        <option value="all">Todas</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $filter_category == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Filtrar</button>
                <a href="courses.php" class="btn-reset">Limpiar</a>
            </form>

            <!-- Tabla de cursos -->
            <div class="courses-table-wrap">
                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <h3>No se encontraron cursos</h3>
                        <p>Ajusta los filtros o <a href="course-form.php" style="color:#0096DC">crea el primero</a>.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:60px;">Portada</th>
                                <th>Curso</th>
                                <th class="col-hide-mobile">Categoría</th>
                                <th class="col-hide-mobile">Nivel</th>
                                <th class="col-hide-mobile">Módulos / Lecciones</th>
                                <th class="col-hide-mobile">Inscritos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <!-- Thumbnail -->
                                    <td style="padding:0.5rem;">
                                        <div class="thumb-cell" onclick="openThumbModal(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($course['course_title'])); ?>')" title="Cambiar portada">
                                            <?php if(!empty($course['thumbnail_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="Portada" class="thumb-cell-img">
                                            <?php else: ?>
                                                <div class="thumb-cell-empty" style="background:<?php echo htmlspecialchars($course['category_color']); ?>20;border:2px dashed <?php echo htmlspecialchars($course['category_color']); ?>;">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="<?php echo htmlspecialchars($course['category_color']); ?>" stroke-width="1.5" style="width:18px;height:18px;"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Nombre -->
                                    <td>
                                        <div class="course-name-cell">
                                            <div class="course-color-dot" style="background: <?php echo htmlspecialchars($course['category_color']); ?>"></div>
                                            <div>
                                                <div class="course-cell-title">
                                                    <?php if ($course['is_featured']): ?>
                                                        <span class="featured-star" title="Destacado">★</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($course['course_title']); ?>
                                                </div>
                                                <div class="course-cell-meta"><?php echo $course['estimated_hours']; ?>h estimadas</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Categoría -->
                                    <td class="col-hide-mobile" style="font-size: 0.85rem; color: #4A4A4A;">
                                        <?php echo htmlspecialchars($course['category_name']); ?>
                                    </td>

                                    <!-- Nivel -->
                                    <td class="col-hide-mobile">
                                        <span class="level-pill">
                                            <?php
                                            $levels = ['beginner' => 'Básico', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzado'];
                                            echo $levels[$course['difficulty_level']] ?? $course['difficulty_level'];
                                            ?>
                                        </span>
                                    </td>

                                    <!-- Módulos / Lecciones -->
                                    <td class="col-hide-mobile" style="font-size: 0.85rem; color: #4A4A4A; text-align: center;">
                                        <?php echo $course['module_count']; ?> / <?php echo $course['lesson_count']; ?>
                                    </td>

                                    <!-- Inscritos -->
                                    <td class="col-hide-mobile" style="font-size: 0.875rem; font-weight: 700; color: #003C64; text-align: center;">
                                        <?php echo number_format($course['enrollment_count']); ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <span class="status-pill status-<?php echo $course['status']; ?>">
                                            <?php echo $course['status'] === 'published' ? 'Publicado' : 'Borrador'; ?>
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <div class="actions-cell">
                                            <!-- Editar -->
                                            <a href="course-form.php?id=<?php echo $course['course_id']; ?>" class="btn-action btn-edit">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Editar
                                            </a>

                                            <!-- Publicar / Despublicar -->
                                            <form method="POST" action="courses.php" style="display:inline;">
                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $course['status']; ?>">
                                                <button type="submit" name="toggle_status"
                                                    class="btn-action <?php echo $course['status'] === 'published' ? 'btn-draft' : 'btn-publish'; ?>">
                                                    <?php if ($course['status'] === 'published'): ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                                        Despublicar
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        Publicar
                                                    <?php endif; ?>
                                                </button>
                                            </form>

                                            <!-- Eliminar -->
                                            <button type="button"
                                                class="btn-action btn-delete"
                                                onclick="confirmDelete(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars(addslashes($course['course_title'])); ?>', <?php echo $course['enrollment_count']; ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal subida de thumbnail -->
    <div class="modal-overlay" id="thumbModal">
        <div class="modal-box" style="max-width:420px;">
            <div class="modal-title" id="thumbModalTitle">Cambiar portada del curso</div>
            <input type="hidden" id="thumbModalCourseId">
            <div id="thumbModalPreviewWrap" style="margin-bottom:1rem;text-align:center;display:none;">
                <img id="thumbModalPreview" src="" alt="Portada actual" style="max-width:100%;border-radius:8px;max-height:160px;object-fit:cover;">
            </div>
            <div style="border:2px dashed #C0D4E8;border-radius:10px;padding:1.5rem;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;" id="thumbModalDrop">
                <input type="file" accept="image/jpeg,image/png,image/webp" id="thumbModalInput" onchange="uploadThumbFromModal(this)"
                       style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                <div style="font-size:1.5rem;margin-bottom:0.3rem;">🖼️</div>
                <div style="font-size:0.875rem;font-weight:600;color:#003C64;">Arrastra la imagen o haz clic</div>
                <div style="font-size:0.75rem;color:#8C8C8C;margin-top:0.2rem;">JPG, PNG, WebP · Máx 5 MB</div>
            </div>
            <div style="margin-top:0.75rem;display:none;" id="thumbModalProgress">
                <div style="height:6px;background:#E0E0E0;border-radius:99px;overflow:hidden;"><div id="thumbModalBar" style="height:100%;background:linear-gradient(90deg,#003C64,#0096DC);width:0;border-radius:99px;transition:width 0.3s;"></div></div>
                <div id="thumbModalStatus" style="font-size:0.8rem;color:#6B6B6B;margin-top:0.4rem;">Subiendo...</div>
            </div>
            <div class="modal-actions" style="margin-top:1rem;">
                <button type="button" class="btn-cancel" onclick="closeThumbModal()">Cerrar</button>
                <a id="thumbModalEditLink" href="#" class="btn-primary" style="font-size:0.85rem;">Ir al editor completo</a>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div class="modal-title">¿Eliminar este curso?</div>
            <div class="modal-desc" id="modalDesc"></div>

            <form method="POST" action="courses.php" class="modal-actions">
                <input type="hidden" name="course_id" id="deleteCourseId">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button type="submit" name="delete_course" class="btn-confirm-delete">Sí, eliminar</button>
            </form>
        </div>
    </div>

    <script>
        // Thumbnail modal
        function openThumbModal(courseId, courseTitle) {
            document.getElementById('thumbModalCourseId').value = courseId;
            document.getElementById('thumbModalTitle').textContent = 'Portada: ' + courseTitle;
            document.getElementById('thumbModalEditLink').href = 'course-form.php?id=' + courseId + '#course-files';
            document.getElementById('thumbModalProgress').style.display = 'none';
            document.getElementById('thumbModalBar').style.width = '0';
            // Mostrar imagen actual si existe en la celda
            const img = document.querySelector(`tr:has([onclick*="openThumbModal(${courseId},"]]) .thumb-cell-img`);
            const prevWrap = document.getElementById('thumbModalPreviewWrap');
            const prevImg  = document.getElementById('thumbModalPreview');
            if (img) { prevImg.src = img.src; prevWrap.style.display='block'; }
            else prevWrap.style.display='none';
            document.getElementById('thumbModal').classList.add('active');
        }
        function closeThumbModal() { document.getElementById('thumbModal').classList.remove('active'); }
        document.getElementById('thumbModal').addEventListener('click', function(e){ if(e.target===this) closeThumbModal(); });

        function uploadThumbFromModal(input) {
            const file = input.files[0]; if (!file) return;
            const courseId = document.getElementById('thumbModalCourseId').value;
            const prog = document.getElementById('thumbModalProgress');
            const bar  = document.getElementById('thumbModalBar');
            const stat = document.getElementById('thumbModalStatus');
            prog.style.display = 'block';

            const fd = new FormData();
            fd.append('file', file); fd.append('context', 'thumbnail');
            fd.append('file_type', 'thumbnail'); fd.append('course_id', courseId);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_file.php', true);
            xhr.upload.onprogress = e => { if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';stat.textContent='Subiendo... '+p+'%';} };
            xhr.onload = () => {
                const d = JSON.parse(xhr.responseText);
                if (d.success) {
                    stat.textContent = '✓ Portada actualizada';
                    stat.style.color = '#00875A';
                    // Update the thumbnail in the table row immediately
                    const cells = document.querySelectorAll('.thumb-cell');
                    cells.forEach(cell => {
                        if (cell.getAttribute('onclick') && cell.getAttribute('onclick').includes('openThumbModal('+courseId+',')) {
                            cell.innerHTML = `<img src="${d.url}" alt="Portada" class="thumb-cell-img">`;
                        }
                    });
                    document.getElementById('thumbModalPreview').src = d.url;
                    document.getElementById('thumbModalPreviewWrap').style.display = 'block';
                    setTimeout(() => closeThumbModal(), 1200);
                } else { alert('Error: ' + d.message); prog.style.display='none'; }
            };
            xhr.send(fd);
        }

        // Gestión del modal de eliminación
        function confirmDelete(courseId, courseTitle, enrollmentCount) {
            document.getElementById('deleteCourseId').value = courseId;
            const desc = enrollmentCount > 0
                ? `El curso "<strong>${courseTitle}</strong>" tiene <strong>${enrollmentCount} inscripciones</strong> activas y no puede eliminarse.`
                : `Esta acción eliminará permanentemente el curso "<strong>${courseTitle}</strong>" y todo su contenido. No se puede deshacer.`;
            document.getElementById('modalDesc').innerHTML = desc;

            // Si tiene inscripciones, deshabilitar el botón de confirmar
            const confirmBtn = document.querySelector('.btn-confirm-delete');
            confirmBtn.disabled = enrollmentCount > 0;
            confirmBtn.style.opacity = enrollmentCount > 0 ? '0.4' : '1';

            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Cerrar modal con clic en overlay
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // Auto-ocultar alertas de feedback tras 4 segundos
        const alert = document.querySelector('.feedback-alert');
        if (alert) setTimeout(() => alert.style.opacity = '0', 4000);
    </script>
</body>
</html>