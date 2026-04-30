<?php
session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

$admin_name = $_SESSION['user_name'] ?? 'Administrador';

// Cursos para el filtro
$courses = $conn->query("
    SELECT c.course_id, c.course_title, COUNT(e.enrollment_id) AS enrolled
    FROM courses c
    LEFT JOIN course_enrollments e ON e.course_id = c.course_id AND e.status != 'completed'
    GROUP BY c.course_id
    HAVING enrolled > 0
    ORDER BY c.course_title ASC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Predicción de Abandono — Whirlpool Training</title>
<link rel="stylesheet" href="../css/dark-mode.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Open Sans', sans-serif;
    background: #F4F6F9;
    color: #1a2332;
}

/* ── Page ─────────────────────────────────────────────────────────── */
.page-wrapper { padding: 2rem 2.5rem; max-width: 1400px; margin: 0 auto; }

.page-header { margin-bottom: 2rem; }
.page-header h1 {
    font-family: 'Nunito Sans', sans-serif;
    font-size: 1.75rem; font-weight: 800;
    color: #004976;
}
.page-header p { color: #64748b; margin-top: .35rem; font-size: .95rem; }

/* ── Summary cards ────────────────────────────────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}
.summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.summary-icon { display: none; }
.summary-info { text-align: center; }
.summary-info .num  { font-family: 'Nunito Sans', sans-serif; font-size: 1.75rem; font-weight: 800; line-height: 1; }
.summary-info .lbl  { font-size: .78rem; color: #64748b; margin-top: .2rem; }
.num-total  { color: #004976; }
.num-alto   { color: #DC2626; }
.num-medio  { color: #D97706; }
.num-bajo   { color: #059669; }
.num-sin    { color: #94a3b8; }

/* ── Toolbar ──────────────────────────────────────────────────────── */
.toolbar {
    background: #fff;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    display: flex; align-items: center; flex-wrap: wrap; gap: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.toolbar select, .toolbar input[type=text] {
    padding: .5rem .9rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-family: 'Open Sans', sans-serif;
    font-size: .9rem;
    color: #1a2332;
    background: #f8fafc;
    outline: none;
    transition: border-color .2s;
}
.toolbar select:focus, .toolbar input:focus { border-color: #0099D8; }
.btn {
    padding: .5rem 1.2rem;
    border-radius: 8px;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: .88rem;
    cursor: pointer;
    border: none;
    transition: all .2s;
}
.btn-primary { background: #0099D8; color: #fff; }
.btn-primary:hover { background: #007bb5; }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #FEE2E2; color: #DC2626; }
.btn-danger:hover { background: #FECACA; }

.toolbar-right { margin-left: auto; display: flex; gap: .6rem; }

/* ── Table ────────────────────────────────────────────────────────── */
.table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    overflow: hidden;
}
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: .88rem; }
thead th {
    background: #004976;
    color: #fff;
    padding: .85rem 1rem;
    text-align: left;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: .8rem;
    letter-spacing: .04em;
    white-space: nowrap;
}
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
tbody tr:hover { background: #f8fafc; }
tbody td { padding: .85rem 1rem; vertical-align: middle; }

/* ── Risk badges ──────────────────────────────────────────────────── */
.badge {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .75rem;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 700;
    font-family: 'Nunito Sans', sans-serif;
    white-space: nowrap;
}
.badge-alto    { background: #FEE2E2; color: #991B1B; }
.badge-medio   { background: #FEF3C7; color: #92400E; }
.badge-bajo    { background: #D1FAE5; color: #065F46; }
.badge-sin     { background: #F1F5F9; color: #64748b; }
.badge-dot {
    width: 7px; height: 7px; border-radius: 50%;
    display: inline-block;
}
.badge-alto  .badge-dot { background: #DC2626; }
.badge-medio .badge-dot { background: #D97706; }
.badge-bajo  .badge-dot { background: #10B981; }

/* ── Progress bar ─────────────────────────────────────────────────── */
.progress-wrap { display: flex; align-items: center; gap: .5rem; }
.progress-bar-bg {
    flex: 1; height: 6px; background: #e2e8f0;
    border-radius: 99px; overflow: hidden; min-width: 60px;
}
.progress-bar-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, #0099D8, #004976);
    transition: width .4s;
}

/* ── Score pill ───────────────────────────────────────────────────── */
.score-pill {
    display: inline-block;
    width: 36px; height: 36px; line-height: 36px;
    text-align: center;
    border-radius: 50%;
    font-weight: 800;
    font-family: 'Nunito Sans', sans-serif;
    font-size: .82rem;
}
.score-high   { background: #FEE2E2; color: #DC2626; }
.score-medium { background: #FEF3C7; color: #D97706; }
.score-low    { background: #D1FAE5; color: #059669; }
.score-none   { background: #F1F5F9; color: #94a3b8; }

/* ── Action btn ──────────────────────────────────────────────────── */
.btn-analyze {
    background: #EEF2FF; color: #4338CA;
    border: none; border-radius: 7px;
    padding: .38rem .85rem;
    font-size: .8rem; font-weight: 700;
    font-family: 'Nunito Sans', sans-serif;
    cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.btn-analyze:hover { background: #E0E7FF; }
.btn-analyze:disabled { opacity: .5; cursor: not-allowed; }

/* ── Detail drawer (slide-in) ─────────────────────────────────────── */
.drawer-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.35); z-index: 200;
}
.drawer-overlay.open { display: block; }
.drawer {
    position: fixed; right: 0; top: 0; bottom: 0;
    width: 420px; background: #fff;
    box-shadow: -4px 0 24px rgba(0,0,0,.12);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .3s ease;
    z-index: 201;
}
.drawer.open { transform: translateX(0); }
.drawer-header {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.drawer-header h2 { font-family: 'Nunito Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: #004976; }
.drawer-close { background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.4rem; line-height: 1; }
.drawer-body { flex: 1; overflow-y: auto; padding: 1.5rem; }
.drawer-section { margin-bottom: 1.5rem; }
.drawer-section h3 { font-family: 'Nunito Sans', sans-serif; font-size: .8rem; font-weight: 700; color: #94a3b8; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .75rem; }
.drawer-kv { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid #f8fafc; font-size: .88rem; }
.drawer-kv .k { color: #64748b; }
.drawer-kv .v { font-weight: 600; text-align: right; }
.reason-item { display: flex; gap: .6rem; padding: .5rem 0; font-size: .88rem; }
.reason-dot { width: 6px; height: 6px; border-radius: 50%; background: #DC2626; margin-top: .35rem; flex-shrink: 0; }
.action-box { background: #EFF6FF; border-left: 4px solid #0099D8; border-radius: 0 8px 8px 0; padding: 1rem; font-size: .88rem; color: #1e40af; line-height: 1.6; }

/* ── Empty + loading ──────────────────────────────────────────────── */
.table-empty { padding: 3rem; text-align: center; color: #94a3b8; }
.table-empty svg { display: block; margin: 0 auto 1rem; opacity: .4; }
.spinner { display: inline-block; width: 18px; height: 18px; border: 2.5px solid #e2e8f0; border-top-color: #0099D8; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: .4rem; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Toast ────────────────────────────────────────────────────────── */
#toast {
    position: fixed; bottom: 2rem; right: 2rem;
    background: #1e293b; color: #fff;
    padding: .75rem 1.25rem; border-radius: 10px;
    font-size: .88rem; font-family: 'Nunito Sans', sans-serif; font-weight: 600;
    opacity: 0; transform: translateY(8px);
    transition: all .3s; pointer-events: none; z-index: 999;
}
#toast.show { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>
<script>
window.ADMIN_NAME   = "<?= htmlspecialchars($admin_name) ?>";
window.ADMIN_ACTIVE = "dropout";
</script>
<script src="/admin/js/admin-navbar.js" defer></script>

<main class="admin-main">
<div class="page-wrapper">

  <!-- Header -->
  <div class="page-header">
    <h1> Predicción de Abandono</h1>
    <p>Analiza el riesgo de que los empleados abandonen sus cursos usando inteligencia artificial.</p>
  </div>

  <!-- Summary cards -->
  <div class="summary-grid" id="summaryGrid">
    <div class="summary-card">
      <div class="summary-icon icon-total"></div>
      <div class="summary-info"><div class="num num-total" id="cnt-total">—</div><div class="lbl">Total activos</div></div>
    </div>
    <div class="summary-card">
      <div class="summary-icon icon-alto"></div>
      <div class="summary-info"><div class="num num-alto" id="cnt-alto">—</div><div class="lbl">Riesgo alto</div></div>
    </div>
    <div class="summary-card">
      <div class="summary-icon icon-medio"></div>
      <div class="summary-info"><div class="num num-medio" id="cnt-medio">—</div><div class="lbl">Riesgo medio</div></div>
    </div>
    <div class="summary-card">
      <div class="summary-icon icon-bajo"></div>
      <div class="summary-info"><div class="num num-bajo" id="cnt-bajo">—</div><div class="lbl">Riesgo bajo</div></div>
    </div>
    <div class="summary-card">
      <div class="summary-icon icon-sin"></div>
      <div class="summary-info"><div class="num num-sin" id="cnt-sin">—</div><div class="lbl">Sin analizar</div></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <select id="filterCourse">
      <option value="">Todos los cursos</option>
      <?php foreach ($courses as $c): ?>
      <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_title']) ?> (<?= $c['enrolled'] ?>)</option>
      <?php endforeach; ?>
    </select>

    <select id="filterRisk">
      <option value="">Todos los niveles</option>
      <option value="alto"> Riesgo alto</option>
      <option value="medio"> Riesgo medio</option>
      <option value="bajo"> Riesgo bajo</option>
    </select>

    <input type="text" id="searchInput" placeholder="Buscar empleado o curso…" style="min-width:200px">

    <div class="toolbar-right">
      <button class="btn btn-secondary" onclick="loadTable()"> Actualizar</button>
      <button class="btn btn-secondary" onclick="exportXLSX()" title="Exportar a Excel"> Excel</button>
      <button class="btn btn-secondary" onclick="exportPDF()" title="Exportar a PDF"> PDF</button>
      <button class="btn btn-primary" onclick="analyzeAll()"> Analizar todos</button>
    </div>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Curso</th>
            <th>Progreso</th>
            <th>Últ. actividad</th>
            <th>Riesgo</th>
            <th>Score</th>
            <th>Analizado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr><td colspan="8" class="table-empty"><span class="spinner"></span> Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /page-wrapper -->
</main>

<!-- Detail Drawer -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <h2 id="drawerTitle">Detalle de riesgo</h2>
    <button class="drawer-close" onclick="closeDrawer()">×</button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <!-- filled by JS -->
  </div>
</div>

<div id="toast"></div>

<script>
let allRows = [];

async function loadTable() {
  const course = document.getElementById('filterCourse').value;
  const risk   = document.getElementById('filterRisk').value;

  let url = 'dropout_risk.php?';
  if (course) url += `course_id=${course}&`;
  if (risk)   url += `risk=${risk}&`;

  document.getElementById('tableBody').innerHTML =
    '<tr><td colspan="8" class="table-empty"><span class="spinner"></span> Cargando…</td></tr>';

  try {
    const res  = await fetch(url);
    const data = await res.json();
    if (!data.success) { showToast('Error al cargar datos'); return; }

    allRows = data.enrollments;
    renderTable(allRows);
    updateSummary(allRows);
  } catch(e) {
    document.getElementById('tableBody').innerHTML =
      '<tr><td colspan="8" class="table-empty">Error de conexión</td></tr>';
  }
}

function renderTable(rows) {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const filtered = search
    ? rows.filter(r =>
        `${r.first_name} ${r.last_name} ${r.course_title}`.toLowerCase().includes(search)
      )
    : rows;

  if (!filtered.length) {
    document.getElementById('tableBody').innerHTML =
      '<tr><td colspan="8" class="table-empty"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6M12 9v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Sin resultados</td></tr>';
    return;
  }

  document.getElementById('tableBody').innerHTML = filtered.map(r => {
    const name     = `${r.first_name} ${r.last_name}`;
    const dept     = r.department || '—';
    const prog     = parseFloat(r.progress_percentage || 0).toFixed(0);
    const lastAct  = r.last_accessed ? daysSince(r.last_accessed) : '—';
    const riskBadge = riskLabel(r.risk_level);
    const scoreEl   = scoreWidget(r.risk_score);
    const analyzed  = r.analyzed_at ? relativeDate(r.analyzed_at) : '<span style="color:#94a3b8">Pendiente</span>';

    return `<tr>
      <td>
        <div style="font-weight:600">${esc(name)}</div>
        <div style="font-size:.78rem;color:#64748b">${esc(dept)}</div>
      </td>
      <td style="max-width:200px">
        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(r.course_title)}</div>
      </td>
      <td style="min-width:130px">
        <div class="progress-wrap">
          <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${prog}%"></div></div>
          <span style="font-size:.8rem;font-weight:600;min-width:34px">${prog}%</span>
        </div>
      </td>
      <td style="white-space:nowrap">${lastAct}</td>
      <td>${riskBadge}</td>
      <td>${scoreEl}</td>
      <td style="font-size:.8rem;color:#64748b;white-space:nowrap">${analyzed}</td>
      <td>
        <button class="btn-analyze" data-uid="${r.user_id}" data-cid="${r.course_id}"
          onclick="analyzeOne(this, ${r.user_id}, ${r.course_id}, '${esc(name)}')">
           Analizar
        </button>
        ${r.risk_level ? `<button class="btn-analyze" style="margin-left:.4rem;background:#F0FDF4;color:#065F46"
          onclick="openDrawer(${r.user_id}, ${r.course_id})"> Ver</button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

async function analyzeOne(btn, userId, courseId, name) {
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  try {
    const res = await fetch('dropout_risk.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action:'analyze_user', user_id: userId, course_id: courseId })
    });
    const data = await res.json();

    if (data.success) {
      showToast(`✅ ${name} analizado — Riesgo: ${data.prediction.risk_level}`);
      loadTable();
    } else {
      showToast('Error: ' + (data.error || 'Desconocido'));
      btn.disabled = false;
      btn.textContent = ' Analizar';
    }
  } catch(e) {
    showToast('Error de conexión');
    btn.disabled = false;
    btn.textContent = ' Analizar';
  }
}

async function analyzeAll() {
  if (!allRows.length) { showToast('No hay datos cargados'); return; }
  const pending = allRows.filter(r => !r.risk_level || r.risk_level === 'desconocido');
  if (!pending.length) { showToast('Todos ya han sido analizados'); return; }

  showToast(` Analizando ${pending.length} empleados…`);

  for (let i = 0; i < pending.length; i++) {
    const r = pending[i];
    try {
      await fetch('dropout_risk.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'analyze_user', user_id: r.user_id, course_id: r.course_id })
      });
    } catch(e) {}
    await new Promise(res => setTimeout(res, 800));
  }

  showToast('✅ Análisis completado');
  loadTable();
}

function openDrawer(userId, courseId) {
  const r = allRows.find(x => x.user_id == userId && x.course_id == courseId);
  if (!r) return;

  const name = `${r.first_name} ${r.last_name}`;
  document.getElementById('drawerTitle').textContent = name;

  const reasons = Array.isArray(r.reasons) ? r.reasons : [];
  const reasonsHTML = reasons.length
    ? reasons.map(rs => `<div class="reason-item"><span class="reason-dot"></span>${esc(rs)}</div>`).join('')
    : '<div style="color:#94a3b8;font-size:.88rem">Sin razones registradas</div>';

  document.getElementById('drawerBody').innerHTML = `
    <div class="drawer-section">
      <h3>Empleado</h3>
      <div class="drawer-kv"><span class="k">Nombre</span><span class="v">${esc(name)}</span></div>
      <div class="drawer-kv"><span class="k">Departamento</span><span class="v">${esc(r.department || '—')}</span></div>
      <div class="drawer-kv"><span class="k">Curso</span><span class="v" style="max-width:200px;text-align:right">${esc(r.course_title)}</span></div>
    </div>

    <div class="drawer-section">
      <h3>Datos de progreso</h3>
      <div class="drawer-kv"><span class="k">Progreso</span><span class="v">${parseFloat(r.progress_percentage).toFixed(1)}%</span></div>
      <div class="drawer-kv"><span class="k">Última actividad</span><span class="v">${r.last_accessed ? daysSince(r.last_accessed) : '—'}</span></div>
      <div class="drawer-kv"><span class="k">Inscrito</span><span class="v">${r.enrollment_date ? new Date(r.enrollment_date).toLocaleDateString('es-MX') : '—'}</span></div>
    </div>

    <div class="drawer-section">
      <h3>Resultado del análisis</h3>
      <div class="drawer-kv"><span class="k">Nivel de riesgo</span><span class="v">${riskLabel(r.risk_level)}</span></div>
      <div class="drawer-kv"><span class="k">Score</span><span class="v">${scoreWidget(r.risk_score)}</span></div>
      <div class="drawer-kv"><span class="k">Analizado</span><span class="v">${r.analyzed_at ? relativeDate(r.analyzed_at) : '—'}</span></div>
    </div>

    <div class="drawer-section">
      <h3>Razones detectadas</h3>
      ${reasonsHTML}
    </div>

    ${r.recommended_action ? `
    <div class="drawer-section">
      <h3>Acción recomendada</h3>
      <div class="action-box">${esc(r.recommended_action)}</div>
    </div>` : ''}

    <button class="btn btn-primary" style="width:100%;margin-top:.5rem"
      onclick="analyzeOne(this, ${userId}, ${courseId}, '${esc(name)}'); closeDrawer()">
       Re-analizar
    </button>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
}

function closeDrawer() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
}

function updateSummary(rows) {
  const counts = { alto: 0, medio: 0, bajo: 0, sin: 0 };
  rows.forEach(r => {
    if (r.risk_level === 'alto')  counts.alto++;
    else if (r.risk_level === 'medio') counts.medio++;
    else if (r.risk_level === 'bajo')  counts.bajo++;
    else counts.sin++;
  });
  document.getElementById('cnt-total').textContent = rows.length;
  document.getElementById('cnt-alto').textContent  = counts.alto;
  document.getElementById('cnt-medio').textContent = counts.medio;
  document.getElementById('cnt-bajo').textContent  = counts.bajo;
  document.getElementById('cnt-sin').textContent   = counts.sin;
}

function riskLabel(level) {
  const map = {
    alto:  '<span class="badge badge-alto"><span class="badge-dot"></span>Alto</span>',
    medio: '<span class="badge badge-medio"><span class="badge-dot"></span>Medio</span>',
    bajo:  '<span class="badge badge-bajo"><span class="badge-dot"></span>Bajo</span>',
  };
  return map[level] || '<span class="badge badge-sin">Sin analizar</span>';
}

function scoreWidget(score) {
  if (!score && score !== 0) return '<span class="score-pill score-none">—</span>';
  const s = parseInt(score);
  const cls = s >= 70 ? 'score-high' : s >= 40 ? 'score-medium' : 'score-low';
  return `<span class="score-pill ${cls}">${s}</span>`;
}

function daysSince(dateStr) {
  if (!dateStr) return '—';
  const d = Math.floor((Date.now() - new Date(dateStr)) / 86400000);
  if (d <= 0) return 'Hoy';
  if (d === 1) return 'Ayer';
  return `Hace ${d} días`;
}

function relativeDate(dateStr) {
  if (!dateStr) return '—';
  const d = Math.floor((Date.now() - new Date(dateStr)) / 86400000);
  if (d <= 0) return 'Hoy';
  if (d === 1) return 'Ayer';
  return `Hace ${d} días`;
}

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3500);
}

document.getElementById('filterCourse').addEventListener('change', loadTable);
document.getElementById('filterRisk').addEventListener('change', loadTable);
document.getElementById('searchInput').addEventListener('input', () => renderTable(allRows));

function getFilteredRows() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  return search
    ? allRows.filter(r => `${r.first_name} ${r.last_name} ${r.course_title}`.toLowerCase().includes(search))
    : allRows;
}

function exportXLSX() {
  const rows = getFilteredRows();
  if (!rows.length) { showToast('No hay datos para exportar'); return; }

  const headers = ['Empleado', 'Departamento', 'Curso', 'Progreso (%)', 'Última actividad', 'Nivel de riesgo', 'Score', 'Analizado'];
  const data = rows.map(r => [
    `${r.first_name} ${r.last_name}`,
    r.department || '—',
    r.course_title,
    parseFloat(r.progress_percentage || 0).toFixed(1),
    r.last_accessed ? daysSince(r.last_accessed) : '—',
    r.risk_level ? r.risk_level.charAt(0).toUpperCase() + r.risk_level.slice(1) : 'Sin analizar',
    r.risk_score ?? '—',
    r.analyzed_at ? relativeDate(r.analyzed_at) : 'Pendiente'
  ]);

  const wb = XLSX.utils.book_new();
  const wsData = [headers, ...data];
  const ws = XLSX.utils.aoa_to_sheet(wsData);

  ws['!cols'] = [22,20,30,14,18,16,10,16].map(w => ({ wch: w }));

  headers.forEach((_, i) => {
    const cell = ws[XLSX.utils.encode_cell({ r: 0, c: i })];
    if (cell) {
      cell.s = { font: { bold: true }, fill: { fgColor: { rgb: '004976' } }, alignment: { horizontal: 'center' } };
    }
  });

  XLSX.utils.book_append_sheet(wb, ws, 'Predicciones de Abandono');

  const counts = { alto: 0, medio: 0, bajo: 0, sin: 0 };
  rows.forEach(r => {
    if (r.risk_level === 'alto') counts.alto++;
    else if (r.risk_level === 'medio') counts.medio++;
    else if (r.risk_level === 'bajo') counts.bajo++;
    else counts.sin++;
  });
  const summaryData = [
    ['Resumen — Predicción de Abandono'],
    ['Generado', new Date().toLocaleString('es-MX')],
    [],
    ['Categoría', 'Cantidad'],
    ['Total activos', rows.length],
    ['Riesgo Alto', counts.alto],
    ['Riesgo Medio', counts.medio],
    ['Riesgo Bajo', counts.bajo],
    ['Sin analizar', counts.sin],
  ];
  const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
  wsSummary['!cols'] = [{ wch: 22 }, { wch: 18 }];
  XLSX.utils.book_append_sheet(wb, wsSummary, 'Resumen');

  const date = new Date().toISOString().slice(0,10);
  XLSX.writeFile(wb, `prediccion_abandono_${date}.xlsx`);
  showToast('✅ Excel descargado');
}

function exportPDF() {
  const rows = getFilteredRows();
  if (!rows.length) { showToast('No hay datos para exportar'); return; }

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();

  doc.setFillColor(0, 73, 118); // #004976
  doc.rect(0, 0, pageW, 22, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(14);
  doc.setFont('helvetica', 'bold');
  doc.text('Whirlpool Training — Predicción de Abandono', 14, 14);

  const date = new Date().toLocaleDateString('es-MX', { day:'2-digit', month:'long', year:'numeric' });
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text(`Generado: ${date}`, pageW - 14, 14, { align: 'right' });

  const counts = { alto: 0, medio: 0, bajo: 0, sin: 0 };
  rows.forEach(r => {
    if (r.risk_level === 'alto') counts.alto++;
    else if (r.risk_level === 'medio') counts.medio++;
    else if (r.risk_level === 'bajo') counts.bajo++;
    else counts.sin++;
  });

  doc.setTextColor(30, 30, 30);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text(`Total: ${rows.length}  |  Alto: ${counts.alto}  |  Medio: ${counts.medio}  |  Bajo: ${counts.bajo}  |  Sin analizar: ${counts.sin}`, 14, 30);

  const head = [['Empleado', 'Departamento', 'Curso', 'Progreso', 'Últ. actividad', 'Nivel de riesgo', 'Score', 'Analizado']];
  const body = rows.map(r => [
    `${r.first_name} ${r.last_name}`,
    r.department || '—',
    r.course_title.length > 30 ? r.course_title.slice(0, 28) + '…' : r.course_title,
    parseFloat(r.progress_percentage || 0).toFixed(0) + '%',
    r.last_accessed ? daysSince(r.last_accessed) : '—',
    r.risk_level ? r.risk_level.charAt(0).toUpperCase() + r.risk_level.slice(1) : 'Sin analizar',
    r.risk_score ?? '—',
    r.analyzed_at ? relativeDate(r.analyzed_at) : 'Pendiente'
  ]);

  doc.autoTable({
    head,
    body,
    startY: 34,
    styles: { font: 'helvetica', fontSize: 8, cellPadding: 3, lineColor: [226, 232, 240], lineWidth: 0.2 },
    headStyles: { fillColor: [0, 153, 216], textColor: 255, fontStyle: 'bold', fontSize: 8 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: {
      0: { cellWidth: 38 },
      1: { cellWidth: 28 },
      2: { cellWidth: 52 },
      3: { cellWidth: 20, halign: 'center' },
      4: { cellWidth: 26, halign: 'center' },
      5: { cellWidth: 26, halign: 'center' },
      6: { cellWidth: 16, halign: 'center' },
      7: { cellWidth: 26, halign: 'center' }
    },
    didDrawCell: (d) => {
      if (d.section === 'body' && d.column.index === 5) {
        const val = d.cell.raw?.toLowerCase();
        if (val === 'alto') { doc.setTextColor(220, 38, 38); }
        else if (val === 'medio') { doc.setTextColor(217, 119, 6); }
        else if (val === 'bajo') { doc.setTextColor(5, 150, 105); }
        else { doc.setTextColor(148, 163, 184); }
        doc.setFont('helvetica', 'bold');
        doc.text(d.cell.raw, d.cell.x + d.cell.width / 2, d.cell.y + d.cell.height / 2 + 1, { align: 'center' });
        doc.setTextColor(0); doc.setFont('helvetica', 'normal');
      }
    },
    margin: { left: 14, right: 14 },
    didDrawPage: (data) => {
      const pg = doc.internal.getCurrentPageInfo().pageNumber;
      const total = doc.internal.getNumberOfPages();
      doc.setFontSize(8);
      doc.setTextColor(148, 163, 184);
      doc.text(`Página ${pg} de ${total}`, pageW / 2, doc.internal.pageSize.getHeight() - 6, { align: 'center' });
    }
  });

  const filename = `prediccion_abandono_${new Date().toISOString().slice(0,10)}.pdf`;
  doc.save(filename);
  showToast('✅ PDF descargado');
}

// Init
loadTable();
</script>
<!-- Export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
</body>
</html>