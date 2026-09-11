<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id ? q('SELECT * FROM job_applications WHERE id=? AND user_id=?', [$id,$userId])->fetch() : null;
if ($id && !$row) { http_response_code(404); exit('الفرصة غير موجودة'); }
$data = $row ?: [
    'organization_id'=>'','company'=>'','job_title'=>'','job_url'=>'','source'=>'LinkedIn',
    'location'=>'','employment_type'=>'','deadline'=>'','contact_name'=>'','contact_url'=>'','notes'=>''
];
$orgs = q('SELECT id,name FROM organizations WHERE user_id=? ORDER BY name',[$userId])->fetchAll();
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($data) as $key) $data[$key] = trim((string)($_POST[$key] ?? ''));
    add_required_error($errors, 'company', $data['company'], 'الجهة');
    add_required_error($errors, 'job_title', $data['job_title'], 'المسمى الوظيفي');
    add_max_error($errors, 'company', $data['company'], 'الجهة', 180);
    add_max_error($errors, 'job_title', $data['job_title'], 'المسمى الوظيفي', 220);
    add_url_error($errors, 'job_url', $data['job_url'], 'رابط الإعلان');
    add_max_error($errors, 'job_url', $data['job_url'], 'رابط الإعلان', 500);
    add_max_error($errors, 'source', $data['source'], 'المصدر', 120);
    add_max_error($errors, 'location', $data['location'], 'المكان', 160);
    add_max_error($errors, 'employment_type', $data['employment_type'], 'نوع الدوام', 100);
    add_date_error($errors, 'deadline', $data['deadline'], 'آخر موعد');
    add_max_error($errors, 'contact_name', $data['contact_name'], 'الشخص المرتبط', 180);
    add_url_error($errors, 'contact_url', $data['contact_url'], 'رابط الشخص');
    if ($data['organization_id'] !== '' && !(int)scalar('SELECT COUNT(*) FROM organizations WHERE id=? AND user_id=?', [(int)$data['organization_id'],$userId])) {
        $errors['organization_id'] = 'الجهة المرتبطة غير موجودة.';
    }
    if ($data['company'] && $data['job_title']) {
        $duplicate = (int)scalar('SELECT COUNT(*) FROM job_applications WHERE user_id=? AND LOWER(company)=LOWER(?) AND LOWER(job_title)=LOWER(?) AND id<>?', [$userId,$data['company'],$data['job_title'],$id]);
        if ($duplicate) $errors['job_title'] = 'هذه الفرصة مسجلة مسبقاً للجهة نفسها.';
    }

    if (!$errors) {
        if ($id) {
            q('UPDATE job_applications SET organization_id=?,company=?,job_title=?,job_url=?,source=?,location=?,employment_type=?,deadline=?,contact_name=?,contact_url=?,notes=? WHERE id=? AND user_id=?', [
                $data['organization_id']?:null,$data['company'],$data['job_title'],$data['job_url'],$data['source'],$data['location'],$data['employment_type'],$data['deadline']?:null,$data['contact_name'],$data['contact_url'],$data['notes'],$id,$userId
            ]);
            q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$id,'تحديث','تم تحديث بيانات الفرصة الأساسية']);
            flash('success', 'تم تحديث بيانات الفرصة.');
        } else {
            q('INSERT INTO job_applications(user_id,organization_id,company,job_title,job_url,source,location,employment_type,deadline,contact_name,contact_url,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
                $userId,$data['organization_id']?:null,$data['company'],$data['job_title'],$data['job_url'],$data['source'],$data['location'],$data['employment_type'],$data['deadline']?:null,$data['contact_name'],$data['contact_url'],$data['notes']
            ]);
            $id = (int)db()->lastInsertId();
            q('INSERT INTO job_activities(job_application_id,activity_type,status_to,summary) VALUES(?,?,?,?)', [$id,'إنشاء','محفوظة','تم تسجيل الفرصة داخل مساحة العمل']);
            flash('success', 'تم إنشاء مساحة الفرصة. أضف وثائق التقديم من الصفحة التالية.');
        }
        redirect('job_view.php?id='.$id);
    }
}

$pageTitle = $id ? 'تعديل بيانات الفرصة' : 'إضافة فرصة جديدة';
$pageDescription = 'سجّل الإعلان أولاً؛ الوثائق والمقابلات تُدار داخل مساحة الفرصة';
$active = 'jobs';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<div class="form-layout job-create-layout">
    <aside class="form-guide">
        <span class="guide-kicker">إنشاء مساحة فرصة</span>
        <h2>ابدأ بأقل قدر مفيد</h2>
        <p>سجّل الإعلان والموعد فقط. بعد الحفظ تحصل على Workspace لإدارة الملفات والمراحل والمقابلات.</p>
        <div class="guide-rule"><strong>لن نطلب هنا:</strong><span>CV أو Cover Letter كنص، ولا تفاصيل مقابلة لم تحدث بعد.</span></div>
    </aside>
    <section class="section form-card">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <fieldset class="form-section">
                <legend><span>01</span><div><strong>الإعلان</strong><small>البيانات التي تعرّف الفرصة</small></div></legend>
                <div class="form-grid">
                    <div class="field<?= field_class($errors,'job_title') ?>"><label for="job_title">المسمى الوظيفي <em>*</em></label><input id="job_title" name="job_title" value="<?= e($data['job_title']) ?>" maxlength="220" required placeholder="Data Systems Consultant" aria-invalid="<?= isset($errors['job_title'])?'true':'false' ?>"><?= field_error($errors,'job_title') ?></div>
                    <div class="field<?= field_class($errors,'company') ?>"><label for="company">الجهة <em>*</em></label><input id="company" name="company" value="<?= e($data['company']) ?>" maxlength="180" required autocomplete="organization" placeholder="اسم الشركة أو المنظمة" aria-invalid="<?= isset($errors['company'])?'true':'false' ?>"><?= field_error($errors,'company') ?></div>
                    <div class="field full<?= field_class($errors,'organization_id') ?>"><label for="organization_id">ربط بجهة موجودة <span class="optional">اختياري</span></label><?php if(count($orgs)>12):?><input class="select-search" type="search" data-select-filter="organization_id" placeholder="ابحث داخل الجهات..." aria-label="البحث داخل الجهات"><?php endif;?><select id="organization_id" name="organization_id" data-copy-option-to="company"><option value="">بدون ربط</option><?php foreach($orgs as $o):?><option value="<?= (int)$o['id'] ?>" <?= (int)$data['organization_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach;?></select><?= field_error($errors,'organization_id') ?></div>
                    <div class="field full<?= field_class($errors,'job_url') ?>"><label for="job_url">رابط الإعلان</label><input id="job_url" type="url" name="job_url" value="<?= e($data['job_url']) ?>" maxlength="500" inputmode="url" placeholder="https://..." aria-invalid="<?= isset($errors['job_url'])?'true':'false' ?>"><?= field_error($errors,'job_url') ?></div>
                    <div class="field<?= field_class($errors,'source') ?>"><label for="source">المصدر</label><input id="source" name="source" value="<?= e($data['source']) ?>" maxlength="120" placeholder="LinkedIn، إحالة، موقع الجهة" aria-invalid="<?= isset($errors['source'])?'true':'false' ?>"><?= field_error($errors,'source') ?></div>
                    <div class="field<?= field_class($errors,'deadline') ?>"><label for="deadline">آخر موعد للتقديم</label><input id="deadline" type="date" name="deadline" value="<?= e($data['deadline']) ?>" aria-invalid="<?= isset($errors['deadline'])?'true':'false' ?>"><?= field_error($errors,'deadline') ?></div>
                    <div class="field<?= field_class($errors,'location') ?>"><label for="location">المكان</label><input id="location" name="location" value="<?= e($data['location']) ?>" maxlength="160" placeholder="Remote، Dubai، Damascus" aria-invalid="<?= isset($errors['location'])?'true':'false' ?>"><?= field_error($errors,'location') ?></div>
                    <div class="field<?= field_class($errors,'employment_type') ?>"><label for="employment_type">نوع التعاقد</label><input id="employment_type" name="employment_type" value="<?= e($data['employment_type']) ?>" maxlength="100" placeholder="Full-time، Contract، Freelance" aria-invalid="<?= isset($errors['employment_type'])?'true':'false' ?>"><?= field_error($errors,'employment_type') ?></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>02</span><div><strong>نقطة الاتصال والسياق</strong><small>اختياري، ويمكن تحديثه لاحقاً</small></div></legend>
                <div class="form-grid">
                    <div class="field"><label for="contact_name">Recruiter أو Hiring Manager</label><input id="contact_name" name="contact_name" value="<?= e($data['contact_name']) ?>" maxlength="180" placeholder="اسم الشخص"></div>
                    <div class="field<?= field_class($errors,'contact_url') ?>"><label for="contact_url">رابط الشخص</label><input id="contact_url" type="url" name="contact_url" value="<?= e($data['contact_url']) ?>" maxlength="500" inputmode="url" placeholder="LinkedIn URL" aria-invalid="<?= isset($errors['contact_url'])?'true':'false' ?>"><?= field_error($errors,'contact_url') ?></div>
                    <div class="field full"><label for="notes">ملاحظات أولية</label><textarea id="notes" name="notes" placeholder="لماذا تناسبك الفرصة؟ ما المهارات التي يجب إبرازها؟"><?= e($data['notes']) ?></textarea></div>
                </div>
            </fieldset>
            <div class="form-actions"><button class="btn" type="submit"><?= $id?'حفظ التعديلات':'إنشاء مساحة الفرصة' ?></button><a class="btn secondary" href="<?= $id?'job_view.php?id='.$id:'jobs.php' ?>">إلغاء</a></div>
        </form>
    </section>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';
