<?php

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$feedback = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_username') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');

        if (strlen($first_name) < 2 || strlen($last_name) < 2) {
            $feedback = ['type' => 'error', 'form' => 'name', 'msg' => 'Nombre y apellido deben tener al menos 2 caracteres.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $first_name, $last_name, $user_id);

            if ($stmt->execute()) {
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $feedback = ['type' => 'success', 'form' => 'name', 'msg' => 'Nombre actualizado correctamente.'];
            } else {
                $feedback = ['type' => 'error', 'form' => 'name', 'msg' => 'Error al actualizar el nombre.'];
            }
            $stmt->close();
        }
    }

    if ($action === 'update_password') {
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw     = $_POST['new_password']     ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        if (strlen($new_pw) < 8) {
            $feedback = ['type' => 'error', 'form' => 'pw', 'msg' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
        } elseif ($new_pw !== $confirm_pw) {
            $feedback = ['type' => 'error', 'form' => 'pw', 'msg' => 'Las contraseñas nuevas no coinciden.'];
        } else {
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($current_pw, $row['password_hash'])) {
                $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed, $user_id);

                if ($stmt->execute()) {
                    $feedback = ['type' => 'success', 'form' => 'pw', 'msg' => 'Contraseña actualizada correctamente.'];
                } else {
                    $feedback = ['type' => 'error', 'form' => 'pw', 'msg' => 'Error al actualizar la contraseña.'];
                }
                $stmt->close();
            } else {
                $feedback = ['type' => 'error', 'form' => 'pw', 'msg' => 'La contraseña actual es incorrecta.'];
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT first_name, last_name, email, department, role, created_at, last_login
    FROM users
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_name = $_SESSION['user_name'] ?? ($user['first_name'] . ' ' . $user['last_name']);

$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM course_enrollments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_enrolled = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM course_enrollments WHERE user_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_completed = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT ROUND(AVG(progress_percentage), 0) AS avg_p FROM course_enrollments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_progress = (int)($stmt->get_result()->fetch_assoc()['avg_p'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM lesson_progress WHERE user_id = ? AND is_completed = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$lessons_done = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT MAX(last_accessed) AS last_act FROM course_enrollments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$last_act_raw  = $stmt->get_result()->fetch_assoc()['last_act'];
$last_activity = $last_act_raw ? date('d/m/Y', strtotime($last_act_raw)) : 'Sin actividad';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Whirlpool Learning</title>

    <link rel="preconnect" href="https:
    <link rel="preconnect" href="https:
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
    <link rel="stylesheet" href="css/notifications.css">
    <link href="https:

    <style>
        :root {
            --blue-accomplished : #003C64;
            --blue-accent       : #0096DC;
            --blue-light        : #E6F0FA;
            --black             : #141414;
            --neutral-50        : #F9FAFB;
            --neutral-100       : #F3F4F6;
            --neutral-200       : #E5E7EB;
            --neutral-400       : #9CA3AF;
            --neutral-600       : #4B5563;
            --neutral-700       : #374151;
            --white             : #FFFFFF;
            --success           : #16A34A;
            --error             : #DC2626;
            --radius-md         : 8px;
            --radius-lg         : 12px;
            --radius-xl         : 16px;
            --shadow-sm         : 0 1px 3px rgba(0,0,0,.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Open Sans', sans-serif;
            background: var(--neutral-100);
            color: var(--black);
            min-height: 100vh;
        }

        h1, h2, h3 { font-family: 'Nunito Sans', sans-serif; }

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
            padding: 1rem 2rem;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 2rem;
        }

        .nav-brand { display: flex; align-items: center; }

        .brand-logo { height: 36px; width: auto; object-fit: contain; }

        .nav-menu { display: flex; gap: 2rem; justify-content: center; }

        .nav-link {
            color: var(--neutral-600);
            text-decoration: none;
            font-weight: 500;
            padding: .5rem 1rem;
            border-radius: var(--radius-md);
            transition: background .2s, color .2s;
            font-size: .95rem;
        }

        .nav-link:hover  { background: var(--blue-light); color: var(--blue-accent); }
        .nav-link.active { background: var(--blue-light); color: var(--blue-accomplished); font-weight: 600; }

        .nav-icons {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .nav-user {
            position: relative;
            cursor: pointer;
        }

        .nav-user .user-name {
            color: var(--blue-accomplished);
            font-weight: 600;
            font-size: .95rem;
            padding: .4rem .75rem;
            border-radius: var(--radius-md);
            transition: background .2s;
            user-select: none;
        }

        .nav-user:hover .user-name { background: var(--blue-light); }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 25px rgba(0,0,0,.12);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: opacity .2s, transform .2s, visibility .2s;
            overflow: hidden;
        }

        .nav-user:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: block;
            padding: .75rem 1.25rem;
            color: var(--neutral-700);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: background .15s;
        }

        .user-dropdown a:hover { background: var(--blue-light); color: var(--blue-accomplished); }

        .input-eye-wrap { position: relative; }
        .input-eye-wrap input { padding-right: 2.75rem; }

        .eye-btn {
            position: absolute;
            right: .75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            color: var(--neutral-400);
            display: flex;
            align-items: center;
            transition: color .2s;
            line-height: 0;
        }

        .eye-btn:hover { color: var(--blue-accomplished); }

        .page-wrapper {
            max-width: 1100px;
            margin: 2.5rem auto;
            padding: 5rem 1.5rem 2rem;
            display: grid;
            gap: 1.5rem;
        }

        .page-header h1 {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--blue-accomplished);
        }

        .page-header p {
            color: var(--neutral-600);
            font-size: .9rem;
            margin-top: .25rem;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 1.75rem 2rem;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--blue-accomplished);
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .card-title svg {
            width: 18px;
            height: 18px;
            color: var(--blue-accent);
            flex-shrink: 0;
        }

        .identity-card {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            flex-wrap: wrap;
        }

        .avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue-accomplished), var(--blue-accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-family: 'Nunito Sans', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .identity-info h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--blue-accomplished);
        }

        .identity-info p {
            color: var(--neutral-600);
            font-size: .9rem;
            margin-top: .25rem;
        }

        .badge-role {
            display: inline-block;
            margin-top: .5rem;
            padding: .25rem .75rem;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 700;
            background: var(--blue-light);
            color: var(--blue-accomplished);
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .member-since {
            margin-left: auto;
            text-align: right;
            font-size: .82rem;
            color: var(--neutral-400);
            line-height: 1.8;
        }

        .member-since span {
            display: block;
            font-size: .95rem;
            font-weight: 600;
            color: var(--neutral-700);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
        }

        .stat-item {
            background: var(--neutral-50);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1rem;
            text-align: center;
        }

        .stat-value {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--blue-accomplished);
            line-height: 1;
        }

        .stat-value.accent { color: var(--blue-accent); }

        .stat-label {
            font-size: .8rem;
            color: var(--neutral-600);
            margin-top: .4rem;
            font-weight: 500;
        }

        .progress-bar-bg {
            background: var(--neutral-200);
            border-radius: 999px;
            height: 6px;
            overflow: hidden;
            margin-top: .7rem;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue-accomplished), var(--blue-accent));
            border-radius: 999px;
        }

        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group { margin-bottom: 1rem; }

        .form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: .4rem;
        }

        .form-group input {
            width: 100%;
            padding: .65rem .9rem;
            border: 1.5px solid var(--neutral-200);
            border-radius: var(--radius-md);
            font-family: 'Open Sans', sans-serif;
            font-size: .9rem;
            color: var(--black);
            background: var(--white);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 3px rgba(0,150,220,.12);
        }

        .form-group .hint {
            font-size: .75rem;
            color: var(--neutral-400);
            margin-top: .3rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: var(--blue-accomplished);
            color: var(--white);
            border: none;
            padding: .65rem 1.5rem;
            border-radius: var(--radius-md);
            font-family: 'Nunito Sans', sans-serif;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: .5rem;
        }

        .btn-primary:hover  { background: #002a47; }
        .btn-primary:active { transform: scale(.98); }

        .alert {
            padding: .85rem 1rem;
            border-radius: var(--radius-md);
            font-size: .88rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .alert-success { background: #DCFCE7; color: var(--success); border: 1px solid #BBF7D0; }
        .alert-error   { background: #FEE2E2; color: var(--error);   border: 1px solid #FECACA; }

        @media (max-width: 640px) {
            .identity-card { flex-direction: column; align-items: flex-start; }
            .member-since  { margin-left: 0; text-align: left; }
            .form-row      { grid-template-columns: 1fr; }
            .topbar-nav    { gap: .8rem; }
        }

        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .nav-menu.open { display: flex; }
            .nav-hamburger { display: flex; }
        }
    </style>
</head>
<body>

<?php
    $user_name_nav = $_SESSION['user_name'] ?? ($user['first_name'] . ' ' . $user['last_name']);
    $is_admin      = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>
<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="brand-logo">
        </div>
        <div class="nav-menu" id="navMenu">
            <a href="news.php" class="nav-link">Noticias</a>
            <a href="courses.php" class="nav-link">Cursos</a>
            <a href="blog.php" class="nav-link">Comunidad</a>
            <a href="friends.php" class="nav-link">Amigos</a>
        </div>
        <div class="nav-actions">
            <!-- Campana y dark mode: contenedor independiente -->
            <div class="nav-icons">
                <!-- Campana de notificaciones (el widget detecta id=notif-bell y no duplica) -->
                <div id="notif-bell" class="notif-bell-wrapper">
                    <button class="notif-bell-btn" id="notifBellBtn" aria-label="Notificaciones">
                        <svg xmlns="http:
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown" style="display:none">
                        <div class="notif-header">
                            <span>Notificaciones</span>
                            <button class="notif-mark-all" id="notifMarkAll">Marcar todo como leído</button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <p class="notif-empty">Cargando…</p>
                        </div>
                    </div>
                </div>
                <!-- Botón dark mode -->
                <button id="themeToggle" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
                    <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         style="display:none;">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>
            </div>

            <!-- Menú de usuario -->
            <div class="nav-user">
                <div class="user-name">
                    <?php echo htmlspecialchars($user_name_nav); ?>
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
    </div>
</nav>

<main class="page-wrapper">

    <div class="page-header">
        <h1>Mi Perfil</h1>
        <p>Consulta tu actividad y gestiona la información de tu cuenta.</p>
    </div>

    <!-- Tarjeta de identidad -->
    <div class="card">
        <div class="identity-card">
            <div class="avatar"><?= htmlspecialchars($initials ?: '?') ?></div>
            <div class="identity-info">
                <h2><?= htmlspecialchars($display_name) ?></h2>
                <p><?= htmlspecialchars($user['email']) ?></p>
                <?php if ($user['department']): ?>
                    <p><?= htmlspecialchars($user['department']) ?></p>
                <?php endif; ?>
                <span class="badge-role">
                    <?php
                        $roles = ['admin' => 'Administrador', 'instructor' => 'Instructor', 'student' => 'Estudiante'];
                        echo $roles[$user['role']] ?? ucfirst($user['role']);
                    ?>
                </span>
            </div>
            <div class="member-since">
                Miembro desde
                <span><?= date('M Y', strtotime($user['created_at'])) ?></span>
                <?php if ($user['last_login']): ?>
                    Último acceso
                    <span><?= date('d/m/Y', strtotime($user['last_login'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="card">
        <div class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>
            </svg>
            Estadísticas de aprendizaje
        </div>
        <div class="stats-grid">

            <div class="stat-item">
                <div class="stat-value"><?= $total_enrolled ?></div>
                <div class="stat-label">Cursos inscritos</div>
            </div>

            <div class="stat-item">
                <div class="stat-value accent"><?= $total_completed ?></div>
                <div class="stat-label">Cursos completados</div>
            </div>

            <div class="stat-item">
                <div class="stat-value"><?= $lessons_done ?></div>
                <div class="stat-label">Lecciones completadas</div>
            </div>

            <div class="stat-item">
                <div class="stat-value accent"><?= $avg_progress ?>%</div>
                <div class="stat-label">Progreso promedio</div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width:<?= $avg_progress ?>%"></div>
                </div>
            </div>

            <div class="stat-item">
                <div style="font-family:'Nunito Sans',sans-serif;font-size:1.05rem;font-weight:700;color:var(--blue-accomplished);line-height:1.4;">
                    <?= $last_activity ?>
                </div>
                <div class="stat-label">Última actividad</div>
            </div>

        </div>
    </div>

    <!-- Formularios de edición -->
    <div class="forms-grid">

        <!-- Cambiar nombre -->
        <div class="card">
            <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Actualizar nombre
            </div>

            <?php if (!empty($feedback) && $feedback['form'] === 'name'): ?>
                <div class="alert alert-<?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['msg']) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="update_username">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Nombre</label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="<?= htmlspecialchars($user['first_name']) ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="last_name">Apellido</label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="<?= htmlspecialchars($user['last_name']) ?>"
                            required
                        >
                    </div>
                </div>
                <button type="submit" class="btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Guardar nombre
                </button>
            </form>
        </div>

        <!-- Cambiar contraseña -->
        <div class="card">
            <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Cambiar contraseña
            </div>

            <?php if (!empty($feedback) && $feedback['form'] === 'pw'): ?>
                <div class="alert alert-<?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['msg']) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label for="current_password">Contraseña actual</label>
                    <div class="input-eye-wrap">
                        <input type="password" id="current_password" name="current_password" required placeholder="••••••••">
                        <button type="button" class="eye-btn" onclick="toggleEye('current_password', this)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">Nueva contraseña</label>
                    <div class="input-eye-wrap">
                        <input type="password" id="new_password" name="new_password" required placeholder="••••••••">
                        <button type="button" class="eye-btn" onclick="toggleEye('new_password', this)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <p class="hint">Mínimo 8 caracteres.</p>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña</label>
                    <div class="input-eye-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                        <button type="button" class="eye-btn" onclick="toggleEye('confirm_password', this)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Actualizar contraseña
                </button>
            </form>
        </div>

    </div>

</main>

<script>

    function toggleEye(fieldId, btn) {
        var input = document.getElementById(fieldId);
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }

    document.querySelectorAll('.alert').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        }, 5000);
    });
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