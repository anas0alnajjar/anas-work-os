<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';

$id = max(0,(int)($_GET['id'] ?? 0));
if ($id < 1) { http_response_code(404); exit; }

try {
    $media = q('SELECT pm.id,pm.user_id,pm.stored_name,pm.mime_type,pm.size_bytes,pm.is_published,pm.updated_at,u.portfolio_enabled FROM portfolio_media pm INNER JOIN users u ON u.id=pm.user_id WHERE pm.id=?',[$id])->fetch();
} catch (Throwable) {
    $media = false;
}
if (!$media) { http_response_code(404); exit; }

$currentUserId = (int)(current_user()['id'] ?? 0);
$isPublic = (int)$media['is_published'] === 1 && (int)$media['portfolio_enabled'] === 1;
if (!$isPublic && $currentUserId !== (int)$media['user_id']) {
    http_response_code(404); exit;
}

$storageRoot = realpath(__DIR__.'/storage/portfolio');
$filePath = $storageRoot ? realpath($storageRoot.'/'.basename((string)$media['stored_name'])) : false;
if (!$storageRoot || !$filePath || !str_starts_with($filePath,$storageRoot.DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404); exit;
}

$etag = '"'.hash('sha256',(string)$media['stored_name'].'|'.filemtime($filePath).'|'.filesize($filePath)).'"';
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }

header('Content-Type: '.(string)$media['mime_type']);
header('Content-Length: '.(string)filesize($filePath));
header('Content-Disposition: inline; filename="portfolio-image-'.(int)$media['id'].'.'.pathinfo($filePath,PATHINFO_EXTENSION).'"');
header('X-Content-Type-Options: nosniff');
header('ETag: '.$etag);
header($isPublic ? 'Cache-Control: public, max-age=86400' : 'Cache-Control: private, no-store');
readfile($filePath);
