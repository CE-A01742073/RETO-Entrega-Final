<?php
/**
 * Página de autenticación de usuarios
 * Maneja inicio de sesión y registro con diseño profesional
 */

session_start();

// Redirigir si ya hay sesión activa
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// El registro ya no está disponible públicamente (solo admins pueden crear cuentas)
$show_register = false;

// Capturar error de Google OAuth si viene
$google_error = '';
if (!empty($_GET['google_error'])) {
    $err = $_GET['google_error'];
    if ($err === 'no_account') {
        $google_email = htmlspecialchars($_GET['email'] ?? '');
        $google_error = 'El correo <strong>' . $google_email . '</strong> no tiene una cuenta en Whirlpool Learning. Contacta al administrador.';
    } else {
        $google_error = htmlspecialchars($err);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Whirlpool Learning</title>
    <link rel="stylesheet" href="css/styles.css?v=4.22.02">
    <link rel="stylesheet" href="/css/dark-mode.css?v=1.01">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Botón Google SSO ── */
        .google-sso-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 11px 20px;
            background: #ffffff;
            border: 1.5px solid #dadce0;
            border-radius: 8px;
            font-family: 'Open Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #3c4043;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .google-sso-btn:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 6px rgba(0,0,0,0.13);
        }
        .google-sso-btn svg {
            flex-shrink: 0;
        }
        .sso-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0;
            color: #9aa0a8;
            font-size: 13px;
        }
        .sso-divider::before,
        .sso-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }
        .google-error-msg {
            background: #fff3f3;
            border: 1px solid #f5c6c6;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #c0392b;
            margin-bottom: 16px;
            line-height: 1.5;
        }
    </style>
</head>
<body class="auth-page">
    <!-- Fondo animado con burbujas -->
    <div class="bubbles-container">
        <div class="bubble bubble-1"></div>
        <div class="bubble bubble-2"></div>
        <div class="bubble bubble-3"></div>
        <div class="bubble bubble-4"></div>
        <div class="bubble bubble-5"></div>
        <div class="bubble bubble-6"></div>
        <div class="bubble bubble-7"></div>
        <div class="bubble bubble-8"></div>
        <div class="bubble bubble-9"></div>
        <div class="bubble bubble-10"></div>
    </div>

    <!-- Contenedor de autenticación -->
    <div class="auth-wrapper">
        <div class="auth-container">
            <!-- Panel izquierdo informativo -->
            <div class="auth-panel">
                <div class="panel-content">
                    <div class="panel-logo">
                        <img src="assets/images/logo_whirlpool.png" alt="Whirlpool">
                    </div>
                    <p class="panel-subtitle">Plataforma de Transformación Digital</p>
                    <div class="panel-features">
                        <div class="panel-feature">
                            <svg class="check-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Acceso a más de 200 cursos especializados</span>
                        </div>
                        <div class="panel-feature">
                            <svg class="check-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Certificaciones reconocidas internacionalmente</span>
                        </div>
                        <div class="panel-feature">
                            <svg class="check-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Soporte 24/7 con asistente IA</span>
                        </div>
                    </div>

                    <div class="panel-quote">
                        <p>"Hemos trabajado continuamente para crear un entorno participativo e inclusivo en toda nuestra organización"</p>
                        <div class="quote-author">
                            <strong>Marc Bitzer</strong>
                            <span>CEO Whirlpool</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel derecho con formulario -->
            <div class="auth-form-panel">
                <div class="form-header">
                    <a href="index.php" class="back-link">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                            <path d="M12.5 15L7.5 10L12.5 5" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Volver</span>
                    </a>
                    <h2 class="form-title">Bienvenido de nuevo</h2>
                    <p class="form-description">Ingresa tus credenciales para continuar</p>
                </div>

                <?php if (!$show_register): ?>
                    <!-- ── Error de Google OAuth ── -->
                    <?php if ($google_error): ?>
                        <div class="google-error-msg"><?= $google_error ?></div>
                    <?php endif; ?>

                    <!-- ── Botón Google SSO ── -->
                    <a href="auth/google-login.php" class="google-sso-btn">
                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                            <g fill="none" fill-rule="evenodd">
                                <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                                <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
                                <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                                <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                            </g>
                        </svg>
                        Iniciar sesión con Google
                    </a>

                    <!-- ── Separador ── -->
                    <div class="sso-divider">o continúa con tu correo</div>

                    <!-- Formulario de Inicio de Sesión -->
                    <form id="loginForm" class="auth-form" method="POST" action="process/login_process.php">

                        <!-- Inputs ocultos: información del dispositivo -->
                        <input type="hidden" id="screen_resolution" name="screen_resolution">
                        <input type="hidden" id="browser_language" name="browser_language">
                        <input type="hidden" id="timezone" name="timezone">
                        <input type="hidden" id="cpu_cores" name="cpu_cores">
                        <input type="hidden" id="ram_gb" name="ram_gb">
                        <input type="hidden" id="gpu_renderer" name="gpu_renderer">
                        <input type="hidden" id="cpu_architecture" name="cpu_architecture">
                        <input type="hidden" id="connection_type" name="connection_type">

                        <div class="form-group">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                    <path d="M2.5 6.667L10 11.25l7.5-4.583M3.333 15h13.334c.92 0 1.666-.746 1.666-1.667V6.667c0-.92-.746-1.667-1.666-1.667H3.333c-.92 0-1.666.746-1.666 1.667v6.666c0 .92.746 1.667 1.666 1.667z" stroke-width="1.5"/>
                                </svg>
                                <input type="email" id="email" name="email" 
                                       class="form-input" required 
                                       placeholder="tu.correo@whirlpool.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                    <path d="M5.833 9.167V6.667a4.167 4.167 0 118.334 0v2.5m-9.584 0h10.834c.92 0 1.666.746 1.666 1.666v5.834c0 .92-.746 1.666-1.666 1.666H4.583c-.92 0-1.666-.746-1.666-1.666v-5.834c0-.92.746-1.666 1.666-1.666z" stroke-width="1.5"/>
                                </svg>
                                <input type="password" id="password" name="password" 
                                       class="form-input" required 
                                       placeholder="Ingresa tu contraseña">
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="remember_me">
                                <span class="checkbox-label">Recordar sesión</span>
                            </label>
                            <a href="#" class="link-text">¿Olvidaste tu contraseña?</a>
                        </div>

                        <button type="submit" class="btn-submit">
                            Iniciar Sesión
                        </button>

                        <div class="form-divider">
                            <span>¿No tienes cuenta?</span>
                        </div>

                        <p style="text-align:center;font-size:13px;color:#64748B;margin-top:4px;">
                            Las cuentas son creadas por un administrador.<br>
                            Contacta a tu área de Recursos Humanos o TI.
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/auth.js?v=1.2"></script>
    <script src="/js/theme.js?v=1.01.04"></script>

    <!-- Recolección de información del dispositivo -->
    <script>
    (function collectDeviceInfo() {
        var form = document.getElementById('loginForm');
        if (!form) return;

        var resEl = document.getElementById('screen_resolution');
        if (resEl) resEl.value = screen.width + 'x' + screen.height;

        var langEl = document.getElementById('browser_language');
        if (langEl) langEl.value = navigator.language || navigator.userLanguage || '';

        var tzEl = document.getElementById('timezone');
        if (tzEl) {
            try { tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; }
            catch(e) {}
        }

        var coresEl = document.getElementById('cpu_cores');
        if (coresEl) coresEl.value = navigator.hardwareConcurrency || '';

        var ramEl = document.getElementById('ram_gb');
        if (ramEl) ramEl.value = navigator.deviceMemory || '';

        var archEl = document.getElementById('cpu_architecture');
        if (archEl && navigator.userAgentData) {
            navigator.userAgentData.getHighEntropyValues(['architecture'])
                .then(function(data) {
                    archEl.value = data.architecture || '';
                }).catch(function() {});
        }

        var connEl = document.getElementById('connection_type');
        if (connEl) {
            var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            connEl.value = conn ? (conn.effectiveType || conn.type || '') : '';
        }

        var gpuEl = document.getElementById('gpu_renderer');
        if (gpuEl) {
            try {
                var canvas = document.createElement('canvas');
                var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (gl) {
                    var ext = gl.getExtension('WEBGL_debug_renderer_info');
                    if (ext) {
                        gpuEl.value = gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '';
                    }
                }
            } catch(e) {}
        }
    })();
    </script>
</body>
</html>