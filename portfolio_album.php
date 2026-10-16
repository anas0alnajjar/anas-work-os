<?php
declare(strict_types=1);

require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/system_migrations.php';
require_auth();

$userId = current_user_id();
$tableReady = system_table_exists(db(), 'portfolio_media');
$errors = [];
$editingId = max(0, (int)($_GET['edit'] ?? $_POST['id'] ?? 0));

function portfolio_storage_path(): string {
    $path = __DIR__.'/storage/portfolio';
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('تعذر إنشاء مجلد صور الألبوم.');
    }
    return $path;
}

function portfolio_form_values(array $source): array {
    return [
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
    if (!$editing) { http_response_code(404); exit('الصورة غير موجودة.'); }
}
$values = portfolio_form_values($editing ?: []);

if (is_post()) {
    verify_csrf();
    if (!$tableReady) {
        flash('error','طبّق تحديث قاعدة البيانات أولاً قبل إدارة الألبوم.');
        redirect('portfolio_album.php');
    }

    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $mediaId = max(0, (int)($_POST['id'] ?? 0));
        $media = q('SELECT * FROM portfolio_media WHERE id=? AND user_id=?',[$mediaId,$userId])->fetch();
        if (!$media) { http_response_code(404); exit('الصورة غير موجودة.'); }
        if (trim((string)($_POST['delete_confirmation'] ?? '')) !== 'حذف الصورة') {
            flash('error','اكتب عبارة التأكيد كما هي لحذف الصورة.');
            redirect('portfolio_album.php');
        }
        q('DELETE FROM portfolio_media WHERE id=? AND user_id=?',[$mediaId,$userId]);
        $storedPath = __DIR__.'/storage/portfolio/'.basename((string)$media['stored_name']);
        if (is_file($storedPath) && !unlink($storedPath)) error_log('[portfolio-media] Unable to remove '.$media['stored_name']);
        flash('success','حُذفت الصورة من الألبوم.');
        redirect('portfolio_album.php');
    }

    $values = portfolio_form_values($_POST);
    $values['is_published'] = isset($_POST['is_published']) ? 1 : 0;
    add_required_error($errors,'title_ar',$values['title_ar'],'عنوان الصورة');
    add_required_error($errors,'caption_ar',$values['caption_ar'],'وصف الصورة');
    add_required_error($errors,'alt_text_ar',$values['alt_text_ar'],'النص البديل');
    add_max_error($errors,'title_ar',$values['title_ar'],'عنوان الصورة',190);
    add_max_error($errors,'title_en',$values['title_en'],'العنوان الإنجليزي',190);
    add_max_error($errors,'caption_ar',$values['caption_ar'],'وصف الصورة',2000);
    add_max_error($errors,'caption_en',$values['caption_en'],'الوصف الإنجليزي',2000);
    add_max_error($errors,'alt_text_ar',$values['alt_text_ar'],'النص البديل',255);
    add_max_error($errors,'alt_text_en',$values['alt_text_en'],'النص البديل الإنجليزي',255);
    foreach (['project_name'=>'المشروع','organization_name'=>'المؤسسة','location'=>'المكان'] as $field=>$label) {
        add_max_error($errors,$field,$values[$field],$label,190);
    }
    add_max_error($errors,'people_context',$values['people_context'],'السياق أو المرافقون',255);
    add_date_error($errors,'captured_on',$values['captured_on'],'تاريخ الصورة');
    if (!preg_match('/^-?\d+$/',$values['sort_order']) || (int)$values['sort_order'] < -1000 || (int)$values['sort_order'] > 1000) {
        $errors['sort_order'] = 'الترتيب يجب أن يكون رقماً بين -1000 و1000.';
    }

    $existing = null;
    if ($editingId > 0) {
        $existing = q('SELECT * FROM portfolio_media WHERE id=? AND user_id=?',[$editingId,$userId])->fetch();
        if (!$existing) { http_response_code(404); exit('الصورة غير موجودة.'); }
    }

    $upload = $_FILES['image'] ?? null;
    $hasUpload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newFile = null;
    if (!$existing && !$hasUpload) $errors['image'] = 'اختر صورة لإضافتها إلى الألبوم.';
    if ($hasUpload) {
        $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors['image'] = 'فشل رفع الصورة. جرّب ملفاً أصغر أو أعد المحاولة.';
        } elseif ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 8 * 1024 * 1024) {
            $errors['image'] = 'حجم الصورة يجب ألا يتجاوز 8 ميغابايت.';
        } elseif (!class_exists('finfo')) {
            $errors['image'] = 'امتداد fileinfo غير متاح للتحقق الآمن من الملف.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $dimensions = @getimagesize((string)$upload['tmp_name']);
            if (!isset($allowed[$mime])) {
                $errors['image'] = 'الصيغ المقبولة هي JPG وPNG وWebP فقط.';
            } elseif (!$dimensions || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1) {
                $errors['image'] = 'تعذر قراءة أبعاد الصورة.';
            } elseif ((int)$dimensions[0] > 12000 || (int)$dimensions[1] > 12000 || ((int)$dimensions[0] * (int)$dimensions[1]) > 40000000) {
                $errors['image'] = 'أبعاد الصورة كبيرة جداً. استخدم نسخة مهيأة للويب.';
            } else {
                $newFile = [
                    'stored_name'=>bin2hex(random_bytes(20)).'.'.$allowed[$mime],
                    'original_name'=>substr(basename((string)$upload['name']),0,255),
                    'mime_type'=>$mime,
                    'size_bytes'=>(int)$upload['size'],
                    'image_width'=>(int)$dimensions[0],
                    'image_height'=>(int)$dimensions[1],
                    'tmp_name'=>(string)$upload['tmp_name'],
                ];
            }
        }
    }

    if (!$errors) {
        $movedPath = null;
        try {
            if ($newFile) {
                $movedPath = portfolio_storage_path().'/'.$newFile['stored_name'];
                if (!move_uploaded_file($newFile['tmp_name'],$movedPath)) throw new RuntimeException('تعذر حفظ الصورة في التخزين الدائم.');
                chmod($movedPath,0640);
            }
            $params = [
                $values['title_ar'],$values['title_en'] ?: null,$values['caption_ar'],$values['caption_en'] ?: null,
                $values['alt_text_ar'],$values['alt_text_en'] ?: null,$values['captured_on'] ?: null,
                $values['project_name'] ?: null,$values['organization_name'] ?: null,$values['people_context'] ?: null,
                $values['location'] ?: null,(int)$values['sort_order'],$values['is_published'],
            ];
            db()->beginTransaction();
            if ($existing) {
                $sql = 'UPDATE portfolio_media SET title_ar=?,title_en=?,caption_ar=?,caption_en=?,alt_text_ar=?,alt_text_en=?,captured_on=?,project_name=?,organization_name=?,people_context=?,location=?,sort_order=?,is_published=?';
                if ($newFile) {
                    $sql .= ',original_name=?,stored_name=?,mime_type=?,size_bytes=?,image_width=?,image_height=?';
                    array_push($params,$newFile['original_name'],$newFile['stored_name'],$newFile['mime_type'],$newFile['size_bytes'],$newFile['image_width'],$newFile['image_height']);
                }
                $sql .= ' WHERE id=? AND user_id=?';
                array_push($params,$editingId,$userId);
                q($sql,$params);
            } else {
                array_push($params,$newFile['original_name'],$newFile['stored_name'],$newFile['mime_type'],$newFile['size_bytes'],$newFile['image_width'],$newFile['image_height'],$userId);
                q('INSERT INTO portfolio_media (title_ar,title_en,caption_ar,caption_en,alt_text_ar,alt_text_en,captured_on,project_name,organization_name,people_context,location,sort_order,is_published,original_name,stored_name,mime_type,size_bytes,image_width,image_height,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$params);
            }
            db()->commit();
            if ($existing && $newFile) {
                $oldPath = __DIR__.'/storage/portfolio/'.basename((string)$existing['stored_name']);
                if (is_file($oldPath) && !unlink($oldPath)) error_log('[portfolio-media] Unable to remove replaced '.$existing['stored_name']);
            }
            flash('success',$existing ? 'حُفظت تعديلات الصورة.' : 'أُضيفت الصورة إلى الألبوم.');
            redirect('portfolio_album.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            if ($movedPath && is_file($movedPath)) unlink($movedPath);
            error_log('[portfolio-media] '.$exception->getMessage());
            $errors['save'] = 'تعذر حفظ الصورة الآن. لم تُحذف أي صورة سابقة.';
        }
    }
}

$items = $tableReady
    ? q('SELECT * FROM portfolio_media WHERE user_id=? ORDER BY is_published DESC,sort_order ASC,captured_on DESC,id DESC',[$userId])->fetchAll()
    : [];
$publishedCount = count(array_filter($items,static fn(array $item): bool => (int)$item['is_published'] === 1));

$pageTitle = 'ألبوم الموقع';
$pageDescription = 'أضف صوراً حقيقية من مشاريعك وخبراتك مع سياق واضح وتاريخ قابل للعرض';
$active = 'portfolio_album';
require __DIR__.'/includes/layout_top.php';
?>

<?php if (!$tableReady): ?>
<section class="section album-setup-notice">
    <div><span class="album-kicker">إعداد لمرة واحدة</span><h2>الألبوم جاهز في الكود ويحتاج تطبيق تحديث قاعدة البيانات.</h2><p>لن يتم إنشاء الجدول أو تعديل بيانات الإنتاج تلقائياً من هذه الصفحة.</p></div>
    <a class="btn" href="system_update.php">فتح تحديث النظام</a>
</section>
<?php else: ?>
<section class="album-overview" aria-label="ملخص الألبوم">
    <div><span>كل الصور</span><strong><?= count($items) ?></strong></div>
    <div><span>منشورة</span><strong><?= $publishedCount ?></strong></div>
    <div><span>مسودات</span><strong><?= count($items)-$publishedCount ?></strong></div>
    <p>الصور المنشورة تظهر تلقائياً في قسم «من الميدان» في الموقع العام. المسودات تبقى خاصة بحسابك.</p>
</section>

<section class="album-admin-layout">
    <div class="album-editor" id="album-editor">
        <header><div><span class="album-kicker"><?= $editing ? 'تعديل الصورة' : 'صورة جديدة' ?></span><h2><?= $editing ? e($editing['title_ar']) : 'أضف لحظة مهنية موثّقة' ?></h2></div><?php if($editing):?><a class="text-action" href="portfolio_album.php">إلغاء التعديل</a><?php endif;?></header>
        <?= error_summary($errors) ?>
        <?php if(isset($errors['save'])):?><div class="alert error"><span class="alert-icon">!</span><span><?= e($errors['save']) ?></span></div><?php endif;?>
        <form method="post" enctype="multipart/form-data" class="album-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editingId ?>">
            <div class="album-upload-field field<?= field_class($errors,'image') ?>">
                <label for="album-image"><?= $editing ? 'استبدال الصورة (اختياري)' : 'الصورة' ?> <em><?= $editing ? '' : '*' ?></em></label>
                <?php if($editing):?><img class="album-current-image" src="portfolio_media_file.php?id=<?= (int)$editing['id'] ?>" alt=""><?php endif;?>
                <label class="album-file-picker" for="album-image"><span>اختر صورة</span><small>JPG أو PNG أو WebP · حتى 8MB</small></label>
                <input id="album-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $editing?'':'required' ?>>
                <div class="album-file-name" data-album-file-name>لم يتم اختيار ملف</div>
                <?= field_error($errors,'image') ?>
            </div>
            <div class="form-grid album-essential-fields">
                <div class="field<?= field_class($errors,'title_ar') ?>"><label for="title-ar">عنوان الصورة <em>*</em></label><input id="title-ar" name="title_ar" required maxlength="190" value="<?= e($values['title_ar']) ?>" placeholder="مثال: جلسة تدريب فريق إدارة الحالة"><?= field_error($errors,'title_ar') ?></div>
                <div class="field<?= field_class($errors,'captured_on') ?>"><label for="captured-on">تاريخ الصورة</label><input id="captured-on" type="date" name="captured_on" value="<?= e($values['captured_on']) ?>"><?= field_error($errors,'captured_on') ?></div>
                <div class="field full<?= field_class($errors,'caption_ar') ?>"><label for="caption-ar">الوصف الذي سيظهر تحت الصورة <em>*</em></label><textarea id="caption-ar" name="caption_ar" required maxlength="2000" rows="4" placeholder="ماذا يحدث في الصورة؟ ولماذا هذه اللحظة مهمة؟"><?= e($values['caption_ar']) ?></textarea><?= field_error($errors,'caption_ar') ?></div>
                <div class="field full<?= field_class($errors,'alt_text_ar') ?>"><label for="alt-ar">وصف مختصر لذوي الإعاقة البصرية <em>*</em></label><input id="alt-ar" name="alt_text_ar" required maxlength="255" value="<?= e($values['alt_text_ar']) ?>" placeholder="مثال: أنس يشرح لوحة متابعة لفريق إدارة الحالة"><?= field_error($errors,'alt_text_ar') ?></div>
            </div>
            <fieldset class="album-context-fields"><legend>سياق الصورة <small>كلها اختيارية</small></legend><div class="form-grid">
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
                <label class="settings-toggle"><input type="checkbox" name="is_published" value="1" <?= $values['is_published']?'checked':'' ?>><span><strong>جاهزة للنشر</strong><small>اجعلها مسودة حتى تراجع النص والصورة.</small></span></label>
                <div class="field<?= field_class($errors,'sort_order') ?>"><label for="sort-order">الترتيب</label><input id="sort-order" type="number" name="sort_order" min="-1000" max="1000" value="<?= e($values['sort_order']) ?>"><?= field_error($errors,'sort_order') ?></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><?= $editing ? 'حفظ التعديلات' : 'إضافة إلى الألبوم' ?></button><?php if($editing):?><a class="btn secondary" href="portfolio_album.php">إلغاء</a><?php endif;?></div>
        </form>
    </div>

    <aside class="album-guidance"><span class="album-kicker">ما الذي يجعل الصورة مفيدة؟</span><h3>السياق أهم من الزينة.</h3><ol><li><b>متى؟</b><span>ضع التاريخ الحقيقي إن كان معروفاً.</span></li><li><b>أين ولماذا؟</b><span>اربطها بمشروع أو مؤسسة أو نشاط واضح.</span></li><li><b>من دون مبالغة</b><span>صف ما يظهر فعلاً ولا تضف نتائج غير موثقة.</span></li><li><b>احترام الخصوصية</b><span>لا تنشر وجوهاً أو بيانات حساسة دون موافقة.</span></li></ol></aside>
</section>

<section class="album-library" aria-labelledby="album-library-title">
    <header><div><span class="album-kicker">مكتبة الصور</span><h2 id="album-library-title">صور الألبوم</h2></div><span><?= count($items) ?> صورة</span></header>
    <?php if(!$items):?><div class="empty-state"><h2>الألبوم فارغ حالياً</h2><p>أضف أول صورة من النموذج أعلاه، واحفظها كمسودة حتى تصبح جاهزة.</p></div><?php else:?><div class="album-admin-grid">
        <?php foreach($items as $item):?><article class="album-admin-card">
            <div class="album-admin-image"><img src="portfolio_media_file.php?id=<?= (int)$item['id'] ?>" alt="<?= e($item['alt_text_ar']) ?>" loading="lazy"><span class="badge <?= (int)$item['is_published']?'success':'neutral' ?>"><?= (int)$item['is_published']?'جاهزة للنشر':'مسودة' ?></span></div>
            <div class="album-admin-copy"><div class="album-admin-meta"><?php if($item['captured_on']):?><time><?= e($item['captured_on']) ?></time><?php endif;?><?php if($item['project_name']):?><span><?= e($item['project_name']) ?></span><?php endif;?></div><h3><?= e($item['title_ar']) ?></h3><p><?= e($item['caption_ar']) ?></p><div class="album-admin-context"><?php if($item['organization_name']):?><span>المؤسسة: <?= e($item['organization_name']) ?></span><?php endif;?><?php if($item['people_context']):?><span><?= e($item['people_context']) ?></span><?php endif;?></div><div class="album-admin-actions"><a href="portfolio_album.php?edit=<?= (int)$item['id'] ?>#album-editor">تعديل</a><button type="button" data-dialog-open="delete-portfolio-media-<?= (int)$item['id'] ?>">حذف</button></div></div>
        </article>
        <dialog class="delete-dialog" id="delete-portfolio-media-<?= (int)$item['id'] ?>" aria-labelledby="delete-portfolio-media-title-<?= (int)$item['id'] ?>"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button><span class="delete-dialog-icon" aria-hidden="true">!</span><h2 id="delete-portfolio-media-title-<?= (int)$item['id'] ?>">حذف الصورة نهائياً؟</h2><p>سيُحذف سجل الصورة والملف من التخزين. لا يمكن التراجع.</p><div class="field"><label for="delete-portfolio-media-confirmation-<?= (int)$item['id'] ?>">اكتب <strong>حذف الصورة</strong> للتأكيد</label><input id="delete-portfolio-media-confirmation-<?= (int)$item['id'] ?>" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف الصورة" required></div><div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div></form></dialog>
        <?php endforeach;?>
    </div><?php endif;?>
</section>
<?php endif; ?>

<script>
(() => {
    const input = document.getElementById('album-image');
    const output = document.querySelector('[data-album-file-name]');
    input?.addEventListener('change', () => {
        output.textContent = input.files?.[0]?.name || 'لم يتم اختيار ملف';
    });
})();
</script>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
