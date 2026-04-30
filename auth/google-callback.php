<?php
/**
 * auth/google-callback.php
 * Recibe la respuesta de Google, valida y crea sesión
 */

session_start();
require_once '../config/database.php';
require_once '../config/google-oauth.php';

try {
    // 1. Validar estado anti-CSRF
    if (empty($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        throw new Exception('Estado de seguridad inválido. Intenta de nuevo.');
    }
    unset($_SESSION['oauth_state']);

    // 2. Verificar si Google retornó error
    if (isset($_GET['error'])) {
        throw new Exception('Acceso denegado por Google: ' . htmlspecialchars($_GET['error']));
    }

    if (empty($_GET['code'])) {
        throw new Exception('No se recibió código de autorización de Google.');
    }

    // 3. Intercambiar código por access_token
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'code'          => $_GET['code'],
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri'  => GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ]),
            'ignore_errors' => true,
        ]
    ]);

    $tokenResponse = file_get_contents(GOOGLE_TOKEN_URL, false, $context);
    $tokenData     = json_decode($tokenResponse, true);

    if (empty($tokenData['access_token'])) {
        throw new Exception('No se pudo obtener el token de acceso de Google.');
    }

    // 4. Obtener datos del usuario desde Google
    $userContext = stream_context_create([
        'http' => [
            'header'        => "Authorization: Bearer " . $tokenData['access_token'] . "\r\n",
            'ignore_errors' => true,
        ]
    ]);

    $userInfoResponse = file_get_contents(GOOGLE_USERINFO_URL, false, $userContext);
    $googleUser       = json_decode($userInfoResponse, true);

    if (empty($googleUser['email'])) {
        throw new Exception('No se pudo obtener el correo del usuario desde Google.');
    }

    $email     = strtolower(trim($googleUser['email']));
    $firstName = $googleUser['given_name']  ?? '';
    $lastName  = $googleUser['family_name'] ?? '';

    // 5. Buscar usuario en la BD por email
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, email, department, role, status
        FROM users
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    // 6. Si el usuario no existe en el LMS, redirigir con error
    if (!$user) {
        header('Location: ../login.php?google_error=no_account&email=' . urlencode($email));
        exit();
    }

    // 7. Verificar que la cuenta esté activa
    if ($user['status'] !== 'active') {
        $msg = ($user['status'] === 'suspended')
            ? 'Tu cuenta ha sido suspendida. Contacta al administrador.'
            : 'Tu cuenta está inactiva. Contacta al administrador.';
        throw new Exception($msg);
    }

    // 8. Crear sesión (misma estructura que login_process.php)
    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_name']   = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['department']  = $user['department'];
    $_SESSION['login_time']  = time();
    $_SESSION['auth_method'] = 'google';

    // 9. Actualizar último login
    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update->bind_param("i", $user['user_id']);
    $update->execute();
    $update->close();

    $conn->close();

    // 10. Redirigir según rol
    $redirect = ($user['role'] === 'admin') ? '../admin/index.php' : '../courses.php';
    header('Location: ' . $redirect);
    exit();

} catch (Exception $e) {
    if (isset($conn)) $conn->close();
    header('Location: ../login.php?google_error=' . urlencode($e->getMessage()));
    exit();
}
