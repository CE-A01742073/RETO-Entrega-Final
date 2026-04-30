<?php
/**
 * auth/google-login.php
 * Inicia el flujo OAuth2 con Google
 */

session_start();
require_once '../config/google-oauth.php';

// Token anti-CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
    // Descomentar para forzar solo cuentas del dominio corporativo:
    // 'hd'         => 'whirlpool.com',
]);

header('Location: ' . GOOGLE_AUTH_URL . '?' . $params);
exit();
