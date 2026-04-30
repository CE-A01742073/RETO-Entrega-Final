<?php
session_start();
require_once '../config/database.php';
require_once '../config/notify.php';
require_once 'auth-check.php';

$admin_name = $_SESSION['user_name'] ?? 'Administrador';
$course_id  = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit    = $course_id > 0;
$course     = null;
$modules    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_course_file' && $is_edit) {
        $fid = intval($_POST['file_id'] ?? 0);
        $r = $conn->query("SELECT file_url FROM course_files WHERE file_id={$fid} AND course_id={$course_id}");
        if ($row = $r->fetch_assoc()) {
            $disk_path = dirname(__DIR__) . '/' . ltrim(str_replace('../', '', $row['file_url']), '/');
            if (file_exists($disk_path)) unlink($disk_path);
        }
        $conn->query("DELETE FROM course_files WHERE file_id={$fid} AND course_id={$course_id}");
        header("Location: course-form.php?id={$course_id}&msg=file_deleted#course-files"); exit();
    }

    if ($action === 'save_course') {
        $title       = trim($_POST['course_title'] ?? '');
        $description = trim($_POST['course_description'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $level       = $_POST['difficulty_level'] ?? 'beginner';
        $hours       = floatval($_POST['estimated_hours'] ?? 0);
        $status      = $_POST['status'] ?? 'draft';
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        if ($is_edit) {
            $instructor_id = intval($_POST['instructor_id'] ?? 0) ?: null;
            $stmt = $conn->prepare("UPDATE courses SET course_title=?,course_description=?,category_id=?,difficulty_level=?,estimated_hours=?,status=?,is_featured=?,instructor_id=? WHERE course_id=?");
            $stmt->bind_param("ssisssiii",$title,$description,$category_id,$level,$hours,$status,$is_featured,$instructor_id,$course_id);
            $stmt->execute(); $stmt->close();
            header("Location: course-form.php?id={$course_id}&msg=saved"); exit();
        } else {
            $instructor_id = intval($_POST['instructor_id'] ?? 0) ?: null;
            $stmt = $conn->prepare("INSERT INTO courses (course_title,course_description,category_id,difficulty_level,estimated_hours,status,is_featured,instructor_id) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisssii",$title,$description,$category_id,$level,$hours,$status,$is_featured,$instructor_id);
            $stmt->execute(); $new_id=$conn->insert_id; $stmt->close();

            if ($status === 'published') {
                $users_q = $conn->query("SELECT user_id FROM users WHERE role = 'employee'");
                while ($u = $users_q->fetch_assoc()) {
                    notify($conn,(int)$u['user_id'],'new_course','¡Nuevo curso disponible!',"Se publicó el curso «{$title}». ¡Inscríbete ahora!","course-detail.php?id={$new_id}");
                }
            }
            header("Location: course-form.php?id={$new_id}&msg=created"); exit();
        }
    }
    if ($action==='add_module'&&$is_edit) {
        $mt=trim($_POST['module_title']??''); $md=trim($_POST['module_description']??'');
        $no=$conn->query("SELECT COALESCE(MAX(module_order),0)+1 AS n FROM course_modules WHERE course_id={$course_id}")->fetch_assoc()['n'];
        $stmt=$conn->prepare("INSERT INTO course_modules (course_id,module_title,module_description,module_order) VALUES (?,?,?,?)");
        $stmt->bind_param("issi",$course_id,$mt,$md,$no); $stmt->execute(); $stmt->close();
        header("Location: course-form.php?id={$course_id}&msg=module_added#modules"); exit();
    }
    if ($action==='delete_module'&&$is_edit) {
        $mid=intval($_POST['module_id']??0);
        $conn->query("DELETE FROM course_lessons WHERE module_id={$mid}");
        $conn->query("DELETE FROM course_modules WHERE module_id={$mid} AND course_id={$course_id}");
        header("Location: course-form.php?id={$course_id}&msg=module_deleted#modules"); exit();
    }
    if ($action==='add_lesson'&&$is_edit) {
        $mid=intval($_POST['module_id']??0);
        $lt=trim($_POST['lesson_title']??''); $ld=trim($_POST['lesson_description']??'');
        $ct=$_POST['content_type']??'video'; $cu=trim($_POST['content_url']??'');
        $cx=trim($_POST['content_text']??''); $dur=intval($_POST['duration_minutes']??0);
        $ip=isset($_POST['is_preview'])?1:0;
        $no=$conn->query("SELECT COALESCE(MAX(lesson_order),0)+1 AS n FROM course_lessons WHERE module_id={$mid}")->fetch_assoc()['n'];
        $stmt=$conn->prepare("INSERT INTO course_lessons (module_id,lesson_title,lesson_description,content_type,content_url,content_text,duration_minutes,lesson_order,is_preview) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssiii",$mid,$lt,$ld,$ct,$cu,$cx,$dur,$no,$ip); $stmt->execute(); $stmt->close();
        header("Location: course-form.php?id={$course_id}&msg=lesson_added#modules"); exit();
    }
    if ($action==='delete_lesson'&&$is_edit) {
        $lid=intval($_POST['lesson_id']??0);
        $conn->query("DELETE FROM course_lessons WHERE lesson_id={$lid}");
        header("Location: course-form.php?id={$course_id}&msg=lesson_deleted#modules"); exit();
    }
    if ($action==='reorder_items'&&$is_edit) {
        header('Content-Type: application/json');
        $type  = $_POST['type'] ?? '';   // 'module' o 'lesson'
        $raw   = $_POST['ids']  ?? '';
        $ids   = array_filter(array_map('intval', explode(',', $raw)));
        if (empty($ids)) { echo json_encode(['ok'=>false]); exit(); }
        if ($type === 'module') {
            foreach ($ids as $pos => $mid) {
                $o = $pos + 1;
                $conn->query("UPDATE course_modules SET module_order={$o} WHERE module_id={$mid} AND course_id={$course_id}");
            }
        } elseif ($type === 'lesson') {
            $mid = intval($_POST['module_id'] ?? 0);
            foreach ($ids as $pos => $lid) {
                $o = $pos + 1;
                $conn->query("UPDATE course_lessons SET lesson_order={$o} WHERE lesson_id={$lid} AND module_id={$mid}");
            }
        }
        echo json_encode(['ok'=>true]); exit();
    }
    if ($action==='add_category') {
        $cn=trim($_POST['cat_name']??''); $cs=preg_replace('/[^a-z0-9-]/','',strtolower(trim($_POST['cat_slug']??'')));
        $cd=trim($_POST['cat_desc']??''); $ci=trim($_POST['cat_icon']??'book'); $cc=trim($_POST['cat_color']??'#003C64');
        $no=$conn->query("SELECT COALESCE(MAX(display_order),0)+1 AS n FROM course_categories")->fetch_assoc()['n'];
        $stmt=$conn->prepare("INSERT INTO course_categories (category_name,category_slug,description,icon,color,display_order,status) VALUES (?,?,?,?,?,?,'active')");
        $stmt->bind_param("sssssi",$cn,$cs,$cd,$ci,$cc,$no); $stmt->execute(); $stmt->close();
        header("Location: course-form.php".($is_edit?"?id={$course_id}":"")."&msg=cat_added#categories"); exit();
    }
    if ($action==='edit_category') {
        $cid=intval($_POST['cat_id']??0); $cn=trim($_POST['cat_name']??'');
        $cd=trim($_POST['cat_desc']??''); $ci=trim($_POST['cat_icon']??'book');
        $cc=trim($_POST['cat_color']??'#003C64'); $cst=$_POST['cat_status']??'active';
        $stmt=$conn->prepare("UPDATE course_categories SET category_name=?,description=?,icon=?,color=?,status=? WHERE category_id=?");
        $stmt->bind_param("sssssi",$cn,$cd,$ci,$cc,$cst,$cid); $stmt->execute(); $stmt->close();
        header("Location: course-form.php".($is_edit?"?id={$course_id}":"")."&msg=cat_updated#categories"); exit();
    }
    if ($action==='delete_category') {
        $cid=intval($_POST['cat_id']??0);
        $in_use=$conn->query("SELECT COUNT(*) AS c FROM courses WHERE category_id={$cid}")->fetch_assoc()['c'];
        if ($in_use>0) { header("Location: course-form.php".($is_edit?"?id={$course_id}":"")."&error=cat_in_use#categories"); exit(); }
        $conn->query("DELETE FROM course_categories WHERE category_id={$cid}");
        header("Location: course-form.php".($is_edit?"?id={$course_id}":"")."&msg=cat_deleted#categories"); exit();
    }
}

if ($is_edit) {
    $stmt=$conn->prepare("SELECT * FROM courses WHERE course_id=?");
    $stmt->bind_param("i",$course_id); $stmt->execute();
    $course=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$course) { header("Location: courses.php"); exit(); }
    $mr=$conn->query("SELECT * FROM course_modules WHERE course_id={$course_id} ORDER BY module_order");
    while ($mod=$mr->fetch_assoc()) {
        $lr=$conn->query("SELECT * FROM course_lessons WHERE module_id={$mod['module_id']} ORDER BY lesson_order");
        $mod['lessons']=$lr->fetch_all(MYSQLI_ASSOC); $modules[]=$mod;
    }
    $course_files = $conn->query("SELECT * FROM course_files WHERE course_id={$course_id} ORDER BY display_order, uploaded_at")->fetch_all(MYSQLI_ASSOC);

    $quiz_lesson_ids = [];
    if (!empty($modules)) {
        $all_lesson_ids = [];
        foreach ($modules as $mod) {
            foreach ($mod['lessons'] as $les) {
                $all_lesson_ids[] = intval($les['lesson_id']);
            }
        }
        if (!empty($all_lesson_ids)) {
            $ids_str = implode(',', $all_lesson_ids);
            $qz_res  = $conn->query("SELECT lesson_id FROM lesson_quizzes WHERE lesson_id IN ({$ids_str})");
            while ($qz_row = $qz_res->fetch_assoc()) {
                $quiz_lesson_ids[] = intval($qz_row['lesson_id']);
            }
        }
    }
}

$categories=$conn->query("SELECT * FROM course_categories ORDER BY display_order")->fetch_all(MYSQLI_ASSOC);

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS instructor_id INT DEFAULT NULL");

$instructors = $conn->query("SELECT user_id, first_name, last_name, role FROM users WHERE role IN ('admin','employee') ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_json MEDIUMTEXT DEFAULT NULL");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_embed_url TEXT DEFAULT NULL");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_presentation_id VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_position INT DEFAULT 0");
$existing_slides = null;
$slides_position = 0;
if ($is_edit && $course_id > 0) {
    $sl = $conn->prepare("SELECT slides_json, slides_position FROM courses WHERE course_id = ?");
    if ($sl) {
        $sl->bind_param('i', $course_id);
        $sl->execute();
        $row_sl          = $sl->get_result()->fetch_assoc();
        $existing_slides = $row_sl['slides_json']     ?? null;
        $slides_position = intval($row_sl['slides_position'] ?? 0);
        $sl->close();
    }
}

$conn->close();

$msg=$_GET['msg']??''; $error=$_GET['error']??'';
$msg_texts=['saved'=>'✓ Cambios guardados.','created'=>'✓ Curso creado. Ahora agrega módulos y lecciones.',
    'module_added'=>'✓ Módulo agregado.','module_deleted'=>'✓ Módulo eliminado.',
    'lesson_added'=>'✓ Lección agregada.','lesson_deleted'=>'✓ Lección eliminada.',
    'cat_added'=>'✓ Categoría creada.','cat_updated'=>'✓ Categoría actualizada.','cat_deleted'=>'✓ Categoría eliminada.',
    'file_deleted'=>'✓ Archivo eliminado.'];
$error_texts=['cat_in_use'=>'✗ No se puede eliminar: hay cursos usando esta categoría.'];
$preset_colors=['#003C64','#0096DC','#00875A','#D97706','#C0392B','#7B2FBE','#0077B6','#2D6A4F','#E63946','#F4A261'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit?'Editar':'Nuevo'; ?> Curso - Admin Whirlpool</title>
    <link rel="stylesheet" href="../css/styles.css?v=4.3">
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Open Sans',sans-serif;background:#F0F4F8;color:#2D2D2D}
        .admin-topbar{background:white;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #E8E8E8;position:sticky;top:0;z-index:50}
        .topbar-title{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:1.25rem;color:#003C64}
        .topbar-subtitle{font-size:0.8rem;color:#6B6B6B;margin-top:0.1rem}
        .admin-content{padding:2rem;}
        .btn-primary{display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#003C64,#0096DC);color:white;padding:0.625rem 1.25rem;border-radius:8px;text-decoration:none;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.875rem;transition:all 0.2s;border:none;cursor:pointer}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,60,100,0.25)}
        .btn-primary svg{width:15px;height:15px}
        .btn-secondary-outline{display:inline-flex;align-items:center;gap:0.5rem;background:white;color:#003C64;padding:0.625rem 1.25rem;border-radius:8px;text-decoration:none;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.875rem;transition:all 0.2s;border:2px solid #003C64;cursor:pointer}
        .btn-secondary-outline:hover{background:#E6F0FA}
        .feedback-alert{padding:0.875rem 1.25rem;border-radius:8px;margin-bottom:1.5rem;font-size:0.875rem;font-weight:600}
        .alert-success{background:#E6FAF0;color:#00875A;border:1px solid #A8EDCC}
        .alert-error{background:#FFF0F0;color:#C0392B;border:1px solid #F5AEAE}
        .form-card{background:white;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.05);border:1px solid #F0F0F0;margin-bottom:2rem;overflow:hidden}
        .form-card-header{padding:1.25rem 1.5rem;border-bottom:1px solid #F0F0F0;display:flex;align-items:center;gap:0.75rem}
        .form-card-icon{width:36px;height:36px;background:linear-gradient(135deg,#003C64,#0096DC);border-radius:8px;display:flex;align-items:center;justify-content:center}
        .form-card-icon svg{width:18px;height:18px;stroke:white}
        .form-card-title{font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:1rem;color:#003C64}
        .form-card-body{padding:1.5rem}
        .field-row{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
        .field-group{margin-bottom:1.25rem}
        .field-group:last-child{margin-bottom:0}
        .field-label{display:block;font-size:0.78rem;font-weight:700;color:#4A4A4A;margin-bottom:0.4rem;text-transform:uppercase;letter-spacing:0.04em}
        .field-required{color:#C0392B}
        .field-input,.field-select,.field-textarea{width:100%;padding:0.625rem 0.875rem;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Open Sans',sans-serif;color:#2D2D2D;background:white;transition:border-color 0.2s,box-shadow 0.2s}
        .field-input:focus,.field-select:focus,.field-textarea:focus{outline:none;border-color:#0096DC;box-shadow:0 0 0 3px rgba(0,150,220,0.1)}
        .field-textarea{resize:vertical;min-height:100px}
        .field-hint{font-size:0.75rem;color:#8C8C8C;margin-top:0.3rem}
        .checkbox-field{display:flex;align-items:center;gap:0.625rem;cursor:pointer}
        .checkbox-field input[type="checkbox"]{width:16px;height:16px;accent-color:#0096DC;cursor:pointer}
        .checkbox-field span{font-size:0.875rem;color:#4A4A4A;font-weight:600}
        .form-actions{display:flex;gap:0.75rem;justify-content:flex-end;padding-top:1rem;border-top:1px solid #F0F0F0;margin-top:1.5rem}
        .section-header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
        .section-title-lg{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:1.1rem;color:#003C64}
        .module-block{background:white;border-radius:12px;border:1.5px solid #E8EEF5;margin-bottom:1rem;overflow:hidden}
        .module-header-bar{display:flex;align-items:center;gap:0.875rem;padding:1rem 1.25rem;background:#F8FAFB;border-bottom:1px solid #EFEFEF;cursor:pointer;user-select:none}
        .module-number{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#003C64,#0096DC);color:white;font-weight:800;font-size:0.8rem;font-family:'Nunito Sans',sans-serif;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .module-title-text{flex:1;font-weight:700;font-family:'Nunito Sans',sans-serif;color:#1A1A1A;font-size:0.95rem}
        .module-lesson-count{font-size:0.75rem;color:#8C8C8C;font-weight:600}
        .module-toggle-icon{width:18px;height:18px;stroke:#6B6B6B;transition:transform 0.2s}
        .module-body{padding:1.25rem}
        .lessons-list{margin-bottom:1.25rem}
        .lesson-item{display:flex;align-items:center;gap:0.875rem;padding:0.75rem 1rem;background:#F8FAFB;border-radius:8px;margin-bottom:0.5rem;border:1px solid #EFEFEF}
        .lesson-type-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .lesson-type-icon svg{width:15px;height:15px}
        .type-video{background:#E6F0FA}.type-video svg{stroke:#003C64}
        .type-pdf{background:#FFEDF0}.type-pdf svg{stroke:#C0392B}
        .type-image{background:#E6FAF0}.type-image svg{stroke:#00875A}
        .type-text{background:#FFF4E6}.type-text svg{stroke:#D97706}
        .type-link{background:#F0E6FA}.type-link svg{stroke:#7B2FBE}
        .type-quiz{background:#FFF4E6}.type-quiz svg{stroke:#D97706}
        .lesson-info{flex:1;min-width:0}
        .lesson-item-title{font-weight:600;font-size:0.875rem;color:#1A1A1A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lesson-item-meta{font-size:0.75rem;color:#8C8C8C;margin-top:0.1rem}
        .lesson-preview-badge{font-size:0.7rem;background:#E0F4FF;color:#0077B6;padding:0.15rem 0.5rem;border-radius:99px;font-weight:700;flex-shrink:0}
        .btn-delete-small{padding:0.3rem 0.6rem;background:#FFF0F0;color:#C0392B;border:none;border-radius:6px;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.15s;flex-shrink:0}
        .btn-delete-small:hover{background:#C0392B;color:white}

        /* ── Botón quiz en lesson-item ── */
        .btn-quiz-lesson{font-size:0.72rem;background:#FFF4E6;color:#D97706;border:1.5px solid #FDDCAA;border-radius:6px;padding:0.25rem 0.6rem;cursor:pointer;font-family:'Nunito Sans',sans-serif;font-weight:700;white-space:nowrap;flex-shrink:0;transition:all 0.15s;}
        .btn-quiz-lesson.has-quiz{background:#ECFDF5;color:#065F46;border-color:#6EE7B7;}
        .btn-quiz-lesson:hover{opacity:0.8;}

        .add-lesson-form{background:#F8FAFB;border-radius:10px;padding:1.25rem;border:1.5px dashed #D0DCE8}
        .add-lesson-title{font-size:0.8rem;font-weight:700;color:#003C64;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:1rem}
        .content-type-selector{display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem}
        .type-btn{padding:0.4rem 0.875rem;border-radius:99px;border:1.5px solid #D0DCE8;background:white;font-size:0.78rem;font-weight:700;color:#6B6B6B;cursor:pointer;transition:all 0.15s;display:flex;align-items:center;gap:0.35rem}
        .type-btn svg{width:13px;height:13px}
        .type-btn.active{background:#003C64;color:white;border-color:#003C64}
        .type-btn:hover:not(.active){border-color:#003C64;color:#003C64}
        .content-panel{display:none}.content-panel.active{display:block}
        .upload-area{border:2px dashed #C0D4E8;border-radius:10px;padding:2rem;text-align:center;background:white;cursor:pointer;transition:all 0.2s;position:relative;margin-bottom:0.5rem}
        .upload-area:hover,.upload-area.drag-over{border-color:#0096DC;background:#F0F8FF}
        .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .upload-icon{font-size:2rem;margin-bottom:0.5rem}
        .upload-label{font-size:0.875rem;font-weight:600;color:#003C64}
        .upload-hint{font-size:0.75rem;color:#8C8C8C;margin-top:0.25rem}
        .upload-progress{display:none;margin-top:0.75rem}
        .progress-track{height:6px;background:#E0E0E0;border-radius:99px;overflow:hidden}
        .progress-bar-fill{height:100%;background:linear-gradient(90deg,#003C64,#0096DC);border-radius:99px;width:0;transition:width 0.3s}
        .upload-status{font-size:0.8rem;color:#6B6B6B;margin-top:0.4rem}
        .file-preview{display:none;align-items:center;gap:0.75rem;padding:0.75rem 1rem;background:#E6F0FA;border-radius:8px;margin-top:0.75rem}
        .file-preview.show{display:flex}
        .file-preview-name{flex:1;font-size:0.85rem;font-weight:600;color:#003C64;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .file-preview-size{font-size:0.75rem;color:#6B6B6B}
        .btn-remove-file{background:none;border:none;color:#C0392B;cursor:pointer;font-size:1rem;padding:0}
        .rich-editor-toolbar{display:flex;gap:0.25rem;padding:0.5rem;background:#F0F4F8;border:1px solid #E0E0E0;border-radius:8px 8px 0 0;flex-wrap:wrap}
        .toolbar-btn{padding:0.3rem 0.5rem;background:white;border:1px solid #D0DCE8;border-radius:4px;cursor:pointer;font-size:0.8rem;font-weight:700;color:#4A4A4A;transition:all 0.15s}
        .toolbar-btn:hover{background:#003C64;color:white;border-color:#003C64}
        .rich-editor{min-height:150px;padding:0.875rem;border:1px solid #E0E0E0;border-top:none;border-radius:0 0 8px 8px;font-size:0.9rem;font-family:'Open Sans',sans-serif;outline:none;background:white}
        .rich-editor:empty::before{content:attr(data-placeholder);color:#ADADAD}
        .lesson-fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.875rem;margin-bottom:1rem}
        .lesson-field-full{grid-column:1/-1}
        .inline-label{font-size:0.75rem;font-weight:700;color:#6B6B6B;display:block;margin-bottom:0.3rem;text-transform:uppercase}
        .inline-input,.inline-select{width:100%;padding:0.5rem 0.75rem;border:1px solid #DDE4EC;border-radius:6px;font-size:0.85rem;font-family:'Open Sans',sans-serif;background:white}
        .inline-input:focus,.inline-select:focus{outline:none;border-color:#0096DC}
        .btn-add-lesson{background:#003C64;color:white;border:none;border-radius:6px;padding:0.5rem 1rem;font-size:0.8rem;font-weight:700;font-family:'Nunito Sans',sans-serif;cursor:pointer;transition:background 0.2s;display:inline-flex;align-items:center;gap:0.3rem;margin-top:0.5rem}
        .btn-add-lesson:hover{background:#0096DC}
        .btn-add-lesson svg{width:13px;height:13px}
        .add-module-card{background:white;border-radius:12px;border:2px dashed #C0D4E8;padding:1.5rem;margin-top:0.5rem}
        .add-module-title{font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.9rem;color:#003C64;margin-bottom:1rem}
        .no-course-notice{background:#E6F0FA;border-radius:12px;padding:2rem;text-align:center;color:#003C64}
        .no-course-notice p{font-size:0.9rem;color:#4A4A4A;margin-top:0.5rem}
        .cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:1rem;margin-bottom:1.5rem}
        .cat-card{background:#F8FAFB;border-radius:10px;border:1.5px solid #E8EEF5;overflow:hidden}
        .cat-card-header{display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1rem}
        .cat-color-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0}
        .cat-card-name{flex:1;font-weight:700;font-size:0.9rem;font-family:'Nunito Sans',sans-serif;color:#1A1A1A}
        .cat-status-pill{font-size:0.68rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:99px}
        .cat-status-active{background:#E6FAF0;color:#00875A}
        .cat-status-inactive{background:#F0F0F0;color:#8C8C8C}
        .cat-card-body{padding:0.875rem 1rem;border-top:1px solid #EFEFEF}
        .cat-card-desc{font-size:0.8rem;color:#6B6B6B;margin-bottom:0.75rem;min-height:1rem}
        .cat-card-actions{display:flex;gap:0.5rem}
        .btn-cat-edit{flex:1;padding:0.4rem;background:#E6F0FA;color:#003C64;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.15s}
        .btn-cat-edit:hover{background:#003C64;color:white}
        .btn-cat-delete{padding:0.4rem 0.75rem;background:#FFF0F0;color:#C0392B;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.15s}
        .btn-cat-delete:hover{background:#C0392B;color:white}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center}
        .modal-overlay.active{display:flex}
        .modal-box{background:white;border-radius:16px;padding:2rem;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto}
        .modal-title{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:1.1rem;color:#003C64;margin-bottom:1.25rem}
        .modal-actions{display:flex;gap:0.75rem;margin-top:1.25rem}
        .btn-cancel{flex:1;padding:0.75rem;background:#F0F4F8;color:#4A4A4A;border:none;border-radius:8px;font-weight:700;font-family:'Nunito Sans',sans-serif;cursor:pointer;font-size:0.9rem}
        .btn-cancel:hover{background:#E0E8F0}
        .color-options{display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.5rem}
        .color-swatch{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all 0.15s}
        .color-swatch.selected{border-color:#1A1A1A;transform:scale(1.15)}
        .color-input-row{display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem}
        .video-sub-tabs{display:flex;gap:0.4rem;margin-bottom:0.75rem}
        .thumbnail-zone{display:flex;gap:1.5rem;align-items:flex-start}
        .thumbnail-preview-box{width:180px;height:120px;border-radius:10px;overflow:hidden;flex-shrink:0;background:#E6F0FA;display:flex;align-items:center;justify-content:center;border:2px solid #E0E0E0}
        .thumbnail-preview-box img{width:100%;height:100%;object-fit:cover}
        .thumbnail-preview-box .no-thumb{font-size:2.5rem}
        .thumbnail-upload-side{flex:1}
        .thumb-upload-area{border:2px dashed #C0D4E8;border-radius:10px;padding:1.25rem;text-align:center;background:white;cursor:pointer;transition:all 0.2s;position:relative}
        .thumb-upload-area:hover{border-color:#0096DC;background:#F0F8FF}
        .thumb-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .thumb-upload-label{font-size:0.875rem;font-weight:600;color:#003C64}
        .thumb-upload-hint{font-size:0.75rem;color:#8C8C8C;margin-top:0.25rem}
        .cf-file-list{margin-bottom:1.25rem}
        .cf-file-item{display:flex;align-items:center;gap:0.875rem;padding:0.75rem 1rem;background:#F8FAFB;border-radius:8px;margin-bottom:0.5rem;border:1px solid #EFEFEF}
        .cf-file-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .cf-file-info{flex:1;min-width:0}
        .cf-file-name{font-weight:600;font-size:0.875rem;color:#1A1A1A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .cf-file-meta{font-size:0.75rem;color:#8C8C8C;margin-top:0.1rem}
        .cf-file-desc{font-size:0.78rem;color:#6B6B6B;margin-top:0.1rem;font-style:italic}
        .cf-upload-zone{border:2px dashed #C0D4E8;border-radius:10px;padding:1.5rem;background:white}
        .cf-drop-area{border:2px dashed #D0DCE8;border-radius:8px;padding:1.5rem;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;margin-bottom:1rem}
        .cf-drop-area:hover,.cf-drop-area.drag-over{border-color:#0096DC;background:#F0F8FF}
        .cf-drop-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .cf-type-tabs{display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.75rem}
        .cf-queued-list{margin-bottom:0.75rem}
        .cf-queued-item{display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;background:#F0F8FF;border-radius:6px;margin-bottom:0.4rem}
        .cf-queued-name{flex:1;font-size:0.82rem;font-weight:600;color:#003C64;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .cf-queued-bar-wrap{width:80px}
        .cf-queued-bar{height:4px;background:#E0E0E0;border-radius:99px;overflow:hidden}
        .cf-queued-fill{height:100%;background:linear-gradient(90deg,#003C64,#0096DC);width:0;transition:width 0.3s;border-radius:99px}
        .cf-queued-pct{font-size:0.7rem;color:#6B6B6B;min-width:32px;text-align:right}
        .cf-queued-status{font-size:0.75rem;color:#00875A;font-weight:600}
        .cf-queued-remove{background:none;border:none;color:#C0392B;cursor:pointer;font-size:0.9rem;padding:0}
        @keyframes spin{to{transform:rotate(360deg)}}
        .admin-main{margin-left:260px;min-height:100vh;}
        /* ── Drag & Drop ── */
        .drag-handle{cursor:grab;color:#B0B8C4;font-size:1.1rem;padding:0 2px;flex-shrink:0;line-height:1;user-select:none;touch-action:none;}
        .drag-handle:active{cursor:grabbing;}
        .module-block.drag-over-mod{border:2px dashed #0099D8;background:#F0F8FF;}
        .lesson-item.drag-over-les{border:2px dashed #0099D8;background:#EDF7FF;}
        .module-block.dragging-mod{opacity:0.45;}
        .lesson-item.dragging-les{opacity:0.45;}
        .drag-save-indicator{position:fixed;bottom:1.5rem;right:1.5rem;background:#00875A;color:white;padding:0.5rem 1rem;border-radius:8px;font-size:0.82rem;font-weight:700;font-family:'Nunito Sans',sans-serif;z-index:9999;opacity:0;transition:opacity 0.3s;pointer-events:none;}
        .drag-save-indicator.show{opacity:1;}
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ROLE   = "<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'admin'); ?>";
        window.ADMIN_ACTIVE = "course-form";
    </script>
    <script src="/admin/js/admin-navbar.js" defer></script>
</head>
<body>
<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <div class="topbar-title"><?php echo $is_edit?'Editar Curso':'Nuevo Curso'; ?></div>
            <div class="topbar-subtitle"><?php echo $is_edit?htmlspecialchars($course['course_title']):'Completa los datos para crear el curso'; ?></div>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <a href="courses.php" class="btn-secondary-outline">← Volver</a>
            <?php if($is_edit): ?>
            <a href="../course-detail.php?id=<?php echo $course_id; ?>" target="_blank" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Vista previa
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <?php if($msg&&isset($msg_texts[$msg])): ?><div class="feedback-alert alert-success"><?php echo $msg_texts[$msg]; ?></div><?php endif; ?>
        <?php if($error&&isset($error_texts[$error])): ?><div class="feedback-alert alert-error"><?php echo $error_texts[$error]; ?></div><?php endif; ?>

        <!-- ── Información general ─────────────────────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div>
                <div class="form-card-title">Información General del Curso</div>
            </div>
            <div class="form-card-body">
                <form method="POST" action="course-form.php<?php echo $is_edit?'?id='.$course_id:''; ?>">
                    <input type="hidden" name="action" value="save_course">
                    <div class="field-group">
                        <label class="field-label">Título <span class="field-required">*</span></label>
                        <input type="text" name="course_title" class="field-input" required placeholder="Ej. Introducción a la Inteligencia Artificial" value="<?php echo htmlspecialchars($course['course_title']??''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Descripción <span class="field-required">*</span></label>
                        <textarea name="course_description" class="field-textarea" required placeholder="Describe qué aprenderán los empleados..."><?php echo htmlspecialchars($course['course_description']??''); ?></textarea>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">Categoría <span class="field-required">*</span></label>
                            <select name="category_id" class="field-select" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach($categories as $cat): if($cat['status']!=='active') continue; ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo ($course['category_id']??'')==$cat['category_id']?'selected':''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-hint">¿No encuentras la categoría? <a href="#categories" style="color:#0096DC;font-weight:600;">Créala abajo ↓</a></div>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Nivel de dificultad</label>
                            <select name="difficulty_level" class="field-select">
                                <option value="beginner" <?php echo ($course['difficulty_level']??'')==='beginner'?'selected':''; ?>>Básico</option>
                                <option value="intermediate" <?php echo ($course['difficulty_level']??'')==='intermediate'?'selected':''; ?>>Intermedio</option>
                                <option value="advanced" <?php echo ($course['difficulty_level']??'')==='advanced'?'selected':''; ?>>Avanzado</option>
                            </select>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label">Horas estimadas</label>
                            <input type="number" name="estimated_hours" class="field-input" step="0.5" min="0.5" placeholder="Ej. 4.5" value="<?php echo htmlspecialchars($course['estimated_hours']??''); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label">Estado de publicación</label>
                            <select name="status" class="field-select">
                                <option value="draft" <?php echo ($course['status']??'draft')==='draft'?'selected':''; ?>>Borrador</option>
                                <option value="published" <?php echo ($course['status']??'')==='published'?'selected':''; ?>>Publicado</option>
                            </select>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="checkbox-field">
                            <input type="checkbox" name="is_featured" <?php echo ($course['is_featured']??0)?'checked':''; ?>>
                            <span>⭐ Destacar este curso en la página principal</span>
                        </label>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Instructor</label>
                        <select name="instructor_id" class="field-select">
                            <option value="">— Sin instructor asignado —</option>
                            <?php foreach($instructors as $inst):
                                $selected = (isset($course['instructor_id']) && $course['instructor_id'] == $inst['user_id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $inst['user_id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($inst['first_name'].' '.$inst['last_name']); ?><?php echo $inst['role']==='admin' ? ' (Admin)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">Nombre que aparecerá como instructor en la página del curso.</div>
                    </div>
                    <div class="form-actions">
                        <a href="courses.php" class="btn-secondary-outline">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                            <?php echo $is_edit?'Guardar cambios':'Crear curso'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Módulos y lecciones ─────────────────────────────── -->
        <div id="modules" style="margin-bottom:2rem;">
        <?php if(!$is_edit): ?>
            <div class="no-course-notice"><strong>📚 Módulos y lecciones</strong><p>Primero guarda los datos del curso para poder agregar módulos y lecciones.</p></div>
        <?php else: ?>
            <div class="section-header-bar">
                <div class="section-title-lg">Módulos y Lecciones <span style="font-size:0.8rem;font-weight:400;color:#8C8C8C;margin-left:0.5rem;">(<?php echo count($modules); ?> módulos)</span></div>
            </div>

            <?php foreach($modules as $mi=>$module): ?>
            <div class="module-block" draggable="true" data-module-id="<?php echo $module['module_id']; ?>">
                <div class="module-header-bar" onclick="toggleModule(<?php echo $module['module_id']; ?>)">
                    <span class="drag-handle" title="Arrastrar módulo" onclick="event.stopPropagation()">⠿</span>
                    <div class="module-number"><?php echo $mi+1; ?></div>
                    <div class="module-title-text"><?php echo htmlspecialchars($module['module_title']); ?></div>
                    <div class="module-lesson-count"><?php echo count($module['lessons']); ?> lección(es)</div>
                    <form method="POST" action="course-form.php?id=<?php echo $course_id; ?>" onsubmit="return confirm('¿Eliminar este módulo y todas sus lecciones?');" style="margin-left:0.5rem;">
                        <input type="hidden" name="action" value="delete_module">
                        <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
                        <button type="submit" class="btn-delete-small" onclick="event.stopPropagation()">Eliminar módulo</button>
                    </form>
                    <svg class="module-toggle-icon" id="icon-<?php echo $module['module_id']; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="module-body" id="body-<?php echo $module['module_id']; ?>" style="display:none;">
                    <?php if(!empty($module['lessons'])): ?>
                    <div class="lessons-list" data-module-id="<?php echo $module['module_id']; ?>">
                        <?php foreach($module['lessons'] as $lesson):
                            $icons=['video'=>'<path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>','pdf'=>'<path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>','image'=>'<path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>','text'=>'<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>','link'=>'<path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>','quiz'=>'<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'];
                            $icon=$icons[$lesson['content_type']]??$icons['text'];
                            $has_quiz = in_array(intval($lesson['lesson_id']), $quiz_lesson_ids);
                        ?>
                        <div class="lesson-item" draggable="true" data-lesson-id="<?php echo $lesson['lesson_id']; ?>">
                            <span class="drag-handle" title="Arrastrar lección">⠿</span>
                            <div class="lesson-type-icon type-<?php echo $lesson['content_type']; ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php echo $icon; ?></svg>
                            </div>
                            <div class="lesson-info">
                                <div class="lesson-item-title"><?php echo htmlspecialchars($lesson['lesson_title']); ?></div>
                                <div class="lesson-item-meta"><?php echo ucfirst($lesson['content_type']); ?><?php if($lesson['duration_minutes']): ?> · <?php echo $lesson['duration_minutes']; ?> min<?php endif; ?><?php if($lesson['content_url']): ?> · <span style="color:#0096DC;">Con recurso adjunto</span><?php endif; ?></div>
                            </div>
                            <?php if($lesson['is_preview']): ?><span class="lesson-preview-badge">Vista previa</span><?php endif; ?>

                            <!-- ── Botón Quiz ── -->
                            <button
                                type="button"
                                class="btn-quiz-lesson <?php echo $has_quiz ? 'has-quiz' : ''; ?>"
                                data-lesson-quiz="<?php echo $lesson['lesson_id']; ?>"
                                onclick="QuizBuilder.openForLesson(<?php echo $lesson['lesson_id']; ?>, <?php echo $course_id; ?>, '<?php echo htmlspecialchars(addslashes($lesson['lesson_title'])); ?>')"
                            ><?php echo $has_quiz ? '✅ Quiz' : '+ Quiz'; ?></button>

                            <form method="POST" action="course-form.php?id=<?php echo $course_id; ?>" onsubmit="return confirm('¿Eliminar esta lección?');">
                                <input type="hidden" name="action" value="delete_lesson">
                                <input type="hidden" name="lesson_id" value="<?php echo $lesson['lesson_id']; ?>">
                                <button type="submit" class="btn-delete-small">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p style="font-size:0.85rem;color:#8C8C8C;margin-bottom:1rem;">Este módulo no tiene lecciones aún.</p><?php endif; ?>

                    <!-- Formulario agregar lección -->
                    <div class="add-lesson-form">
                        <div class="add-lesson-title">+ Agregar lección</div>
                        <form method="POST" action="course-form.php?id=<?php echo $course_id; ?>">
                            <input type="hidden" name="action" value="add_lesson">
                            <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
                            <input type="hidden" name="content_type" id="ct_<?php echo $module['module_id']; ?>" value="video">
                            <input type="hidden" name="content_url"  id="cu_<?php echo $module['module_id']; ?>" value="">
                            <input type="hidden" name="content_text" id="cx_<?php echo $module['module_id']; ?>" value="">
                            <div class="content-type-selector" id="typeSelector-<?php echo $module['module_id']; ?>">
                                <button type="button" class="type-btn active" onclick="setType(<?php echo $module['module_id']; ?>,'video',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Video
                                </button>
                                <button type="button" class="type-btn" onclick="setType(<?php echo $module['module_id']; ?>,'link',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    Link
                                </button>
                                <button type="button" class="type-btn" onclick="setType(<?php echo $module['module_id']; ?>,'pdf',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    PDF
                                </button>
                                <button type="button" class="type-btn" onclick="setType(<?php echo $module['module_id']; ?>,'image',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Imagen
                                </button>
                                <button type="button" class="type-btn" onclick="setType(<?php echo $module['module_id']; ?>,'text',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    Texto
                                </button>
                            </div>
                            <div class="content-panel active" id="p_video_<?php echo $module['module_id']; ?>">
                                <div class="video-sub-tabs">
                                    <button type="button" class="type-btn active" id="vt_up_<?php echo $module['module_id']; ?>" onclick="switchVideoTab(<?php echo $module['module_id']; ?>,'upload')">⬆ Subir MP4</button>
                                    <button type="button" class="type-btn" id="vt_url_<?php echo $module['module_id']; ?>" onclick="switchVideoTab(<?php echo $module['module_id']; ?>,'url')">🔗 YouTube / Vimeo</button>
                                </div>
                                <div id="vUpArea_<?php echo $module['module_id']; ?>">
                                    <div class="upload-area" id="ua_video_<?php echo $module['module_id']; ?>">
                                        <input type="file" accept="video/mp4,video/webm" onchange="uploadFile(this,<?php echo $module['module_id']; ?>,'video')">
                                        <div class="upload-icon">🎬</div>
                                        <div class="upload-label">Arrastra el video o haz clic</div>
                                        <div class="upload-hint">MP4, WebM · Máx 500 MB</div>
                                    </div>
                                    <div class="upload-progress" id="up_video_<?php echo $module['module_id']; ?>">
                                        <div class="progress-track"><div class="progress-bar-fill" id="pb_video_<?php echo $module['module_id']; ?>"></div></div>
                                        <div class="upload-status" id="ps_video_<?php echo $module['module_id']; ?>">Subiendo...</div>
                                    </div>
                                    <div class="file-preview" id="fp_video_<?php echo $module['module_id']; ?>">
                                        <span>🎬</span>
                                        <span class="file-preview-name" id="fn_video_<?php echo $module['module_id']; ?>"></span>
                                        <span class="file-preview-size" id="fs_video_<?php echo $module['module_id']; ?>"></span>
                                        <button type="button" class="btn-remove-file" onclick="removeFile(<?php echo $module['module_id']; ?>,'video')">✕</button>
                                    </div>
                                </div>
                                <div id="vUrlArea_<?php echo $module['module_id']; ?>" style="display:none;">
                                    <label class="inline-label">URL de YouTube o Vimeo</label>
                                    <input type="url" class="inline-input" placeholder="https://youtube.com/watch?v=..." oninput="setCU(<?php echo $module['module_id']; ?>,this.value)">
                                </div>
                            </div>
                            <div class="content-panel" id="p_link_<?php echo $module['module_id']; ?>">
                                <label class="inline-label">URL del recurso externo</label>
                                <input type="url" class="inline-input" placeholder="https://..." oninput="setCU(<?php echo $module['module_id']; ?>,this.value)">
                            </div>
                            <div class="content-panel" id="p_pdf_<?php echo $module['module_id']; ?>">
                                <div class="upload-area" id="ua_pdf_<?php echo $module['module_id']; ?>">
                                    <input type="file" accept="application/pdf" onchange="uploadFile(this,<?php echo $module['module_id']; ?>,'pdf')">
                                    <div class="upload-icon">📄</div>
                                    <div class="upload-label">Arrastra el PDF o haz clic</div>
                                    <div class="upload-hint">PDF · Máx 50 MB</div>
                                </div>
                                <div class="upload-progress" id="up_pdf_<?php echo $module['module_id']; ?>">
                                    <div class="progress-track"><div class="progress-bar-fill" id="pb_pdf_<?php echo $module['module_id']; ?>"></div></div>
                                    <div class="upload-status" id="ps_pdf_<?php echo $module['module_id']; ?>">Subiendo...</div>
                                </div>
                                <div class="file-preview" id="fp_pdf_<?php echo $module['module_id']; ?>">
                                    <span>📄</span>
                                    <span class="file-preview-name" id="fn_pdf_<?php echo $module['module_id']; ?>"></span>
                                    <span class="file-preview-size" id="fs_pdf_<?php echo $module['module_id']; ?>"></span>
                                    <button type="button" class="btn-remove-file" onclick="removeFile(<?php echo $module['module_id']; ?>,'pdf')">✕</button>
                                </div>
                            </div>
                            <div class="content-panel" id="p_image_<?php echo $module['module_id']; ?>">
                                <div class="upload-area" id="ua_image_<?php echo $module['module_id']; ?>">
                                    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" onchange="uploadFile(this,<?php echo $module['module_id']; ?>,'image')">
                                    <div class="upload-icon">🖼️</div>
                                    <div class="upload-label">Arrastra la imagen o haz clic</div>
                                    <div class="upload-hint">JPG, PNG, WebP · Máx 10 MB</div>
                                </div>
                                <div class="upload-progress" id="up_image_<?php echo $module['module_id']; ?>">
                                    <div class="progress-track"><div class="progress-bar-fill" id="pb_image_<?php echo $module['module_id']; ?>"></div></div>
                                    <div class="upload-status" id="ps_image_<?php echo $module['module_id']; ?>">Subiendo...</div>
                                </div>
                                <div class="file-preview" id="fp_image_<?php echo $module['module_id']; ?>">
                                    <span>🖼️</span>
                                    <span class="file-preview-name" id="fn_image_<?php echo $module['module_id']; ?>"></span>
                                    <span class="file-preview-size" id="fs_image_<?php echo $module['module_id']; ?>"></span>
                                    <button type="button" class="btn-remove-file" onclick="removeFile(<?php echo $module['module_id']; ?>,'image')">✕</button>
                                </div>
                            </div>
                            <div class="content-panel" id="p_text_<?php echo $module['module_id']; ?>">
                                <div class="rich-editor-toolbar">
                                    <button type="button" class="toolbar-btn" onclick="fmt('bold')"><b>B</b></button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('italic')"><i>I</i></button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('underline')"><u>U</u></button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('insertUnorderedList')">• Lista</button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('insertOrderedList')">1. Lista</button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('formatBlock','h3')">H3</button>
                                    <button type="button" class="toolbar-btn" onclick="fmt('removeFormat')">Limpiar</button>
                                </div>
                                <div class="rich-editor" id="re_<?php echo $module['module_id']; ?>"
                                     contenteditable="true" data-placeholder="Escribe el contenido aquí..."
                                     oninput="setCX(<?php echo $module['module_id']; ?>,this.innerHTML)"></div>
                            </div>
                            <div class="lesson-fields-grid" style="margin-top:1rem;">
                                <div class="lesson-field-full">
                                    <label class="inline-label">Título de la lección *</label>
                                    <input type="text" name="lesson_title" class="inline-input" required placeholder="Ej. ¿Qué es machine learning?">
                                </div>
                                <div>
                                    <label class="inline-label">Duración (minutos)</label>
                                    <input type="number" name="duration_minutes" class="inline-input" min="0" placeholder="Ej. 15">
                                </div>
                                <div style="display:flex;align-items:flex-end;padding-bottom:0.3rem;">
                                    <label class="checkbox-field">
                                        <input type="checkbox" name="is_preview">
                                        <span>Disponible como vista previa</span>
                                    </label>
                                </div>
                                <div class="lesson-field-full">
                                    <label class="inline-label">Descripción breve</label>
                                    <input type="text" name="lesson_description" class="inline-input" placeholder="Descripción corta...">
                                </div>
                            </div>
                            <button type="submit" class="btn-add-lesson">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                                Agregar lección
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="add-module-card">
                <div class="add-module-title">+ Agregar nuevo módulo</div>
                <form method="POST" action="course-form.php?id=<?php echo $course_id; ?>">
                    <input type="hidden" name="action" value="add_module">
                    <div class="field-row" style="margin-bottom:1rem;">
                        <div><label class="field-label">Título *</label><input type="text" name="module_title" class="field-input" required placeholder="Ej. Módulo 1: Fundamentos"></div>
                        <div><label class="field-label">Descripción</label><input type="text" name="module_description" class="field-input" placeholder="Breve descripción..."></div>
                    </div>
                    <button type="submit" class="btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>Agregar módulo</button>
                </form>
            </div>
        <?php endif; ?>
        </div>

        <!-- ── Thumbnail ───────────────────────────────────────── -->
        <?php if($is_edit): ?>
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                <div class="form-card-title">Imagen de portada (thumbnail)</div>
            </div>
            <div class="form-card-body">
                <div class="thumbnail-zone">
                    <div class="thumbnail-preview-box" id="thumbPreviewBox">
                        <?php if(!empty($course['thumbnail_url'])): ?>
                            <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" id="thumbImg" alt="Thumbnail">
                        <?php else: ?>
                            <span class="no-thumb" id="thumbPlaceholder">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <div class="thumbnail-upload-side">
                        <div class="thumb-upload-area" id="thumbDropArea">
                            <input type="file" accept="image/jpeg,image/png,image/webp" id="thumbInput" onchange="uploadThumbnail(this)">
                            <div class="upload-icon" style="font-size:1.5rem;margin-bottom:0.3rem;">⬆</div>
                            <div class="thumb-upload-label">Arrastra la imagen o haz clic para seleccionar</div>
                            <div class="thumb-upload-hint">JPG, PNG, WebP · Recomendado 800×450px · Máx 5 MB</div>
                        </div>
                        <div style="margin-top:0.75rem;" id="thumbProgressWrap" style="display:none;">
                            <div class="progress-track"><div class="progress-bar-fill" id="thumbProgressBar" style="width:0"></div></div>
                            <div class="upload-status" id="thumbProgressStatus">Subiendo...</div>
                        </div>
                        <?php if(!empty($course['thumbnail_url'])): ?>
                        <div style="margin-top:0.75rem;font-size:0.8rem;color:#00875A;font-weight:600;" id="thumbCurrentMsg">✓ Imagen de portada cargada. Sube otra para reemplazarla.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Archivos del curso ──────────────────────────────── -->
        <div class="form-card" id="course-files">
            <div class="form-card-header">
                <div class="form-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg></div>
                <div class="form-card-title">Archivos del curso <span style="font-size:0.8rem;font-weight:400;color:#8C8C8C;">(<?php echo count($course_files); ?> archivos)</span></div>
            </div>
            <div class="form-card-body">
                <p style="font-size:0.85rem;color:#6B6B6B;margin-bottom:1.25rem;">Los estudiantes inscritos podrán ver y descargar estos archivos desde la página del curso.</p>
                <?php if(!empty($course_files)): ?>
                <div class="cf-file-list">
                    <?php
                    $cf_icons=['pdf'=>'📄','image'=>'🖼️','document'=>'📋','video'=>'🎬','other'=>'📁'];
                    foreach($course_files as $cf):
                        $icon=$cf_icons[$cf['file_type']]??'📁';
                        $size_str=$cf['file_size_kb']>=1024?round($cf['file_size_kb']/1024,1).' MB':$cf['file_size_kb'].' KB';
                    ?>
                    <div class="cf-file-item">
                        <div class="cf-file-icon"><?php echo $icon; ?></div>
                        <div class="cf-file-info">
                            <div class="cf-file-name"><?php echo htmlspecialchars($cf['file_name']); ?></div>
                            <div class="cf-file-meta"><?php echo strtoupper($cf['file_type']); ?> · <?php echo $size_str; ?> · Subido <?php echo date('d/m/Y',strtotime($cf['uploaded_at'])); ?></div>
                            <?php if($cf['description']): ?><div class="cf-file-desc"><?php echo htmlspecialchars($cf['description']); ?></div><?php endif; ?>
                        </div>
                        <a href="<?php echo htmlspecialchars($cf['file_url']); ?>" target="_blank" class="btn-secondary-outline" style="padding:0.35rem 0.75rem;font-size:0.75rem;">Ver</a>
                        <form method="POST" action="course-form.php?id=<?php echo $course_id; ?>" onsubmit="return confirm('¿Eliminar este archivo?');">
                            <input type="hidden" name="action" value="delete_course_file">
                            <input type="hidden" name="file_id" value="<?php echo $cf['file_id']; ?>">
                            <button type="submit" class="btn-delete-small">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="cf-upload-zone">
                    <div style="font-size:0.8rem;font-weight:700;color:#003C64;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.875rem;">+ Subir archivos</div>
                    <div class="cf-type-tabs" id="cfTypeTabs">
                        <button type="button" class="type-btn active" onclick="setCFType('doc',this)">📋 Documento / PDF</button>
                        <button type="button" class="type-btn" onclick="setCFType('cf_image',this)">🖼️ Imagen</button>
                        <button type="button" class="type-btn" onclick="setCFType('cf_video',this)">🎬 Video</button>
                    </div>
                    <div class="cf-drop-area" id="cfDropArea">
                        <input type="file" id="cfFileInput" multiple onchange="enqueueCFFiles(this)">
                        <div style="font-size:1.5rem;margin-bottom:0.3rem;" id="cfDropIcon">📋</div>
                        <div style="font-size:0.875rem;font-weight:600;color:#003C64;">Arrastra los archivos o haz clic para seleccionar</div>
                        <div style="font-size:0.75rem;color:#8C8C8C;margin-top:0.25rem;" id="cfDropHint">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV · Máx 100 MB por archivo</div>
                    </div>
                    <div style="margin-bottom:0.75rem;">
                        <label class="inline-label">Descripción de los archivos (opcional)</label>
                        <input type="text" class="inline-input" id="cfDescription" placeholder="Ej. Material de lectura del módulo 1...">
                    </div>
                    <div class="cf-queued-list" id="cfQueue"></div>
                    <button type="button" class="btn-primary" id="cfUploadBtn" onclick="uploadCFQueue()" style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px;"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Subir archivos al servidor
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Presentación IA ─────────────────────────────────── -->
        <div class="form-card" id="slides-card">
            <div class="form-card-header">
                <div class="form-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
                <div class="form-card-title">Presentación del Curso <span style="font-size:0.75rem;font-weight:600;color:#0099D8;background:#E5F4FC;padding:0.2rem 0.6rem;border-radius:99px;margin-left:0.5rem;">✨ Generada por IA</span></div>
            </div>
            <div class="form-card-body">
                <?php if($existing_slides): ?>
                <div id="slides-existing" style="margin-bottom:1.5rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1.25rem;background:#E5F4FC;border-radius:10px;margin-bottom:1rem;flex-wrap:wrap;">
                        <span style="font-size:1.25rem;">✅</span>
                        <div style="flex:1;min-width:150px;">
                            <div style="font-weight:700;color:#004976;font-size:0.9rem;">Presentación generada</div>
                            <div style="font-size:0.78rem;color:#0099D8;">Lista para los estudiantes.</div>
                        </div>
                        <a href="../course-slides-viewer.php?course_id=<?php echo $course_id; ?>" target="_blank"
                           style="padding:0.4rem 0.875rem;background:#004976;color:white;border-radius:8px;font-size:0.78rem;font-weight:700;text-decoration:none;">Ver →</a>
                        <button type="button" onclick="deleteSlides()"
                            style="padding:0.4rem 0.875rem;background:none;border:1.5px solid #e74c3c;color:#e74c3c;border-radius:8px;font-size:0.78rem;font-weight:700;cursor:pointer;">🗑 Borrar</button>
                    </div>
                    <div style="background:#f8fbfd;border:1.5px solid #E5F4FC;border-radius:10px;padding:1rem 1.25rem;">
                        <label style="font-weight:700;font-size:0.8rem;color:#003C64;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:0.75rem;">📍 Posición en el curso</label>
                        <p style="font-size:0.8rem;color:#6B6B6B;margin-bottom:0.875rem;">Elige dónde aparece la presentación entre los módulos del curso.</p>
                        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                            <select id="slides-position-select" style="padding:0.5rem 0.875rem;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.85rem;font-family:inherit;color:#003C64;font-weight:600;outline:none;cursor:pointer;">
                                <option value="0" <?php echo $slides_position==0?'selected':''; ?>>— Sin asignar —</option>
                                <?php foreach($modules as $mod): ?>
                                <option value="<?php echo $mod['module_id']; ?>" <?php echo $slides_position==$mod['module_id']?'selected':''; ?>>
                                    📦 <?php echo htmlspecialchars($mod['module_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="savePosition(document.getElementById('slides-position-select').value)"
                                style="padding:0.5rem 1rem;background:#004976;color:white;border:none;border-radius:8px;font-size:0.82rem;font-weight:700;cursor:pointer;">Guardar posición</button>
                            <span id="position-saved" style="display:none;font-size:0.78rem;color:#27ae60;font-weight:700;">✓ Guardado</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <p style="font-size:0.85rem;color:#6B6B6B;margin-bottom:1.25rem;">Gemini generará automáticamente una presentación profesional de 8 diapositivas para este curso.</p>
                <div style="margin-bottom:1.25rem;">
                    <label style="font-weight:700;font-size:0.8rem;color:#003C64;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:0.75rem;">
                        Temas a cubrir <span style="font-weight:400;color:#8C8C8C;text-transform:none;">(opcional)</span>
                    </label>
                    <div id="topics-list" style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:0.75rem;"></div>
                    <button type="button" onclick="addTopicRow()"
                        style="font-size:0.78rem;font-weight:700;color:#0099D8;background:none;border:1.5px dashed #0099D8;border-radius:8px;padding:0.45rem 1rem;cursor:pointer;">+ Agregar tema</button>
                </div>
                <button type="button" id="btn-gen-slides" onclick="generateSlides()"
                    style="display:flex;align-items:center;gap:0.625rem;padding:0.75rem 1.5rem;background:linear-gradient(135deg,#004976,#0099D8);color:white;border:none;border-radius:10px;font-size:0.9rem;font-weight:700;cursor:pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path d="M5 3l14 9-14 9V3z"/></svg>
                    <?php echo $existing_slides?'Regenerar presentación':'Generar presentación con IA'; ?>
                </button>
                <div id="slides-status" style="display:none;margin-top:1rem;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Categorías ──────────────────────────────────────── -->
        <div id="categories" class="form-card">
            <div class="form-card-header">
                <div class="form-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div>
                <div class="form-card-title">Gestión de Categorías</div>
            </div>
            <div class="form-card-body">
                <div class="cat-grid">
                    <?php foreach($categories as $cat): ?>
                    <div class="cat-card">
                        <div class="cat-card-header">
                            <div class="cat-color-dot" style="background:<?php echo htmlspecialchars($cat['color']); ?>"></div>
                            <div class="cat-card-name"><?php echo htmlspecialchars($cat['category_name']); ?></div>
                            <span class="cat-status-pill cat-status-<?php echo $cat['status']; ?>"><?php echo $cat['status']==='active'?'Activa':'Inactiva'; ?></span>
                        </div>
                        <div class="cat-card-body">
                            <div class="cat-card-desc"><?php echo htmlspecialchars($cat['description']??''); ?></div>
                            <div class="cat-card-actions">
                                <button type="button" class="btn-cat-edit" onclick='openEditCat(<?php echo json_encode($cat); ?>)'>✏️ Editar</button>
                                <form method="POST" action="course-form.php<?php echo $is_edit?'?id='.$course_id:''; ?>" onsubmit="return confirm('¿Eliminar categoría?');">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="cat_id" value="<?php echo $cat['category_id']; ?>">
                                    <button type="submit" class="btn-cat-delete">🗑</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="border-top:1px solid #F0F0F0;padding-top:1.5rem;">
                    <div style="font-family:'Nunito Sans',sans-serif;font-weight:700;color:#003C64;margin-bottom:1rem;">+ Nueva categoría</div>
                    <form method="POST" action="course-form.php<?php echo $is_edit?'?id='.$course_id:''; ?>">
                        <input type="hidden" name="action" value="add_category">
                        <div class="field-row">
                            <div class="field-group"><label class="field-label">Nombre *</label><input type="text" name="cat_name" class="field-input" required placeholder="Ej. Innovación" oninput="autoSlug(this)"></div>
                            <div class="field-group"><label class="field-label">Slug (URL) *</label><input type="text" name="cat_slug" id="newSlug" class="field-input" required placeholder="innovacion"><div class="field-hint">Solo letras minúsculas, números y guiones</div></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label class="field-label">Descripción</label><input type="text" name="cat_desc" class="field-input" placeholder="Breve descripción"></div>
                            <div class="field-group"><label class="field-label">Ícono</label><input type="text" name="cat_icon" class="field-input" value="book" placeholder="brain, code, chart..."></div>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Color</label>
                            <div class="color-options" id="newSwatches">
                                <?php foreach($preset_colors as $clr): ?>
                                <div class="color-swatch <?php echo $clr==='#003C64'?'selected':''; ?>" style="background:<?php echo $clr; ?>;" onclick="pickColor('newCatColor','newSwatches','<?php echo $clr; ?>',this)"></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="color-input-row">
                                <span style="font-size:0.75rem;color:#6B6B6B;">HEX personalizado:</span>
                                <input type="color" name="cat_color" id="newCatColor" value="#003C64" style="width:40px;height:32px;border:1px solid #E0E0E0;border-radius:6px;cursor:pointer;padding:2px;" oninput="clearSwatches('newSwatches')">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>Crear categoría</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<div class="drag-save-indicator" id="dragSaveIndicator">✓ Orden guardado</div>

<!-- Modal editar categoría -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">Editar Categoría</div>
        <form method="POST" action="course-form.php<?php echo $is_edit?'?id='.$course_id:''; ?>">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="cat_id" id="eCatId">
            <div class="field-group"><label class="field-label">Nombre *</label><input type="text" name="cat_name" id="eCatName" class="field-input" required></div>
            <div class="field-group"><label class="field-label">Descripción</label><input type="text" name="cat_desc" id="eCatDesc" class="field-input"></div>
            <div class="field-group"><label class="field-label">Ícono</label><input type="text" name="cat_icon" id="eCatIcon" class="field-input"></div>
            <div class="field-group">
                <label class="field-label">Color</label>
                <div class="color-options" id="editSwatches">
                    <?php foreach($preset_colors as $clr): ?>
                    <div class="color-swatch" style="background:<?php echo $clr; ?>;" onclick="pickColor('eCatColor','editSwatches','<?php echo $clr; ?>',this)"></div>
                    <?php endforeach; ?>
                </div>
                <div class="color-input-row">
                    <span style="font-size:0.75rem;color:#6B6B6B;">HEX:</span>
                    <input type="color" name="cat_color" id="eCatColor" value="#003C64" style="width:40px;height:32px;border:1px solid #E0E0E0;border-radius:6px;cursor:pointer;padding:2px;" oninput="clearSwatches('editSwatches')">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Estado</label>
                <select name="cat_status" id="eCatStatus" class="field-select">
                    <option value="active">Activa</option>
                    <option value="inactive">Inactiva</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModule(id){const b=document.getElementById('body-'+id),ic=document.getElementById('icon-'+id),open=b.style.display!=='none';b.style.display=open?'none':'block';ic.style.transform=open?'rotate(0deg)':'rotate(180deg)';}
document.addEventListener('DOMContentLoaded',function(){const f=document.querySelector('[id^="body-"]'),fi=document.querySelector('[id^="icon-"]');if(f){f.style.display='block';fi.style.transform='rotate(180deg)';}if(window.location.hash){const el=document.querySelector(window.location.hash);if(el)setTimeout(()=>el.scrollIntoView({behavior:'smooth'}),200);}});

function setType(mid,type,btn){
    document.getElementById('ct_'+mid).value=type;
    document.getElementById('cu_'+mid).value='';
    document.querySelectorAll('#typeSelector-'+mid+' .type-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    ['video','link','pdf','image','text'].forEach(t=>{const p=document.getElementById('p_'+t+'_'+mid);if(p)p.classList.toggle('active',t===type);});
}

function switchVideoTab(mid,tab){
    const up=document.getElementById('vUpArea_'+mid),url=document.getElementById('vUrlArea_'+mid);
    const bUp=document.getElementById('vt_up_'+mid),bUrl=document.getElementById('vt_url_'+mid);
    up.style.display=tab==='upload'?'block':'none';
    url.style.display=tab==='url'?'block':'none';
    bUp.classList.toggle('active',tab==='upload');
    bUrl.classList.toggle('active',tab==='url');
    document.getElementById('cu_'+mid).value='';
}

function setCU(mid,val){document.getElementById('cu_'+mid).value=val;}
function setCX(mid,val){document.getElementById('cx_'+mid).value=val;}

function uploadFile(input,mid,ftype){
    const file=input.files[0]; if(!file)return;
    const upDiv=document.getElementById('up_'+ftype+'_'+mid);
    const pb=document.getElementById('pb_'+ftype+'_'+mid);
    const ps=document.getElementById('ps_'+ftype+'_'+mid);
    const fp=document.getElementById('fp_'+ftype+'_'+mid);
    const ua=document.getElementById('ua_'+ftype+'_'+mid);
    upDiv.style.display='block'; fp.classList.remove('show');
    const fd=new FormData(); fd.append('file',file); fd.append('file_type',ftype);
    const xhr=new XMLHttpRequest(); xhr.open('POST','upload_file.php',true);
    xhr.upload.onprogress=function(e){if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);pb.style.width=p+'%';ps.textContent='Subiendo... '+p+'%';}};
    xhr.onload=function(){
        try{const d=JSON.parse(xhr.responseText);
        if(d.success){document.getElementById('cu_'+mid).value=d.url;upDiv.style.display='none';
            document.getElementById('fn_'+ftype+'_'+mid).textContent=d.file_name;
            document.getElementById('fs_'+ftype+'_'+mid).textContent=d.file_size;
            fp.classList.add('show');ua.style.opacity='0.5';}
        else{upDiv.style.display='none';alert('Error: '+d.message);}
        }catch(e){upDiv.style.display='none';alert('Error al procesar la respuesta del servidor.');}
    };
    xhr.onerror=function(){upDiv.style.display='none';alert('Error de conexión.');};
    xhr.send(fd);
}
function removeFile(mid,ftype){
    document.getElementById('cu_'+mid).value='';
    document.getElementById('fp_'+ftype+'_'+mid).classList.remove('show');
    document.getElementById('ua_'+ftype+'_'+mid).style.opacity='1';
}

function fmt(cmd,val=null){document.execCommand(cmd,false,val);}

document.querySelectorAll('.upload-area').forEach(a=>{
    a.addEventListener('dragover',e=>{e.preventDefault();a.classList.add('drag-over');});
    a.addEventListener('dragleave',()=>a.classList.remove('drag-over'));
    a.addEventListener('drop',e=>{e.preventDefault();a.classList.remove('drag-over');const inp=a.querySelector('input[type="file"]');if(inp&&e.dataTransfer.files.length){inp.files=e.dataTransfer.files;inp.dispatchEvent(new Event('change'));}});
});

function autoSlug(el){document.getElementById('newSlug').value=el.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-');}

function uploadThumbnail(input){
    const file=input.files[0]; if(!file)return;
    const wrap=document.getElementById('thumbProgressWrap');
    const bar=document.getElementById('thumbProgressBar');
    const stat=document.getElementById('thumbProgressStatus');
    if(wrap)wrap.style.display='block';
    const fd=new FormData();
    fd.append('file',file); fd.append('context','thumbnail');
    fd.append('file_type','thumbnail'); fd.append('course_id','<?php echo $course_id; ?>');
    const xhr=new XMLHttpRequest();
    xhr.open('POST','upload_file.php',true);
    xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';stat.textContent='Subiendo... '+p+'%';}};
    xhr.onload=()=>{
        const d=JSON.parse(xhr.responseText);
        if(d.success){
            document.getElementById('thumbPreviewBox').innerHTML=`<img src="${d.url}" alt="Thumbnail" style="width:100%;height:100%;object-fit:cover;">`;
            if(wrap)wrap.style.display='none';
            let msg=document.getElementById('thumbCurrentMsg');
            if(!msg){msg=document.createElement('div');msg.id='thumbCurrentMsg';msg.style.cssText='margin-top:0.75rem;font-size:0.8rem;color:#00875A;font-weight:600;';document.getElementById('thumbDropArea').after(msg);}
            msg.textContent='✓ Imagen de portada actualizada.';
        }else{alert('Error: '+d.message);if(wrap)wrap.style.display='none';}
    };
    xhr.send(fd);
}
const _thumbDrop=document.getElementById('thumbDropArea');
if(_thumbDrop){
    _thumbDrop.addEventListener('dragover',e=>{e.preventDefault();_thumbDrop.style.borderColor='#0096DC';_thumbDrop.style.background='#F0F8FF';});
    _thumbDrop.addEventListener('dragleave',()=>{_thumbDrop.style.borderColor='';_thumbDrop.style.background='';});
    _thumbDrop.addEventListener('drop',e=>{e.preventDefault();_thumbDrop.style.borderColor='';_thumbDrop.style.background='';const inp=document.getElementById('thumbInput');inp.files=e.dataTransfer.files;uploadThumbnail(inp);});
}

let cfCurrentType='doc';
let cfQueue=[];
const cfTypeHints={'doc':{icon:'📋',hint:'PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV · Máx 100 MB'},'cf_image':{icon:'🖼️',hint:'JPG, PNG, WebP, GIF · Máx 20 MB'},'cf_video':{icon:'🎬',hint:'MP4, WebM · Máx 500 MB'}};
const cfTypeAccepts={'doc':'.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv','cf_image':'.jpg,.jpeg,.png,.webp,.gif','cf_video':'.mp4,.webm'};

function setCFType(type,btn){
    cfCurrentType=type;
    document.querySelectorAll('#cfTypeTabs .type-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const h=cfTypeHints[type];
    document.getElementById('cfDropIcon').textContent=h.icon;
    document.getElementById('cfDropHint').textContent=h.hint;
    document.getElementById('cfFileInput').accept=cfTypeAccepts[type]||'';
}
function enqueueCFFiles(input){
    Array.from(input.files).forEach(file=>{
        const id='cf_'+Date.now()+'_'+Math.random().toString(36).slice(2);
        cfQueue.push({id,file,type:cfCurrentType,status:'pending'});
    });
    renderCFQueue();
    document.getElementById('cfUploadBtn').style.display=cfQueue.length?'inline-flex':'none';
    input.value='';
}
function renderCFQueue(){
    const c=document.getElementById('cfQueue'); c.innerHTML='';
    cfQueue.forEach(item=>{
        const sz=item.file.size>=1024*1024?(item.file.size/1024/1024).toFixed(1)+' MB':Math.round(item.file.size/1024)+' KB';
        const row=document.createElement('div'); row.className='cf-queued-item'; row.id='cfrow_'+item.id;
        row.innerHTML=`<span style="font-size:1rem;">${cfTypeHints[item.type]?.icon||'📁'}</span>
            <span class="cf-queued-name" title="${item.file.name}">${item.file.name}</span>
            <span style="font-size:0.72rem;color:#8C8C8C;">${sz}</span>
            <div class="cf-queued-bar-wrap"><div class="cf-queued-bar"><div class="cf-queued-fill" id="cfbar_${item.id}"></div></div></div>
            <span class="cf-queued-pct" id="cfpct_${item.id}">0%</span>
            <span class="cf-queued-status" id="cfstat_${item.id}" style="display:none;">✓</span>
            ${item.status==='pending'?`<button type="button" class="cf-queued-remove" onclick="removeCFItem('${item.id}')">✕</button>`:''}`;
        c.appendChild(row);
    });
}
function removeCFItem(id){cfQueue=cfQueue.filter(i=>i.id!==id);renderCFQueue();document.getElementById('cfUploadBtn').style.display=cfQueue.length?'inline-flex':'none';}
async function uploadCFQueue(){
    const desc=document.getElementById('cfDescription').value;
    const pending=cfQueue.filter(i=>i.status==='pending');
    if(!pending.length)return;
    document.getElementById('cfUploadBtn').disabled=true;
    for(const item of pending){
        item.status='uploading';
        const fd=new FormData();
        fd.append('file',item.file); fd.append('context','course_file');
        fd.append('file_type',item.type); fd.append('course_id','<?php echo $course_id; ?>');
        fd.append('description',desc);
        await new Promise(resolve=>{
            const xhr=new XMLHttpRequest(); xhr.open('POST','upload_file.php',true);
            xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);const b=document.getElementById('cfbar_'+item.id);const t=document.getElementById('cfpct_'+item.id);if(b)b.style.width=p+'%';if(t)t.textContent=p+'%';}};
            xhr.onload=()=>{
                try{const d=JSON.parse(xhr.responseText);const s=document.getElementById('cfstat_'+item.id);
                if(d.success){item.status='done';if(s){s.style.display='inline';s.textContent='✓ Subido';}}
                else{item.status='error';if(s){s.style.display='inline';s.textContent='✗ Error';s.style.color='#C0392B';}}
                }catch(e){} resolve();
            };
            xhr.onerror=()=>{item.status='error';resolve()};
            xhr.send(fd);
        });
    }
    setTimeout(()=>{window.location.href='course-form.php?id=<?php echo $course_id; ?>&msg=saved#course-files';},700);
}
const _cfDrop=document.getElementById('cfDropArea');
if(_cfDrop){
    _cfDrop.addEventListener('dragover',e=>{e.preventDefault();_cfDrop.classList.add('drag-over');});
    _cfDrop.addEventListener('dragleave',()=>_cfDrop.classList.remove('drag-over'));
    _cfDrop.addEventListener('drop',e=>{e.preventDefault();_cfDrop.classList.remove('drag-over');const dt=new DataTransfer();Array.from(e.dataTransfer.files).forEach(f=>dt.items.add(f));const inp=document.getElementById('cfFileInput');inp.files=dt.files;enqueueCFFiles(inp);});
}

function pickColor(inputId,swatchId,color,el){document.getElementById(inputId).value=color;document.querySelectorAll('#'+swatchId+' .color-swatch').forEach(s=>s.classList.remove('selected'));el.classList.add('selected');}
function clearSwatches(id){document.querySelectorAll('#'+id+' .color-swatch').forEach(s=>s.classList.remove('selected'));}

function openEditCat(cat){
    document.getElementById('eCatId').value=cat.category_id;
    document.getElementById('eCatName').value=cat.category_name;
    document.getElementById('eCatDesc').value=cat.description||'';
    document.getElementById('eCatIcon').value=cat.icon||'';
    document.getElementById('eCatColor').value=cat.color||'#003C64';
    document.getElementById('eCatStatus').value=cat.status;
    document.getElementById('editModal').classList.add('active');
}
function closeModal(){document.getElementById('editModal').classList.remove('active');}
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

function deleteSlides(){
    if(!confirm('¿Borrar la presentación? Esta acción no se puede deshacer.'))return;
    fetch('generate_slides.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',course_id:<?php echo $course_id; ?>})})
    .then(r=>r.json()).then(data=>{if(data.success){location.reload();}else{alert('Error al borrar: '+(data.error||'desconocido'));}}).catch(()=>alert('Error de conexión.'));
}
function savePosition(val){
    fetch('generate_slides.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'set_position',course_id:<?php echo $course_id; ?>,position:parseInt(val)})})
    .then(r=>r.json()).then(data=>{if(data.success){const el=document.getElementById('position-saved');el.style.display='inline';setTimeout(()=>el.style.display='none',2000);}});
}
function addTopicRow(){
    const list=document.getElementById('topics-list');
    const row=document.createElement('div');
    row.style.cssText='display:flex;gap:0.5rem;align-items:center;';
    row.innerHTML=`<input type="text" placeholder="Ej. Seguridad en el trabajo..." style="flex:1;padding:0.5rem 0.875rem;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.85rem;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#0099D8'" onblur="this.style.borderColor='#E0E0E0'"><button type="button" onclick="this.parentElement.remove()" style="width:30px;height:30px;background:none;border:1.5px solid #E0E0E0;border-radius:7px;cursor:pointer;color:#999;font-size:1rem;flex-shrink:0;">✕</button>`;
    list.appendChild(row);
    row.querySelector('input').focus();
}
function generateSlides(){
    const btn=document.getElementById('btn-gen-slides');
    const status=document.getElementById('slides-status');
    const inputs=document.querySelectorAll('#topics-list input');
    const topics=[...inputs].map(i=>i.value.trim()).filter(Boolean);
    const courseTitle=document.querySelector('input[name="course_title"]')?.value||'<?php echo addslashes($course['course_title']??''); ?>';
    const description=document.querySelector('textarea[name="course_description"]')?.value||'<?php echo addslashes($course['course_description']??''); ?>';
    const difficulty=document.querySelector('select[name="difficulty_level"]')?.value||'<?php echo $course['difficulty_level']??'beginner'; ?>';
    btn.disabled=true;
    btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" opacity=".25"/><path d="M21 12a9 9 0 00-9-9"/></svg> Generando con IA...';
    status.style.display='block';
    status.innerHTML='<div style="padding:0.875rem 1.25rem;background:#FFF8E1;border-radius:10px;font-size:0.85rem;color:#856404;font-weight:600;">⏳ Gemini está generando tu presentación (puede tardar 15-20 segundos)...</div>';
    fetch('generate_slides.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({course_id:<?php echo $course_id; ?>,course_title:courseTitle,description:description,difficulty:difficulty,topics:topics})})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            status.innerHTML=`<div style="padding:1rem 1.25rem;background:#E5F4FC;border-radius:10px;display:flex;align-items:center;gap:1rem;"><span style="font-size:1.5rem;">✅</span><div><div style="font-weight:700;color:#004976;font-size:0.9rem;">¡Presentación generada! ${data.slides?.length||8} diapositivas listas.</div></div><a href="../course-slides-viewer.php?course_id=<?php echo $course_id; ?>" target="_blank" style="margin-left:auto;padding:0.4rem 0.875rem;background:#004976;color:white;border-radius:8px;font-size:0.78rem;font-weight:700;text-decoration:none;">Ver →</a></div>`;
            btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path d="M5 3l14 9-14 9V3z"/></svg> Regenerar presentación';
        }else{
            status.innerHTML=`<div style="padding:0.875rem 1.25rem;background:#FFF0F0;border-radius:10px;font-size:0.85rem;color:#c0392b;font-weight:600;">❌ Error: ${data.error||'No se pudo generar la presentación.'}</div>`;
            btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path d="M5 3l14 9-14 9V3z"/></svg> Reintentar';
        }
    })
    .catch(()=>{status.innerHTML='<div style="padding:0.875rem 1.25rem;background:#FFF0F0;border-radius:10px;font-size:0.85rem;color:#c0392b;font-weight:600;">❌ Error de conexión. Intenta de nuevo.</div>';btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path d="M5 3l14 9-14 9V3z"/></svg> Reintentar';})
    .finally(()=>{btn.disabled=false;});
}

(function(){
    const COURSE_ID = <?php echo $course_id > 0 ? $course_id : 0; ?>;
    if (!COURSE_ID) return;

    let dragSrc = null;
    let dragType = null;

    function showSaved(){
        const el = document.getElementById('dragSaveIndicator');
        if(!el) return;
        el.classList.add('show');
        setTimeout(()=>el.classList.remove('show'), 2000);
    }

    async function saveOrder(type, ids, moduleId){
        const fd = new FormData();
        fd.append('action','reorder_items');
        fd.append('type', type);
        fd.append('ids', ids.join(','));
        if(moduleId) fd.append('module_id', moduleId);
        const r = await fetch('course-form.php?id='+COURSE_ID, {method:'POST', body:fd});
        const d = await r.json().catch(()=>({ok:false}));
        if(d.ok) showSaved();
    }

    function initModuleDrag(){
        const container = document.getElementById('modules');
        if(!container) return;

        container.addEventListener('dragstart', e=>{
            // no interferir con drag de lección
            if(e.target.closest('.lesson-item[data-lesson-id]')) return;
            const mod = e.target.closest('.module-block[data-module-id]');
            if(!mod) return;
            dragSrc = mod;
            dragType = 'module';
            setTimeout(()=>mod.classList.add('dragging-mod'), 0);
            e.dataTransfer.effectAllowed = 'move';
        });

        container.addEventListener('dragend', e=>{
            if(dragType !== 'module') return;
            document.querySelectorAll('.module-block').forEach(m=>{
                m.classList.remove('dragging-mod');
                m.classList.remove('drag-over-mod');
            });
            const ids = [...document.querySelectorAll('#modules > .module-block[data-module-id]')]
                .map(m=>m.dataset.moduleId);
            saveOrder('module', ids, null);
            dragSrc = null; dragType = null;
        });

        container.addEventListener('dragover', e=>{
            if(dragType !== 'module') return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const target = e.target.closest('.module-block[data-module-id]');
            document.querySelectorAll('.module-block').forEach(m=>m.classList.remove('drag-over-mod'));
            if(target && target !== dragSrc) target.classList.add('drag-over-mod');
        });

        container.addEventListener('drop', e=>{
            if(dragType !== 'module') return;
            e.preventDefault();
            const target = e.target.closest('.module-block[data-module-id]');
            if(!target || target === dragSrc || !dragSrc) return;
            const items = [...document.querySelectorAll('#modules > .module-block[data-module-id]')];
            const srcIdx = items.indexOf(dragSrc);
            const tgtIdx = items.indexOf(target);
            if(srcIdx < tgtIdx) target.after(dragSrc);
            else target.before(dragSrc);
            document.querySelectorAll('#modules > .module-block[data-module-id] .module-number')
                .forEach((nb,i)=>{ nb.textContent = i+1; });
        });
    }

    function initLessonDrag(){
        document.addEventListener('dragstart', e=>{
            if(dragType === 'module') return;
            const les = e.target.closest('.lesson-item[data-lesson-id]');
            if(!les) return;
            dragSrc = les;
            dragType = 'lesson';
            setTimeout(()=>les.classList.add('dragging-les'), 0);
            e.dataTransfer.effectAllowed = 'move';
        });

        document.addEventListener('dragover', e=>{
            if(dragType !== 'lesson') return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const target = e.target.closest('.lesson-item[data-lesson-id]');
            document.querySelectorAll('.lesson-item').forEach(l=>l.classList.remove('drag-over-les'));
            if(target && target !== dragSrc) target.classList.add('drag-over-les');
        });

        document.addEventListener('drop', e=>{
            if(dragType !== 'lesson') return;
            e.preventDefault();
            const target = e.target.closest('.lesson-item[data-lesson-id]');
            if(!target || target === dragSrc || !dragSrc) return;
            const list = target.closest('.lessons-list[data-module-id]');
            if(!list) return;
            const items = [...list.querySelectorAll('.lesson-item[data-lesson-id]')];
            const srcIdx = items.indexOf(dragSrc);
            const tgtIdx = items.indexOf(target);
            if(srcIdx < tgtIdx) target.after(dragSrc);
            else target.before(dragSrc);
        });

        document.addEventListener('dragend', e=>{
            if(dragType !== 'lesson') return;
            document.querySelectorAll('.lesson-item').forEach(l=>{
                l.classList.remove('dragging-les');
                l.classList.remove('drag-over-les');
            });
            const parentList = dragSrc ? dragSrc.closest('.lessons-list[data-module-id]') : null;
            if(parentList){
                const modId = parentList.dataset.moduleId;
                const ids = [...parentList.querySelectorAll('.lesson-item[data-lesson-id]')]
                    .map(l=>l.dataset.lessonId);
                saveOrder('lesson', ids, modId);
            }
            dragSrc = null; dragType = null;
        });
    }

    document.addEventListener('DOMContentLoaded', ()=>{
        initModuleDrag();
        initLessonDrag();
    });
})();
</script>

<!-- ── Quiz Builder ──────────────────────────────────────────────── -->
<script src="../js/quiz-builder.js?v=1.01"></script>
</body>
</html>