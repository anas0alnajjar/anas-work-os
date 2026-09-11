<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id ? q('SELECT * FROM organizations WHERE id=? AND user_id=?', [$id,$userId])->fetch() : null;
if ($id && !$row) { http_response_code(404); exit('الجهة غير موجودة'); }
$data = $row ?: ['name'=>'','category'=>'','priority'=>'تركيز الآن','status'=>'هدف','website'=>'','fit_reason'=>'','first_offer'=>'','notes'=>''];
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($data) as $key) $data[$key] = trim((string)($_POST[$key] ?? ''));

    add_required_error($errors, 'name', $data['name'], 'اسم الجهة');
    add_max_error($errors, 'name', $data['name'], 'اسم الجهة', 180);
    add_max_error($errors, 'category', $data['category'], 'القطاع', 120);
    add_choice_error($errors, 'priority', $data['priority'], organization_priorities(), 'الأولوية');
    add_choice_error($errors, 'status', $data['status'], organization_statuses(), 'الحالة');
    add_url_error($errors, 'website', $data['website'], 'الموقع الإلكتروني');
    add_max_error($errors, 'website', $data['website'], 'الموقع الإلكتروني', 500);
    if ($data['name'] !== '') {
        $duplicate = (int)scalar('SELECT COUNT(*) FROM organizations WHERE user_id=? AND LOWER(name)=LOWER(?) AND id<>?', [$userId,$data['name'], $id]);
        if ($duplicate) $errors['name'] = 'توجد جهة مسجلة بهذا الاسم. افتحها بدلاً من إنشاء سجل مكرر.';
    }

    if (!$errors) {
        $values = [$data['name'],$data['category'],$data['priority'],$data['status'],$data['website'],$data['fit_reason'],$data['first_offer'],$data['notes']];
        $destination = '';
        if ($id) {
            q('UPDATE organizations SET name=?,category=?,priority=?,status=?,website=?,fit_reason=?,first_offer=?,notes=? WHERE id=? AND user_id=?', [...$values, $id,$userId]);
            flash('success', 'تم تحديث الجهة بنجاح.');
            $destination = 'organization_view.php?id='.$id;
        } else {
            q('INSERT INTO organizations(user_id,name,category,priority,status,website,fit_reason,first_offer,notes) VALUES(?,?,?,?,?,?,?,?,?)', [$userId,...$values]);
            $id = (int)db()->lastInsertId();
            flash('success', 'تمت إضافة الجهة. الخطوة التالية: أضف شخصاً مناسباً داخلها.');
            $destination = 'contact_form.php?organization_id='.$id;
        }
        redirect($destination);
    }
}

$pageTitle = $id ? 'تعديل الجهة' : 'إضافة جهة';
$pageDescription = $id ? 'حدّث موقع الجهة في مسار العلاقة' : 'ابدأ ببيانات مختصرة تساعدك على اتخاذ قرار التواصل';
$active = 'organizations';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<div class="form-layout">
    <aside class="form-guide">
        <span class="guide-kicker">الخطوة 1 من مسار العلاقات</span>
        <h2>سجّل الجهة بهدف واضح</h2>
        <p>لا تجمع أسماءً فقط. حدد لماذا تناسبك الجهة وما أول عرض أو مدخل مناسب للتواصل معها.</p>
        <ol><li class="active">بيانات الجهة</li><li>إضافة شخص</li><li>تسجيل تواصل</li><li>تحديد متابعة</li></ol>
    </aside>
    <section class="section form-card">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <fieldset class="form-section">
                <legend><span>01</span><div><strong>المعلومات الأساسية</strong><small>الحقول اللازمة للتعريف والفرز</small></div></legend>
                <div class="form-grid">
                    <div class="field<?= field_class($errors, 'name') ?>"><label for="name">اسم الجهة <em>*</em></label><input id="name" name="name" value="<?= e($data['name']) ?>" maxlength="180" required autocomplete="organization" aria-invalid="<?= isset($errors['name'])?'true':'false' ?>" placeholder="مثال: iMMAP Inc."><?= field_error($errors, 'name') ?></div>
                    <div class="field<?= field_class($errors, 'category') ?>"><label for="category">القطاع</label><input id="category" name="category" value="<?= e($data['category']) ?>" maxlength="120" placeholder="استشارات، بيانات، تنمية..." aria-invalid="<?= isset($errors['category'])?'true':'false' ?>"><?= field_error($errors, 'category') ?></div>
                    <div class="field<?= field_class($errors, 'priority') ?>"><label for="priority">الأولوية <em>*</em></label><select id="priority" name="priority" required aria-invalid="<?= isset($errors['priority'])?'true':'false' ?>"><?= select_options(organization_priorities(), $data['priority']) ?></select><?= field_error($errors, 'priority') ?></div>
                    <div class="field<?= field_class($errors, 'status') ?>"><label for="status">مرحلة العلاقة <em>*</em></label><select id="status" name="status" required aria-invalid="<?= isset($errors['status'])?'true':'false' ?>"><?= select_options(organization_statuses(), $data['status']) ?></select><?= field_error($errors, 'status') ?></div>
                    <div class="field full<?= field_class($errors, 'website') ?>"><label for="website">الموقع الإلكتروني</label><input id="website" type="url" name="website" value="<?= e($data['website']) ?>" maxlength="500" inputmode="url" autocomplete="url" placeholder="https://example.com" aria-invalid="<?= isset($errors['website'])?'true':'false' ?>"><?= field_error($errors, 'website') ?></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>02</span><div><strong>قرار التواصل</strong><small>المعلومات التي تذكّرك لماذا وكيف تتواصل</small></div></legend>
                <div class="form-grid">
                    <div class="field full"><label for="fit_reason">لماذا هذه الجهة مناسبة؟</label><textarea id="fit_reason" name="fit_reason" placeholder="اكتب سبباً عملياً مختصراً، لا وصفاً عاماً..."><?= e($data['fit_reason']) ?></textarea><small class="field-hint">مثال: تحتاج أنظمة بيانات ميدانية ويمكنني تقديم نموذج أولي سريع.</small></div>
                    <div class="field full"><label for="first_offer">ما أول عرض أو مدخل للتواصل؟</label><textarea id="first_offer" name="first_offer" placeholder="خدمة محددة، إحالة، مشروع صغير، أو سؤال مهني..."><?= e($data['first_offer']) ?></textarea></div>
                    <div class="field full"><label for="notes">ملاحظات داخلية</label><textarea id="notes" name="notes" placeholder="سياق إضافي لا يدخل في رسالة التواصل..."><?= e($data['notes']) ?></textarea></div>
                </div>
            </fieldset>
            <div class="form-actions"><button class="btn" type="submit"><?= $id ? 'حفظ التعديلات' : 'حفظ والانتقال لإضافة شخص' ?></button><a class="btn secondary" href="<?= $id ? 'organization_view.php?id='.$id : 'organizations.php' ?>">إلغاء</a></div>
        </form>
    </section>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';
