<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Función para validar correo corporativo
function validateCorporateEmail($email) {
    $allowed_domains = ['whirlpool.com', 'whirlpool.com.mx', 'whirlpool.ca'];
    $email_parts = explode('@', $email);
    
    if (count($email_parts) !== 2) {
        return false;
    }
    
    $domain = strtolower($email_parts[1]);
    return in_array($domain, $allowed_domains);
}

// Función para validar fortaleza de contraseña
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos una mayúscula'];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos una minúscula'];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos un número'];
    }
    
    return ['valid' => true];
}

try {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $accept_terms = isset($_POST['accept_terms']);
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($department)) {
        throw new Exception('Todos los campos son obligatorios');
    }
    
    if (!$accept_terms) {
        throw new Exception('Debe aceptar los términos y condiciones');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de correo electrónico inválido');
    }
    if (!validateCorporateEmail($email)) {
        throw new Exception('Debe utilizar un correo corporativo de Whirlpool (@whirlpool.com, @whirlpool.com.mx, @whirlpool.ca)');
    }
    if ($password !== $confirm_password) {
        throw new Exception('Las contraseñas no coinciden');
    }
    $password_validation = validatePasswordStrength($password);
    if (!$password_validation['valid']) {
        throw new Exception($password_validation['message']);
    }
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Este correo electrónico ya está registrado');
    }
    $stmt->close();
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, department, role, status) 
        VALUES (?, ?, ?, ?, ?, 'student', 'active')
    ");
    
    $stmt->bind_param("sssss", $first_name, $last_name, $email, $password_hash, $department);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $_SESSION['user_role'] = 'student';
        $_SESSION['department'] = $department;
        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cuenta creada exitosamente',
            'redirect' => '../courses.php'
        ]);
    } else {
        throw new Exception('Error al crear la cuenta. Intente nuevamente');
    }
    
    $stmt->close();
    
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