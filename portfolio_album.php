<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/system_migrations.php';
require_auth();

$userId = current_user_id();
$tableReady = system_table_exists(db(), 'portfolio_media');
$videoReady = $tableReady && system_column_exists(db(),'portfolio_media','media_kind');
$errors = [];
$editingId = max(0, (int)($_GET['edit'] ?? $_POST['id'] ?? 0));

function portfolio_storage_path(): string {
    $path = __DIR__.'/storage/portfolio';
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('تعذر إنشاء مجلد وسائط الألبوم.');
    }
    return $path;
}

function portfolio_has_upload(?array $upload): bool {
    return is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function portfolio_validate_upload(?array $upload, string $field, array $allowed, int $maxBytes, bool $readDimensions, array &$errors): ?array {
    if (!portfolio_has_upload($upload)) return null;
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errors[$field] = 'فشل رفع الملف. تحقّق من الحجم ثم أعد المحاولة.';
        return null;
    }
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) {
        $errors[$field] = 'حجم الملف يتجاوز الحد المسموح.';
        return null;
    }
    if (!class_exists('finfo')) {
        $errors[$field] = 'امتداد fileinfo غير متاح للتحقق الآمن من الملف.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
    if (!isset($allowed[$mime])) {
        $errors[$field] = 'صيغة الملف غير مدعومة.';
        return null;
    }
    $width = 0;
    $height = 0;
    if ($readDimensions) {
        $dimensions = @getimagesize((string)$upload['tmp_name']);
        if (!$dimensions || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1) {
            $errors[$field] = 'تعذر قراءة أبعاد الصورة.';
            return null;
        }
        $width = (int)$dimensions[0];
        $height = (int)$dimensions[1];
        if ($width > 12000 || $height > 12000 || ($width * $height) > 40000000) {
            $errors[$field] = 'أبعاد الصورة كبيرة جداً. استخدم نسخة مهيأة للويب.';
            return null;
        }
    }
    return [
        'stored_name'=>bin2hex(random_bytes(20)).'.'.$allowed[$mime],
        'original_name'=>substr(basename((string)$upload['name']),0,255),
        'mime_type'=>$mime,
        'size_bytes'=>$size,
        'image_width'=>$width,
        'image_height'=>$height,
        'tmp_name'=>(string)$upload['tmp_name'],
    ];
}

function portfolio_remove_stored_file(?string $storedName): void {
    $storedName = trim((string)$storedName);
    if ($storedName === '') return;
    $path = __DIR__.'/storage/portfolio/'.basename($storedName);
    if (is_file($path) && !unlink($path)) error_log('[portfolio-media] Unable to remove '.$storedName);
}

function portfolio_form_values(array $source): array {
    return [
        'media_kind'=>($source['media_kind'] ?? 'image') === 'video' ? 'video' : 'image',
        'title_ar'=>trim((string)($source['title_ar'] ?? '')),
        'title_en'=>trim((string)($source['title_en'] ?? '')),
        'caption_ar'=>trim((string)($source['caption_ar'] ?? '')),
        'caption_en'=>trim((string)($source['caption_en'] ?? '')),
        'alt_text_ar'=>trim((string)($source['alt_text_ar'] ?? '')),
        'alt_text_en'=>trim((string)($source['alt_text_en'] ?? '')),
        'captured_on'=>trim((string)($source['captured_on'] ?? '')),
        'project_name'=>trim((string)($source['project_name'] ?? '')),
        'organization_name'=>trim((string)($source['organization_name'] ?? '')),
        'people_context'=>trim((string)($source['people_context'] ?? '')),
        'location'=>trim((string)($source['location'] ?? '')),
        'sort_order'=>(string)($source['sort_order'] ?? '0'),
        'is_published'=>(int)($source['is_published'] ?? 0),
    ];
}

$emptyValues = portfolio_form_values([]);
$editing = null;
if ($tableReady && $editingId > 0) {
    $editing = q('SELECT * FROM portfolio_media WHERE id=? AND user_id=?',[$editingId,$userId])->fetch();
    if (!$editing) { http_response_code(404); exit('العنصر غير موجود.'); }
}
$values = portfolio_form_values($editing ?: []);

if (is_post()) {
    verify_csrf();
    if (!$tableReady) {
        flash('error','طبّق تحديث قاعدة البيانات أولاً قبل إدارة الألبوم.');
        redirect('portfolio_album.php');
    }
    if (!$videoReady) {
        flash('error','طبّق تحديث دعم الفيديو من صفحة تحديث النظام أولاً.');
        redirect('portfolio_album.php');
    }

    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $mediaId = max(0, (int)($_POST['id'] ?? 0));
        $media = q('SELECT * FROM portfolio_media WHERE id=? AND user_id=?',[$mediaId,$userId])->fetch();
        if (!$media) { http_response_code(404); exit('العنصر غير موجود.'); }
        if (trim((string)($_POST['delete_confirmation'] ?? '')) !== 'حذف من الألبوم') {
            flash('error','اكتب عبارة التأكيد كما هي لحذف العنصر.');
            redirect('portfolio_album.php');
        }
        q('DELETE FROM portfolio_media WHERE id=? AND user_id=?',[$mediaId,$userId]);
        portfolio_remove_stored_file((string)$media['stored_name']);
        portfolio_remove_stored_file((string)($media['poster_stored_name'] ?? ''));
        flash('success','حُذف العنصر من الألبوم.');
        redirect('portfolio_album.php');
    }

    $values = portfolio_form_values($_POST);
    $values['is_published'] = isset($_POST['is_published']) ? 1 : 0;
    add_required_error($errors,'title_ar',$values['title_ar'],'العنوان');
    add_required_error($errors,'caption_ar',$values['caption_ar'],'الوصف');
    add_required_error($errors,'alt_text_ar',$values['alt_text_ar'],'النص البديل');
    add_max_error($errors,'title_ar',$values['title_ar'],'العنوان',190);
    add_max_error($errors,'title_en',$values['title_en'],'العنوان الإنجليزي',190);
    add_max_error($errors,'caption_ar',$values['caption_ar'],'الوصف',2000);
    add_max_error($errors,'caption_en',$values['caption_en'],'الوصف الإنجليزي',2000);
    add_max_error($errors,'alt_text_ar',$values['alt_text_ar'],'النص البديل',255);
    add_max_error($errors,'alt_text_en',$values['alt_text_en'],'النص البديل الإنجليزي',255);
    foreach (['project_name'=>'المشروع','organization_name'=>'المؤسسة','location'=>'المكان'] as $field=>$label) {
        add_max_error($errors,$field,$values[$field],$label,190);
    }
    add_max_error($errors,'people_context',$values['people_context'],'السياق أو المرافقون',255);
    add_date_error($errors,'captured_on',$values['captured_on'],'تاريخ الوسيط');
    if (!preg_match('/^-?\d+$/',$values['sort_order']) || (int)$values['sort_order'] < -1000 || (int)$values['sort_order'] > 1000) {
        $errors['sort_order'] = 'الترتيب يجب أن يكون رقماً بين -1000 و1000.';
    }

    $existing = null;
    if ($editingId > 0) {
        $existing = q('SELECT * FROM portfolio_media WHERE id=? AND user_id=?',[$editingId,$userId])->fetch();
        if (!$existing) { http_response_code(404); exit('العنصر غير موجود.'); }
    }

    $existingKind = ($existing['media_kind'] ?? 'image') === 'video' ? 'video' : 'image';
    $kindChanged = $existing && $existingKind !== $values['media_kind'];
    $mediaUpload = $_FILES['media'] ?? ($_FILES['image'] ?? null);
    $posterUpload = $_FILES['poster'] ?? null;
    $needsMedia = !$existing || $kindChanged;
    $needsPoster = $values['media_kind'] === 'video'
        && (!$existing || $kindChanged || empty($existing['poster_stored_name']));

    if ($needsMedia && !portfolio_has_upload($mediaUpload)) {
        $errors['media'] = $values['media_kind'] === 'video' ? 'اختر ملف الفيديو.' : 'اختر الصورة.';
    }
    if ($needsPoster && !portfolio_has_upload($posterUpload)) $errors['poster'] = 'اختر صورة غلاف للفيديو.';

    $imageTypes = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $videoTypes = ['video/mp4'=>'mp4','video/webm'=>'webm'];
    $newMediaFile = portfolio_validate_upload(
        $mediaUpload,
        'media',
        $values['media_kind'] === 'video' ? $videoTypes : $imageTypes,
        $values['media_kind'] === 'video' ? 40 * 1024 * 1024 : 8 * 1024 * 1024,
        $values['media_kind'] === 'image',
        $errors
    );
    $newPosterFile = $values['media_kind'] === 'video'
        ? portfolio_validate_upload($posterUpload,'poster',$imageTypes,8 * 1024 * 1024,true,$errors)
        : null;

    if (!$errors) {
        $movedPaths = [];
        try {
            foreach (array_filter([$newMediaFile,$newPosterFile]) as $newFile) {
                $movedPath = portfolio_storage_path().'/'.$newFile['stored_name'];
                if (!move_uploaded_file($newFile['tmp_name'],$movedPath)) throw new RuntimeException('تعذر حفظ الملف في التخزين الدائم.');
                chmod($movedPath,0640);
                $movedPaths[] = $movedPath;
            }
            $params = [
                $values['media_kind'],$values['title_ar'],$values['title_en'] ?: null,$values['caption_ar'],$values['caption_en'] ?: null,
                $values['alt_text_ar'],$values['alt_text_en'] ?: null,$values['captured_on'] ?: null,
                $values['project_name'] ?: null,$values['organization_name'] ?: null,$values['people_context'] ?: null,
                $values['location'] ?: null,(int)$values['sort_order'],$values['is_published'],
            ];
            db()->beginTransaction();
            if ($existing) {
                $sql = 'UPDATE portfolio_media SET media_kind=?,title_ar=?,title_en=?,caption_ar=?,caption_en=?,alt_text_ar=?,alt_text_en=?,captured_on=?,project_name=?,organization_name=?,people_context=?,location=?,sort_order=?,is_published=?';
                if ($newMediaFile) {
                    $sql .= ',original_name=?,stored_name=?,mime_type=?,size_bytes=?,image_width=?,image_height=?';
                    $width = $values['media_kind'] === 'video' ? (int)($newPosterFile['image_width'] ?? $existing['image_width']) : $newMediaFile['image_width'];
                    $height = $values['media_kind'] === 'video' ? (int)($newPosterFile['image_height'] ?? $existing['image_height']) : $newMediaFile['image_height'];
                    array_push($params,$newMediaFile['original_name'],$newMediaFile['stored_name'],$newMediaFile['mime_type'],$newMediaFile['size_bytes'],$width,$height);
                } elseif ($newPosterFile) {
                    $sql .= ',image_width=?,image_height=?';
                    array_push($params,$newPosterFile['image_width'],$newPosterFile['image_height']);
                }
                if ($values['media_kind'] === 'video' && $newPosterFile) {
                    $sql .= ',poster_original_name=?,poster_stored_name=?,poster_mime_type=?,poster_size_bytes=?';
                    array_push($params,$newPosterFile['original_name'],$newPosterFile['stored_name'],$newPosterFile['mime_type'],$newPosterFile['size_bytes']);
                } elseif ($values['media_kind'] === 'image') {
                    $sql .= ',poster_original_name=NULL,poster_stored_name=NULL,poster_mime_type=NULL,poster_size_bytes=NULL';
                }
                $sql .= ' WHERE id=? AND user_id=?';
                array_push($params,$editingId,$userId);
                q($sql,$params);
            } else {
                $dimensionsSource = $values['media_kind'] === 'video' ? $newPosterFile : $newMediaFile;
                array_push(
                    $params,
                    $newMediaFile['original_name'],$newMediaFile['stored_name'],$newMediaFile['mime_type'],$newMediaFile['size_bytes'],
                    $dimensionsSource['image_width'],$dimensionsSource['image_height'],
                    $newPosterFile['original_name'] ?? null,$newPosterFile['stored_name'] ?? null,$newPosterFile['mime_type'] ?? null,$newPosterFile['size_bytes'] ?? null,
                    $userId
                );
                q('INSERT INTO portfolio_media (media_kind,title_ar,title_en,caption_ar,caption_en,alt_text_ar,alt_text_en,captured_on,project_name,organization_name,people_context,location,sort_order,is_published,original_name,stored_name,mime_type,size_bytes,image_width,image_height,poster_original_name,poster_stored_name,poster_mime_type,poster_size_bytes,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$params);
            }
            db()->commit();
            if ($existing && $newMediaFile) portfolio_remove_stored_file((string)$existing['stored_name']);
            if ($existing && !empty($existing['poster_stored_name']) && ($newPosterFile || $values['media_kind'] === 'image')) {
                portfolio_remove_stored_file((string)$existing['poster_stored_name']);
            }
            flash('success',$existing ? 'حُفظت تعديلات العنصر.' : 'أُضيف العنصر إلى الألبوم.');
            redirect('portfolio_album.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            foreach ($movedPaths as $movedPath) if (is_file($movedPath)) unlink($movedPath);
            error_log('[portfolio-media] '.$exception->getMessage());
            $errors['save'] = 'تعذر حفظ العنصر الآن. لم يُحذف أي ملف سابق.';
        }
    }
}

$items = $tableReady
    ? q('SELECT * FROM portfolio_media WHERE user_id=? ORDER BY is_published DESC,sort_order ASC,captured_on DESC,id DESC',[$userId])->fetchAll()
    : [];
$publishedCount = count(array_filter($items,static fn(array $item): bool => (int)$item['is_published'] === 1));

$pageTitle = 'ألبوم الموقع';
$pageDescription = 'أضف صوراً وفيديوهات حقيقية من مشاريعك وخبراتك مع سياق واضح وتاريخ قابل للعرض';
$active = 'portfolio_album';
require __DIR__.'/includes/layout_top.php';
?>

<?php if (!$tableReady || !$videoReady): ?>
<section class="section album-setup-notice">
    <div><span class="album-kicker">إعداد لمرة واحدة</span><h2>الألبوم يحتاج تطبيق آخر تحديث لقاعدة البيانات.</h2><p>طبّق التحديث الآمن لتفعيل الصور والفيديوهات ضمن السجل نفسه.</p></div>
    <a class="btn" href="system_update.php">فتح تحديث النظام</a>
</section>
<?php else: ?>
<section class="album-overview" aria-label="ملخص الألبوم">
    <div><span>كل الوسائط</span><strong><?= count($items) ?></strong></div>
    <div><span>منشورة</span><strong><?= $publishedCount ?></strong></div>
    <div><span>مسودات</span><strong><?= count($items)-$publishedCount ?></strong></div>
    <p>الصور والفيديوهات المنشورة تظهر تلقائياً في قسم «من الميدان». المسودات تبقى خاصة بحسابك.</p>
</section>

<section class="album-admin-layout">
    <div class="album-editor" id="album-editor">
        <header><div><span class="album-kicker"><?= $editing ? 'تعديل عنصر' : 'عنصر جديد' ?></span><h2><?= $editing ? e($editing['title_ar']) : 'أضف لحظة مهنية موثّقة' ?></h2></div><?php if($editing):?><a class="text-action" href="portfolio_album.php">إلغاء التعديل</a><?php endif;?></header>
        <?= error_summary($errors) ?>
        <?php if(isset($errors['save'])):?><div class="alert error"><span class="alert-icon">!</span><span><?= e($errors['save']) ?></span></div><?php endif;?>
        <form method="post" enctype="multipart/form-data" class="album-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editingId ?>">
            <fieldset class="album-media-type" data-album-media-type data-initial-kind="<?= e($values['media_kind']) ?>" data-has-existing="<?= $editing ? '1' : '0' ?>" data-has-poster="<?= !empty($editing['poster_stored_name']) ? '1' : '0' ?>">
                <legend>نوع الوسيط</legend>
                <label><input type="radio" name="media_kind" value="image" <?= $values['media_kind']==='image'?'checked':'' ?>><span><strong>صورة</strong><small>JPG · PNG · WebP</small></span></label>
                <label><input type="radio" name="media_kind" value="video" <?= $values['media_kind']==='video'?'checked':'' ?>><span><strong>فيديو</strong><small>MP4 · WebM</small></span></label>
            </fieldset>
            <?php if($editing):?>
                <?php if(($editing['media_kind'] ?? 'image') === 'video'):?>
                    <video class="album-current-media" controls playsinline preload="metadata" poster="portfolio_media_file.php?id=<?= (int)$editing['id'] ?>&amp;variant=poster"><source src="portfolio_media_file.php?id=<?= (int)$editing['id'] ?>" type="<?= e($editing['mime_type']) ?>"></video>
                <?php else:?><img class="album-current-media" src="portfolio_media_file.php?id=<?= (int)$editing['id'] ?>" alt=""><?php endif;?>
            <?php endif;?>
            <div class="album-upload-field field<?= field_class($errors,'media') ?>">
                <label for="album-media"><span data-media-file-label><?= $values['media_kind']==='video'?'ملف الفيديو':'ملف الصورة' ?></span> <em><?= $editing ? '' : '*' ?></em></label>
                <label class="album-file-picker" for="album-media"><span data-media-picker-label><?= $values['media_kind']==='video'?'اختر فيديو':'اختر صورة' ?></span><small data-media-picker-help><?= $values['media_kind']==='video'?'MP4 أو WebM · حتى 40MB':'JPG أو PNG أو WebP · حتى 8MB' ?></small></label>
                <input id="album-media" type="file" name="media" accept="<?= $values['media_kind']==='video'?'video/mp4,video/webm':'image/jpeg,image/png,image/webp' ?>" <?= $editing?'':'required' ?>>
                <div class="album-file-name" data-album-file-name="media">لم يتم اختيار ملف</div>
                <?= field_error($errors,'media') ?>
            </div>
            <div class="album-upload-field album-poster-field field<?= field_class($errors,'poster') ?>" data-video-poster-field <?= $values['media_kind']==='video'?'':'hidden' ?>>
                <label for="album-poster">صورة غلاف الفيديو <em><?= (!$editing || empty($editing['poster_stored_name'])) ? '*' : '' ?></em></label>
                <?php if($editing && !empty($editing['poster_stored_name'])):?><img class="album-poster-preview" src="portfolio_media_file.php?id=<?= (int)$editing['id'] ?>&amp;variant=poster" alt=""><?php endif;?>
                <label class="album-file-picker" for="album-poster"><span>اختر صورة الغلاف</span><small>JPG أو PNG أو WebP · حتى 8MB</small></label>
                <input id="album-poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                <div class="album-file-name" data-album-file-name="poster">لم يتم اختيار ملف</div>
                <?= field_error($errors,'poster') ?>
            </div>
            <div class="form-grid album-essential-fields">
                <div class="field<?= field_class($errors,'title_ar') ?>"><label for="title-ar">العنوان <em>*</em></label><input id="title-ar" name="title_ar" required maxlength="190" value="<?= e($values['title_ar']) ?>" placeholder="مثال: جلسة تدريب فريق إدارة الحالة"><?= field_error($errors,'title_ar') ?></div>
                <div class="field<?= field_class($errors,'captured_on') ?>"><label for="captured-on">تاريخ الالتقاط أو التسجيل</label><input id="captured-on" type="date" name="captured_on" value="<?= e($values['captured_on']) ?>"><?= field_error($errors,'captured_on') ?></div>
                <div class="field full<?= field_class($errors,'caption_ar') ?>"><label for="caption-ar">الوصف الذي سيظهر تحت العنصر <em>*</em></label><textarea id="caption-ar" name="caption_ar" required maxlength="2000" rows="4" placeholder="ماذا يحدث؟ وما سياق هذه اللحظة؟"><?= e($values['caption_ar']) ?></textarea><?= field_error($errors,'caption_ar') ?></div>
                <div class="field full<?= field_class($errors,'alt_text_ar') ?>"><label for="alt-ar">وصف مختصر لذوي الإعاقة البصرية <em>*</em></label><input id="alt-ar" name="alt_text_ar" required maxlength="255" value="<?= e($values['alt_text_ar']) ?>" placeholder="مثال: أنس يشرح لوحة متابعة لفريق إدارة الحالة"><?= field_error($errors,'alt_text_ar') ?></div>
            </div>
            <fieldset class="album-context-fields"><legend>سياق العنصر <small>كلها اختيارية</small></legend><div class="form-grid">
                <div class="field<?= field_class($errors,'project_name') ?>"><label for="project-name">المشروع</label><input id="project-name" name="project_name" maxlength="190" value="<?= e($values['project_name']) ?>" placeholder="اسم المشروع أو النظام"><?= field_error($errors,'project_name') ?></div>
                <div class="field<?= field_class($errors,'organization_name') ?>"><label for="organization-name">المؤسسة</label><input id="organization-name" name="organization_name" maxlength="190" value="<?= e($values['organization_name']) ?>" placeholder="اسم المؤسسة أو الجهة"><?= field_error($errors,'organization_name') ?></div>
                <div class="field<?= field_class($errors,'people_context') ?>"><label for="people-context">برفقة / ضمن</label><input id="people-context" name="people_context" maxlength="255" value="<?= e($values['people_context']) ?>" placeholder="مثال: فريق المتابعة والتقييم"><?= field_error($errors,'people_context') ?></div>
                <div class="field<?= field_class($errors,'location') ?>"><label for="album-location">المكان</label><input id="album-location" name="location" maxlength="190" value="<?= e($values['location']) ?>" placeholder="المدينة أو موقع النشاط"><?= field_error($errors,'location') ?></div>
            </div></fieldset>
            <details class="album-english-fields"><summary>النسخة الإنجليزية <span>اختيارية</span></summary><div class="form-grid">
                <div class="field<?= field_class($errors,'title_en') ?>"><label for="title-en">English title</label><input id="title-en" name="title_en" maxlength="190" dir="ltr" value="<?= e($values['title_en']) ?>"><?= field_error($errors,'title_en') ?></div>
                <div class="field<?= field_class($errors,'alt_text_en') ?>"><label for="alt-en">English alternative text</label><input id="alt-en" name="alt_text_en" maxlength="255" dir="ltr" value="<?= e($values['alt_text_en']) ?>"><?= field_error($errors,'alt_text_en') ?></div>
                <div class="field full<?= field_class($errors,'caption_en') ?>"><label for="caption-en">English caption</label><textarea id="caption-en" name="caption_en" maxlength="2000" rows="4" dir="ltr"><?= e($values['caption_en']) ?></textarea><?= field_error($errors,'caption_en') ?></div>
            </div></details>
            <div class="album-publish-row">
                <label class="settings-toggle"><input type="checkbox" name="is_published" value="1" <?= $values['is_published']?'checked':'' ?>><span><strong>جاهز للنشر</strong><small>اتركه مسودة حتى تراجع النص والوسيط.</small></span></label>
                <div class="field<?= field_class($errors,'sort_order') ?>"><label for="sort-order">الترتيب</label><input id="sort-order" type="number" name="sort_order" min="-1000" max="1000" value="<?= e($values['sort_order']) ?>"><?= field_error($errors,'sort_order') ?></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><?= $editing ? 'حفظ التعديلات' : 'إضافة إلى الألبوم' ?></button><?php if($editing):?><a class="btn secondary" href="portfolio_album.php">إلغاء</a><?php endif;?></div>
        </form>
    </div>

    <aside class="album-guidance"><span class="album-kicker">ما الذي يجعل الوسيط مفيداً؟</span><h3>السياق أهم من الزينة.</h3><ol><li><b>متى؟</b><span>ضع التاريخ الحقيقي إن كان معروفاً.</span></li><li><b>أين ولماذا؟</b><span>اربطه بمشروع أو مؤسسة أو نشاط واضح.</span></li><li><b>فيديو مختصر</b><span>استخدم مقطعاً مركزاً وصورة غلاف واضحة.</span></li><li><b>احترام الخصوصية</b><span>لا تنشر وجوهاً أو بيانات حساسة دون موافقة.</span></li></ol></aside>
</section>

<section class="album-library" aria-labelledby="album-library-title">
    <header><div><span class="album-kicker">مكتبة الوسائط</span><h2 id="album-library-title">محتوى الألبوم</h2></div><span><?= count($items) ?> عنصر</span></header>
    <?php if(!$items):?><div class="empty-state"><h2>الألبوم فارغ حالياً</h2><p>أضف أول صورة أو فيديو، واحفظه كمسودة حتى يصبح جاهزاً.</p></div><?php else:?><div class="album-admin-grid">
        <?php foreach($items as $item):?><article class="album-admin-card">
            <div class="album-admin-image"><img src="portfolio_media_file.php?id=<?= (int)$item['id'] ?><?= ($item['media_kind'] ?? 'image')==='video'?'&amp;variant=poster':'' ?>" alt="<?= e($item['alt_text_ar']) ?>" loading="lazy"><?php if(($item['media_kind'] ?? 'image')==='video'):?><span class="album-video-mark" aria-label="فيديو">▶</span><?php endif;?><span class="badge <?= (int)$item['is_published']?'success':'neutral' ?>"><?= (int)$item['is_published']?'منشور':'مسودة' ?></span></div>
            <div class="album-admin-copy"><div class="album-admin-meta"><?php if($item['captured_on']):?><time><?= e($item['captured_on']) ?></time><?php endif;?><?php if($item['project_name']):?><span><?= e($item['project_name']) ?></span><?php endif;?></div><h3><?= e($item['title_ar']) ?></h3><p><?= e($item['caption_ar']) ?></p><div class="album-admin-context"><?php if($item['organization_name']):?><span>المؤسسة: <?= e($item['organization_name']) ?></span><?php endif;?><?php if($item['people_context']):?><span><?= e($item['people_context']) ?></span><?php endif;?></div><div class="album-admin-actions"><a href="portfolio_album.php?edit=<?= (int)$item['id'] ?>#album-editor">تعديل</a><button type="button" data-dialog-open="delete-portfolio-media-<?= (int)$item['id'] ?>">حذف</button></div></div>
        </article>
        <dialog class="delete-dialog" id="delete-portfolio-media-<?= (int)$item['id'] ?>" aria-labelledby="delete-portfolio-media-title-<?= (int)$item['id'] ?>"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button><span class="delete-dialog-icon" aria-hidden="true">!</span><h2 id="delete-portfolio-media-title-<?= (int)$item['id'] ?>">حذف العنصر نهائياً؟</h2><p>سيُحذف السجل والملف وصورة الغلاف إن وجدت. لا يمكن التراجع.</p><div class="field"><label for="delete-portfolio-media-confirmation-<?= (int)$item['id'] ?>">اكتب <strong>حذف من الألبوم</strong> للتأكيد</label><input id="delete-portfolio-media-confirmation-<?= (int)$item['id'] ?>" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف من الألبوم" required></div><div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div></form></dialog>
        <?php endforeach;?>
    </div><?php endif;?>
</section>
<?php endif; ?>

<script>
(() => {
    const typeField = document.querySelector('[data-album-media-type]');
    const mediaInput = document.getElementById('album-media');
    const posterInput = document.getElementById('album-poster');
    const posterField = document.querySelector('[data-video-poster-field]');
    const mediaLabel = document.querySelector('[data-media-file-label]');
    const pickerLabel = document.querySelector('[data-media-picker-label]');
    const pickerHelp = document.querySelector('[data-media-picker-help]');
    const initialKind = typeField?.dataset.initialKind || 'image';
    const hasExisting = typeField?.dataset.hasExisting === '1';
    const hasPoster = typeField?.dataset.hasPoster === '1';

    function selectedKind() {
        return typeField?.querySelector('input[name="media_kind"]:checked')?.value === 'video' ? 'video' : 'image';
    }

    function syncMediaFields() {
        const kind = selectedKind();
        const kindChanged = hasExisting && kind !== initialKind;
        if (mediaInput) {
            mediaInput.accept = kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp';
            mediaInput.required = !hasExisting || kindChanged;
        }
        if (posterField) posterField.hidden = kind !== 'video';
        if (posterInput) posterInput.required = kind === 'video' && (!hasExisting || kindChanged || !hasPoster);
        if (mediaLabel) mediaLabel.textContent = kind === 'video' ? 'ملف الفيديو' : 'ملف الصورة';
        if (pickerLabel) pickerLabel.textContent = kind === 'video' ? 'اختر فيديو' : 'اختر صورة';
        if (pickerHelp) pickerHelp.textContent = kind === 'video' ? 'MP4 أو WebM · حتى 40MB' : 'JPG أو PNG أو WebP · حتى 8MB';
    }

    typeField?.addEventListener('change', syncMediaFields);
    syncMediaFields();
    [mediaInput,posterInput].forEach((input) => input?.addEventListener('change', () => {
        const output = document.querySelector(`[data-album-file-name="${input === mediaInput ? 'media' : 'poster'}"]`);
        if (output) output.textContent = input.files?.[0]?.name || 'لم يتم اختيار ملف';
    }));
})();
</script>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
