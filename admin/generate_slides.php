<?php
// Genera slides con Gemini y guarda el JSON en BD

session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit();
}

$body      = json_decode(file_get_contents('php://input'), true);
$action    = $body['action'] ?? 'generate';
$course_id = intval($body['course_id'] ?? 0);

if ($action === 'delete') {
    if (!$course_id) { echo json_encode(['success'=>false,'error'=>'course_id requerido']); exit(); }
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_json MEDIUMTEXT DEFAULT NULL");
    $stmt = $conn->prepare("UPDATE courses SET slides_json=NULL, slides_embed_url=NULL, slides_presentation_id=NULL WHERE course_id=?");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $conn->close();
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'set_position') {
    if (!$course_id) { echo json_encode(['success'=>false,'error'=>'course_id requerido']); exit(); }
    $position = intval($body['position'] ?? 0);
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_position INT DEFAULT 0");
    $stmt = $conn->prepare("UPDATE courses SET slides_position=? WHERE course_id=?");
    $stmt->bind_param('ii', $position, $course_id);
    $stmt->execute();
    $conn->close();
    echo json_encode(['success' => true]);
    exit();
}

$title       = trim($body['course_title']  ?? '');
$description = trim($body['description']   ?? '');
$difficulty  = $body['difficulty']         ?? 'beginner';
$topics      = array_filter(array_map('trim', $body['topics'] ?? []));

if (!$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El título del curso es requerido.']);
    exit();
}

$level_map   = ['beginner' => 'Básico', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzado'];
$level_label = $level_map[$difficulty] ?? 'Básico';
$topics_text = !empty($topics)
    ? "Temas definidos:\n" . implode("\n", array_map(fn($t) => "- $t", $topics))
    : "Genera los temas más relevantes para este curso.";

$prompt = <<<PROMPT
Eres un diseñador instruccional experto. Genera contenido para una presentación de capacitación corporativa de Whirlpool.

CURSO: {$title}
DESCRIPCIÓN: {$description}
NIVEL: {$level_label}
{$topics_text}

Genera EXACTAMENTE 8 diapositivas. Responde ÚNICAMENTE con JSON válido, sin texto adicional ni bloques markdown.
Los puntos NO deben tener formato markdown como **negritas**. Solo texto plano.

{"slides":[
  {"type":"title","title":"Título principal del curso","subtitle":"Subtítulo motivador"},
  {"type":"agenda","title":"Agenda","points":["Tema 1","Tema 2","Tema 3","Tema 4","Tema 5"]},
  {"type":"content","title":"Título del tema","points":["Punto clave 1","Punto clave 2","Punto clave 3","Punto clave 4"]},
  {"type":"content","title":"Título del tema","points":["Punto clave 1","Punto clave 2","Punto clave 3","Punto clave 4"]},
  {"type":"content","title":"Título del tema","points":["Punto clave 1","Punto clave 2","Punto clave 3","Punto clave 4"]},
  {"type":"content","title":"Título del tema","points":["Punto clave 1","Punto clave 2","Punto clave 3","Punto clave 4"]},
  {"type":"keypoints","title":"Puntos Clave","points":["Aprendizaje clave 1","Aprendizaje clave 2","Aprendizaje clave 3"]},
  {"type":"closing","title":"¡Gracias!","subtitle":"Mensaje motivador de cierre relacionado con Whirlpool"}
]}
PROMPT;

$payload = [
    'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 8192],
];

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . GEMINI_API_KEY);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 45,
]);
$response = curl_exec($ch);
curl_close($ch);

$gemini_data = json_decode($response, true);
$text = $gemini_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$text) {
    echo json_encode(['success' => false, 'error' => 'No se pudo generar el contenido con Gemini.']);
    exit();
}

$text = trim($text);
$text = preg_replace('/^```json\s*/i', '', $text);
$text = preg_replace('/^```\s*/i',    '', $text);
$text = preg_replace('/```\s*$/',     '', $text);
$text = trim($text);

if (!str_starts_with($text, '{')) {
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false) {
        $text = substr($text, $start, $end - $start + 1);
    }
}

$slides_data = json_decode($text, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($slides_data['slides']) || !is_array($slides_data['slides'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error al interpretar respuesta de Gemini: ' . json_last_error_msg(),
        'raw'     => substr($text, 0, 300),
    ]);
    exit();
}

$slides = $slides_data['slides'];

if ($course_id > 0) {
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_json MEDIUMTEXT DEFAULT NULL");
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_embed_url TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS slides_presentation_id VARCHAR(100) DEFAULT NULL");

    $slides_json = json_encode($slides, JSON_UNESCAPED_UNICODE);
    $viewer_url  = "../course-slides-viewer.php?course_id={$course_id}";

    $stmt = $conn->prepare("UPDATE courses SET slides_json = ?, slides_embed_url = ? WHERE course_id = ?");
    $stmt->bind_param('ssi', $slides_json, $viewer_url, $course_id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success'   => true,
    'slides'    => $slides,
    'course_id' => $course_id,
    'message'   => 'Presentación generada exitosamente.',
]);