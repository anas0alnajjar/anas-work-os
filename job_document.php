<?php
require __DIR__.'/includes/bootstrap.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? 0);
$document = q('SELECT d.* FROM job_documents d JOIN job_applications j ON j.id=d.job_application_id WHERE d.id=? AND j.user_id=?', [$id,$userId])->fetch();
if (!$document) { http_response_code(404); exit('المستند غير موجود'); }

$path = __DIR__.'/storage/documents/'.basename($document['stored_name']);
if (!is_file($path)) { http_response_code(404); exit('ملف المستند غير موجود على الخادم'); }

$filename = str_replace(["\r","\n",'"'], '', $document['original_name']);
header('Content-Type: '.$document['mime_type']);
header('Content-Length: '.filesize($path));
header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''".rawurlencode($filename));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
