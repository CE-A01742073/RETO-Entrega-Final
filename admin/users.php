<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$admin_name = $_SESSION['user_name'] ?? 'Admin';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    $me = $_SESSION['user_id'];

    if ($action === 'toggle_status' && $user_id) {
        $cur = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
        $cur->bind_param("i", $user_id);
        $cur->execute();
        $cur_status = $cur->get_result()->fetch_assoc()['status'];
        $new_status = ($cur_status === 'active') ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND user_id != ?");
        $stmt->bind_param("sii", $new_status, $user_id, $me);
        $stmt->execute();
        $message = 'Estado del usuario actualizado correctamente.';
        $message_type = 'success';
    } elseif ($action === 'change_role' && $user_id) {
        $new_role = in_array($_POST['new_role'], ['admin','instructor','student']) ? $_POST['new_role'] : 'student';
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ? AND user_id != ?");
        $stmt->bind_param("sii", $new_role, $user_id, $me);
        $stmt->execute();
        $message = 'Rol del usuario actualizado correctamente.';
        $message_type = 'success';
    } elseif ($action === 'delete_user' && $user_id) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $user_id, $me);
        $stmt->execute();
        $message = 'Usuario eliminado correctamente.';
        $message_type = 'success';
    } elseif ($action === 'create_user') {
        // Función local de validación de contraseña
        $cu_first  = trim($_POST['cu_first_name'] ?? '');
        $cu_last   = trim($_POST['cu_last_name']  ?? '');
        $cu_email  = trim(strtolower($_POST['cu_email'] ?? ''));
        $cu_pass   = $_POST['cu_password'] ?? '';
        $cu_pass2  = $_POST['cu_confirm_password'] ?? '';
        $cu_dept   = trim($_POST['cu_department'] ?? '');
        $cu_role   = in_array($_POST['cu_role'] ?? '', ['admin','instructor','student'])
                     ? $_POST['cu_role'] : 'student';

        $allowed_domains = ['whirlpool.com','whirlpool.com.mx','whirlpool.ca'];
        $email_parts = explode('@', $cu_email);
        $domain_ok = (count($email_parts) === 2 && in_array(strtolower($email_parts[1]), $allowed_domains));

        $pass_ok = (strlen($cu_pass) >= 8
                    && preg_match('/[A-Z]/', $cu_pass)
                    && preg_match('/[a-z]/', $cu_pass)
                    && preg_match('/[0-9]/', $cu_pass));

        if (empty($cu_first) || empty($cu_last) || empty($cu_email) || empty($cu_pass) || empty($cu_dept)) {
            $message = 'Todos los campos son obligatorios.';
            $message_type = 'error';
        } elseif (!filter_var($cu_email, FILTER_VALIDATE_EMAIL) || !$domain_ok) {
            $message = 'Debe ser un correo corporativo de Whirlpool (@whirlpool.com, @whirlpool.com.mx, @whirlpool.ca).';
            $message_type = 'error';
        } elseif ($cu_pass !== $cu_pass2) {
            $message = 'Las contraseñas no coinciden.';
            $message_type = 'error';
        } elseif (!$pass_ok) {
            $message = 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula y un número.';
            $message_type = 'error';
        } else {
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $chk->bind_param("s", $cu_email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = 'Este correo electrónico ya está registrado.';
                $message_type = 'error';
            } else {
                $hash = password_hash($cu_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $ins = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $ins->bind_param("ssssss", $cu_first, $cu_last, $cu_email, $hash, $cu_dept, $cu_role);
                if ($ins->execute()) {
                    $message = "Usuario {$cu_first} {$cu_last} creado correctamente.";
                    $message_type = 'success';
                } else {
                    $message = 'Error al crear el usuario. Intente de nuevo.';
                    $message_type = 'error';
                }
                $ins->close();
            }
            $chk->close();
        }
    }
}

// Filters
$search   = trim($_GET['search'] ?? '');
$role_f   = $_GET['role'] ?? '';
$status_f = $_GET['status'] ?? '';
$sort     = $_GET['sort'] ?? 'created_at';
$order    = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

$allowed_sorts = ['first_name','last_name','email','role','created_at','last_login','status','department'];
if (!in_array($sort, $allowed_sorts)) $sort = 'created_at';

$conditions = [];
$bind_types = '';
$bind_params = [];

if ($search !== '') {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR department LIKE ?)";
    $s = "%$search%";
    $bind_types .= 'ssss';
    array_push($bind_params, $s, $s, $s, $s);
}
if ($role_f !== '') {
    $conditions[] = "role = ?";
    $bind_types .= 's';
    $bind_params[] = $role_f;
}
if ($status_f !== '') {
    $conditions[] = "status = ?";
    $bind_types .= 's';
    $bind_params[] = $status_f;
}

$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users $where_sql");
if ($bind_params) { $count_stmt->bind_param($bind_types, ...$bind_params); }
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = max(1, ceil($total_users / $per_page));
$sql = "SELECT user_id, first_name, last_name, email, role, department,
               status, created_at, last_login, profile_image
        FROM users $where_sql
        ORDER BY $sort $order
        LIMIT ? OFFSET ?";
$fetch_stmt = $conn->prepare($sql);
$ft = $bind_types . 'ii';
$fp = array_merge($bind_params, [$per_page, $offset]);
$fetch_stmt->bind_param($ft, ...$fp);
$fetch_stmt->execute();
$users = $fetch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sum_res = $conn->query("SELECT COUNT(*) as total, SUM(role='admin') as admins,
    SUM(role='instructor') as instructors, SUM(role='student') as students,
    SUM(status='active') as active, SUM(status='inactive') as inactive,
    SUM(status='suspended') as suspended, SUM(DATE(created_at)=CURDATE()) as new_today
    FROM users");
$summary = $sum_res->fetch_assoc();

$toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

function sortLink($col, $label, $sort, $toggle_order, $order, $search, $role_f, $status_f) {
    $o = ($sort === $col) ? $toggle_order : 'ASC';
    $icon = ($sort === $col) ? ($order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $qs = http_build_query(['search'=>$search,'role'=>$role_f,'status'=>$status_f,'sort'=>$col,'order'=>$o,'page'=>1]);
    return "<a href=\"?$qs\">$label <i class=\"fas $icon sort-icon\"></i></a>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios — Whirlpool LMS</title>
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue-dark:   #004976;
            --blue-accent: #0099D8;
            --blue-light:  #E5F4FC;
            --blue-mid:    #006BA6;
            --white:       #FFFFFF;
            --gray-50:     #F8FAFC;
            --gray-100:    #F0F4F8;
            --gray-200:    #E2E8F0;
            --gray-300:    #CBD5E1;
            --gray-400:    #94A3B8;
            --gray-500:    #64748B;
            --gray-700:    #334155;
            --gray-900:    #0F172A;
            --green:       #10B981;
            --green-light: #D1FAE5;
            --red:         #EF4444;
            --red-light:   #FEE2E2;
            --amber:       #F59E0B;
            --amber-light: #FEF3C7;
            --orange:      #F97316;
            --orange-light:#FFEDD5;
            --radius-sm:   6px;
            --radius-md:   12px;
            --radius-lg:   16px;
            --shadow-sm:   0 1px 3px rgba(0,0,0,.08);
            --shadow-lg:   0 8px 32px rgba(0,0,0,.14);
        }
        body { font-family:'Open Sans',sans-serif; background:var(--gray-50); color:var(--gray-700); min-height:100vh; }

        .main { margin-left:260px; min-height:100vh; }
        .topbar { background:var(--white); border-bottom:1px solid var(--gray-200); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar-title { font-family:'Nunito Sans',sans-serif; font-size:20px; font-weight:800; color:var(--blue-dark); display:flex; align-items:center; gap:10px; }

        .btn { display:inline-flex; align-items:center; gap:8px; padding:9px 18px; border-radius:var(--radius-sm); font-family:'Open Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; transition:all .2s; white-space:nowrap; }
        .btn-primary { background:var(--blue-accent); color:#fff; }
        .btn-primary:hover { background:var(--blue-mid); }
        .btn-outline { background:transparent; border:1.5px solid var(--gray-300); color:var(--gray-700); }
        .btn-outline:hover { border-color:var(--blue-accent); color:var(--blue-accent); }
        .btn-danger { background:var(--red); color:#fff; }
        .btn-danger:hover { background:#dc2626; }
        .btn-sm { padding:7px 14px; font-size:12px; }

        .content { padding:28px 32px; }

        .alert { padding:14px 18px; border-radius:var(--radius-md); display:flex; align-items:center; gap:10px; font-size:14px; margin-bottom:24px; }
        .alert-success { background:var(--green-light); color:#065F46; }
        .alert-error   { background:var(--red-light);   color:#991B1B; }

        /* Summary */
        .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px; }
        @media(max-width:1200px){ .summary-grid { grid-template-columns:repeat(2,1fr); } }
        .summary-card { background:var(--white); border-radius:var(--radius-md); padding:20px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:16px; }
        .s-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .s-icon-blue   { background:var(--blue-light);   color:var(--blue-accent); }
        .s-icon-green  { background:var(--green-light);  color:var(--green); }
        .s-icon-red    { background:var(--red-light);    color:var(--red); }
        .s-icon-amber  { background:var(--amber-light);  color:var(--amber); }
        .s-info .s-label { font-size:12px; font-weight:600; color:var(--gray-400); margin-bottom:4px; }
        .s-info .s-value { font-family:'Nunito Sans',sans-serif; font-size:26px; font-weight:800; color:var(--gray-900); line-height:1; }
        .s-info .s-sub   { font-size:11px; color:var(--gray-400); margin-top:3px; }

        /* Roles mini */
        .roles-row { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:24px; }
        .role-mini { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-md); padding:14px 18px; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--shadow-sm); }
        .role-mini .rl { font-size:13px; font-weight:600; color:var(--gray-700); display:flex; align-items:center; gap:8px; }
        .role-mini .rv { font-family:'Nunito Sans',sans-serif; font-size:22px; font-weight:800; color:var(--gray-900); }

        /* Filters */
        .filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-md); padding:16px 20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; box-shadow:var(--shadow-sm); }
        .search-wrap { position:relative; flex:1; min-width:220px; }
        .search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:13px; }
        .filter-input { width:100%; padding:8px 12px 8px 32px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); font-size:13px; font-family:'Open Sans',sans-serif; color:var(--gray-700); transition:border-color .2s; }
        .filter-input:focus { outline:none; border-color:var(--blue-accent); }
        .filter-select { padding:8px 12px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); font-size:13px; font-family:'Open Sans',sans-serif; color:var(--gray-700); background:var(--white); transition:border-color .2s; cursor:pointer; }
        .filter-select:focus { outline:none; border-color:var(--blue-accent); }
        .filter-label { font-size:12px; font-weight:600; color:var(--gray-500); white-space:nowrap; }

        /* Table */
        .table-card { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm); }
        .table-header { padding:16px 20px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; }
        .table-header-title { font-family:'Nunito Sans',sans-serif; font-size:15px; font-weight:800; color:var(--gray-900); }
        .table-count { font-size:12px; color:var(--gray-400); margin-left:8px; font-weight:400; }

        table { width:100%; border-collapse:collapse; }
        thead th { padding:11px 16px; text-align:left; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--gray-400); background:var(--gray-50); border-bottom:1px solid var(--gray-200); white-space:nowrap; }
        thead th a { color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
        thead th a:hover { color:var(--blue-accent); }
        .sort-icon { font-size:10px; }
        tbody tr { transition:background .15s; }
        tbody tr:hover { background:var(--gray-50); }
        tbody td { padding:13px 16px; border-bottom:1px solid var(--gray-100); font-size:13.5px; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }

        .user-cell { display:flex; align-items:center; gap:12px; }
        .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; flex-shrink:0; }
        .avatar-ph { width:40px; height:40px; border-radius:50%; background:var(--blue-light); color:var(--blue-accent); display:flex; align-items:center; justify-content:center; font-family:'Nunito Sans',sans-serif; font-size:14px; font-weight:800; flex-shrink:0; }
        .u-name  { font-weight:600; color:var(--gray-900); font-size:14px; }
        .u-email { font-size:12px; color:var(--gray-400); }

        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-admin      { background:#EFF6FF; color:#1D4ED8; }
        .badge-instructor { background:var(--amber-light); color:#92400E; }
        .badge-student    { background:var(--blue-light); color:var(--blue-mid); }
        .badge-active     { background:var(--green-light); color:#059669; }
        .badge-inactive   { background:var(--gray-100); color:var(--gray-500); }
        .badge-suspended  { background:var(--red-light); color:#DC2626; }

        .actions { display:flex; align-items:center; gap:6px; justify-content:center; }
        .action-btn { width:32px; height:32px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; border:none; cursor:pointer; font-size:13px; transition:all .15s; }
        .ab-view      { background:var(--blue-light);  color:var(--blue-accent); }
        .ab-edit      { background:var(--amber-light); color:var(--amber); }
        .ab-toggle-off{ background:var(--red-light);   color:var(--red); }
        .ab-toggle-on { background:var(--green-light); color:var(--green); }
        .ab-delete    { background:var(--red-light);   color:var(--red); }
        .action-btn:hover { opacity:.8; transform:scale(1.08); }

        /* Pagination */
        .pagination { display:flex; align-items:center; padding:14px 20px; border-top:1px solid var(--gray-100); gap:8px; }
        .pag-info { font-size:13px; color:var(--gray-400); flex:1; }
        .page-btns { display:flex; gap:5px; }
        .page-btn { min-width:34px; height:34px; padding:0 10px; border-radius:var(--radius-sm); font-size:13px; font-weight:600; border:1.5px solid var(--gray-200); background:var(--white); color:var(--gray-700); cursor:pointer; transition:all .15s; display:flex; align-items:center; justify-content:center; text-decoration:none; }
        .page-btn:hover { border-color:var(--blue-accent); color:var(--blue-accent); }
        .page-btn.active { background:var(--blue-accent); border-color:var(--blue-accent); color:#fff; }
        .page-btn.disabled { opacity:.4; pointer-events:none; }

        .empty-state { text-align:center; padding:60px 20px; color:var(--gray-400); }
        .empty-state i { font-size:48px; margin-bottom:16px; display:block; }

        /* Modals */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:var(--white); border-radius:var(--radius-lg); width:460px; max-width:95vw; box-shadow:var(--shadow-lg); overflow:hidden; animation:slideUp .2s ease; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:none;opacity:1} }
        .modal-header { padding:20px 24px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; }
        .modal-header h3 { font-family:'Nunito Sans',sans-serif; font-size:17px; font-weight:800; color:var(--gray-900); }
        .modal-close { background:none; border:none; font-size:20px; color:var(--gray-400); cursor:pointer; line-height:1; }
        .modal-body { padding:24px; }
        .modal-footer { padding:16px 24px; border-top:1px solid var(--gray-100); display:flex; justify-content:flex-end; gap:10px; }
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px; }
        .detail-item label { font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--gray-400); display:block; margin-bottom:4px; }
        .detail-item .val { font-size:14px; color:var(--gray-900); font-weight:500; }
        .role-select { width:100%; padding:10px 14px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); font-size:14px; font-family:'Open Sans',sans-serif; color:var(--gray-700); background:var(--white); margin-top:12px; }
        .role-select:focus { outline:none; border-color:var(--blue-accent); }
        .confirm-text { font-size:14px; color:var(--gray-700); line-height:1.6; }
        .confirm-name { font-weight:700; color:var(--gray-900); }

        /* Modal crear usuario */
        .cu-modal { width:520px; }
        .cu-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .cu-group { margin-bottom:14px; }
        .cu-group label { display:block; font-size:12px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--gray-500); margin-bottom:6px; }
        .cu-input, .cu-select { width:100%; padding:9px 13px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); font-size:13.5px; font-family:'Open Sans',sans-serif; color:var(--gray-700); background:var(--white); transition:border-color .2s; }
        .cu-input:focus, .cu-select:focus { outline:none; border-color:var(--blue-accent); }
        .cu-hint { font-size:11px; color:var(--gray-400); margin-top:4px; }
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ACTIVE = "users";
        window.REOPEN_CREATE = <?php echo ($message_type === 'error' && isset($_POST['action']) && $_POST['action'] === 'create_user') ? 'true' : 'false'; ?>;
    </script>
    <script src="js/admin-navbar.js" defer></script>
</head>
<body>
<div class="admin-main">
    <header class="topbar">
        <div class="topbar-title">
            <i class="fas fa-users" style="color:var(--blue-accent);font-size:18px;"></i>
            Gestión de Usuarios
        </div>
        <div style="display:flex;gap:12px;">
            <a href="users_stats.php" class="btn btn-outline">
                <i class="fas fa-chart-pie"></i> Estadísticas
            </a>
            <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>
    </header>

    <div class="content">

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Summary cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="s-icon s-icon-blue"><i class="fas fa-users"></i></div>
                <div class="s-info">
                    <div class="s-label">Total Usuarios</div>
                    <div class="s-value"><?= number_format($summary['total']) ?></div>
                    <div class="s-sub">en la plataforma</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="s-icon s-icon-green"><i class="fas fa-circle-check"></i></div>
                <div class="s-info">
                    <div class="s-label">Activos</div>
                    <div class="s-value"><?= number_format($summary['active']) ?></div>
                    <div class="s-sub"><?= $summary['total'] > 0 ? round(($summary['active']/$summary['total'])*100,1) : 0 ?>% del total</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="s-icon s-icon-red"><i class="fas fa-ban"></i></div>
                <div class="s-info">
                    <div class="s-label">Inactivos / Suspendidos</div>
                    <div class="s-value"><?= number_format($summary['inactive'] + $summary['suspended']) ?></div>
                    <div class="s-sub"><?= $summary['inactive'] ?> inactivos · <?= $summary['suspended'] ?> suspendidos</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="s-icon s-icon-amber"><i class="fas fa-user-plus"></i></div>
                <div class="s-info">
                    <div class="s-label">Nuevos Hoy</div>
                    <div class="s-value"><?= number_format($summary['new_today']) ?></div>
                    <div class="s-sub">registros del día</div>
                </div>
            </div>
        </div>

        <!-- Roles row -->
        <div class="roles-row">
            <div class="role-mini">
                <div class="rl"><i class="fas fa-graduation-cap" style="color:var(--blue-accent)"></i> Estudiantes</div>
                <div class="rv"><?= number_format($summary['students']) ?></div>
            </div>
            <div class="role-mini">
                <div class="rl"><i class="fas fa-chalkboard-user" style="color:var(--amber)"></i> Instructores</div>
                <div class="rv"><?= number_format($summary['instructors']) ?></div>
            </div>
            <div class="role-mini">
                <div class="rl"><i class="fas fa-shield-halved" style="color:#1D4ED8"></i> Administradores</div>
                <div class="rv"><?= number_format($summary['admins']) ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="filter-input"
                    placeholder="Nombre, email, departamento…"
                    value="<?= htmlspecialchars($search) ?>">
            </div>
            <span class="filter-label">Rol:</span>
            <select name="role" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="student"    <?= $role_f==='student'    ?'selected':'' ?>>Estudiante</option>
                <option value="instructor" <?= $role_f==='instructor' ?'selected':'' ?>>Instructor</option>
                <option value="admin"      <?= $role_f==='admin'      ?'selected':'' ?>>Admin</option>
            </select>
            <span class="filter-label">Estado:</span>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="active"    <?= $status_f==='active'    ?'selected':'' ?>>Activo</option>
                <option value="inactive"  <?= $status_f==='inactive'  ?'selected':'' ?>>Inactivo</option>
                <option value="suspended" <?= $status_f==='suspended' ?'selected':'' ?>>Suspendido</option>
            </select>
            <input type="hidden" name="sort"  value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
            <a href="users.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i> Limpiar</a>
        </form>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-header-title">
                    Usuarios <span class="table-count"><?= $total_users ?> resultado<?= $total_users!=1?'s':'' ?></span>
                </div>
            </div>

            <?php if (empty($users)): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <p>No se encontraron usuarios con los filtros aplicados.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= sortLink('first_name','Usuario',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th class="col-hide-mobile"><?= sortLink('department','Departamento',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th><?= sortLink('role','Rol',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th><?= sortLink('status','Estado',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th class="col-hide-mobile"><?= sortLink('last_login','Último acceso',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th class="col-hide-mobile"><?= sortLink('created_at','Registro',$sort,$toggle_order,$order,$search,$role_f,$status_f) ?></th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $full_name = trim($u['first_name'] . ' ' . $u['last_name']);
                    $words = explode(' ', $full_name);
                    $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
                    $is_active = ($u['status'] === 'active');
                    $role_icons = ['admin'=>'shield-halved','instructor'=>'chalkboard-user','student'=>'graduation-cap'];
                    $role_icon = $role_icons[$u['role']] ?? 'user';
                    $status_labels = ['active'=>'Activo','inactive'=>'Inactivo','suspended'=>'Suspendido'];
                ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <?php if (!empty($u['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($u['profile_image']) ?>" class="avatar" alt="">
                            <?php else: ?>
                                <div class="avatar-ph"><?= $initials ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="u-name"><?= htmlspecialchars($full_name) ?></div>
                                <div class="u-email"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="col-hide-mobile" style="color:var(--gray-500)"><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-<?= $u['role'] ?>">
                            <i class="fas fa-<?= $role_icon ?>"></i>
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $u['status'] ?>">
                            <i class="fas fa-circle" style="font-size:7px;"></i>
                            <?= $status_labels[$u['status']] ?? ucfirst($u['status']) ?>
                        </span>
                    </td>
                    <td class="col-hide-mobile" style="color:var(--gray-500);font-size:13px;">
                        <?= $u['last_login']
                            ? date('d/m/Y H:i', strtotime($u['last_login']))
                            : '<span style="color:var(--gray-300)">Nunca</span>' ?>
                    </td>
                    <td class="col-hide-mobile" style="color:var(--gray-500);font-size:13px;">
                        <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="action-btn ab-view" title="Ver detalle"
                                onclick="openViewModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn ab-edit" title="Cambiar rol"
                                onclick="openRoleModal(<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($full_name)) ?>','<?= $u['role'] ?>')">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button class="action-btn <?= $is_active?'ab-toggle-off':'ab-toggle-on' ?>"
                                title="<?= $is_active?'Desactivar':'Activar' ?>"
                                onclick="openToggleModal(<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($full_name)) ?>',<?= $is_active?'true':'false' ?>)">
                                <i class="fas fa-<?= $is_active?'ban':'check' ?>"></i>
                            </button>
                            <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                            <button class="action-btn ab-delete" title="Eliminar"
                                onclick="openDeleteModal(<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($full_name)) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pag-info">
                    Mostrando <?= $offset+1 ?>–<?= min($offset+$per_page,$total_users) ?> de <?= $total_users ?> usuarios
                </div>
                <div class="page-btns">
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"
                       class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"
                       class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>"
                       class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color:var(--blue-accent);margin-right:8px;"></i>Detalle del Usuario</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex;align-items:center;gap:16px;padding-bottom:16px;border-bottom:1px solid var(--gray-100);">
                <div id="vAvatar" class="avatar-ph" style="width:56px;height:56px;font-size:20px;flex-shrink:0;"></div>
                <div>
                    <div id="vName" style="font-family:'Nunito Sans',sans-serif;font-size:17px;font-weight:800;color:var(--gray-900);"></div>
                    <div id="vEmail" style="font-size:13px;color:var(--gray-400);"></div>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><label>Rol</label><div class="val" id="vRole"></div></div>
                <div class="detail-item"><label>Estado</label><div class="val" id="vStatus"></div></div>
                <div class="detail-item"><label>Departamento</label><div class="val" id="vDept"></div></div>
                <div class="detail-item"><label>ID de usuario</label><div class="val" id="vId"></div></div>
                <div class="detail-item"><label>Fecha de registro</label><div class="val" id="vCreated"></div></div>
                <div class="detail-item"><label>Último acceso</label><div class="val" id="vLogin"></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('viewModal')">Cerrar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="roleModal">
    <form method="POST">
        <input type="hidden" name="action" value="change_role">
        <input type="hidden" name="user_id" id="roleUserId">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-pen" style="color:var(--amber);margin-right:8px;"></i>Cambiar Rol</h3>
                <button type="button" class="modal-close" onclick="closeModal('roleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p class="confirm-text">Cambiar el rol de <span id="roleUserName" class="confirm-name"></span>:</p>
                <select name="new_role" id="roleSelect" class="role-select">
                    <option value="student">Estudiante</option>
                    <option value="instructor">Instructor</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('roleModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambio</button>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="toggleModal">
    <form method="POST">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="toggleUserId">
        <div class="modal">
            <div class="modal-header">
                <h3 id="toggleTitle"></h3>
                <button type="button" class="modal-close" onclick="closeModal('toggleModal')">&times;</button>
            </div>
            <div class="modal-body"><p class="confirm-text" id="toggleText"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('toggleModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="toggleBtn"></button>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="deleteModal">
    <form method="POST">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserId">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-trash" style="color:var(--red);margin-right:8px;"></i>Eliminar Usuario</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p class="confirm-text">
                    ¿Eliminar a <span id="deleteUserName" class="confirm-name"></span>?<br><br>
                    <span style="color:var(--red);font-size:13px;"><i class="fas fa-triangle-exclamation"></i> Esta acción es irreversible.</span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancelar</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal: Crear Usuario -->
<div class="modal-overlay" id="createModal">
    <form method="POST">
        <input type="hidden" name="action" value="create_user">
        <div class="modal cu-modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color:var(--blue-accent);margin-right:8px;"></i>Nuevo Usuario</h3>
                <button type="button" class="modal-close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="cu-form-row">
                    <div class="cu-group">
                        <label for="cu_first_name">Nombre</label>
                        <input type="text" id="cu_first_name" name="cu_first_name" class="cu-input" required placeholder="Nombre">
                    </div>
                    <div class="cu-group">
                        <label for="cu_last_name">Apellido</label>
                        <input type="text" id="cu_last_name" name="cu_last_name" class="cu-input" required placeholder="Apellido">
                    </div>
                </div>
                <div class="cu-group">
                    <label for="cu_email">Correo Corporativo</label>
                    <input type="email" id="cu_email" name="cu_email" class="cu-input" required placeholder="nombre@whirlpool.com">
                    <div class="cu-hint">Dominios válidos: @whirlpool.com · @whirlpool.com.mx · @whirlpool.ca</div>
                </div>
                <div class="cu-form-row">
                    <div class="cu-group">
                        <label for="cu_department">Departamento</label>
                        <select id="cu_department" name="cu_department" class="cu-select" required>
                            <option value="">Seleccionar…</option>
                            <option value="IT">Tecnología de la Información</option>
                            <option value="Operations">Operaciones</option>
                            <option value="HR">Recursos Humanos</option>
                            <option value="Finance">Finanzas</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Sales">Ventas</option>
                            <option value="Engineering">Ingeniería</option>
                            <option value="Other">Otro</option>
                        </select>
                    </div>
                    <div class="cu-group">
                        <label for="cu_role">Rol</label>
                        <select id="cu_role" name="cu_role" class="cu-select">
                            <option value="student">Estudiante</option>
                            <option value="instructor">Instructor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="cu-form-row">
                    <div class="cu-group">
                        <label for="cu_password">Contraseña</label>
                        <input type="password" id="cu_password" name="cu_password" class="cu-input" required placeholder="Mín. 8 caracteres">
                        <div class="cu-hint">Mayúscula, minúscula y número requeridos.</div>
                    </div>
                    <div class="cu-group">
                        <label for="cu_confirm_password">Confirmar</label>
                        <input type="password" id="cu_confirm_password" name="cu_confirm_password" class="cu-input" required placeholder="Repite la contraseña">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Crear Usuario</button>
            </div>
        </div>
    </form>
</div>

<script>
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openCreateModal(){ document.getElementById('createModal').classList.add('open'); }

function openViewModal(u){
    const n = (u.first_name+' '+u.last_name).trim();
    const words = n.split(' ');
    const ini = ((words[0]||'')[0]||'') + ((words[1]||'')[0]||'');
    document.getElementById('vAvatar').textContent  = ini.toUpperCase();
    document.getElementById('vName').textContent    = n;
    document.getElementById('vEmail').textContent   = u.email;
    document.getElementById('vRole').textContent    = {admin:'Administrador',instructor:'Instructor',student:'Estudiante'}[u.role]||u.role;
    document.getElementById('vStatus').textContent  = {active:'Activo',inactive:'Inactivo',suspended:'Suspendido'}[u.status]||u.status;
    document.getElementById('vDept').textContent    = u.department||'—';
    document.getElementById('vId').textContent      = '#'+u.user_id;
    document.getElementById('vCreated').textContent = u.created_at ? u.created_at.slice(0,10) : '—';
    document.getElementById('vLogin').textContent   = u.last_login ? u.last_login.slice(0,16).replace('T',' ') : 'Nunca';
    document.getElementById('viewModal').classList.add('open');
}
function openRoleModal(id,name,role){
    document.getElementById('roleUserId').value = id;
    document.getElementById('roleUserName').textContent = name;
    document.getElementById('roleSelect').value = role;
    document.getElementById('roleModal').classList.add('open');
}
function openToggleModal(id,name,isActive){
    document.getElementById('toggleUserId').value = id;
    if(isActive){
        document.getElementById('toggleTitle').innerHTML = '<i class="fas fa-ban" style="color:var(--red);margin-right:8px;"></i>Desactivar Usuario';
        document.getElementById('toggleText').innerHTML  = 'El usuario <span class="confirm-name">'+name+'</span> no podrá acceder a la plataforma.';
        document.getElementById('toggleBtn').textContent = 'Desactivar';
        document.getElementById('toggleBtn').className  = 'btn btn-danger';
    } else {
        document.getElementById('toggleTitle').innerHTML = '<i class="fas fa-check" style="color:var(--green);margin-right:8px;"></i>Activar Usuario';
        document.getElementById('toggleText').innerHTML  = '¿Activar a <span class="confirm-name">'+name+'</span>? Podrá acceder nuevamente.';
        document.getElementById('toggleBtn').textContent = 'Activar';
        document.getElementById('toggleBtn').className  = 'btn btn-primary';
    }
    document.getElementById('toggleModal').classList.add('open');
}
function openDeleteModal(id,name){
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{ if(e.target===o) o.classList.remove('open'); });
});
if(window.REOPEN_CREATE) openCreateModal();
</script>
</body>
</html>