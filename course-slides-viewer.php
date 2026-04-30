<?php

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit('Acceso denegado.');
}

$course_id = intval($_GET['course_id'] ?? 0);
if (!$course_id) { http_response_code(404); exit('Curso no encontrado.'); }

$stmt = $conn->prepare("SELECT course_title, slides_json FROM courses WHERE course_id = ?");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$course || !$course['slides_json']) { http_response_code(404); exit('Presentación no disponible.'); }

$slides = json_decode($course['slides_json'], true) ?? [];
$title  = htmlspecialchars($course['course_title']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?> — Presentación</title>
<link rel="stylesheet" href="css/styles.css?v=4.22.02">
<link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
<link rel="stylesheet" href="css/notifications.css">
<link href="https:
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blue-dark:  #004976;
    --blue-mid:   #0099D8;
    --blue-light: #E5F4FC;
    --white:      #FFFFFF;
    --gray-text:  #2D2D2D;
}

html, body {
    width: 100%; height: 100%;
    background: #0a0f1a;
    font-family: 'Open Sans', sans-serif;
    overflow: hidden;
}

.slider {
    width: 100%; height: 100vh;
    position: relative;
    overflow: hidden;
}

.slide {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 5vh 8vw;
    opacity: 0;
    transform: translateX(60px);
    transition: opacity 0.45s ease, transform 0.45s ease;
    pointer-events: none;
}
.slide.active {
    opacity: 1;
    transform: translateX(0);
    pointer-events: all;
}
.slide.out {
    opacity: 0;
    transform: translateX(-60px);
}

.slide-title {
    background: linear-gradient(135deg, #004976 0%, #002d4a 50%, #001829 100%);
    text-align: center;
}
.slide-title::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 70% 50% at 80% 20%, rgba(0,153,216,0.18) 0%, transparent 60%),
        radial-gradient(ellipse 50% 60% at 10% 80%, rgba(0,73,118,0.3) 0%, transparent 60%);
}
.slide-title .logo-mark {
    width: 72px; height: 4px;
    background: var(--blue-mid);
    border-radius: 2px;
    margin: 0 auto 2rem;
    position: relative;
}
.slide-title h1 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900;
    color: var(--white);
    line-height: 1.15;
    max-width: 800px;
    position: relative;
    letter-spacing: -0.02em;
    margin-bottom: 1.5rem;
}
.slide-title .subtitle {
    font-size: clamp(1rem, 2vw, 1.3rem);
    color: rgba(0,153,216,0.9);
    font-weight: 600;
    max-width: 600px;
    position: relative;
    line-height: 1.5;
}
.slide-title .course-badge {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(0,153,216,0.15);
    border: 1px solid rgba(0,153,216,0.3);
    color: var(--blue-mid);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 0.4rem 1rem;
    border-radius: 99px;
    margin-bottom: 1.5rem;
}

.slide-agenda {
    background: var(--white);
    align-items: flex-start;
}
.slide-agenda .slide-inner {
    width: 100%; max-width: 900px;
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 4vw;
    align-items: center;
}
.slide-agenda .left-col {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.slide-agenda .left-col .label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--blue-mid);
}
.slide-agenda .left-col h2 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 900;
    color: var(--blue-dark);
    line-height: 1.1;
    letter-spacing: -0.02em;
}
.slide-agenda .left-col .accent-bar {
    width: 48px; height: 4px;
    background: var(--blue-mid);
    border-radius: 2px;
}
.agenda-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.agenda-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 1.25rem;
    background: var(--blue-light);
    border-radius: 10px;
    border-left: 3px solid var(--blue-mid);
    font-size: clamp(0.85rem, 1.5vw, 1rem);
    font-weight: 600;
    color: var(--blue-dark);
    transition: transform 0.2s;
}
.agenda-item:hover { transform: translateX(4px); }
.agenda-num {
    width: 28px; height: 28px;
    background: var(--blue-mid);
    color: white;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    font-family: 'Nunito Sans', sans-serif;
    flex-shrink: 0;
}

.slide-content {
    background: var(--white);
    align-items: flex-start;
    justify-content: flex-start;
    padding-top: 6vh;
}
.slide-content .slide-inner {
    width: 100%; max-width: 900px;
}
.slide-content .slide-header {
    margin-bottom: 2.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--blue-light);
    display: flex;
    align-items: flex-end;
    gap: 1.5rem;
}
.slide-number-badge {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, var(--blue-dark), var(--blue-mid));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 900;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.slide-content h2 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: clamp(1.5rem, 3vw, 2.25rem);
    font-weight: 800;
    color: var(--blue-dark);
    line-height: 1.2;
    letter-spacing: -0.02em;
}
.points-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.point-card {
    background: #f8fbfd;
    border: 1.5px solid var(--blue-light);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.point-card:hover {
    border-color: var(--blue-mid);
    box-shadow: 0 4px 16px rgba(0,153,216,0.1);
}
.point-dot {
    width: 8px; height: 8px;
    background: var(--blue-mid);
    border-radius: 50%;
    margin-top: 0.4rem;
    flex-shrink: 0;
}
.point-text {
    font-size: clamp(0.85rem, 1.4vw, 0.95rem);
    color: var(--gray-text);
    line-height: 1.55;
    font-weight: 400;
}

.slide-keypoints {
    background: linear-gradient(160deg, var(--blue-dark) 0%, #002d4a 100%);
    align-items: flex-start;
    justify-content: flex-start;
    padding-top: 6vh;
}
.slide-keypoints .slide-inner { width: 100%; max-width: 900px; }
.slide-keypoints h2 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: clamp(1.5rem, 3vw, 2.25rem);
    font-weight: 800;
    color: var(--white);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.slide-keypoints h2 .icon {
    font-size: 1.5rem;
}
.keypoints-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.keypoint-item {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
    padding: 1.25rem 1.5rem;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
.keypoint-icon {
    width: 36px; height: 36px;
    background: var(--blue-mid);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.keypoint-text {
    font-size: clamp(0.9rem, 1.5vw, 1.05rem);
    color: rgba(255,255,255,0.9);
    line-height: 1.55;
    font-weight: 400;
    padding-top: 0.1rem;
}

.slide-closing {
    background: linear-gradient(135deg, #004976 0%, #0099D8 100%);
    text-align: center;
}
.slide-closing::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% 50%, rgba(255,255,255,0.06) 0%, transparent 70%);
}
.slide-closing h1 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 900;
    color: var(--white);
    position: relative;
    margin-bottom: 1.5rem;
    letter-spacing: -0.02em;
}
.slide-closing .subtitle {
    font-size: clamp(1rem, 2vw, 1.25rem);
    color: rgba(255,255,255,0.85);
    max-width: 600px;
    position: relative;
    line-height: 1.6;
}
.slide-closing .whirlpool-brand {
    position: relative;
    margin-top: 2.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: 0.05em;
    color: rgba(255,255,255,0.6);
    text-transform: uppercase;
}
.slide-closing .whirlpool-brand::before,
.slide-closing .whirlpool-brand::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.2);
    max-width: 80px;
}

.nav-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 64px;
    background: rgba(10,15,26,0.95);
    backdrop-filter: blur(12px);
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    z-index: 100;
}
.nav-progress {
    display: flex;
    gap: 6px;
    align-items: center;
}
.nav-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    transition: all 0.3s;
    cursor: pointer;
}
.nav-dot.active {
    background: var(--blue-mid);
    width: 20px;
    border-radius: 3px;
}
.nav-btn {
    width: 40px; height: 40px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.06);
    color: white;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
    font-size: 1.1rem;
}
.nav-btn:hover:not(:disabled) {
    background: var(--blue-mid);
    border-color: var(--blue-mid);
}
.nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.nav-controls { display: flex; gap: 0.5rem; align-items: center; }
.nav-counter {
    font-family: 'Nunito Sans', sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(255,255,255,0.4);
    min-width: 50px;
    text-align: center;
}

.slide-counter-top {
    position: fixed;
    top: 1.25rem; right: 1.5rem;
    font-family: 'Nunito Sans', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255,255,255,0.35);
    z-index: 50;
    letter-spacing: 0.05em;
}

.progress-bar-top {
    position: fixed;
    top: 0; left: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--blue-dark), var(--blue-mid));
    transition: width 0.4s ease;
    z-index: 200;
}

.keyboard-hint {
    position: fixed;
    bottom: 72px; right: 1.5rem;
    font-size: 0.68rem;
    color: rgba(255,255,255,0.2);
    font-family: 'Nunito Sans', sans-serif;
}
</style>
</head>
<body>

<div class="progress-bar-top" id="progressBar"></div>
<div class="slide-counter-top" id="counterTop"></div>
<div class="keyboard-hint">← → para navegar</div>

<div class="slider" id="slider">
<?php
$slide_num = 0;
foreach ($slides as $i => $slide):
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
</div>

<div class="nav-bar">
    <div class="nav-progress" id="navDots"></div>
    <div class="nav-counter" id="navCounter"></div>
    <div class="nav-controls">
        <button class="nav-btn" id="btnPrev" onclick="navigate(-1)" title="Anterior">←</button>
        <button class="nav-btn" id="btnNext" onclick="navigate(1)" title="Siguiente">→</button>
    </div>
</div>

<script>
const total   = <?php echo count($slides); ?>;
let current   = 0;
const slides  = document.querySelectorAll('.slide');
const dotsEl  = document.getElementById('navDots');
const counter = document.getElementById('navCounter');
const counterTop = document.getElementById('counterTop');
const progressBar = document.getElementById('progressBar');

for (let i = 0; i < total; i++) {
    const d = document.createElement('div');
    d.className = 'nav-dot' + (i === 0 ? ' active' : '');
    d.onclick = () => goTo(i);
    dotsEl.appendChild(d);
}

function updateUI() {
    const dots = document.querySelectorAll('.nav-dot');
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
    counter.textContent = (current + 1) + ' / ' + total;
    counterTop.textContent = (current + 1) + ' / ' + total;
    progressBar.style.width = ((current + 1) / total * 100) + '%';
    document.getElementById('btnPrev').disabled = current === 0;
    document.getElementById('btnNext').disabled = current === total - 1;
}

function goTo(n) {
    if (n < 0 || n >= total || n === current) return;
    slides[current].classList.remove('active');
    slides[current].classList.add('out');
    setTimeout(() => slides[current < n ? current : n + 1]?.classList.remove('out'), 450);

    const old = current;
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
    <script src="/js/theme.js?v=1.01.04"></script>
    <script src="js/notifications-widget.js"></script>
</body>
</html>