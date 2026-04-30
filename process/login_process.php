<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}
function checkLoginAttempts($conn, $email, $ip_address) {
    $time_limit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? 
        AND ip_address = ? 
        AND attempt_time > ? 
        AND success = FALSE
    ");
    
    $stmt->bind_param("sss", $email, $ip_address, $time_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['attempts'];
}
function logLoginAttempt($conn, $email, $ip_address, $success) {
    $stmt = $conn->prepare("
        INSERT INTO login_attempts (email, ip_address, success) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->bind_param("ssi", $email, $ip_address, $success);
    $stmt->execute();
    $stmt->close();
}

try {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    if (empty($email) || empty($password)) {
        throw new Exception('Correo electrónico y contraseña son obligatorios');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de correo electrónico inválido');
    }
    $failed_attempts = checkLoginAttempts($conn, $email, $ip_address);
    
    if ($failed_attempts >= 5) {
        throw new Exception('Demasiados intentos fallidos. Por favor intente en 15 minutos');
    }
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, email, password_hash, department, role, status 
        FROM users 
        WHERE email = ?
    ");
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logLoginAttempt($conn, $email, $ip_address, false);
        throw new Exception('Credenciales incorrectas');
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    if ($user['status'] !== 'active') {
        logLoginAttempt($conn, $email, $ip_address, false);
        
        if ($user['status'] === 'suspended') {
            throw new Exception('Su cuenta ha sido suspendida. Contacte al administrador');
        } else {
            throw new Exception('Su cuenta está inactiva. Contacte al administrador');
        }
    }
    if (!password_verify($password, $user['password_hash'])) {
        logLoginAttempt($conn, $email, $ip_address, false);
        throw new Exception('Credenciales incorrectas');
    }
    logLoginAttempt($conn, $email, $ip_address, true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['login_time'] = time();
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update_stmt->bind_param("i", $user['user_id']);
    $update_stmt->execute();
    $update_stmt->close();
    if ($remember_me) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $session_stmt = $conn->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $session_stmt->bind_param("issss", $user['user_id'], $token, $ip_address, $user_agent, $expires);
        $session_stmt->execute();
        $session_stmt->close();
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        // Redirigir según rol: admin va al panel, estudiante a cursos
        'redirect' => ($user['role'] === 'admin') ? '../admin/index.php' : '../courses.php',
        'user' => [
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>