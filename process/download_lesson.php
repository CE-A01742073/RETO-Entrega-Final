<?php

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$lesson_raw = $_GET['lesson_id'] ?? '';
$course_id  = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$is_slides  = ($lesson_raw === 'slides');
$lesson_id  = $is_slides ? 0 : intval($lesson_raw);

if ($course_id === 0 || (!$is_slides && $lesson_id === 0)) {
    http_response_code(400);
    exit('Parámetros inválidos.');
}

$stmt = $conn->prepare("SELECT enrollment_id FROM course_enrollments WHERE user_id = ? AND course_id = ?");
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    exit('No estás inscrito en este curso.');
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT c.course_title, c.slides_json, cat.category_name
    FROM courses c
    INNER JOIN course_categories cat ON c.category_id = cat.category_id
    WHERE c.course_id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$course) { http_response_code(404); exit('Curso no encontrado.'); }

$lesson = null;
if (!$is_slides) {
    $stmt = $conn->prepare("
        SELECT l.*, m.module_title
        FROM course_lessons l
        INNER JOIN course_modules m ON l.module_id = m.module_id
        WHERE l.lesson_id = ? AND m.course_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $course_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lesson) { http_response_code(404); exit('Lección no encontrada.'); }
}

$conn->close();

function urlToDataUri(string $url): ?string {
    if (empty($url)) return null;
    foreach (['youtube.com','youtu.be','vimeo.com'] as $skip) {
        if (strpos($url, $skip) !== false) return null;
    }
    $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'application/octet-stream';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

$download_date = date('d/m/Y H:i');

if ($is_slides) {
    $slides_raw = $course['slides_json'] ?? '';
    if (empty($slides_raw)) { http_response_code(404); exit('Presentación no disponible.'); }

    $slides_decoded = json_decode($slides_raw, true) ?? [];
    $slides_arr = isset($slides_decoded['slides']) ? $slides_decoded['slides'] : (is_array($slides_decoded) ? $slides_decoded : []);
    if (empty($slides_arr)) { http_response_code(404); exit('Presentación vacía.'); }

    $safe_title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $course['course_title']);

    ob_start();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($course['course_title']); ?> — Presentación Offline · Whirlpool Learning</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--blue-dark:#004976;--blue-mid:#0099D8;--blue-light:#E5F4FC;--white:#FFFFFF;--gray-text:#2D2D2D}
html,body{width:100%;height:100%;background:#0a0f1a;font-family:'Segoe UI',Arial,sans-serif;overflow:hidden}

/* Offline ribbon */
.offline-ribbon{
    position:fixed;top:0;left:0;right:0;
    background:rgba(0,73,118,0.92);
    backdrop-filter:blur(8px);
    border-bottom:1px solid rgba(0,153,216,0.35);
    display:flex;align-items:center;justify-content:space-between;
    padding:0.45rem 1.5rem;
    z-index:300;
    font-size:0.74rem;
    color:rgba(255,255,255,0.75);
    gap:1rem;
}
.offline-ribbon .left{display:flex;align-items:center;gap:0.5rem}
.offline-ribbon .left svg{width:13px;height:13px;color:#0099D8;flex-shrink:0}
.offline-ribbon strong{color:white}
.offline-badge-pill{
    background:rgba(0,153,216,0.2);
    border:1px solid rgba(0,153,216,0.4);
    color:#0099D8;
    border-radius:20px;
    padding:0.2rem 0.6rem;
    font-size:0.7rem;
    font-weight:700;
    white-space:nowrap;
    display:flex;align-items:center;gap:0.3rem;
}
.offline-badge-pill svg{width:11px;height:11px}

/* Slider */
.slider{width:100%;height:100vh;position:relative;overflow:hidden;padding-top:34px}

.slide{
    position:absolute;inset:0;
    display:flex;flex-direction:column;
    justify-content:center;align-items:center;
    padding:6vh 8vw 74px;
    opacity:0;transform:translateX(60px);
    transition:opacity 0.45s ease,transform 0.45s ease;
    pointer-events:none;
}
.slide.active{opacity:1;transform:translateX(0);pointer-events:all}
.slide.out{opacity:0;transform:translateX(-60px)}

/* TITLE */
.slide-title{background:linear-gradient(135deg,#004976 0%,#002d4a 50%,#001829 100%);text-align:center}
.slide-title::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 50% at 80% 20%,rgba(0,153,216,.18) 0%,transparent 60%),radial-gradient(ellipse 50% 60% at 10% 80%,rgba(0,73,118,.3) 0%,transparent 60%)}
.slide-title .logo-mark{width:72px;height:4px;background:var(--blue-mid);border-radius:2px;margin:0 auto 2rem;position:relative}
.slide-title h1{font-family:'Segoe UI',Arial,sans-serif;font-size:clamp(2rem,5vw,3.5rem);font-weight:900;color:var(--white);line-height:1.15;max-width:800px;position:relative;letter-spacing:-.02em;margin-bottom:1.5rem}
.slide-title .subtitle{font-size:clamp(1rem,2vw,1.3rem);color:rgba(0,153,216,.9);font-weight:600;max-width:600px;position:relative;line-height:1.5}
.slide-title .course-badge{position:relative;display:inline-flex;align-items:center;gap:.5rem;background:rgba(0,153,216,.15);border:1px solid rgba(0,153,216,.3);color:var(--blue-mid);font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.4rem 1rem;border-radius:99px;margin-bottom:1.5rem}

/* AGENDA */
.slide-agenda{background:var(--white);align-items:flex-start}
.slide-agenda .slide-inner{width:100%;max-width:900px;display:grid;grid-template-columns:1fr 2fr;gap:4vw;align-items:center}
.slide-agenda .left-col{display:flex;flex-direction:column;gap:.75rem}
.slide-agenda .left-col .label{font-size:.7rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--blue-mid)}
.slide-agenda .left-col h2{font-family:'Segoe UI',Arial,sans-serif;font-size:clamp(2rem,4vw,3rem);font-weight:900;color:var(--blue-dark);line-height:1.1;letter-spacing:-.02em}
.slide-agenda .left-col .accent-bar{width:48px;height:4px;background:var(--blue-mid);border-radius:2px}
.agenda-list{display:flex;flex-direction:column;gap:.75rem}
.agenda-item{display:flex;align-items:center;gap:1rem;padding:.875rem 1.25rem;background:var(--blue-light);border-radius:10px;border-left:3px solid var(--blue-mid);font-size:clamp(.85rem,1.5vw,1rem);font-weight:600;color:var(--blue-dark)}
.agenda-num{width:28px;height:28px;background:var(--blue-mid);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;flex-shrink:0}

/* CONTENT */
.slide-content{background:var(--white);align-items:flex-start;justify-content:flex-start;padding-top:7vh}
.slide-content .slide-inner{width:100%;max-width:900px}
.slide-content .slide-header{margin-bottom:2.5rem;padding-bottom:1.25rem;border-bottom:2px solid var(--blue-light);display:flex;align-items:flex-end;gap:1.5rem}
.slide-number-badge{width:48px;height:48px;background:linear-gradient(135deg,var(--blue-dark),var(--blue-mid));border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1.1rem;flex-shrink:0}
.slide-content h2{font-family:'Segoe UI',Arial,sans-serif;font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;color:var(--blue-dark);line-height:1.2;letter-spacing:-.02em}
.points-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.point-card{background:#f8fbfd;border:1.5px solid var(--blue-light);border-radius:12px;padding:1.25rem 1.5rem;display:flex;gap:1rem;align-items:flex-start}
.point-dot{width:8px;height:8px;background:var(--blue-mid);border-radius:50%;margin-top:.4rem;flex-shrink:0}
.point-text{font-size:clamp(.85rem,1.4vw,.95rem);color:var(--gray-text);line-height:1.55}

/* KEYPOINTS */
.slide-keypoints{background:linear-gradient(160deg,var(--blue-dark) 0%,#002d4a 100%);align-items:flex-start;justify-content:flex-start;padding-top:7vh}
.slide-keypoints .slide-inner{width:100%;max-width:900px}
.slide-keypoints h2{font-family:'Segoe UI',Arial,sans-serif;font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;color:var(--white);margin-bottom:2rem;display:flex;align-items:center;gap:1rem}
.slide-keypoints h2 .icon{font-size:1.5rem}
.keypoints-list{display:flex;flex-direction:column;gap:1rem}
.keypoint-item{display:flex;gap:1.25rem;align-items:flex-start;padding:1.25rem 1.5rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px}
.keypoint-icon{width:36px;height:36px;background:var(--blue-mid);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.keypoint-text{font-size:clamp(.9rem,1.5vw,1.05rem);color:rgba(255,255,255,.9);line-height:1.55;padding-top:.1rem}

/* CLOSING */
.slide-closing{background:linear-gradient(135deg,#004976 0%,#0099D8 100%);text-align:center}
.slide-closing::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 50%,rgba(255,255,255,.06) 0%,transparent 70%)}
.slide-closing h1{font-family:'Segoe UI',Arial,sans-serif;font-size:clamp(2.5rem,6vw,4rem);font-weight:900;color:var(--white);position:relative;margin-bottom:1.5rem;letter-spacing:-.02em}
.slide-closing .subtitle{font-size:clamp(1rem,2vw,1.25rem);color:rgba(255,255,255,.85);max-width:600px;position:relative;line-height:1.6}
.slide-closing .whirlpool-brand{position:relative;margin-top:2.5rem;display:flex;align-items:center;gap:.75rem;font-weight:700;font-size:.9rem;letter-spacing:.05em;color:rgba(255,255,255,.6);text-transform:uppercase}
.slide-closing .whirlpool-brand::before,.slide-closing .whirlpool-brand::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.2);max-width:80px}

/* Nav bar */
.nav-bar{position:fixed;bottom:0;left:0;right:0;height:64px;background:rgba(10,15,26,.95);backdrop-filter:blur(12px);border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;z-index:100}
.nav-progress{display:flex;gap:6px;align-items:center}
.nav-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.2);transition:all .3s;cursor:pointer}
.nav-dot.active{background:var(--blue-mid);width:20px;border-radius:3px}
.nav-btn{width:40px;height:40px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:white;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;font-size:1.1rem}
.nav-btn:hover:not(:disabled){background:var(--blue-mid);border-color:var(--blue-mid)}
.nav-btn:disabled{opacity:.3;cursor:not-allowed}
.nav-controls{display:flex;gap:.5rem;align-items:center}
.nav-counter{font-size:.78rem;font-weight:700;color:rgba(255,255,255,.4);min-width:50px;text-align:center}
.progress-bar-top{position:fixed;top:34px;left:0;height:3px;background:linear-gradient(90deg,var(--blue-dark),var(--blue-mid));transition:width .4s ease;z-index:200}
.slide-counter-top{position:fixed;top:calc(34px + 1.25rem);right:1.5rem;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.35);z-index:50;letter-spacing:.05em}
.keyboard-hint{position:fixed;bottom:72px;right:1.5rem;font-size:.68rem;color:rgba(255,255,255,.2)}
</style>
</head>
<body>

<!-- Ribbon offline -->
<div class="offline-ribbon">
    <div class="left">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
        <span><strong><?php echo htmlspecialchars($course['course_title']); ?></strong> — Presentación IA &nbsp;·&nbsp; Descargado el <?php echo $download_date; ?></span>
    </div>
    <div class="offline-badge-pill">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
        Modo Offline
    </div>
</div>

<div class="progress-bar-top" id="progressBar"></div>
<div class="slide-counter-top" id="counterTop"></div>
<div class="keyboard-hint">← → para navegar</div>

<div class="slider" id="slider">
<?php
$slide_num = 0;
foreach ($slides_arr as $i => $slide):
    $type = $slide['type'] ?? 'content';
    $is_content = $type === 'content';
    if ($is_content) $slide_num++;
?>
<div class="slide slide-<?php echo htmlspecialchars($type); ?><?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo $i; ?>">

<?php if ($type === 'title'): ?>
    <div class="course-badge">✦ Whirlpool Training</div>
    <div class="logo-mark"></div>
    <h1><?php echo htmlspecialchars($slide['title'] ?? ''); ?></h1>
    <p class="subtitle"><?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?></p>

<?php elseif ($type === 'agenda'): ?>
    <div class="slide-inner">
        <div class="left-col">
            <span class="label">Contenido</span>
            <h2><?php echo htmlspecialchars($slide['title'] ?? 'Agenda'); ?></h2>
            <div class="accent-bar"></div>
        </div>
        <div class="agenda-list">
            <?php foreach (($slide['points'] ?? []) as $pi => $point): ?>
            <div class="agenda-item">
                <span class="agenda-num"><?php echo $pi + 1; ?></span>
                <?php echo htmlspecialchars($point); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php elseif ($type === 'content'): ?>
    <div class="slide-inner">
        <div class="slide-header">
            <div class="slide-number-badge"><?php echo sprintf('%02d', $slide_num); ?></div>
            <h2><?php echo htmlspecialchars($slide['title'] ?? ''); ?></h2>
        </div>
        <div class="points-grid">
            <?php foreach (($slide['points'] ?? []) as $point): ?>
            <div class="point-card">
                <div class="point-dot"></div>
                <span class="point-text"><?php echo htmlspecialchars($point); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php elseif ($type === 'keypoints'): ?>
    <div class="slide-inner">
        <h2><span class="icon">⭐</span><?php echo htmlspecialchars($slide['title'] ?? 'Puntos Clave'); ?></h2>
        <div class="keypoints-list">
            <?php $kp_icons = ['💡','🎯','🚀','✅','🔑']; ?>
            <?php foreach (($slide['points'] ?? []) as $ki => $point): ?>
            <div class="keypoint-item">
                <div class="keypoint-icon"><?php echo $kp_icons[$ki % count($kp_icons)]; ?></div>
                <span class="keypoint-text"><?php echo htmlspecialchars($point); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php elseif ($type === 'closing'): ?>
    <h1><?php echo htmlspecialchars($slide['title'] ?? '¡Gracias!'); ?></h1>
    <p class="subtitle"><?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?></p>
    <div class="whirlpool-brand">Whirlpool Training</div>

<?php endif; ?>
</div>
<?php endforeach; ?>
</div><!-- /slider -->

<div class="nav-bar">
    <div class="nav-progress" id="navDots"></div>
    <div class="nav-counter" id="navCounter"></div>
    <div class="nav-controls">
        <button class="nav-btn" id="btnPrev" onclick="navigate(-1)" title="Anterior">←</button>
        <button class="nav-btn" id="btnNext" onclick="navigate(1)" title="Siguiente">→</button>
    </div>
</div>

<script>
const total   = <?php echo count($slides_arr); ?>;
let current   = 0;
const slides  = document.querySelectorAll('.slide');
const dotsEl  = document.getElementById('navDots');
const counter = document.getElementById('navCounter');
const counterTop  = document.getElementById('counterTop');
const progressBar = document.getElementById('progressBar');

for (let i = 0; i < total; i++) {
    const d = document.createElement('div');
    d.className = 'nav-dot' + (i === 0 ? ' active' : '');
    d.onclick = () => goTo(i);
    dotsEl.appendChild(d);
}

function updateUI() {
    document.querySelectorAll('.nav-dot').forEach((d,i) => d.classList.toggle('active', i === current));
    counter.textContent    = (current + 1) + ' / ' + total;
    counterTop.textContent = (current + 1) + ' / ' + total;
    progressBar.style.width = ((current + 1) / total * 100) + '%';
    document.getElementById('btnPrev').disabled = current === 0;
    document.getElementById('btnNext').disabled = current === total - 1;
}

function goTo(n) {
    if (n < 0 || n >= total || n === current) return;
    const old = current;
    slides[old].classList.remove('active');
    slides[old].classList.add('out');
    setTimeout(() => slides[old].classList.remove('out'), 450);
    current = n;
    slides[current].classList.add('active');
    updateUI();
}

function navigate(dir) { goTo(current + dir); }

document.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') navigate(1);
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   navigate(-1);
});

let touchX = 0;
document.addEventListener('touchstart', e => touchX = e.touches[0].clientX);
document.addEventListener('touchend',   e => {
    const dx = touchX - e.changedTouches[0].clientX;
    if (Math.abs(dx) > 50) navigate(dx > 0 ? 1 : -1);
});

updateUI();
</script>
</body>
</html>
<?php
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="presentacion_' . $safe_title . '_offline.html"');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: no-store');
    echo $html;
    exit();
}

//  RAMA B: LECCIÓN NORMAL
$ctype = $lesson['content_type'];
$curl  = $lesson['content_url']  ?? '';
$ctext = $lesson['content_text'] ?? '';

$is_youtube = strpos($curl, 'youtube.com') !== false || strpos($curl, 'youtu.be') !== false;
$is_vimeo   = strpos($curl, 'vimeo.com')   !== false;

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

$image_data_uri = ($ctype === 'image' && !empty($curl)) ? urlToDataUri($curl) : null;
$resources      = !empty($lesson['resources']) ? json_decode($lesson['resources'], true) : [];
$safe_title     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $lesson['lesson_title']);

ob_start();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($lesson['lesson_title']); ?> — Offline · Whirlpool Learning</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f8;color:#1a1a1a;line-height:1.7;min-height:100vh}
.hdr{background:linear-gradient(135deg,#004976 0%,#0099D8 100%);color:#fff;padding:1.5rem 2rem;display:flex;align-items:center;gap:1.25rem;box-shadow:0 2px 12px rgba(0,73,118,.25)}
.logo{width:44px;height:44px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.logo svg{width:26px;height:26px}
.hdr-text{flex:1}
.breadcrumb{font-size:.75rem;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem}
.hdr-text h1{font-size:1.25rem;font-weight:700;line-height:1.3}
.badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:20px;padding:.3rem .75rem;font-size:.74rem;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:.35rem}
.meta{background:#fff;border-bottom:1px solid #e5e9ef;padding:.6rem 2rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;font-size:.8rem;color:#555}
.mi{display:flex;align-items:center;gap:.35rem}
.mi svg{width:14px;height:14px;opacity:.6}
.pill{background:#e6f3fb;color:#004976;border-radius:12px;padding:.2rem .65rem;font-weight:600;font-size:.74rem}
.wrap{max-width:900px;margin:2rem auto;padding:0 1.25rem 3rem}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.07);overflow:hidden}
.body{padding:2rem 2.25rem}
.desc{color:#4a5568;font-size:.93rem;padding:.85rem 1rem;background:#f7fafc;border-left:3px solid #0099D8;border-radius:0 8px 8px 0;margin-bottom:1.75rem}
.vcard{background:#0a0a0f;border-radius:10px;padding:2.5rem 1.5rem;text-align:center;color:#fff}
.pi{width:64px;height:64px;background:rgba(0,153,216,.2);border:2px solid rgba(0,153,216,.45);border-radius:50%;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center}
.pi svg{width:28px;height:28px}
.vcard h3{font-size:1rem;font-weight:600;margin-bottom:.4rem}
.vcard p{font-size:.85rem;opacity:.65;margin-bottom:1.25rem}
video{width:100%;border-radius:10px;background:#000;display:block;max-height:520px}
.btn-go{display:inline-flex;align-items:center;gap:.4rem;background:#0099D8;color:#fff;padding:.55rem 1.25rem;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600}
.btn-pdf{display:inline-flex;align-items:center;gap:.4rem;background:#004976;color:#fff;padding:.5rem 1.1rem;border-radius:7px;text-decoration:none;font-size:.82rem;font-weight:600;margin-top:.75rem}
.pnote{display:flex;align-items:flex-start;gap:1rem;padding:1.25rem 1.5rem;background:#f7fafc;border:1.5px solid #e2e8f0;border-radius:10px}
.pe{font-size:2.5rem;flex-shrink:0}
.pnote h3{font-size:.95rem;font-weight:700;color:#004976;margin-bottom:.25rem}
.pnote p{font-size:.82rem;color:#666}
.img-wrap img{max-width:100%;border-radius:10px;display:block;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.1)}
.rt{font-size:.95rem;line-height:1.85;color:#2d3748}
.rt h1,.rt h2,.rt h3,.rt h4{color:#004976;margin:1.5rem 0 .5rem;line-height:1.3}
.rt h2{font-size:1.15rem}.rt h3{font-size:1rem}
.rt p{margin-bottom:.75rem}
.rt ul,.rt ol{padding-left:1.6rem;margin:.5rem 0 1rem}
.rt li{margin-bottom:.35rem}
.rt strong,.rt b{color:#1a202c}
.rt a{color:#0099D8}
.rt blockquote{border-left:3px solid #0099D8;padding:.5rem 1rem;background:#f7fafc;border-radius:0 6px 6px 0;margin:.75rem 0;color:#555}
.rt code{background:#edf2f7;padding:.15em .4em;border-radius:4px;font-size:.88em;color:#d53f8c}
.rt pre{background:#1a202c;color:#e2e8f0;padding:1rem 1.25rem;border-radius:8px;overflow-x:auto;font-size:.87rem;margin:1rem 0}
.rt table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.88rem}
.rt th{background:#004976;color:#fff;padding:.5rem .75rem;text-align:left}
.rt td{padding:.45rem .75rem;border-bottom:1px solid #e2e8f0}
.rt tr:nth-child(even) td{background:#f7fafc}
.lnk{text-align:center;padding:2rem 1.5rem}
.le{font-size:2.5rem;margin-bottom:.75rem}
.lnk h3{color:#004976;font-size:1rem;margin-bottom:.5rem}
.lnk p{color:#666;font-size:.87rem;margin-bottom:1rem}
.lurl{display:block;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.6rem 1rem;font-size:.82rem;color:#0099D8;word-break:break-all;text-decoration:none;margin-bottom:1rem}
.res-sec{margin-top:1.75rem;padding-top:1.5rem;border-top:1px solid #e2e8f0}
.res-sec h3{font-size:.875rem;font-weight:700;color:#004976;margin-bottom:.85rem;display:flex;align-items:center;gap:.4rem}
.res-list{display:flex;flex-direction:column;gap:.5rem}
.ri{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem}
.ri svg{width:16px;height:16px;color:#0099D8;flex-shrink:0}
.ri span{flex:1}
.ri a{color:#0099D8;font-size:.8rem;text-decoration:none;margin-left:auto}
.notice{margin-top:1.5rem;padding:.85rem 1.25rem;background:#fffbeb;border:1px solid #f6cc52;border-radius:10px;font-size:.8rem;color:#744210;display:flex;align-items:flex-start;gap:.5rem}
.notice svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.ph{text-align:center;padding:2rem;color:#888}
.ph .em{font-size:2.5rem}
.foot{text-align:center;font-size:.75rem;color:#a0aec0;margin-top:2.5rem;padding-top:1rem;border-top:1px solid #e2e8f0}
@media(max-width:600px){.hdr{padding:1rem;flex-wrap:wrap}.body{padding:1.25rem}.wrap{padding:0 .75rem 2rem}.meta{padding:.6rem 1rem}}
@media print{body{background:#fff}.card{box-shadow:none}.badge{display:none}}
</style>
</head>
<body>
<header class="hdr">
  <div class="logo">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#004976" stroke-width="2.5"/><circle cx="12" cy="12" r="4" fill="#0099D8"/></svg>
  </div>
  <div class="hdr-text">
    <div class="breadcrumb"><?php echo htmlspecialchars($course['category_name']); ?> › <?php echo htmlspecialchars($course['course_title']); ?> › <?php echo htmlspecialchars($lesson['module_title']); ?></div>
    <h1><?php echo htmlspecialchars($lesson['lesson_title']); ?></h1>
  </div>
  <div class="badge">
    <svg viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
    Modo Offline
  </div>
</header>
<div class="meta">
  <div class="mi"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zM4 8h12v8H4V8z"/></svg>Descargado el <?php echo $download_date; ?></div>
  <?php if ($lesson['duration_minutes'] > 0): ?>
  <div class="mi"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/></svg><?php echo $lesson['duration_minutes']; ?> min</div>
  <?php endif; ?>
  <div class="mi"><span class="pill"><?php echo ucfirst(htmlspecialchars($ctype)); ?></span></div>
</div>
<div class="wrap"><div class="card"><div class="body">

<?php if (!empty($lesson['lesson_description'])): ?>
<p class="desc"><?php echo htmlspecialchars($lesson['lesson_description']); ?></p>
<?php endif; ?>

<?php if ($ctype === 'video'): ?>
  <?php if ($is_youtube && $youtube_id): ?>
  <div class="vcard">
    <div class="pi"><svg viewBox="0 0 24 24" fill="#0099D8"><path d="M8 5v14l11-7z"/></svg></div>
    <h3>Video de YouTube</h3><p>Requiere conexión a internet.</p>
    <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($youtube_id); ?>" target="_blank" class="btn-go">
      <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>Ver en YouTube
    </a>
    <p style="margin-top:.75rem;font-size:.73rem;opacity:.4">youtu.be/<?php echo htmlspecialchars($youtube_id); ?></p>
  </div>
  <?php elseif ($is_vimeo && $vimeo_id): ?>
  <div class="vcard">
    <div class="pi"><svg viewBox="0 0 24 24" fill="#0099D8"><path d="M8 5v14l11-7z"/></svg></div>
    <h3>Video de Vimeo</h3><p>Requiere conexión a internet.</p>
    <a href="https://vimeo.com/<?php echo htmlspecialchars($vimeo_id); ?>" target="_blank" class="btn-go">Ver en Vimeo</a>
  </div>
  <?php elseif (!empty($curl)): ?>
  <video controls><source src="<?php echo htmlspecialchars($curl); ?>" type="video/mp4">Tu navegador no soporta video HTML5.</video>
  <p style="font-size:.78rem;color:#888;margin-top:.5rem;text-align:center">⚠️ El archivo de video debe estar accesible desde este dispositivo.</p>
  <?php else: ?>
  <div class="ph"><div class="em">🎬</div><p>No hay video disponible.</p></div>
  <?php endif; ?>

<?php elseif ($ctype === 'pdf'): ?>
  <?php if (!empty($curl)): ?>
  <div class="pnote">
    <div class="pe">📄</div>
    <div>
      <h3>Documento PDF</h3><p>Guarda el PDF en tu dispositivo para acceso sin conexión.</p>
      <a href="<?php echo htmlspecialchars($curl); ?>" class="btn-pdf" download>
        <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/></svg>
        Descargar / Abrir PDF
      </a>
      <p style="margin-top:.5rem;font-size:.73rem;color:#aaa;word-break:break-all"><?php echo htmlspecialchars($curl); ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="ph"><div class="em">📄</div><p>No hay PDF disponible.</p></div>
  <?php endif; ?>

<?php elseif ($ctype === 'image'): ?>
  <?php if ($image_data_uri): ?>
  <div class="img-wrap">
    <img src="<?php echo $image_data_uri; ?>" alt="<?php echo htmlspecialchars($lesson['lesson_title']); ?>">
    <p style="text-align:center;font-size:.75rem;color:#aaa;margin-top:.5rem">✅ Imagen embebida — disponible sin conexión</p>
  </div>
  <?php elseif (!empty($curl)): ?>
  <div class="img-wrap">
    <img src="<?php echo htmlspecialchars($curl); ?>" alt="<?php echo htmlspecialchars($lesson['lesson_title']); ?>">
    <p style="text-align:center;font-size:.75rem;color:#888;margin-top:.5rem">⚠️ La imagen requiere conexión.</p>
  </div>
  <?php else: ?>
  <div class="ph"><div class="em">🖼️</div><p>No hay imagen disponible.</p></div>
  <?php endif; ?>

<?php elseif ($ctype === 'text'): ?>
  <div class="rt">
    <?php if (!empty($ctext)): echo $ctext;
    elseif (!empty($curl)): echo nl2br(htmlspecialchars($curl));
    else: ?><div class="ph"><div class="em">📝</div><p>Sin contenido de texto.</p></div><?php endif; ?>
  </div>

<?php elseif ($ctype === 'link'): ?>
  <?php if (!empty($curl)): ?>
  <div class="lnk">
    <div class="le">🔗</div><h3>Recurso externo</h3><p>Requiere conexión a internet.</p>
    <a href="<?php echo htmlspecialchars($curl); ?>" class="lurl" target="_blank"><?php echo htmlspecialchars($curl); ?></a>
    <a href="<?php echo htmlspecialchars($curl); ?>" target="_blank" class="btn-go">
      <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
      Abrir recurso
    </a>
  </div>
  <?php else: ?>
  <div class="ph"><div class="em">🔗</div><p>No hay enlace disponible.</p></div>
  <?php endif; ?>

<?php else: ?>
  <div class="ph"><div class="em">📚</div><p>Tipo de contenido no soportado.</p></div>
<?php endif; ?>

<?php if (!empty($resources)): ?>
<div class="res-sec">
  <h3><svg viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px"><path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z"/></svg>Recursos adicionales</h3>
  <div class="res-list">
    <?php foreach ($resources as $res): ?>
    <div class="ri">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
      <span><?php echo htmlspecialchars($res['name'] ?? 'Recurso'); ?></span>
      <?php if (!empty($res['url'])): ?><a href="<?php echo htmlspecialchars($res['url']); ?>" target="_blank">Abrir</a><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="notice">
  <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/></svg>
  <span>Copia offline de <strong><?php echo htmlspecialchars($lesson['lesson_title']); ?></strong> — <?php echo htmlspecialchars($course['course_title']); ?>. El progreso <strong>no se actualiza</strong> offline.</span>
</div>

</div></div>
<div class="foot">Whirlpool Learning &nbsp;·&nbsp; <?php echo $download_date; ?> &nbsp;·&nbsp; Solo para uso personal del empleado</div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="leccion_' . $safe_title . '_offline.html"');
header('Content-Length: ' . strlen($html));
header('Cache-Control: no-store');
echo $html;
exit();