<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Usuario';
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';

if (!defined('NEWS_API_KEY')) {
    $envPath = dirname(__DIR__, 1) . '/.env';
    if (file_exists($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
    define('NEWS_API_KEY', $_ENV['NEWS_API_KEY'] ?? '');
    define('NEWS_API_URL', $_ENV['NEWS_API_URL'] ?? 'https://newsdata.io/api/1/latest');
}

$tabs = [
    'technology'    => ['label' => 'Tecnología', 'q' => 'software innovation digital tech', 'cat' => 'technology'],
    'ai'            => ['label' => 'Inteligencia Artificial', 'q' => '"artificial intelligence" OR "machine learning" OR "ChatGPT" OR "generative AI"', 'cat' => 'technology'],
    'manufacturing' => ['label' => 'Manufactura', 'q' => 'manufacturing industry supply chain production', 'cat' => 'business'],
    'appliances'    => ['label' => 'Electrodomésticos', 'q' => 'home appliances consumer electronics household', 'cat' => 'business'],
    'training'      => ['label' => 'Capacitación', 'q' => 'corporate training workforce learning development', 'cat' => 'education'],
];

$active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs)
    ? $_GET['tab']
    : 'technology';

$tab_cfg = $tabs[$active_tab];

function fetchNews(string $query, string $category = ''): array {
    $params = [
        'apikey'   => NEWS_API_KEY,
        'q'        => $query,
        'language' => 'es,en',
    ];
    if ($category) $params['category'] = $category;
    $url = NEWS_API_URL . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'WhirlpoolLearning/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $raw === '') return ['error' => 'No se pudo conectar con la API de noticias. ' . $err];

    $data = json_decode($raw, true);
    if (($data['status'] ?? '') !== 'success') {
        return ['error' => $data['message'] ?? 'Error desconocido de la API.'];
    }

    return $data['results'] ?? [];
}

$news  = fetchNews($tab_cfg['q'], $tab_cfg['cat'] ?? '');
$error = null;
if (isset($news['error'])) {
    $error = $news['error'];
    $news  = [];
}

function timeAgo(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 3600)   return round($diff / 60) . ' min';
    if ($diff < 86400)  return round($diff / 3600) . 'h';
    if ($diff < 604800) return round($diff / 86400) . 'd';
    return date('d M Y', strtotime($dateStr));
}

function excerpt(string $text, int $len = 120): string {
    $text = strip_tags($text);
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias – Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=3.91">
    <link rel="stylesheet" href="css/courses.css?v=1.11.02">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01.01">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/news.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="brand-logo">
        </div>
        <div class="nav-menu" id="navMenu">
            <a href="news.php"       class="nav-link active">Noticias</a>
            <a href="courses.php"    class="nav-link">Cursos</a>
            <a href="blog.php"       class="nav-link">Comunidad</a>
            <a href="friends.php"    class="nav-link">Amigos</a>
        </div>
        <div class="nav-user">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
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

<main class="news-main">

<section class="news-hero">
    <div class="hero-content">
        <h1>Noticias Globales</h1>
        <p>Mantente al día con las últimas noticias de tecnología, IA, manufactura y más.</p>
    </div>
</section>

<div class="tabs-wrap">
    <?php foreach ($tabs as $key => $cfg): ?>
        <a href="?tab=<?php echo $key; ?>"
           class="tab-btn <?php echo $active_tab === $key ? 'active' : ''; ?>">
            <?php echo $cfg['icon']; ?> <?php echo $cfg['label']; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="news-grid-section">

    <div class="section-header">
        <h2><?php echo $tab_cfg['icon'] . ' ' . $tab_cfg['label']; ?></h2>
        <?php if (!empty($news)): ?>
            <span class="badge-count"><?php echo count($news); ?> artículos</span>
        <?php endif; ?>
        <a href="?tab=<?php echo $active_tab; ?>" class="refresh-btn">
            ↻ Actualizar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="msg-box">
            <div class="icon">⚠️</div>
            <h3>No se pudieron cargar las noticias</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>

    <?php elseif (empty($news)): ?>
        <div class="msg-box">
            <div class="icon">📭</div>
            <h3>Sin resultados</h3>
            <p>No se encontraron noticias para esta categoría en este momento. Intenta más tarde.</p>
        </div>

    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($news as $art): ?>
                <?php
                    $url     = htmlspecialchars($art['link'] ?? '#');
                    $title   = htmlspecialchars($art['title'] ?? 'Sin título');
                    $source  = htmlspecialchars($art['source_name'] ?? $art['source_id'] ?? '');
                    $desc    = htmlspecialchars(excerpt($art['description'] ?? $art['content'] ?? ''));
                    $img     = $art['image_url'] ?? null;
                    $lang    = strtoupper($art['language'] ?? '');
                    $pubdate = $art['pubDate'] ?? '';
                    $ago     = $pubdate ? timeAgo($pubdate) : '';
                ?>
                <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer" class="news-card">
                    <?php if ($img): ?>
                        <img class="card-img"
                             src="<?php echo htmlspecialchars($img); ?>"
                             alt="<?php echo $title; ?>"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="card-img-placeholder" style="display:none;">
                            <?php echo $tab_cfg['icon']; ?>
                        </div>
                    <?php else: ?>
                        <div class="card-img-placeholder"><?php echo $tab_cfg['icon']; ?></div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="card-source">
                            <span class="source-name"><?php echo $source; ?></span>
                            <?php if ($ago): ?>
                                <span class="card-time"><?php echo $ago; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="card-title"><?php echo $title; ?></div>

                        <?php if ($desc): ?>
                            <div class="card-desc"><?php echo $desc; ?></div>
                        <?php endif; ?>

                        <div class="card-footer">
                            <?php if ($lang): ?>
                                <span class="lang-tag"><?php echo $lang; ?></span>
                            <?php endif; ?>
                            <span class="read-more">Leer más →</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</main>

    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="/js/chatbot-widget.js?v=1.12.11"></script>
    <?php endif; ?>
    <script src="/js/faq-widget.js?v=1.0"></script>
    <script src="/js/theme.js?v=1.01.05"></script>
    <script src="/js/leaderboard-widget.js?v=1.0.0"></script>
    <script src="js/notifications-widget.js"></script>
    <script>
        const userInfo = document.querySelector('.user-info');
        const userDropdown = document.querySelector('.user-dropdown');
        if (userInfo && userDropdown) {
            userInfo.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = userDropdown.style.opacity === '1';
                userDropdown.style.opacity     = isOpen ? '0'       : '1';
                userDropdown.style.visibility  = isOpen ? 'hidden'  : 'visible';
                userDropdown.style.transform   = isOpen ? 'translateY(-10px)' : 'translateY(0)';
            });
            document.addEventListener('click', function() {
                userDropdown.style.opacity    = '0';
                userDropdown.style.visibility = 'hidden';
                userDropdown.style.transform  = 'translateY(-10px)';
            });
        }
    </script>
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