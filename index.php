<?php
/**
 * Página principal del sistema
 * Redirige a usuarios autenticados a courses o muestra página de bienvenida
 */

session_start();

// Verificar si el usuario ya tiene sesión activa
if (isset($_SESSION['user_id'])) {
    header("Location: courses.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whirlpool - Plataforma de Capacitación Digital</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.00">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .whirlpool-accomplished {
            background: #003C64 !important;
            background: linear-gradient(135deg, #003C64 0%, #0096DC 100%) !important;
        }
        .whirlpool-accent {
            background: #0096DC !important;
            background: linear-gradient(135deg, #0096DC 0%, #00AEEF 100%) !important;
        }
        .whirlpool-dark {
            background: #002A47 !important;
            background: linear-gradient(135deg, #002A47 0%, #003C64 100%) !important;
        }
    </style>
</head>
<body>
    <!-- Navegación superior -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="brand-logo">
            </div>
            <div class="nav-actions">
                <a href="login.php" class="nav-link">Iniciar Sesión</a>
                <a href="login.php?action=register" class="btn-nav-primary">Comenzar</a>
            </div>
        </div>
    </nav>

    <!-- Sección Hero -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-dot"></span>
                    Transformación Digital 2026
                </div>
                <h1 class="hero-title">
                    Potencia tu crecimiento profesional con
                    <span class="gradient-text">Inteligencia Artificial</span>
                </h1>
                <p class="hero-description">
                    Plataforma empresarial de aprendizaje continuo diseñada para impulsar 
                    el desarrollo de habilidades tecnológicas en la era digital
                </p>
                <div class="hero-actions">
                    <a href="login.php?action=register" class="btn-hero-primary">
                        <span>Crear Cuenta</span>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number">1,200+</div>
                        <div class="stat-label">Empleados Activos</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-number">95%</div>
                        <div class="stat-label">Satisfacción</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Soporte IA</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="visual-card visual-card-1">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="card-content">
                        <div class="card-title">Progreso del Día</div>
                        <div class="card-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 67%"></div>
                            </div>
                            <span class="progress-text">67%</span>
                        </div>
                    </div>
                </div>
                <div class="visual-card visual-card-2">
                    <div class="achievement-icon"> <img src="assets/images/star.png" alt="Whirlpool" class="brand-logo"> </div>
                    <div class="achievement-text">Nivel 5 Alcanzado</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección de características -->
    <section class="features-section" id="features">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label">Características</span>
                <h2 class="section-title">Todo lo que necesitas para aprender y crecer</h2>
                <p class="section-description">
                    Herramientas profesionales diseñadas para maximizar tu potencial
                </p>
            </div>
            
            <div class="features-grid">
                <!-- Biblioteca de Cursos -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accomplished">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Biblioteca de Cursos</h3>
                    <p class="feature-description">
                        Accede a contenido actualizado sobre IA, automatización y 
                        tecnologías emergentes diseñado por expertos
                    </p>
                </div>
    
                <!-- Analytics Avanzado -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Analytics Avanzado</h3>
                    <p class="feature-description">
                        Seguimiento detallado de tu progreso con métricas en tiempo 
                        real y reportes personalizados
                    </p>
                </div>
    
                <!-- Aprendizaje Colaborativo -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accomplished">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Aprendizaje Colaborativo</h3>
                    <p class="feature-description">
                        Conecta con colegas, forma equipos de estudio y participa 
                        en proyectos grupales
                    </p>
                </div>
    
                <!-- Asistente IA -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Asistente IA</h3>
                    <p class="feature-description">
                        Obtén respuestas instantáneas y recomendaciones personalizadas 
                        con nuestro asistente virtual
                    </p>
                </div>
    
                <!-- Rutas de Aprendizaje -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accomplished">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Rutas de Aprendizaje</h3>
                    <p class="feature-description">
                        Planes estructurados que se adaptan a tu ritmo y objetivos 
                        profesionales específicos
                    </p>
                </div>
    
                <!-- Gamificación -->
                <div class="feature-item">
                    <div class="feature-icon whirlpool-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Gamificación</h3>
                    <p class="feature-description">
                        Gana insignias, sube de nivel y compite en rankings mientras 
                        desarrollas nuevas habilidades
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer corporativo -->
    <footer class="main-footer">
    <div class="footer-container">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="assets/images/logo_whirlpool.png" alt="Whirlpool" class="footer-logo">
            </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Plataforma</h4>
                        <a href="#">Cursos</a>
                    </div>
                    <div class="footer-column">
                        <h4>Recursos</h4>
                        <a href="#">Documentación</a>
                        <a href="#">Ayuda</a>
                    </div>
                    <div class="footer-column">
                        <h4>Empresa</h4>
                        <a href="#">Acerca de</a>
                        <a href="#">Contacto</a>
                        <a href="#">Privacidad</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Whirlpool Corporation. Todos los derechos reservados.</p>
                <div class="footer-legal">
                    <a href="#">Términos de Servicio</a>
                    <span>•</span>
                    <a href="#">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>
    <script src="/js/faq-widget.js?v=1.12.1"></script>
</body>
</html>