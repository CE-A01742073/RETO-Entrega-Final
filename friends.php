<?php

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';

$pending_in = [];
$stmt = $conn->prepare("
    SELECT f.friendship_id, f.requester_id,
           CONCAT(u.first_name,' ',u.last_name) AS requester_name,
           f.created_at
    FROM friendships f
    JOIN users u ON u.user_id = f.requester_id
    WHERE f.addressee_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $pending_in[] = $r;
$stmt->close();

$friends_ids = [];
$stmt = $conn->prepare("
    SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END AS friend_id
    FROM friendships
    WHERE (requester_id = ? OR addressee_id = ?) AND status = 'accepted'
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $friends_ids[] = (int)$r['friend_id'];
$stmt->close();

$sent_pending = [];
$stmt = $conn->prepare("
    SELECT friendship_id, addressee_id FROM friendships
    WHERE requester_id = ? AND status = 'pending'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $sent_pending[(int)$r['addressee_id']] = (int)$r['friendship_id'];
$stmt->close();

$students = [];
$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.role,
        CONCAT(u.first_name,' ',u.last_name) AS full_name,
        u.department,
        u.created_at,
        COUNT(DISTINCT e.course_id)                                         AS enrolled,
        COUNT(DISTINCT CASE WHEN e.status='completed' THEN e.course_id END) AS completed,
        COALESCE(ROUND(AVG(e.progress_percentage),0), 0)                    AS avg_progress,
        COUNT(DISTINCT lp.lesson_id)                                        AS lessons_done
    FROM users u
    LEFT JOIN course_enrollments e  ON e.user_id  = u.user_id
    LEFT JOIN lesson_progress    lp ON lp.user_id = u.user_id AND lp.is_completed = 1
    WHERE u.role IN ('student', 'admin') AND u.user_id != ?
    GROUP BY u.user_id
    ORDER BY full_name ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $students[] = $r;
$stmt->close();

$conn->close();

function getRelation(int $uid, array $friends_ids, array $sent_pending): string
{
    if (in_array($uid, $friends_ids, true)) return 'friends';
    if (isset($sent_pending[$uid]))          return 'sent';
    return 'none';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amigos - Whirlpool Learning</title>
    <link rel="stylesheet" href="/css/styles.css?v=3.92">
    <link rel="stylesheet" href="/css/friends.css?v=1.02.01">
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
                <a href="courses.php" class="nav-link">Cursos</a>
                <a href="blog.php" class="nav-link">Comunidad</a>
                <a href="friends.php" class="nav-link active">Amigos</a>
        </div>
        <div class="nav-user nav-actions">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
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

<main>
    <div class="page-header">
        <h1>Comunidad de la Plataforma</h1>
        <p>Explora el progreso de tus compañeros y conecta con ellos</p>
    </div>

    <!-- Solicitudes pendientes entrantes -->
    <?php if (!empty($pending_in)): ?>
    <div class="requests-banner" id="pending-banner">
        <h3>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Solicitudes de amistad
            <span class="req-badge"><?= count($pending_in) ?></span>
        </h3>
        <?php foreach ($pending_in as $req): ?>
        <div class="request-item" id="req-<?= $req['friendship_id'] ?>">
            <div class="req-avatar"><?= strtoupper(substr($req['requester_name'], 0, 2)) ?></div>
            <div class="req-info">
                <strong><?= htmlspecialchars($req['requester_name']) ?></strong>
                <span><?= date('d/m/Y', strtotime($req['created_at'])) ?></span>
            </div>
            <div class="req-actions">
                <button class="btn-accept" onclick="respondRequest(<?= $req['friendship_id'] ?>, 'accept', this)">Aceptar</button>
                <button class="btn-reject" onclick="respondRequest(<?= $req['friendship_id'] ?>, 'reject', this)">Rechazar</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Búsqueda -->
    <div class="search-wrap">
        <input type="text" class="search-input-friends" id="student-search" placeholder="Buscar estudiante..." oninput="filterStudents()" autocomplete="off">
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="all"     onclick="switchTab(this,'all')">Todos</button>
        <button class="tab-btn"        data-tab="friends" onclick="switchTab(this,'friends')">Mis amigos</button>
    </div>

    <!-- Grid -->
    <div class="students-grid" id="students-grid">
        <?php foreach ($students as $s):
            $rel      = getRelation($s['user_id'], $friends_ids, $sent_pending);
            $initials = strtoupper(substr($s['full_name'], 0, 2));
            $fid      = $sent_pending[$s['user_id']] ?? 0;
        ?>
        <div class="student-card"
             data-name="<?= htmlspecialchars(strtolower($s['full_name'])) ?>"
             data-rel="<?= $rel ?>"
             data-uid="<?= $s['user_id'] ?>"
             data-fid="<?= $fid ?>"
        >
            <div class="sc-top">
                <div class="sc-avatar <?= $rel === 'friends' ? 'friend' : ($s['role'] === 'admin' ? 'admin' : '') ?>"><?= $initials ?></div>
                <div>
                    <div class="sc-name">
                        <?= htmlspecialchars($s['full_name']) ?>
                        <?php if ($s['role'] === 'admin'): ?>
                            <span class="admin-badge">Admin</span>
                        <?php endif; ?>
                    </div>
                    <div class="sc-dept"><?= htmlspecialchars($s['department'] ?? 'Sin departamento') ?></div>
                </div>
            </div>

            <div class="sc-stats">
                <div class="sc-stat">
                    <div class="sc-stat-val"><?= $s['enrolled'] ?></div>
                    <div class="sc-stat-lbl">Cursos<br>inscritos</div>
                </div>
                <div class="sc-stat">
                    <div class="sc-stat-val green"><?= $s['completed'] ?></div>
                    <div class="sc-stat-lbl">Cursos<br>completados</div>
                </div>
                <div class="sc-stat">
                    <div class="sc-stat-val"><?= $s['lessons_done'] ?></div>
                    <div class="sc-stat-lbl">Lecciones<br>hechas</div>
                </div>
            </div>

            <div class="sc-progress-wrap">
                <div class="sc-progress-label">
                    <span>Progreso promedio</span>
                    <span><?= $s['avg_progress'] ?>%</span>
                </div>
                <div class="sc-progress-bar">
                    <div class="sc-progress-fill" style="width:<?= $s['avg_progress'] ?>%"></div>
                </div>
            </div>

            <div class="sc-action">
                <?php if ($rel === 'friends'): ?>
                    <button class="btn-add-friend state-friends" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                        Amigos
                    </button>
                <?php elseif ($rel === 'sent'): ?>
                    <button class="btn-add-friend state-sent"
                            onclick="cancelRequest(this, <?= $s['user_id'] ?>, <?= $fid ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                        Solicitud enviada
                    </button>
                <?php else: ?>
                    <button class="btn-add-friend state-none"
                            onclick="sendRequest(this, <?= $s['user_id'] ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Agregar amigo
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($students)): ?>
        <div class="no-results" style="grid-column:1/-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <h3>No hay otros estudiantes aún</h3>
            <p>Invita a tus compañeros a unirse a la plataforma</p>
        </div>
        <?php endif; ?>
    </div>

    <div id="no-results-msg" style="display:none" class="no-results">
        <h3>Sin resultados</h3>
        <p>Prueba con otro nombre</p>
    </div>
</main>

<div id="fr-toast"></div>

<?php if (isset($_SESSION['user_id'])): ?>
<script src="/js/chatbot-widget.js?v=2.0"></script>
<?php endif; ?>

<script>
const FRIENDS_API = '/api/friends_api.php';

function showToast(msg, dur = 2800) {
    const t = document.getElementById('fr-toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), dur);
}

async function sendRequest(btn, uid) {
    btn.disabled = true;
    try {
        const r = await fetch(FRIENDS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_request', target_id: uid })
        });
        const d = await r.json();
        if (d.success) {
            btn.className = 'btn-add-friend state-sent';
            btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg> Solicitud enviada`;
            btn.onclick = null;
            const card = btn.closest('.student-card');

            showToast('✓ Solicitud enviada');
        } else {
            showToast(d.message || 'Error al enviar solicitud');
            btn.disabled = false;
        }
    } catch {
        showToast('Error de conexión');
        btn.disabled = false;
    }
}

async function cancelRequest(btn, uid, fid) {
    btn.disabled = true;
    try {
        const r = await fetch(FRIENDS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel', friendship_id: fid })
        });
        const d = await r.json();
        if (d.success) {
            btn.className = 'btn-add-friend state-none';
            btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Agregar amigo`;
            btn.onclick = function(){ sendRequest(this, uid); };
            btn.disabled = false;
            showToast('Solicitud cancelada');
        } else {
            showToast(d.message || 'Error');
            btn.disabled = false;
        }
    } catch {
        showToast('Error de conexión');
        btn.disabled = false;
    }
}

async function respondRequest(fid, action, btn) {
    btn.disabled = true;
    try {
        const r = await fetch(FRIENDS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, friendship_id: fid })
        });
        const d = await r.json();
        if (d.success) {
            const item = document.getElementById('req-' + fid);
            item.style.transition = 'opacity .3s';
            item.style.opacity = '0';
            setTimeout(() => {
                item.remove();
                const banner = document.getElementById('pending-banner');
                if (banner && banner.querySelectorAll('.request-item').length === 0) {
                    banner.remove();
                }
                if (action === 'accept') location.reload();
            }, 300);
            showToast(action === 'accept' ? '✓ ¡Ahora son amigos!' : 'Solicitud rechazada');
        } else {
            showToast(d.message || 'Error');
            btn.disabled = false;
        }
    } catch {
        showToast('Error de conexión');
        btn.disabled = false;
    }
}

function filterStudents() {
    const q   = document.getElementById('student-search').value.toLowerCase().trim();
    const tab = document.querySelector('.tab-btn.active').dataset.tab;
    applyFilters(q, tab);
}

function switchTab(tabBtn, tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    tabBtn.classList.add('active');
    const q = document.getElementById('student-search').value.toLowerCase().trim();
    applyFilters(q, tab);
}

function applyFilters(q, tab) {
    const cards = document.querySelectorAll('.student-card');
    let visible = 0;
    cards.forEach(card => {
        const nameMatch = card.dataset.name.includes(q);
        const tabMatch  = tab === 'all' || card.dataset.rel === 'friends';
        const show      = nameMatch && tabMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('no-results-msg').style.display = visible === 0 ? 'block' : 'none';
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