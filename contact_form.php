<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$orgPref = (int)($_GET['organization_id'] ?? 0);
$row = $id ? q('SELECT c.* FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE c.id=? AND o.user_id=?', [$id,$userId])->fetch() : null;
if ($id && !$row) { http_response_code(404); exit('الشخص غير موجود'); }
$data = $row ?: ['organization_id'=>$orgPref,'name'=>'','job_title'=>'','email'=>'','phone'=>'','profile_url'=>'','relationship_type'=>'شبكة مهنية','status'=>'لم أتواصل','last_contact_date'=>'','next_followup_date'=>'','notes'=>''];
$orgs = q('SELECT id,name FROM organizations WHERE user_id=? ORDER BY name',[$userId])->fetchAll();
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($data) as $key) $data[$key] = trim((string)($_POST[$key] ?? ''));

    add_required_error($errors, 'organization_id', $data['organization_id'], 'الجهة');
    add_required_error($errors, 'name', $data['name'], 'اسم الشخص');
    add_max_error($errors, 'name', $data['name'], 'اسم الشخص', 180);
    add_max_error($errors, 'job_title', $data['job_title'], 'المنصب', 180);
    add_email_error($errors, 'email', $data['email']);
    add_max_error($errors, 'email', $data['email'], 'البريد الإلكتروني', 180);
    add_max_error($errors, 'phone', $data['phone'], 'الهاتف', 80);
    add_url_error($errors, 'profile_url', $data['profile_url'], 'رابط الحساب');
    add_max_error($errors, 'profile_url', $data['profile_url'], 'رابط الحساب', 500);
    add_choice_error($errors, 'relationship_type', $data['relationship_type'], relationship_types(), 'نوع العلاقة');
    add_choice_error($errors, 'status', $data['status'], contact_statuses(), 'الحالة');
    add_date_error($errors, 'last_contact_date', $data['last_contact_date'], 'آخر تواصل');
    add_date_error($errors, 'next_followup_date', $data['next_followup_date'], 'موعد المتابعة');

    if ($data['organization_id'] !== '' && !(int)scalar('SELECT COUNT(*) FROM organizations WHERE id=? AND user_id=?', [(int)$data['organization_id'],$userId])) {
        $errors['organization_id'] = 'الجهة المحددة غير موجودة.';
    }
    if ($data['name'] !== '' && $data['organization_id'] !== '') {
        $duplicate = (int)scalar('SELECT COUNT(*) FROM contacts WHERE organization_id=? AND LOWER(name)=LOWER(?) AND id<>?', [(int)$data['organization_id'], $data['name'], $id]);
        if ($duplicate) $errors['name'] = 'هذا الشخص مسجل مسبقاً داخل الجهة نفسها.';
    }
    if ($data['status'] !== 'لم أتواصل' && $data['last_contact_date'] === '' && in_array($data['status'], ['تم الإرسال','تم القبول','تم الرد','اجتماع','عرض مرسل'], true)) {
        $errors['last_contact_date'] = 'حدد تاريخ آخر تواصل عند اختيار هذه المرحلة.';
    }
    if (in_array($data['status'], ['تم الإرسال','تم القبول','تم الرد','اجتماع','عرض مرسل'], true) && $data['next_followup_date'] === '') {
        $errors['next_followup_date'] = 'حدد موعد المتابعة كي لا تضيع العلاقة داخل المسار.';
    }
    if ($data['last_contact_date'] && $data['next_followup_date'] && $data['next_followup_date'] < $data['last_contact_date']) {
        $errors['next_followup_date'] = 'موعد المتابعة يجب أن يكون بعد تاريخ آخر تواصل.';
    }

    if (!$errors) {
        if (in_array($data['status'], ['لا متابعة','مغلق'], true)) $data['next_followup_date'] = '';
        $values = [(int)$data['organization_id'],$data['name'],$data['job_title'],$data['email'],$data['phone'],$data['profile_url'],$data['relationship_type'],$data['status'],$data['last_contact_date']?:null,$data['next_followup_date']?:null,$data['notes']];
        if ($id) {
            q('UPDATE contacts SET organization_id=?,name=?,job_title=?,email=?,phone=?,profile_url=?,relationship_type=?,status=?,last_contact_date=?,next_followup_date=?,notes=? WHERE id=?', [...$values, $id]);
            flash('success', 'تم تحديث بيانات الشخص وخطة المتابعة.');
        } else {
            q('INSERT INTO contacts(organization_id,name,job_title,email,phone,profile_url,relationship_type,status,last_contact_date,next_followup_date,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)', $values);
            $id = (int)db()->lastInsertId();
            flash('success', 'تمت إضافة الشخص. سجّل أول تواصل أو حدد موعد المتابعة التالي.');
        }
        redirect('contact_view.php?id='.$id);
    }
}

$pageTitle = $id ? 'تعديل الشخص' : 'إضافة شخص';
$pageDescription = 'اربط الشخص بجهة وحدد حالة العلاقة والخطوة الزمنية التالية';
$active = 'contacts';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<div class="form-layout">
    <aside class="form-guide">
        <span class="guide-kicker">الخطوة 2 من مسار العلاقات</span>
        <h2>الشخص هو نقطة العمل</h2>
        <p>الجهة وحدها لا تُتابع. اربط كل تواصل بشخص محدد، وحافظ على حالة واحدة وموعد تالٍ واضح.</p>
        <ol><li>بيانات الجهة</li><li class="active">إضافة شخص</li><li>تسجيل تواصل</li><li>تحديد متابعة</li></ol>
    </aside>
    <section class="section form-card">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <fieldset class="form-section">
                <legend><span>01</span><div><strong>الهوية المهنية</strong><small>من هو الشخص وأين يعمل؟</small></div></legend>
                <div class="form-grid">
                    <div class="field full<?= field_class($errors, 'organization_id') ?>">
                        <label for="organization_id">الجهة <em>*</em></label>
                        <?php if (count($orgs) > 12): ?><input class="select-search" type="search" data-select-filter="organization_id" placeholder="اكتب للبحث داخل <?= count($orgs) ?> جهة..." aria-label="البحث داخل قائمة الجهات"><?php endif; ?>
                        <select id="organization_id" name="organization_id" required aria-invalid="<?= isset($errors['organization_id'])?'true':'false' ?>"><option value="">اختر الجهة</option><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$data['organization_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
                        <?= field_error($errors, 'organization_id') ?>
                        <?php if (!$orgs): ?><small class="field-error">لا توجد جهات. <a href="organization_form.php">أضف جهة أولاً</a>.</small><?php endif; ?>
                    </div>
                    <div class="field<?= field_class($errors, 'name') ?>"><label for="name">اسم الشخص <em>*</em></label><input id="name" name="name" value="<?= e($data['name']) ?>" maxlength="180" required autocomplete="name" placeholder="الاسم الكامل" aria-invalid="<?= isset($errors['name'])?'true':'false' ?>"><?= field_error($errors, 'name') ?></div>
                    <div class="field<?= field_class($errors, 'job_title') ?>"><label for="job_title">المنصب</label><input id="job_title" name="job_title" value="<?= e($data['job_title']) ?>" maxlength="180" autocomplete="organization-title" placeholder="المسمى الوظيفي" aria-invalid="<?= isset($errors['job_title'])?'true':'false' ?>"><?= field_error($errors, 'job_title') ?></div>
                    <div class="field<?= field_class($errors, 'relationship_type') ?>"><label for="relationship_type">نوع العلاقة <em>*</em></label><select id="relationship_type" name="relationship_type" required aria-invalid="<?= isset($errors['relationship_type'])?'true':'false' ?>"><?= select_options(relationship_types(), $data['relationship_type']) ?></select><?= field_error($errors, 'relationship_type') ?></div>
                    <div class="field<?= field_class($errors, 'status') ?>"><label for="status">مرحلة التواصل <em>*</em></label><select id="status" name="status" required aria-invalid="<?= isset($errors['status'])?'true':'false' ?>"><?= select_options(contact_statuses(), $data['status']) ?></select><?= field_error($errors, 'status') ?></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>02</span><div><strong>قنوات التواصل</strong><small>يكفي إدخال القناة التي ستستخدمها فعلياً</small></div></legend>
                <div class="form-grid">
                    <div class="field full<?= field_class($errors, 'profile_url') ?>"><label for="profile_url">LinkedIn أو رابط الحساب</label><input id="profile_url" type="url" name="profile_url" value="<?= e($data['profile_url']) ?>" maxlength="500" inputmode="url" autocomplete="url" placeholder="https://linkedin.com/in/..." aria-invalid="<?= isset($errors['profile_url'])?'true':'false' ?>"><?= field_error($errors, 'profile_url') ?></div>
                    <div class="field<?= field_class($errors, 'email') ?>"><label for="email">البريد الإلكتروني</label><input id="email" type="email" name="email" value="<?= e($data['email']) ?>" maxlength="180" inputmode="email" autocomplete="email" placeholder="name@example.com" aria-invalid="<?= isset($errors['email'])?'true':'false' ?>"><?= field_error($errors, 'email') ?></div>
                    <div class="field<?= field_class($errors, 'phone') ?>"><label for="phone">الهاتف</label><input id="phone" name="phone" value="<?= e($data['phone']) ?>" maxlength="80" inputmode="tel" autocomplete="tel" placeholder="+963 ..." aria-invalid="<?= isset($errors['phone'])?'true':'false' ?>"><?= field_error($errors, 'phone') ?></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>03</span><div><strong>التوقيت والخطوة التالية</strong><small>التاريخ القادم هو ما يحوّل السجل إلى مهمة</small></div></legend>
                <div class="form-grid">
                    <div class="field<?= field_class($errors, 'last_contact_date') ?>"><label for="last_contact_date">تاريخ آخر تواصل</label><input id="last_contact_date" type="date" name="last_contact_date" value="<?= e($data['last_contact_date']) ?>" aria-invalid="<?= isset($errors['last_contact_date'])?'true':'false' ?>"><?= field_error($errors, 'last_contact_date') ?></div>
                    <div class="field<?= field_class($errors, 'next_followup_date') ?>"><label for="next_followup_date">موعد المتابعة القادمة</label><input id="next_followup_date" type="date" name="next_followup_date" value="<?= e($data['next_followup_date']) ?>" aria-invalid="<?= isset($errors['next_followup_date'])?'true':'false' ?>"><?= field_error($errors, 'next_followup_date') ?><small class="field-hint">اتركه فارغاً فقط إذا كانت الحالة «لا متابعة» أو «مغلق».</small></div>
                    <div class="field full"><label for="notes">ملاحظات مفيدة للتواصل</label><textarea id="notes" name="notes" placeholder="السياق، الاهتمامات، نقطة الدخول، أو ما يجب تجنبه..."><?= e($data['notes']) ?></textarea></div>
                </div>
            </fieldset>
            <div class="form-actions"><button class="btn" type="submit"><?= $id ? 'حفظ التعديلات' : 'حفظ الشخص' ?></button><a class="btn secondary" href="<?= $id ? 'contact_view.php?id='.$id : 'contacts.php' ?>">إلغاء</a></div>
        </form>
    </section>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';
