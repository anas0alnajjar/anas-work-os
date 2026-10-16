<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';

$id = max(0,(int)($_GET['id'] ?? 0));
$variant = (string)($_GET['variant'] ?? 'media');
if (!in_array($variant,['media','poster'],true)) { http_response_code(404); exit; }
if ($id < 1) { http_response_code(404); exit; }

try {
    $media = q('SELECT pm.id,pm.user_id,pm.media_kind,pm.stored_name,pm.mime_type,pm.size_bytes,
                       pm.poster_stored_name,pm.poster_mime_type,pm.poster_size_bytes,
                       pm.is_published,pm.updated_at,u.portfolio_enabled
                FROM portfolio_media pm
                INNER JOIN users u ON u.id=pm.user_id
                WHERE pm.id=?',[$id])->fetch();
} catch (Throwable) {
    $media = false;
}
if (!$media) { http_response_code(404); exit; }

$currentUserId = (int)(current_user()['id'] ?? 0);
$isPublic = (int)$media['is_published'] === 1 && (int)$media['portfolio_enabled'] === 1;
if (!$isPublic && $currentUserId !== (int)$media['user_id']) {
    http_response_code(404); exit;
}

$storedName = $variant === 'poster' ? (string)($media['poster_stored_name'] ?? '') : (string)$media['stored_name'];
$mimeType = $variant === 'poster' ? (string)($media['poster_mime_type'] ?? '') : (string)$media['mime_type'];
if ($variant === 'poster' && (($media['media_kind'] ?? 'image') !== 'video' || $storedName === '')) { http_response_code(404); exit; }

$storageRoot = realpath(__DIR__.'/storage/portfolio');
$filePath = $storageRoot ? realpath($storageRoot.'/'.basename($storedName)) : false;
if (!$storageRoot || !$filePath || !str_starts_with($filePath,$storageRoot.DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404); exit;
}

$etag = '"'.hash('sha256',$storedName.'|'.filemtime($filePath).'|'.filesize($filePath)).'"';
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }

header('Content-Type: '.$mimeType);
header('Content-Disposition: inline; filename="portfolio-'.($variant === 'poster' ? 'poster' : 'media').'-'.(int)$media['id'].'.'.pathinfo($filePath,PATHINFO_EXTENSION).'"');
header('X-Content-Type-Options: nosniff');
header('ETag: '.$etag);
header($isPublic ? 'Cache-Control: public, max-age=86400' : 'Cache-Control: private, no-store');

$fileSize = filesize($filePath);
$isVideo = $variant === 'media' && str_starts_with($mimeType,'video/');
if (!$isVideo) {
    header('Content-Length: '.(string)$fileSize);
    readfile($filePath);
    exit;
}

header('Accept-Ranges: bytes');
$start = 0;
$end = $fileSize - 1;
$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/',$range,$matches)) {
        header('Content-Range: bytes */'.$fileSize);
        http_response_code(416);
        exit;
    }
    if ($matches[1] === '' && $matches[2] !== '') {
        $suffixLength = min((int)$matches[2],$fileSize);
        $start = $fileSize - $suffixLength;
    } else {
        $start = (int)$matches[1];
        if ($matches[2] !== '') $end = min((int)$matches[2],$end);
    }
    if ($start < 0 || $start > $end || $start >= $fileSize) {
        header('Content-Range: bytes */'.$fileSize);
        http_response_code(416);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes '.$start.'-'.$end.'/'.$fileSize);
}

$length = $end - $start + 1;
header('Content-Length: '.(string)$length);
$handle = fopen($filePath,'rb');
if (!$handle) { http_response_code(500); exit; }
fseek($handle,$start);
$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle,min(1024 * 1024,$remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    if (connection_aborted()) break;
}
fclose($handle);
