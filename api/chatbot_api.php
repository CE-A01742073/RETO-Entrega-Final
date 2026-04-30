<?php
/**
 * Chatbot API Endpoint
 * Consulta la base de datos del LMS y envía el contexto a Gemini para generar respuestas.
 * Soporta archivos adjuntos (imágenes y PDF) vía inline_data de la API multimodal de Gemini.
 */

require_once __DIR__ . '/../config/database.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada.']);
    exit();
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$user_message = isset($body['message']) ? trim($body['message']) : '';

// Procesar archivo adjunto (opcional)
$file_data = null;
if (!empty($body['file_data']) && !empty($body['file_mime'])) {

    // Tipos MIME permitidos (los que Gemini acepta vía inline_data)
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf'
    ];

    $file_mime = trim($body['file_mime']);
    $file_name = isset($body['file_name']) ? basename(trim($body['file_name'])) : 'archivo';

    if (!in_array($file_mime, $allowed_mimes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de archivo no permitido.']);
        exit();
    }

    // Validar que el base64 sea razonable (máx. ~10 MB → ~13.6 MB en base64)
    $raw_b64 = preg_replace('/\s+/', '', $body['file_data']);
    if (strlen($raw_b64) > 14_000_000) {
        http_response_code(400);
        echo json_encode(['error' => 'El archivo supera el tamaño máximo permitido (10 MB).']);
        exit();
    }

    // Verificar que sea base64 válido
    if (base64_decode($raw_b64, true) === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos del archivo inválidos.']);
        exit();
    }

    $file_data = [
        'base64'    => $raw_b64,
        'mime_type' => $file_mime,
        'name'      => $file_name,
    ];
}

// Debe haber al menos texto o archivo
if (empty($user_message) && $file_data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'El mensaje no puede estar vacío.']);
    exit();
}

$user_id = intval($_SESSION['user_id']);

/**
 * Construye un resumen estructurado de la plataforma para enviarlo como
 * contexto al modelo de lenguaje.
 */
function buildDatabaseContext(mysqli $conn, int $user_id): string
{
    $context = [];

    // Información general de la plataforma
    $stats = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM courses WHERE status = 'published') AS total_courses,
            (SELECT COUNT(*) FROM course_categories) AS total_categories,
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM course_enrollments WHERE user_id = {$user_id}) AS my_enrollments,
            (SELECT COUNT(*) FROM course_enrollments WHERE user_id = {$user_id} AND status = 'completed') AS my_completed
    ")->fetch_assoc();

    $context[] = "=== ESTADÍSTICAS GENERALES DE LA PLATAFORMA ===";
    $context[] = "Cursos publicados: {$stats['total_courses']}";
    $context[] = "Categorías disponibles: {$stats['total_categories']}";
    $context[] = "Total de usuarios registrados: {$stats['total_users']}";
    $context[] = "Cursos en los que el usuario está inscrito: {$stats['my_enrollments']}";
    $context[] = "Cursos completados por el usuario: {$stats['my_completed']}";

    // Catálogo de cursos publicados
    $courses_result = $conn->query("
        SELECT
            c.course_id,
            c.course_title,
            c.course_description,
            c.difficulty_level,
            c.estimated_hours,
            c.enrollment_count,
            c.rating_average,
            cc.category_name,
            CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.category_id
        LEFT JOIN users u ON c.instructor_id = u.user_id
        WHERE c.status = 'published'
        ORDER BY c.enrollment_count DESC
        LIMIT 30
    ");

    $context[] = "\n=== CATÁLOGO DE CURSOS DISPONIBLES ===";
    while ($course = $courses_result->fetch_assoc()) {
        $desc = mb_substr(strip_tags($course['course_description'] ?? ''), 0, 200);
        $rating = $course['rating_average'] > 0 ? number_format($course['rating_average'], 1) . '/5' : 'Sin calificaciones';
        $context[] = "- [{$course['category_name']}] {$course['course_title']} | Nivel: {$course['difficulty_level']} | Duración estimada: {$course['estimated_hours']}h | Inscritos: {$course['enrollment_count']} | Calificación: {$rating} | Instructor: {$course['instructor_name']} | Descripción: {$desc}";
    }

    // Progreso del usuario en sus cursos
    $enrollments_result = $conn->query("
        SELECT
            c.course_title,
            e.progress_percentage,
            e.status,
            e.enrollment_date,
            e.last_accessed,
            cc.category_name
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.course_id
        LEFT JOIN course_categories cc ON c.category_id = cc.category_id
        WHERE e.user_id = {$user_id}
        ORDER BY e.last_accessed DESC
    ");

    $context[] = "\n=== PROGRESO DEL USUARIO ACTUAL ===";
    $has_enrollments = false;
    while ($enroll = $enrollments_result->fetch_assoc()) {
        $has_enrollments = true;
        $last = $enroll['last_accessed'] ? date('d/m/Y', strtotime($enroll['last_accessed'])) : 'Nunca';
        $progress = number_format($enroll['progress_percentage'], 1);
        $context[] = "- {$enroll['course_title']} [{$enroll['category_name']}]: {$progress}% completado | Estado: {$enroll['status']} | Último acceso: {$last}";
    }
    if (!$has_enrollments) {
        $context[] = "El usuario no está inscrito en ningún curso actualmente.";
    }

    // Categorías con conteo de cursos
    $categories_result = $conn->query("
        SELECT
            cc.category_name,
            COUNT(c.course_id) AS course_count
        FROM course_categories cc
        LEFT JOIN courses c ON cc.category_id = c.category_id AND c.status = 'published'
        GROUP BY cc.category_id
        ORDER BY course_count DESC
    ");

    $context[] = "\n=== CATEGORÍAS DE CURSOS ===";
    while ($cat = $categories_result->fetch_assoc()) {
        $context[] = "- {$cat['category_name']}: {$cat['course_count']} cursos";
    }

    return implode("\n", $context);
}

$db_context = buildDatabaseContext($conn, $user_id);

// ── Construir el system prompt ──────────────────────────────────────────────

$system_prompt = <<<PROMPT
Eres un asistente virtual de la plataforma de capacitación de Whirlpool. Tu nombre es "Asistente Whirlpool".
Tu función es ayudar a los empleados a encontrar cursos, entender su progreso y responder preguntas sobre el contenido de la plataforma de aprendizaje.

Responde siempre en español. Sé conciso, profesional y amigable.
Usa únicamente la información del contexto proporcionado para responder preguntas sobre cursos, categorías y progreso del usuario.
Si el usuario adjunta una imagen o un PDF, analízalo y responde preguntas sobre su contenido. Si el documento tiene relación con los cursos disponibles en la plataforma, menciónalo.
Si la pregunta no tiene relación con la plataforma de capacitación ni con el archivo adjunto, indícalo de forma cortés y ofrece ayuda relacionada con los cursos.
No inventes información que no esté en el contexto ni en el archivo adjunto.

CONTEXTO ACTUAL DE LA PLATAFORMA:
{$db_context}
PROMPT;

// ── Construir el contenido del mensaje del usuario para Gemini ──────────────
// La API multimodal acepta un array de "parts" con texto e inline_data.

$user_parts = [];

// Primero el archivo (si existe), luego el texto
// Gemini procesa mejor cuando el archivo va antes del prompt de texto
if ($file_data !== null) {
    $user_parts[] = [
        'inline_data' => [
            'mime_type' => $file_data['mime_type'],
            'data'      => $file_data['base64'],
        ]
    ];
}

// Texto del mensaje (puede ser vacío si solo adjuntó archivo)
$text_content = $user_message;
if (empty($text_content) && $file_data !== null) {
    // Si solo mandó archivo sin texto, pedir descripción por defecto
    $text_content = 'Por favor analiza este archivo y dime qué contiene.';
}
$user_parts[] = ['text' => $text_content];

// ── Payload a Gemini ────────────────────────────────────────────────────────

$payload = [
    'system_instruction' => [
        'parts' => [['text' => $system_prompt]]
    ],
    'contents' => [
        [
            'role'  => 'user',
            'parts' => $user_parts,
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.4,
        'maxOutputTokens' => 1024,
        'topP'            => 0.95,
    ]
];

$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 60, // más tiempo por si el archivo es grande
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(503);
    echo json_encode(['error' => 'Error de conexión con el servicio de IA.']);
    exit();
}

$gemini_response = json_decode($response, true);

// Extraer el texto de la respuesta de Gemini
$bot_message = '';
if (isset($gemini_response['candidates'][0]['content']['parts'][0]['text'])) {
    $bot_message = $gemini_response['candidates'][0]['content']['parts'][0]['text'];
} elseif (isset($gemini_response['error'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Error del servicio de IA: ' . ($gemini_response['error']['message'] ?? 'Error desconocido.')]);
    exit();
} else {
    http_response_code(502);
    echo json_encode(['error' => 'Respuesta inesperada del servicio de IA.']);
    exit();
}

// Registrar la conversación en la base de datos
// Guardamos una referencia al nombre del archivo adjunto en el mensaje de usuario si aplica
$log_user_message = $user_message;
if ($file_data !== null) {
    $log_user_message = '[Archivo: ' . $file_data['name'] . '] ' . $user_message;
}

$stmt = $conn->prepare("
    INSERT INTO chatbot_logs (user_id, user_message, bot_response, created_at)
    VALUES (?, ?, ?, NOW())
");
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $log_user_message, $bot_message);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success'   => true,
    'message'   => $bot_message,
    'timestamp' => date('H:i')
]);