<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$admin_name = $_SESSION['user_name'] ?? 'Admin';

$totals = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(role='admin') as admins,
        SUM(role='instructor') as instructors,
        SUM(role='student') as students,
        SUM(status='active') as active,
        SUM(status='inactive') as inactive,
        SUM(status='suspended') as suspended,
        SUM(DATE(created_at)=CURDATE()) as new_today,
        SUM(DATE(created_at)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as new_week,
        SUM(DATE(created_at)>=DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as new_month
    FROM users
")->fetch_assoc();

$reg_by_month = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

$reg_by_day = $conn->query("
    SELECT DATE(created_at) as day, COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY day ORDER BY day ASC
")->fetch_all(MYSQLI_ASSOC);

$by_dept = $conn->query("
    SELECT IFNULL(NULLIF(department,''), 'Sin departamento') as dept, COUNT(*) as count
    FROM users
    GROUP BY dept ORDER BY count DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$login_activity = $conn->query("
    SELECT DATE(last_login) as day, COUNT(*) as count
    FROM users
    WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND last_login IS NOT NULL
    GROUP BY day ORDER BY day ASC
")->fetch_all(MYSQLI_ASSOC);

$top_active = $conn->query("
    SELECT user_id, first_name, last_name, email, department, role, last_login, created_at,
           DATEDIFF(NOW(), last_login) as days_since_login
    FROM users
    WHERE last_login IS NOT NULL
    ORDER BY last_login DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$inactive_users = $conn->query("
    SELECT user_id, first_name, last_name, email, department, role, last_login, created_at
    FROM users
    WHERE last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$prev_month = $conn->query("
    SELECT COUNT(*) FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
      AND created_at <  DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch_row()[0];
$curr_month  = $totals['new_month'];
$growth_rate = $prev_month > 0
    ? round((($curr_month - $prev_month) / $prev_month) * 100, 1)
    : ($curr_month > 0 ? 100 : 0);
$months_labels = array_map(fn($m) => date('M Y', mktime(0,0,0,intval(substr($m,5,2)),1,intval(substr($m,0,4)))), array_column($reg_by_month,'month'));
$months_data   = array_column($reg_by_month,'count');
$days_labels   = array_map(fn($d) => date('d/m', strtotime($d)), array_column($reg_by_day,'day'));
$days_data     = array_column($reg_by_day,'count');
$dept_labels   = array_column($by_dept,'dept');
$dept_data     = array_column($by_dept,'count');
$login_labels  = array_map(fn($d) => date('d/m', strtotime($d)), array_column($login_activity,'day'));
$login_data    = array_column($login_activity,'count');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Usuarios — Whirlpool LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dark-mode.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
            --purple:      #8B5CF6;
            --purple-light:#EDE9FE;
            --radius-sm:   6px;
            --radius-md:   12px;
            --radius-lg:   16px;
            --shadow-sm:   0 1px 3px rgba(0,0,0,.08);
            --shadow-lg:   0 8px 32px rgba(0,0,0,.12);
        }
        body { font-family:'Open Sans',sans-serif; background:var(--gray-50); color:var(--gray-700); min-height:100vh; }

        .admin-main { margin-left:260px; min-height:100vh; }
        .topbar { background:var(--white); border-bottom:1px solid var(--gray-200); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar-title { font-family:'Nunito Sans',sans-serif; font-size:20px; font-weight:800; color:var(--blue-dark); display:flex; align-items:center; gap:10px; }
        .btn { display:inline-flex; align-items:center; gap:8px; padding:9px 18px; border-radius:var(--radius-sm); font-family:'Open Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; transition:all .2s; }
        .btn-outline { background:transparent; border:1.5px solid var(--gray-300); color:var(--gray-700); }
        .btn-outline:hover { border-color:var(--blue-accent); color:var(--blue-accent); }

        .content { padding:28px 32px; }
        .section-title { font-family:'Nunito Sans',sans-serif; font-size:13px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--gray-400); margin:28px 0 14px; }
        .section-title:first-child { margin-top:0; }

        /* KPI Grid */
        .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
        @media(max-width:1200px){ .kpi-grid { grid-template-columns:repeat(2,1fr); } }
        .kpi-card { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-md); padding:22px 24px; box-shadow:var(--shadow-sm); position:relative; overflow:hidden; }
        .kpi-card::after { content:''; position:absolute; top:0; right:0; width:4px; height:100%; }
        .kpi-blue::after   { background:var(--blue-accent); }
        .kpi-green::after  { background:var(--green); }
        .kpi-amber::after  { background:var(--amber); }
        .kpi-purple::after { background:var(--purple); }
        .kpi-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; margin-bottom:14px; }
        .ki-blue   { background:var(--blue-light);   color:var(--blue-accent); }
        .ki-green  { background:var(--green-light);  color:var(--green); }
        .ki-amber  { background:var(--amber-light);  color:var(--amber); }
        .ki-purple { background:var(--purple-light); color:var(--purple); }
        .kpi-label { font-size:12px; font-weight:600; color:var(--gray-400); margin-bottom:4px; }
        .kpi-value { font-family:'Nunito Sans',sans-serif; font-size:32px; font-weight:800; color:var(--gray-900); line-height:1; }
        .kpi-sub   { font-size:12px; color:var(--gray-400); margin-top:6px; }
        .trend-up   { color:var(--green); font-weight:700; }
        .trend-down { color:var(--red);   font-weight:700; }

        /* Chart cards */
        .chart-grid-main { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .chart-grid-2    { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media(max-width:1100px){ .chart-grid-main,.chart-grid-2 { grid-template-columns:1fr; } }

        .chart-card { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-lg); padding:24px; box-shadow:var(--shadow-sm); }
        .chart-card-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
        .chart-card-title    { font-family:'Nunito Sans',sans-serif; font-size:15px; font-weight:800; color:var(--gray-900); }
        .chart-card-subtitle { font-size:12px; color:var(--gray-400); margin-top:2px; }
        .chart-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; background:var(--blue-light); color:var(--blue-mid); }

        /* Donut legend */
        .donut-legend { display:flex; flex-direction:column; gap:10px; margin-top:16px; }
        .legend-item  { display:flex; align-items:center; gap:10px; font-size:13px; }
        .legend-dot   { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .legend-label { flex:1; color:var(--gray-700); }
        .legend-value { font-weight:700; color:var(--gray-900); }
        .legend-pct   { color:var(--gray-400); font-size:12px; }

        /* Progress bars */
        .prog-bar  { height:6px; border-radius:3px; background:var(--gray-100); overflow:hidden; margin-top:6px; }
        .prog-fill { height:100%; border-radius:3px; }

        /* List cards */
        .list-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media(max-width:1100px){ .list-grid { grid-template-columns:1fr; } }
        .list-card { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm); }
        .list-card-header { padding:18px 20px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; }
        .list-card-title  { font-family:'Nunito Sans',sans-serif; font-size:14px; font-weight:800; color:var(--gray-900); display:flex; align-items:center; gap:8px; }
        .list-item { display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid var(--gray-100); transition:background .1s; }
        .list-item:last-child { border-bottom:none; }
        .list-item:hover { background:var(--gray-50); }
        .avatar-sm { width:36px; height:36px; border-radius:50%; flex-shrink:0; background:var(--blue-light); color:var(--blue-accent); display:flex; align-items:center; justify-content:center; font-family:'Nunito Sans',sans-serif; font-size:13px; font-weight:800; }
        .li-info { flex:1; min-width:0; }
        .li-name { font-size:13px; font-weight:600; color:var(--gray-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .li-sub  { font-size:11px; color:var(--gray-400); }
        .li-right { font-size:12px; color:var(--gray-400); text-align:right; white-space:nowrap; }

        .empty-note { padding:30px; text-align:center; color:var(--gray-400); font-size:14px; }
    </style>
    <script>
        window.ADMIN_NAME   = "<?php echo htmlspecialchars($admin_name); ?>";
        window.ADMIN_ACTIVE = "users_stats";
    </script>
    <script src="/admin/js/admin-navbar.js" defer></script>
</head>
<body>
<div class="admin-main">
    <header class="topbar">
        <div class="topbar-title">
            <i class="fas fa-chart-pie" style="color:var(--blue-accent);font-size:18px;"></i>
            Estadísticas de Usuarios
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:12px;color:var(--gray-400);"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i') ?></span>
            <button onclick="exportXLSX()" class="btn btn-outline"><i class="fas fa-file-excel"></i> Excel</button>
            <button onclick="exportPDF()" class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
            <a href="users.php" class="btn btn-outline"><i class="fas fa-users"></i> Ver Usuarios</a>
        </div>
    </header>

    <div class="content">

        <!-- KPIs -->
        <div class="section-title">Resumen General</div>
        <div class="kpi-grid">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon ki-blue"><i class="fas fa-users"></i></div>
                <div class="kpi-label">Total de Usuarios</div>
                <div class="kpi-value"><?= number_format($totals['total']) ?></div>
                <div class="kpi-sub"><?= $totals['admins'] ?> admins · <?= $totals['instructors'] ?> instructores · <?= $totals['students'] ?> estudiantes</div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-icon ki-green"><i class="fas fa-circle-check"></i></div>
                <div class="kpi-label">Usuarios Activos</div>
                <div class="kpi-value"><?= number_format($totals['active']) ?></div>
                <div class="kpi-sub"><?= $totals['total'] > 0 ? round(($totals['active']/$totals['total'])*100,1) : 0 ?>% del total</div>
            </div>
            <div class="kpi-card kpi-amber">
                <div class="kpi-icon ki-amber"><i class="fas fa-user-plus"></i></div>
                <div class="kpi-label">Nuevos Este Mes</div>
                <div class="kpi-value"><?= number_format($totals['new_month']) ?></div>
                <div class="kpi-sub">
                    <?php if ($growth_rate >= 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> <?= $growth_rate ?>%</span> vs mes anterior
                    <?php else: ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?= abs($growth_rate) ?>%</span> vs mes anterior
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-card kpi-purple">
                <div class="kpi-icon ki-purple"><i class="fas fa-calendar-day"></i></div>
                <div class="kpi-label">Nuevos Hoy</div>
                <div class="kpi-value"><?= number_format($totals['new_today']) ?></div>
                <div class="kpi-sub"><?= number_format($totals['new_week']) ?> esta semana</div>
            </div>
        </div>

        <!-- Monthly + Donut -->
        <div class="section-title">Registros y Distribución</div>
        <div class="chart-grid-main" style="margin-bottom:20px;">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">Registros por Mes</div>
                        <div class="chart-card-subtitle">Últimos 12 meses</div>
                    </div>
                    <span class="chart-badge">12 meses</span>
                </div>
                <div style="position:relative;height:240px;">
                    <canvas id="chartMonthly"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">Distribución</div>
                        <div class="chart-card-subtitle">Estado actual</div>
                    </div>
                </div>
                <div style="position:relative;height:180px;">
                    <canvas id="chartDonut"></canvas>
                </div>
                <div class="donut-legend">
                    <?php
                    $legend = [
                        ['color'=>'#0099D8','label'=>'Estudiantes',    'val'=>$totals['students']],
                        ['color'=>'#F59E0B','label'=>'Instructores',   'val'=>$totals['instructors']],
                        ['color'=>'#004976','label'=>'Administradores','val'=>$totals['admins']],
                        ['color'=>'#10B981','label'=>'Activos',        'val'=>$totals['active']],
                        ['color'=>'#EF4444','label'=>'Inactivos',      'val'=>$totals['inactive'] + $totals['suspended']],
                    ];
                    foreach($legend as $l):
                        $pct = $totals['total'] > 0 ? round(($l['val']/$totals['total'])*100) : 0;
                    ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?= $l['color'] ?>"></div>
                        <span class="legend-label"><?= $l['label'] ?></span>
                        <span class="legend-value"><?= $l['val'] ?></span>
                        <span class="legend-pct"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Daily + Logins -->
        <div class="chart-grid-2" style="margin-bottom:20px;">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">Registros Diarios</div>
                        <div class="chart-card-subtitle">Últimos 30 días</div>
                    </div>
                    <span class="chart-badge">30 días</span>
                </div>
                <div style="position:relative;height:200px;">
                    <canvas id="chartDaily"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">Actividad de Logins</div>
                        <div class="chart-card-subtitle">Accesos diarios · 30 días</div>
                    </div>
                    <span class="chart-badge">30 días</span>
                </div>
                <div style="position:relative;height:200px;">
                    <canvas id="chartLogins"></canvas>
                </div>
            </div>
        </div>

        <!-- Departments -->
        <?php if (!empty($by_dept)): ?>
        <div class="section-title">Distribución por Departamento</div>
        <div class="chart-card" style="margin-bottom:20px;">
            <div class="chart-card-header">
                <div>
                    <div class="chart-card-title">Usuarios por Departamento</div>
                    <div class="chart-card-subtitle">Top <?= count($by_dept) ?> departamentos</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;">
                <div style="position:relative;height:280px;">
                    <canvas id="chartDept"></canvas>
                </div>
                <div>
                    <?php
                    $max_d = max(array_column($by_dept,'count'));
                    $colors = ['#0099D8','#004976','#10B981','#F59E0B','#8B5CF6','#EF4444','#06B6D4','#84CC16','#F97316','#EC4899'];
                    foreach($by_dept as $i => $d): ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                            <span style="color:var(--gray-700);font-weight:500;"><?= htmlspecialchars($d['dept']) ?></span>
                            <span style="font-weight:700;color:var(--gray-900);"><?= $d['count'] ?></span>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:<?= $max_d > 0 ? round(($d['count']/$max_d)*100) : 0 ?>%;background:<?= $colors[$i % count($colors)] ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- User lists -->
        <div class="section-title">Actividad de Usuarios</div>
        <div class="list-grid">
            <div class="list-card">
                <div class="list-card-header">
                    <div class="list-card-title">
                        <span style="color:var(--green)"><i class="fas fa-bolt"></i></span>
                        Más Activos Recientemente
                    </div>
                    <span style="font-size:12px;color:var(--gray-400);">Por último acceso</span>
                </div>
                <?php if (empty($top_active)): ?>
                <div class="empty-note">Sin datos de acceso disponibles</div>
                <?php else: foreach($top_active as $u):
                    $n = trim($u['first_name'].' '.$u['last_name']);
                    $words = explode(' ',$n);
                    $ini = strtoupper(substr($words[0],0,1).(isset($words[1])?substr($words[1],0,1):''));
                    $days = intval($u['days_since_login']);
                ?>
                <div class="list-item">
                    <div class="avatar-sm"><?= $ini ?></div>
                    <div class="li-info">
                        <div class="li-name"><?= htmlspecialchars($n) ?></div>
                        <div class="li-sub"><?= htmlspecialchars($u['department'] ?: $u['email']) ?></div>
                    </div>
                    <div class="li-right">
                        <span style="color:var(--green);font-weight:600;">
                            <?= $days === 0 ? 'Hoy' : 'Hace '.$days.'d' ?>
                        </span><br>
                        <span><?= date('d/m/Y', strtotime($u['last_login'])) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="list-card">
                <div class="list-card-header">
                    <div class="list-card-title">
                        <span style="color:var(--red)"><i class="fas fa-user-clock"></i></span>
                        Sin Actividad Reciente
                    </div>
                    <span style="font-size:12px;color:var(--gray-400);">>30 días / nunca</span>
                </div>
                <?php if (empty($inactive_users)): ?>
                <div class="empty-note">¡Todos los usuarios están activos!</div>
                <?php else: foreach($inactive_users as $u):
                    $n = trim($u['first_name'].' '.$u['last_name']);
                    $words = explode(' ',$n);
                    $ini = strtoupper(substr($words[0],0,1).(isset($words[1])?substr($words[1],0,1):''));
                    $dias_inactivo = $u['last_login'] ? round((time()-strtotime($u['last_login']))/86400) : null;
                ?>
                <div class="list-item">
                    <div class="avatar-sm" style="background:var(--red-light);color:var(--red);"><?= $ini ?></div>
                    <div class="li-info">
                        <div class="li-name"><?= htmlspecialchars($n) ?></div>
                        <div class="li-sub"><?= htmlspecialchars($u['department'] ?: $u['email']) ?></div>
                    </div>
                    <div class="li-right">
                        <?php if ($dias_inactivo !== null): ?>
                            <span style="color:var(--red);font-weight:600;"><?= $dias_inactivo ?>d sin acceso</span><br>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Nunca accedió</span><br>
                        <?php endif; ?>
                        <span>Reg. <?= date('d/m/Y', strtotime($u['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
Chart.defaults.font.family = "'Open Sans', sans-serif";
Chart.defaults.plugins.legend.display = false;
Chart.defaults.plugins.tooltip.backgroundColor = '#0F172A';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;

const G200 = '#E2E8F0';

(function(){
    const ctx = document.getElementById('chartMonthly').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,240);
    grad.addColorStop(0,'rgba(0,153,216,.85)');
    grad.addColorStop(1,'rgba(0,153,216,.15)');
    new Chart(ctx,{
        type:'bar',
        data:{
            labels: <?= json_encode($months_labels) ?>,
            datasets:[{ data:<?= json_encode($months_data) ?>, backgroundColor:grad, borderColor:'#0099D8', borderWidth:2, borderRadius:6, borderSkipped:false }]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            scales:{
                x:{ grid:{display:false}, ticks:{font:{size:11},color:'#94A3B8'} },
                y:{ grid:{color:G200}, ticks:{font:{size:11},color:'#94A3B8',stepSize:1}, border:{display:false} }
            },
            plugins:{ tooltip:{callbacks:{label:c=>` ${c.parsed.y} registros`}} }
        }
    });
})();

new Chart(document.getElementById('chartDonut').getContext('2d'),{
    type:'doughnut',
    data:{
        labels:['Estudiantes','Instructores','Administradores','Activos','Inactivos+Susp'],
        datasets:[{
            data:[<?= $totals['students'] ?>,<?= $totals['instructors'] ?>,<?= $totals['admins'] ?>,<?= $totals['active'] ?>,<?= $totals['inactive']+$totals['suspended'] ?>],
            backgroundColor:['#0099D8','#F59E0B','#004976','#10B981','#EF4444'],
            borderWidth:3, borderColor:'#fff', hoverOffset:6
        }]
    },
    options:{
        responsive:true, maintainAspectRatio:false, cutout:'68%',
        plugins:{ tooltip:{callbacks:{label:c=>` ${c.label}: ${c.parsed}`}} }
    }
});

(function(){
    const ctx = document.getElementById('chartDaily').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,200);
    grad.addColorStop(0,'rgba(0,153,216,.2)'); grad.addColorStop(1,'rgba(0,153,216,0)');
    new Chart(ctx,{
        type:'line',
        data:{
            labels:<?= json_encode($days_labels) ?>,
            datasets:[{ data:<?= json_encode($days_data) ?>, borderColor:'#0099D8', backgroundColor:grad, borderWidth:2, fill:true, tension:.4, pointRadius:3, pointBackgroundColor:'#0099D8' }]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            scales:{
                x:{ grid:{display:false}, ticks:{font:{size:10},color:'#94A3B8',maxTicksLimit:8} },
                y:{ grid:{color:G200}, ticks:{font:{size:11},color:'#94A3B8',stepSize:1}, border:{display:false} }
            }
        }
    });
})();

(function(){
    const ctx = document.getElementById('chartLogins').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,200);
    grad.addColorStop(0,'rgba(16,185,129,.2)'); grad.addColorStop(1,'rgba(16,185,129,0)');
    new Chart(ctx,{
        type:'line',
        data:{
            labels:<?= json_encode($login_labels) ?>,
            datasets:[{ data:<?= json_encode($login_data) ?>, borderColor:'#10B981', backgroundColor:grad, borderWidth:2, fill:true, tension:.4, pointRadius:3, pointBackgroundColor:'#10B981' }]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            scales:{
                x:{ grid:{display:false}, ticks:{font:{size:10},color:'#94A3B8',maxTicksLimit:8} },
                y:{ grid:{color:G200}, ticks:{font:{size:11},color:'#94A3B8',stepSize:1}, border:{display:false} }
            }
        }
    });
})();

<?php if(!empty($by_dept)): ?>
new Chart(document.getElementById('chartDept').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?= json_encode($dept_labels) ?>,
        datasets:[{
            data:<?= json_encode($dept_data) ?>,
            backgroundColor:['#0099D8','#004976','#10B981','#F59E0B','#8B5CF6','#EF4444','#06B6D4','#84CC16','#F97316','#EC4899'],
            borderRadius:4, borderSkipped:false
        }]
    },
    options:{
        indexAxis:'y', responsive:true, maintainAspectRatio:false,
        scales:{
            x:{ grid:{color:G200}, ticks:{font:{size:11},color:'#94A3B8'}, border:{display:false} },
            y:{ grid:{display:false}, ticks:{font:{size:11},color:'#334155'} }
        },
        plugins:{ tooltip:{callbacks:{label:c=>` ${c.parsed.x} usuarios`}} }
    }
});
<?php endif; ?>

const STATS_DATA = {
  totals: {
    total:       <?= (int)$totals['total'] ?>,
    admins:      <?= (int)$totals['admins'] ?>,
    instructors: <?= (int)$totals['instructors'] ?>,
    students:    <?= (int)$totals['students'] ?>,
    active:      <?= (int)$totals['active'] ?>,
    inactive:    <?= (int)$totals['inactive'] ?>,
    suspended:   <?= (int)$totals['suspended'] ?>,
    new_today:   <?= (int)$totals['new_today'] ?>,
    new_week:    <?= (int)$totals['new_week'] ?>,
    new_month:   <?= (int)$totals['new_month'] ?>,
  },
  byMonth:  <?= json_encode(array_map(null, $months_labels, $months_data)) ?>,
  byDay:    <?= json_encode(array_map(null, $days_labels, $days_data)) ?>,
  byDept:   <?= json_encode(array_map(null, $dept_labels, $dept_data)) ?>,
  byLogin:  <?= json_encode(array_map(null, $login_labels, $login_data)) ?>,
  topActive: <?= json_encode($top_active) ?>,
  inactive:  <?= json_encode($inactive_users) ?>,
  growthRate: <?= $growth_rate ?>,
  generatedAt: "<?= date('d/m/Y H:i') ?>"
};

function exportXLSX() {
  const wb = XLSX.utils.book_new();
  const t = STATS_DATA.totals;
  const date = new Date().toISOString().slice(0,10);

  // Sheet 1: Resumen KPIs
  const summary = [
    ['Estadísticas de Usuarios — Whirlpool Training'],
    ['Generado', STATS_DATA.generatedAt],
    [],
    ['Métrica', 'Valor'],
    ['Total de usuarios', t.total],
    ['Usuarios activos', t.active],
    ['Usuarios inactivos', t.inactive],
    ['Usuarios suspendidos', t.suspended],
    ['Administradores', t.admins],
    ['Instructores', t.instructors],
    ['Estudiantes', t.students],
    ['Nuevos hoy', t.new_today],
    ['Nuevos esta semana', t.new_week],
    ['Nuevos este mes', t.new_month],
    ['Crecimiento vs mes anterior', t.new_month > 0 ? `${STATS_DATA.growthRate}%` : '—'],
  ];
  const ws1 = XLSX.utils.aoa_to_sheet(summary);
  ws1['!cols'] = [{ wch: 30 }, { wch: 16 }];
  XLSX.utils.book_append_sheet(wb, ws1, 'Resumen');

  // Sheet 2: Registros por mes
  const ws2 = XLSX.utils.aoa_to_sheet([
    ['Mes', 'Registros'],
    ...STATS_DATA.byMonth.map(([m, c]) => [m, c])
  ]);
  ws2['!cols'] = [{ wch: 18 }, { wch: 12 }];
  XLSX.utils.book_append_sheet(wb, ws2, 'Por Mes');

  // Sheet 3: Por departamento
  const ws3 = XLSX.utils.aoa_to_sheet([
    ['Departamento', 'Usuarios'],
    ...STATS_DATA.byDept.map(([d, c]) => [d, c])
  ]);
  ws3['!cols'] = [{ wch: 28 }, { wch: 12 }];
  XLSX.utils.book_append_sheet(wb, ws3, 'Por Departamento');

  // Sheet 4: Usuarios más activos
  const ws4 = XLSX.utils.aoa_to_sheet([
    ['Nombre', 'Email', 'Departamento', 'Rol', 'Último login'],
    ...STATS_DATA.topActive.map(u => [
      `${u.first_name} ${u.last_name}`,
      u.email,
      u.department || '—',
      u.role,
      u.last_login ? new Date(u.last_login).toLocaleDateString('es-MX') : '—'
    ])
  ]);
  ws4['!cols'] = [{ wch: 26 }, { wch: 30 }, { wch: 22 }, { wch: 14 }, { wch: 16 }];
  XLSX.utils.book_append_sheet(wb, ws4, 'Usuarios Activos');

  // Sheet 5: Sin actividad
  const ws5 = XLSX.utils.aoa_to_sheet([
    ['Nombre', 'Email', 'Departamento', 'Rol', 'Último login', 'Registro'],
    ...STATS_DATA.inactive.map(u => [
      `${u.first_name} ${u.last_name}`,
      u.email,
      u.department || '—',
      u.role,
      u.last_login ? new Date(u.last_login).toLocaleDateString('es-MX') : 'Nunca',
      new Date(u.created_at).toLocaleDateString('es-MX')
    ])
  ]);
  ws5['!cols'] = [{ wch: 26 }, { wch: 30 }, { wch: 22 }, { wch: 14 }, { wch: 16 }, { wch: 14 }];
  XLSX.utils.book_append_sheet(wb, ws5, 'Sin Actividad');

  XLSX.writeFile(wb, `estadisticas_usuarios_${date}.xlsx`);
}

function exportPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const t = STATS_DATA.totals;
  const date = new Date().toISOString().slice(0,10);

  doc.setFillColor(0, 73, 118);
  doc.rect(0, 0, pageW, 22, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(14);
  doc.setFont('helvetica', 'bold');
  doc.text('Whirlpool Training — Estadísticas de Usuarios', 14, 14);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text(`Generado: ${STATS_DATA.generatedAt}`, pageW - 14, 14, { align: 'right' });

  // KPI summary boxes
  const kpis = [
    ['Total Usuarios', t.total, [0, 153, 216]],
    ['Activos', t.active, [16, 185, 129]],
    ['Nuevos este mes', t.new_month, [245, 158, 11]],
    ['Nuevos hoy', t.new_today, [139, 92, 246]],
  ];
  const boxW = (pageW - 28 - 12) / 4;
  kpis.forEach(([label, value, color], i) => {
    const x = 14 + i * (boxW + 4);
    doc.setFillColor(...color);
    doc.roundedRect(x, 28, boxW, 22, 3, 3, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text(String(value), x + boxW / 2, 38, { align: 'center' });
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.text(label, x + boxW / 2, 44, { align: 'center' });
  });

  // Role breakdown
  doc.setTextColor(30, 30, 30);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text(`Admins: ${t.admins}  |  Instructores: ${t.instructors}  |  Estudiantes: ${t.students}  |  Suspendidos: ${t.suspended}  |  Crecimiento: ${STATS_DATA.growthRate}%`, 14, 58);

  // Registros por mes table
  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(0, 73, 118);
  doc.text('Registros por mes (últimos 12)', 14, 66);

  doc.autoTable({
    head: [['Mes', 'Registros']],
    body: STATS_DATA.byMonth.map(([m, c]) => [m, c]),
    startY: 69,
    styles: { font: 'helvetica', fontSize: 8, cellPadding: 2.5 },
    headStyles: { fillColor: [0, 153, 216], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: { 0: { cellWidth: 50 }, 1: { cellWidth: 30, halign: 'center' } },
    margin: { left: 14, right: 14 },
    tableWidth: 84
  });

  // Por departamento
  const afterMonth = doc.lastAutoTable.finalY + 6;
  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(0, 73, 118);
  doc.text('Usuarios por departamento', 14, afterMonth);

  doc.autoTable({
    head: [['Departamento', 'Usuarios']],
    body: STATS_DATA.byDept.map(([d, c]) => [d, c]),
    startY: afterMonth + 3,
    styles: { font: 'helvetica', fontSize: 8, cellPadding: 2.5 },
    headStyles: { fillColor: [0, 73, 118], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: { 0: { cellWidth: 80 }, 1: { cellWidth: 30, halign: 'center' } },
    margin: { left: 14, right: 14 },
    tableWidth: 114
  });

  // Page 2: user lists
  doc.addPage();
  doc.setFillColor(0, 73, 118);
  doc.rect(0, 0, pageW, 14, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.text('Usuarios más activos (últimos 30 días)', 14, 10);

  doc.autoTable({
    head: [['Nombre', 'Departamento / Email', 'Último login']],
    body: STATS_DATA.topActive.map(u => [
      `${u.first_name} ${u.last_name}`,
      u.department || u.email,
      u.last_login ? new Date(u.last_login).toLocaleDateString('es-MX') : '—'
    ]),
    startY: 18,
    styles: { font: 'helvetica', fontSize: 8, cellPadding: 3 },
    headStyles: { fillColor: [16, 185, 129], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    margin: { left: 14, right: 14 }
  });

  const afterActive = doc.lastAutoTable.finalY + 8;
  doc.setFillColor(239, 68, 68);
  doc.rect(0, afterActive - 6, pageW, 14, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.text('Sin actividad reciente (>30 días / nunca)', 14, afterActive + 4);

  doc.autoTable({
    head: [['Nombre', 'Departamento / Email', 'Último login', 'Registro']],
    body: STATS_DATA.inactive.map(u => [
      `${u.first_name} ${u.last_name}`,
      u.department || u.email,
      u.last_login ? new Date(u.last_login).toLocaleDateString('es-MX') : 'Nunca',
      new Date(u.created_at).toLocaleDateString('es-MX')
    ]),
    startY: afterActive + 10,
    styles: { font: 'helvetica', fontSize: 8, cellPadding: 3 },
    headStyles: { fillColor: [239, 68, 68], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [254, 242, 242] },
    margin: { left: 14, right: 14 }
  });

  // Footer all pages
  const totalPages = doc.internal.getNumberOfPages();
  for (let p = 1; p <= totalPages; p++) {
    doc.setPage(p);
    doc.setFontSize(8);
    doc.setTextColor(148, 163, 184);
    doc.text(`Página ${p} de ${totalPages}`, pageW / 2, doc.internal.pageSize.getHeight() - 6, { align: 'center' });
  }

  doc.save(`estadisticas_usuarios_${date}.pdf`);
}
</script>
<!-- Export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
</body>
</html>