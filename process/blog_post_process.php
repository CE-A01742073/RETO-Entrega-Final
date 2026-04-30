<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

require_once '../config/database.php';

$user_id  = $_SESSION['user_id'];
$is_admin = $_SESSION['user_role'] === 'admin';

// Detectar si llega como FormData o JSON
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_form_data = str_contains($content_type, 'multipart/form-data');

$action = '';

if ($is_form_data) {
    $action = $_POST['action'] ?? '';
} else {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
}

switch ($action) {
    case 'create_post':
        $title   = trim($_POST['title']   ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!$title || !$content) {
            echo json_encode(['success' => false, 'message' => 'El título y el contenido son obligatorios.']);
            exit;
        }

        if (mb_strlen($title) > 255) {
            echo json_encode(['success' => false, 'message' => 'El título es demasiado largo.']);
            exit;
        }
        $cover_path = null;
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $file      = $_FILES['cover_image'];
            $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
            $mime      = mime_content_type($file['tmp_name']);
            $max_bytes = 5 * 1024 * 1024;

            if (!in_array($mime, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido. Usa JPG, PNG o WebP.']);
                exit;
            }

            if ($file['size'] > $max_bytes) {
                echo json_encode(['success' => false, 'message' => 'La imagen supera el límite de 5 MB.']);
                exit;
            }

            $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename   = 'blog_' . $user_id . '_' . time() . '.' . strtolower($ext);
            $upload_dir = dirname(__DIR__) . '/uploads/blog/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $cover_path = 'uploads/blog/' . $filename;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO blog_posts (user_id, title, content, cover_image, status)
            VALUES (?, ?, ?, ?, 'published')
        ");
        $stmt->bind_param("isss", $user_id, $title, $content, $cover_path);
        $stmt->execute();
        $post_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'post_id' => $post_id]);
        break;
    case 'delete':
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
            exit;
        }

        $post_id = intval($body['post_id'] ?? 0);
        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'rejected' WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
}