<?php
// POST: genera preguntas de quiz con Gemini

session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$body           = json_decode(file_get_contents('php://input'), true);
$lesson_title   = trim($body['lesson_title']       ?? '');
$lesson_desc    = trim($body['lesson_description'] ?? '');
$course_title   = trim($body['course_title']       ?? '');
$difficulty     = $body['difficulty']              ?? 'intermediate';
$num_questions  = max(3, min(15, intval($body['num_questions'] ?? 5)));
$extra_context  = trim($body['extra_context']      ?? '');

if (!$lesson_title) {
    echo json_encode(['success' => false, 'error' => 'lesson_title es requerido']);
    exit();
}

$level_map   = ['beginner' => 'básico', 'intermediate' => 'intermedio', 'advanced' => 'avanzado'];
$level_label = $level_map[$difficulty] ?? 'intermedio';

$context_block = '';
if ($extra_context) {
    $context_block = "\nContexto adicional del admin: {$extra_context}";
}

$prompt = <<<PROMPT
Eres un experto en diseño instruccional corporativo para Whirlpool. Genera exactamente {$num_questions} preguntas de evaluación para la siguiente lección.

CURSO: {$course_title}
LECCIÓN: {$lesson_title}
DESCRIPCIÓN: {$lesson_desc}
NIVEL: {$level_label}{$context_block}

REGLAS:
- Mezcla tipos: opción única (single), múltiple respuesta (multiple) y verdadero/falso (truefalse)
- Para "single": exactamente 1 opción correcta, 3-4 opciones en total
- Para "multiple": 2 opciones correctas, 4 opciones en total
- Para "truefalse": opciones deben ser exactamente "Verdadero" y "Falso"
- Las preguntas deben evaluar comprensión real, no memorización literal
- Texto plano, sin markdown ni negritas

Responde ÚNICAMENTE con JSON válido, sin texto extra:

{"questions":[
  {
    "question_text": "Texto de la pregunta",
    "question_type": "single",
    "points": 1,
    "options": [
      {"option_text": "Opción A", "is_correct": 1},
      {"option_text": "Opción B", "is_correct": 0},
      {"option_text": "Opción C", "is_correct": 0},
      {"option_text": "Opción D", "is_correct": 0}
    ]
  }
]}
PROMPT;

$payload = [
    'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4096],
];

$ch = curl_init(
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY
);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 45,
]);
$response = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión con Gemini: ' . $curl_err]);
    exit();
}

$gemini = json_decode($response, true);
$text   = $gemini['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$text) {
    $api_err = $gemini['error']['message'] ?? 'Sin respuesta de Gemini';
    echo json_encode(['success' => false, 'error' => $api_err]);
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

$data = json_decode($text, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['questions'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'No se pudo interpretar la respuesta de Gemini: ' . json_last_error_msg(),
        'raw'     => substr($text, 0, 400),
    ]);
    exit();
}

$questions = [];
foreach ($data['questions'] as $q) {
    $qtype = in_array($q['question_type'] ?? '', ['single','multiple','truefalse'])
           ? $q['question_type']
           : 'single';

    $options = [];
    foreach (($q['options'] ?? []) as $o) {
        $otext = trim($o['option_text'] ?? '');
        if (!$otext) continue;
        $options[] = [
            'option_text' => $otext,
            'is_correct'  => intval($o['is_correct'] ?? 0),
        ];
    }

    if (count($options) < 2) continue;

    $questions[] = [
        'question_text' => trim($q['question_text'] ?? 'Pregunta'),
        'question_type' => $qtype,
        'points'        => max(1, intval($q['points'] ?? 1)),
        'options'       => $options,
    ];
}

if (empty($questions)) {
    echo json_encode(['success' => false, 'error' => 'Gemini no devolvió preguntas válidas.']);
    exit();
}

echo json_encode(['success' => true, 'questions' => $questions, 'count' => count($questions)]);