<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
header('X-Content-Type-Options: nosniff');

try {
    $items = q(
        'SELECT pm.id,pm.media_kind,pm.title_ar,pm.title_en,pm.caption_ar,pm.caption_en,
                pm.alt_text_ar,pm.alt_text_en,pm.captured_on,pm.project_name,
                pm.organization_name,pm.people_context,pm.location,
                pm.image_width,pm.image_height,pm.poster_stored_name,pm.updated_at
         FROM portfolio_media pm
         INNER JOIN users u ON u.id=pm.user_id
         WHERE pm.is_published=1 AND u.portfolio_enabled=1
         ORDER BY pm.sort_order ASC,pm.captured_on DESC,pm.id DESC
         LIMIT 12'
    )->fetchAll();
} catch (Throwable) {
    $items = [];
}

$payload = array_map(static function(array $item): array {
    $mediaKind = ($item['media_kind'] ?? 'image') === 'video' ? 'video' : 'image';
    $version = substr(hash('sha256',(string)$item['updated_at']),0,12);
    $mediaUrl = absolute_app_url('portfolio_media_file.php?id='.(int)$item['id'].'&v='.$version);
    $posterUrl = $mediaKind === 'video' && $item['poster_stored_name']
        ? absolute_app_url('portfolio_media_file.php?id='.(int)$item['id'].'&variant=poster&v='.$version)
        : null;
    return [
        'id'=>(int)$item['id'],
        'type'=>$mediaKind,
        'title_ar'=>(string)$item['title_ar'],
        'title_en'=>(string)($item['title_en'] ?: $item['title_ar']),
        'caption_ar'=>(string)$item['caption_ar'],
        'caption_en'=>(string)($item['caption_en'] ?: $item['caption_ar']),
        'alt_text_ar'=>(string)$item['alt_text_ar'],
        'alt_text_en'=>(string)($item['alt_text_en'] ?: $item['alt_text_ar']),
        'captured_on'=>$item['captured_on'] ?: null,
        'project_name'=>$item['project_name'] ?: null,
        'organization_name'=>$item['organization_name'] ?: null,
        'people_context'=>$item['people_context'] ?: null,
        'location'=>$item['location'] ?: null,
        'media_url'=>$mediaUrl,
        'image_url'=>$mediaKind === 'image' ? $mediaUrl : $posterUrl,
        'poster_url'=>$posterUrl,
        'width'=>(int)$item['image_width'],
        'height'=>(int)$item['image_height'],
    ];
},$items);

echo json_encode(['items'=>$payload],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
