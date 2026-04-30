<?php
session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Petición inválida']); exit();
}

$context   = $_POST['context']   ?? 'lesson';   // lesson | thumbnail | course_file
$file_type = $_POST['file_type'] ?? 'image';
$course_id = intval($_POST['course_id'] ?? 0);
$file_desc = trim($_POST['description'] ?? '');
$allowed = [
    'video'    => ['mimes'=>['video/mp4','video/webm','video/ogg'],         'ext'=>['mp4','webm','ogg'],            'max'=>500*1024*1024, 'folder'=>'lessons/videos'],
    'pdf'      => ['mimes'=>['application/pdf'],                             'ext'=>['pdf'],                          'max'=>50*1024*1024,  'folder'=>'lessons/documents'],
    'image'    => ['mimes'=>['image/jpeg','image/png','image/gif','image/webp'],'ext'=>['jpg','jpeg','png','gif','webp'],'max'=>10*1024*1024,'folder'=>'lessons/images'],
    'thumbnail'=> ['mimes'=>['image/jpeg','image/png','image/webp'],         'ext'=>['jpg','jpeg','png','webp'],      'max'=>5*1024*1024,   'folder'=>'thumbnails'],
    'doc'      => ['mimes'=>['application/pdf','application/msword',
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                             'application/vnd.ms-excel',
                             'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                             'application/vnd.ms-powerpoint',
                             'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                             'text/plain','text/csv'],
                   'ext'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv'],
                   'max'=>100*1024*1024, 'folder'=>'course_files/documents'],
    'cf_image' => ['mimes'=>['image/jpeg','image/png','image/gif','image/webp'],
                   'ext'=>['jpg','jpeg','png','gif','webp'],
                   'max'=>20*1024*1024,  'folder'=>'course_files/images'],
    'cf_video' => ['mimes'=>['video/mp4','video/webm'],
                   'ext'=>['mp4','webm'],
                   'max'=>500*1024*1024, 'folder'=>'course_files/videos'],
];
if ($context === 'thumbnail') $file_type = 'thumbnail';

if (!isset($allowed[$file_type])) {
    echo json_encode(['success'=>false,'message'=>'Tipo de archivo no reconocido: '.$file_type]); exit();
}

$cfg   = $allowed[$file_type];
$file  = $_FILES['file'];
$fsize = $file['size'];
$fname = $file['name'];
$ftmp  = $file['tmp_name'];
$fmime = mime_content_type($ftmp);
$fext  = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

if (!in_array($fmime, $cfg['mimes'])) {
    echo json_encode(['success'=>false,'message'=>"Tipo MIME no permitido: {$fmime}"]); exit();
}
if (!in_array($fext, $cfg['ext'])) {
    echo json_encode(['success'=>false,'message'=>"Extensión no permitida: .{$fext}"]); exit();
}
if ($fsize > $cfg['max']) {
    echo json_encode(['success'=>false,'message'=>'El archivo supera el límite de '.($cfg['max']/1024/1024).' MB']); exit();
}
$dest_dir = dirname(__DIR__) . '/uploads/' . $cfg['folder'];
if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
$unique  = uniqid('f_',true) . '.' . $fext;
$dest    = $dest_dir . '/' . $unique;
$pub_url = '../uploads/' . $cfg['folder'] . '/' . $unique;

if (!move_uploaded_file($ftmp, $dest)) {
    echo json_encode(['success'=>false,'message'=>'Error al guardar el archivo en el servidor']); exit();
}

if ($context === 'thumbnail' && $course_id > 0) {
    $stmt = $conn->prepare("UPDATE courses SET thumbnail_url=? WHERE course_id=?");
    $stmt->bind_param("si", $pub_url, $course_id);
    $stmt->execute(); $stmt->close();
}

if ($context === 'course_file' && $course_id > 0) {
    $cf_type = match(true) {
        $fext === 'pdf'                            => 'pdf',
        in_array($fext,['doc','docx','xls','xlsx','ppt','pptx','txt','csv']) => 'document',
        in_array($fext,['jpg','jpeg','png','gif','webp'])                     => 'image',
        in_array($fext,['mp4','webm'])                                        => 'video',
        default                                    => 'other'
    };
    $fsize_kb = intval($fsize / 1024);
    $order_r  = $conn->query("SELECT COALESCE(MAX(display_order),0)+1 AS n FROM course_files WHERE course_id={$course_id}");
    $next_ord = $order_r->fetch_assoc()['n'];
    $stmt = $conn->prepare("INSERT INTO course_files (course_id,file_name,file_url,file_type,file_size_kb,description,display_order) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("isssisi", $course_id, $fname, $pub_url, $cf_type, $fsize_kb, $file_desc, $next_ord);
    $stmt->execute();
    $file_id = $conn->insert_id;
    $stmt->close();
}

$conn->close();

echo json_encode([
    'success'   => true,
    'url'       => $pub_url,
    'file_name' => $fname,
    'file_size' => round($fsize/1024, 1).' KB',
    'file_type' => $file_type,
    'file_id'   => $file_id ?? null,
    'context'   => $context,
]);