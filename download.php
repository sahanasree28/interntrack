<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$user = current_user();
if (!$user) {
    redirect('auth.php');
}
$submissionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$submissionId || $submissionId < 1) {
    http_response_code(404);
    exit('Attachment not found.');
}
$query = db()->prepare(
    'SELECT student_id, file_name, stored_name, file_mime, file_size FROM submissions WHERE id = ? AND stored_name IS NOT NULL'
);
$query->execute([$submissionId]);
$file = $query->fetch();
if (!$file || ($user['role'] !== 'admin' && (int) $file['student_id'] !== (int) $user['id'])) {
    http_response_code(404);
    exit('Attachment not found.');
}
$directory = realpath(__DIR__ . '/storage/uploads');
$storedName = (string) $file['stored_name'];
if ($directory === false || basename($storedName) !== $storedName || !preg_match('/^[a-f0-9]{40}\.(pdf|doc|docx|txt|png|jpg|jpeg)$/', $storedName)) {
    http_response_code(404);
    exit('Attachment not found.');
}
$path = realpath($directory . DIRECTORY_SEPARATOR . $storedName);
if ($path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('Attachment not found.');
}
$downloadName = basename((string) $file['file_name']);
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'attachment';
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . (string) $file['file_mime']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
readfile($path);
