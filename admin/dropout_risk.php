<?php
// GET → lista enrollments con riesgo guardado
// POST { action: 'analyze_user', user_id, course_id } → analiza con Gemini

session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

header('Content-Type: application/json; charset=utf-8');


// Llama a Gemini y devuelve el objeto de riesgo para un enrollment.
function analyzeWithGemini(array $data): array {
    $prompt = <<<PROMPT
Eres un sistema de análisis de aprendizaje corporativo para Whirlpool. Analiza los datos de un empleado en un curso de capacitación y predice su riesgo de abandono.

DATOS DEL EMPLEADO:
- Nombre: {$data['full_name']}
- Departamento: {$data['department']}
- Curso: {$data['course_title']}
- Progreso actual: {$data['progress_pct']}%
- Días desde última sesión: {$data['days_since_last']}
- Sesiones esta semana: {$data['sessions_this_week']}
- Sesiones en los últimos 30 días: {$data['sessions_last_30']}
- Promedio de calificaciones en quizzes: {$data['avg_quiz_score']}% (null = sin quizzes)
- Quizzes reprobados: {$data['failed_quizzes']}
- Días desde inscripción: {$data['days_since_enrollment']}
- Progreso esperado al día de hoy (basado en días transcurridos): {$data['expected_progress']}%

INSTRUCCIONES:
- Evalúa el riesgo de abandono considerando todos los factores
- Un progreso muy por debajo del esperado es señal fuerte de riesgo
- Inactividad prolongada (>7 días) es señal de riesgo
- Múltiples quizzes reprobados aumenta el riesgo
- Responde ÚNICAMENTE con JSON válido, sin texto extra ni markdown

{"risk_level":"alto|medio|bajo","risk_score":75,"reasons":["Razón 1","Razón 2"],"recommended_action":"Acción concreta para el administrador o manager"}
PROMPT;

    $payload = [
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 2048],
    ];

    $ch = curl_init(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY
    );
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['risk_level' => 'desconocido', 'risk_score' => 0, 'reasons' => ['Error curl: ' . $err], 'recommended_action' => ''];
    }

    $gemini = json_decode($response, true);

    if (isset($gemini['error'])) {
        return ['risk_level' => 'desconocido', 'risk_score' => 0, 'reasons' => ['Error API Gemini: ' . ($gemini['error']['message'] ?? 'desconocido')], 'recommended_action' => ''];
    }

    $text = $gemini['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$text) {
        return ['risk_level' => 'desconocido', 'risk_score' => 0, 'reasons' => ['Sin respuesta. Raw: ' . substr($response ?? '', 0, 200)], 'recommended_action' => ''];
    }

    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/^```\s*/i',     '', $text);
    $text = preg_replace('/```\s*$/',      '', $text);
    $text = trim($text);

    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    }

    $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['risk_level' => 'desconocido', 'risk_score' => 0, 'reasons' => ['Parse fallido: ' . json_last_error_msg() . ' | ' . substr($text, 0, 100)], 'recommended_action' => ''];
    }

    return $parsed;
}

// Recopila datos de un enrollment desde la BD.
function getEnrollmentFeatures(mysqli $conn, int $user_id, int $course_id): ?array {
    $stmt = $conn->prepare("
        SELECT u.first_name, u.last_name, u.department,
               c.course_title,
               e.enrollment_id, e.progress_percentage, e.enrollment_date, e.last_accessed,
               e.status AS enrollment_status
        FROM course_enrollments e
        JOIN users  u ON u.user_id  = e.user_id
        JOIN courses c ON c.course_id = e.course_id
        WHERE e.user_id = ? AND e.course_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $user_id, $course_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    $days_since_enrollment = max(0, (int) round((time() - strtotime($row['enrollment_date'])) / 86400));
    if ($row['last_accessed'] && strtotime($row['last_accessed']) > 0) {
        $days_since_last = max(0, (int) round((time() - strtotime($row['last_accessed'])) / 86400));
    } else {
        $days_since_last = $days_since_enrollment;
    }

    $expected_progress = min(100, round($days_since_enrollment / 30 * 100));

    $stmt2 = $conn->prepare("
        SELECT
            SUM(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 7  DAY) THEN 1 ELSE 0 END) AS week,
            SUM(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS month30
        FROM lesson_progress
        WHERE user_id = ? AND course_id = ?
    ");
    $stmt2->bind_param('ii', $user_id, $course_id);
    $stmt2->execute();
    $sess = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    $stmt3 = $conn->prepare("
        SELECT
            ROUND(AVG(CASE WHEN completed_at IS NOT NULL THEN score ELSE NULL END), 1) AS avg_score,
            SUM(CASE WHEN passed = 0 AND completed_at IS NOT NULL THEN 1 ELSE 0 END) AS failed
        FROM quiz_attempts qa
        JOIN lesson_quizzes lq ON lq.quiz_id = qa.quiz_id
        JOIN course_lessons cl ON cl.lesson_id = lq.lesson_id
        JOIN course_modules cm ON cm.module_id = cl.module_id
        WHERE qa.user_id = ? AND cm.course_id = ?
    ");
    $stmt3->bind_param('ii', $user_id, $course_id);
    $stmt3->execute();
    $quiz = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();

    return [
        'user_id'              => $user_id,
        'course_id'            => $course_id,
        'full_name'            => $row['first_name'] . ' ' . $row['last_name'],
        'department'           => $row['department'] ?: 'Sin departamento',
        'course_title'         => $row['course_title'],
        'progress_pct'         => (float) $row['progress_percentage'],
        'days_since_last'      => $days_since_last,
        'days_since_enrollment'=> $days_since_enrollment,
        'expected_progress'    => $expected_progress,
        'sessions_this_week'   => (int) ($sess['week']   ?? 0),
        'sessions_last_30'     => (int) ($sess['month30'] ?? 0),
        'avg_quiz_score'       => $quiz['avg_score'] !== null ? (float) $quiz['avg_score'] : null,
        'failed_quizzes'       => (int) ($quiz['failed'] ?? 0),
        'enrollment_status'    => $row['enrollment_status'],
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $action    = $body['action'] ?? 'analyze_user';
    $user_id   = intval($body['user_id']   ?? 0);
    $course_id = intval($body['course_id'] ?? 0);

    if (!$user_id || !$course_id) {
        echo json_encode(['success' => false, 'error' => 'user_id y course_id son requeridos']);
        exit();
    }

    $features = getEnrollmentFeatures($conn, $user_id, $course_id);
    if (!$features) {
        echo json_encode(['success' => false, 'error' => 'Inscripción no encontrada']);
        exit();
    }

    $prediction = analyzeWithGemini($features);

    $conn->query("
        CREATE TABLE IF NOT EXISTS dropout_predictions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            course_id   INT NOT NULL,
            risk_level  ENUM('alto','medio','bajo','desconocido') NOT NULL,
            risk_score  TINYINT UNSIGNED DEFAULT 0,
            reasons     TEXT,
            recommended_action TEXT,
            analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_course (user_id, course_id)
        )
    ");

    $reasons_json = json_encode($prediction['reasons'] ?? [], JSON_UNESCAPED_UNICODE);
    $risk_level   = $prediction['risk_level']        ?? 'desconocido';
    $risk_score   = (int) ($prediction['risk_score'] ?? 0);
    $action_text  = $prediction['recommended_action'] ?? '';

    $ins = $conn->prepare("
        INSERT INTO dropout_predictions (user_id, course_id, risk_level, risk_score, reasons, recommended_action)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            risk_level=VALUES(risk_level), risk_score=VALUES(risk_score),
            reasons=VALUES(reasons), recommended_action=VALUES(recommended_action),
            analyzed_at=NOW()
    ");
    $ins->bind_param('iisiss', $user_id, $course_id, $risk_level, $risk_score, $reasons_json, $action_text);
    $ins->execute();
    $ins->close();
    $conn->close();

    echo json_encode([
        'success'    => true,
        'features'   => $features,
        'prediction' => $prediction,
    ]);
    exit();
}

if ($method === 'GET') {
    $filter_risk = $_GET['risk'] ?? '';
    $course_id   = intval($_GET['course_id'] ?? 0);

    $conn->query("
        CREATE TABLE IF NOT EXISTS dropout_predictions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            course_id   INT NOT NULL,
            risk_level  ENUM('alto','medio','bajo','desconocido') NOT NULL,
            risk_score  TINYINT UNSIGNED DEFAULT 0,
            reasons     TEXT,
            recommended_action TEXT,
            analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_course (user_id, course_id)
        )
    ");

    $where_parts = ["e.status NOT IN ('completed')"];
    $params      = [];
    $types       = '';

    if ($filter_risk) {
        $where_parts[] = "dp.risk_level = ?";
        $params[]      = $filter_risk;
        $types        .= 's';
    }
    if ($course_id) {
        $where_parts[] = "e.course_id = ?";
        $params[]      = $course_id;
        $types        .= 'i';
    }

    $where = implode(' AND ', $where_parts);

    $sql = "
        SELECT
            u.user_id, u.first_name, u.last_name, u.email, u.department,
            c.course_id, c.course_title,
            e.progress_percentage, e.enrollment_date, e.last_accessed,
            dp.risk_level, dp.risk_score, dp.reasons, dp.recommended_action, dp.analyzed_at
        FROM course_enrollments e
        JOIN users   u  ON u.user_id   = e.user_id
        JOIN courses c  ON c.course_id = e.course_id
        LEFT JOIN dropout_predictions dp ON dp.user_id = e.user_id AND dp.course_id = e.course_id
        WHERE {$where}
        ORDER BY dp.risk_score DESC, e.last_accessed ASC
    ";

    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    foreach ($rows as &$r) {
        $r['reasons'] = $r['reasons'] ? json_decode($r['reasons'], true) : null;
    }
    unset($r);

    $conn->close();
    echo json_encode(['success' => true, 'enrollments' => $rows, 'total' => count($rows)]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);