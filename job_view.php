<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/options.php';
require_once __DIR__.'/includes/reminders.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? 0);
$job = q('SELECT j.*,o.name linked_organization FROM job_applications j LEFT JOIN organizations o ON o.id=j.organization_id AND o.user_id=j.user_id WHERE j.id=? AND j.user_id=?', [$id,$userId])->fetch();
if (!$job) { http_response_code(404); exit('الفرصة غير موجودة'); }
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_job') {
        $confirmation = trim((string)($_POST['delete_confirmation'] ?? ''));
        if ($confirmation !== 'حذف') {
            flash('error', 'اكتب كلمة حذف كما هي لتأكيد العملية.');
            redirect('job_view.php?id='.$id.'#danger-zone');
        }

        $storedDocuments = q('SELECT stored_name FROM job_documents WHERE job_application_id=?', [$id])->fetchAll();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q('DELETE FROM job_applications WHERE id=? AND user_id=?', [$id,$userId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }

        $storageRoot = realpath(__DIR__.'/storage/documents');
        if ($storageRoot !== false) {
            foreach ($storedDocuments as $storedDocument) {
                $storedName = basename((string)$storedDocument['stored_name']);
                $documentPath = $storageRoot.DIRECTORY_SEPARATOR.$storedName;
                if (is_file($documentPath)) @unlink($documentPath);
            }
        }
        flash('success', 'تم حذف الفرصة وجميع وثائقها ومقابلاتها وسجلها.');
        redirect('jobs.php');
    }

    if ($action === 'send_reminder_now') {
        try {
            $result = reminder_send_digest(true, $id, $userId);
            flash('success', 'تم إرسال تذكير الفرصة إلى بريدك بنجاح.');
        } catch (Throwable $exception) {
            flash('error', 'تعذر إرسال البريد الآن. تحقق من اتصال SMTP ثم أعد المحاولة.');
        }
        redirect('job_view.php?id='.$id.'#email-reminders');
    }

    if ($action === 'update_stage') {
        $status = trim((string)($_POST['status'] ?? ''));
        $appliedDate = trim((string)($_POST['applied_date'] ?? ''));
        $followup = trim((string)($_POST['next_followup_date'] ?? ''));
        add_choice_error($errors, 'status', $status, job_statuses(), 'مرحلة الطلب');
        add_date_error($errors, 'applied_date', $appliedDate, 'تاريخ التقديم');
        add_date_error($errors, 'next_followup_date', $followup, 'المتابعة القادمة');
        if (in_array($status, ['تم التقديم','قيد المراجعة','مقابلة','اختبار','عرض وظيفي','مقبول','مرفوض'], true) && $appliedDate === '') {
            $errors['applied_date'] = 'تاريخ التقديم مطلوب لهذه المرحلة.';
        }
        if (in_array($status, ['تم التقديم','قيد المراجعة'], true) && $followup === '') {
            $errors['next_followup_date'] = 'حدد موعد متابعة للطلب بعد التقديم.';
        }
        if ($appliedDate && $followup && $followup < $appliedDate) $errors['next_followup_date'] = 'المتابعة يجب أن تكون بعد تاريخ التقديم.';
        if (!$errors) {
            q('UPDATE job_applications SET status=?,applied_date=?,next_followup_date=? WHERE id=? AND user_id=?', [$status,$appliedDate?:null,$followup?:null,$id,$userId]);
            if ($status !== $job['status']) {
                q('INSERT INTO job_activities(job_application_id,activity_type,status_from,status_to,summary) VALUES(?,?,?,?,?)', [$id,'تغيير مرحلة',$job['status'],$status,'انتقل الطلب من '.$job['status'].' إلى '.$status]);
            } else {
                q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$id,'تحديث مواعيد','تم تحديث مواعيد التقديم والمتابعة']);
            }
            flash('success', 'تم تحديث مرحلة الطلب ومواعيده.');
            redirect('job_view.php?id='.$id);
        }
    }

    if ($action === 'upload_document') {
        $type = trim((string)($_POST['document_type'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        if (!array_key_exists($type, job_document_types())) $errors['document_type'] = 'اختر نوع مستند صحيحاً.';
        add_max_error($errors, 'label', $label, 'وصف المستند', 180);
        $file = $_FILES['document'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors['document'] = 'اختر ملف PDF أو Word لا يتجاوز 8 MB.';
        } elseif ((int)$file['size'] > 8 * 1024 * 1024) {
            $errors['document'] = 'حجم الملف يتجاوز الحد المسموح 8 MB.';
        } elseif (text_length(basename((string)$file['name'])) > 255) {
            $errors['document'] = 'اسم الملف طويل جداً. أعد تسميته باسم أقصر.';
        } elseif (!class_exists('finfo')) {
            $errors['document'] = 'امتداد Fileinfo مطلوب على الخادم للتحقق الآمن من الملفات.';
        } else {
            $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = [
                'pdf' => ['application/pdf'],
                'doc' => ['application/msword','application/octet-stream'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],
            ];
            if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
                $errors['document'] = 'نوع الملف غير مسموح. استخدم PDF أو DOC أو DOCX فقط.';
            } else {
                $signature = file_get_contents($file['tmp_name'], false, null, 0, 8);
                if ($extension === 'pdf' && !str_starts_with((string)$signature, '%PDF')) $errors['document'] = 'ملف PDF غير صالح.';
                if ($extension === 'doc' && strtoupper(bin2hex((string)$signature)) !== 'D0CF11E0A1B11AE1') $errors['document'] = 'ملف Word غير صالح.';
                if ($extension === 'docx' && class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    $opened = $zip->open($file['tmp_name']) === true;
                    if (!$opened || $zip->locateName('word/document.xml') === false) $errors['document'] = 'ملف DOCX غير صالح.';
                    if ($opened) $zip->close();
                }
            }
        }
        if (!$errors) {
            $storage = __DIR__.'/storage/documents';
            if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) throw new RuntimeException('تعذر إنشاء مجلد حفظ المستندات.');
            $storedName = bin2hex(random_bytes(24)).'.'.$extension;
            $target = $storage.'/'.$storedName;
            if (!move_uploaded_file($file['tmp_name'], $target)) $errors['document'] = 'تعذر حفظ الملف. تحقق من صلاحيات مجلد storage.';
            elseif (!chmod($target, 0640)) $errors['document'] = 'تم رفع الملف لكن تعذر ضبط صلاحياته الآمنة.';
            if ($errors && is_file($target)) unlink($target);
            if (!$errors) {
                try {
                    q('INSERT INTO job_documents(job_application_id,document_type,label,original_name,stored_name,mime_type,size_bytes) VALUES(?,?,?,?,?,?,?)', [$id,$type,$label,basename((string)$file['name']),$storedName,$mime,(int)$file['size']]);
                    q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$id,'مستند','تم رفع '.document_type_label($type).': '.basename((string)$file['name'])]);
                } catch (Throwable $e) {
                    if (is_file($target)) unlink($target);
                    throw $e;
                }
                flash('success', 'تم رفع المستند وربطه بهذه الفرصة.');
                redirect('job_view.php?id='.$id.'#documents');
            }
        }
    }

    if ($action === 'delete_document') {
        $documentId = (int)($_POST['document_id'] ?? 0);
        $document = q('SELECT * FROM job_documents WHERE id=? AND job_application_id=?', [$documentId,$id])->fetch();
        if ($document) {
            q('DELETE FROM job_documents WHERE id=?', [$documentId]);
            $path = __DIR__.'/storage/documents/'.basename($document['stored_name']);
            if (is_file($path)) unlink($path);
            q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$id,'مستند','تم حذف المستند: '.$document['original_name']]);
            flash('success', 'تم حذف المستند.');
        }
        redirect('job_view.php?id='.$id.'#documents');
    }

    if ($action === 'interview_status') {
        $interviewId = (int)($_POST['interview_id'] ?? 0);
        $interviewStatus = trim((string)($_POST['interview_status'] ?? ''));
        if (!in_array($interviewStatus, interview_statuses(), true)) $errors['interview_status'] = 'حالة المقابلة غير صحيحة.';
        $interview = q('SELECT * FROM job_interviews WHERE id=? AND job_application_id=?', [$interviewId,$id])->fetch();
        if (!$interview) $errors['interview_status'] = 'المقابلة غير موجودة.';
        if (!$errors) {
            q('UPDATE job_interviews SET status=? WHERE id=?', [$interviewStatus,$interviewId]);
            q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$id,'مقابلة','تم تحديث حالة الجولة '.$interview['round_number'].' إلى '.$interviewStatus]);
            flash('success', 'تم تحديث حالة المقابلة.');
            redirect('job_view.php?id='.$id.'#interviews');
        }
    }
}

$documents = q('SELECT * FROM job_documents WHERE job_application_id=? ORDER BY created_at DESC', [$id])->fetchAll();
$interviews = q('SELECT * FROM job_interviews WHERE job_application_id=? ORDER BY scheduled_at ASC,round_number ASC', [$id])->fetchAll();
$activities = q('SELECT * FROM job_activities WHERE job_application_id=? ORDER BY created_at DESC,id DESC LIMIT 10', [$id])->fetchAll();
$documentTypesPresent = array_column($documents, 'document_type');

$stageGroups = [
    ['label'=>'حفظ','statuses'=>['محفوظة']],
    ['label'=>'تحضير','statuses'=>['قيد التحضير']],
    ['label'=>'تقديم','statuses'=>['تم التقديم','قيد المراجعة']],
    ['label'=>'مقابلات','statuses'=>['مقابلة','اختبار']],
    ['label'=>'قرار','statuses'=>['عرض وظيفي','مقبول','مرفوض','منسحب','مغلق']],
];
$currentStage = 0;
foreach ($stageGroups as $index=>$group) if (in_array($job['status'], $group['statuses'], true)) $currentStage = $index;

$nextAction = ['title'=>'جهّز طلباً مخصصاً','text'=>'ارفع نسخة CV مناسبة للفرصة ثم انقل الطلب إلى «قيد التحضير».','action'=>'إضافة مستند','href'=>'#documents'];
if ($job['status']==='قيد التحضير') {
    $nextAction = in_array('cv',$documentTypesPresent,true)
        ? ['title'=>'راجع ثم قدّم','text'=>'الـCV موجود. أضف خطاب التغطية عند الحاجة ثم سجّل تاريخ التقديم.','action'=>'تحديث المرحلة','href'=>'#stage-control']
        : ['title'=>'أضف نسخة CV','text'=>'لا يوجد ملف سيرة ذاتية مرتبط بهذه الفرصة بعد.','action'=>'رفع CV','href'=>'#documents'];
}
if (in_array($job['status'], ['تم التقديم','قيد المراجعة'], true)) $nextAction = ['title'=>'لا تترك الطلب صامتاً','text'=>'راجع موعد المتابعة وتواصل مع Recruiter أو Hiring Manager عند حلوله.','action'=>'إدارة المتابعة','href'=>'#stage-control'];
if (in_array($job['status'], ['مقابلة','اختبار'], true)) $nextAction = ['title'=>'حضّر الجولة القادمة','text'=>'سجّل الموعد والمحاور والأسئلة المتوقعة وخطة التحضير.','action'=>'إضافة مقابلة','href'=>'interview_form.php?job_id='.$id];
if ($job['status']==='عرض وظيفي') $nextAction = ['title'=>'راجع العرض كقرار تجاري','text'=>'وثّق التفاصيل والملاحظات قبل الانتقال إلى قبول أو إغلاق.','action'=>'مراجعة البيانات','href'=>'#application-notes'];
if ($job['status']==='مقبول') $nextAction = ['title'=>'تم إغلاق الفرصة بنجاح','text'=>'احتفظ بالوثائق والمقابلات كسجل يوضح ما الذي نجح في هذا الطلب.','action'=>'مراجعة السجل','href'=>'#application-notes'];
if (in_array($job['status'], ['مرفوض','منسحب','مغلق'], true)) $nextAction = ['title'=>'استخرج التعلم','text'=>'دوّن سبب النتيجة وما ستغيّره في الطلب القادم.','action'=>'إضافة ملاحظة','href'=>'#application-notes'];

$primaryDocuments = [];
foreach ($documents as $document) if (!isset($primaryDocuments[$document['document_type']])) $primaryDocuments[$document['document_type']] = $document;
$documentSlots = [
    ['type'=>'cv','title'=>'السيرة الذاتية','hint'=>'مطلوب','description'=>'النسخة المخصصة لهذه الوظيفة'],
    ['type'=>'cover_letter','title'=>'خطاب التغطية','hint'=>'موصى به','description'=>'الخطاب المرسل مع الطلب'],
    ['type'=>'portfolio','title'=>'ملف الأعمال','hint'=>'اختياري','description'=>'نماذج العمل أو Case studies'],
];
$stageProgress = (($currentStage + 1) / count($stageGroups)) * 100;
$mailConfig = $config['mail'] ?? [];
$account = q('SELECT reminder_email,reminders_enabled FROM users WHERE id=?',[$userId])->fetch();
$mailRecipient = (string)($account['reminder_email'] ?? '');
$mailEnabled = !empty($mailConfig['enabled']) && !empty($account['reminders_enabled']) && filter_var($mailRecipient,FILTER_VALIDATE_EMAIL);
$pageTitle = $job['job_title'];
$pageDescription = $job['company'].' · مساحة تشغيل طلب العمل';
$active = 'jobs';
$workspaceMode = true;
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<section class="application-header">
    <div class="application-header-top">
        <div class="application-identity">
            <span class="company-monogram"><?= e(strtoupper(substr($job['company'],0,1))) ?></span>
            <div>
                <div class="application-overline"><span>طلب عمل #<?= $id ?></span><span class="<?= badge_class($job['status']) ?>"><?= e($job['status']) ?></span></div>
                <h1><?= e($job['job_title']) ?></h1>
                <p><strong><?= e($job['company']) ?></strong><?= $job['location']?' · '.e($job['location']):'' ?><?= $job['employment_type']?' · '.e($job['employment_type']):'' ?></p>
            </div>
        </div>
        <div class="application-actions">
            <?php if($job['job_url']):?><a class="btn secondary" target="_blank" rel="noopener" href="<?= e($job['job_url']) ?>"><span>الإعلان</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg></a><?php endif;?>
            <a class="btn secondary" href="job_form.php?id=<?= $id ?>"><span>تعديل</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 20 9-9-4-4-9 9-1 5 5-1ZM15 9l4 4"/></svg></a>
        </div>
    </div>
    <div class="application-facts">
        <div><span>تاريخ التقديم</span><strong><?= date_or_dash($job['applied_date']) ?></strong></div>
        <div><span>المتابعة القادمة</span><strong class="<?= ($job['next_followup_date']&&strtotime($job['next_followup_date'])<strtotime(date('Y-m-d')))?'due':'' ?>"><?= date_or_dash($job['next_followup_date']) ?></strong></div>
        <div><span>آخر موعد</span><strong><?= date_or_dash($job['deadline']) ?></strong></div>
        <div><span>الوثائق</span><strong><?= count($documents) ?> ملفات</strong></div>
        <div><span>المقابلات</span><strong><?= count($interviews) ?> جولات</strong></div>
    </div>
    <div class="application-stage-desktop" aria-label="مراحل طلب العمل">
        <?php foreach($stageGroups as $index=>$stage):?><div class="application-stage <?= $index<$currentStage?'complete':($index===$currentStage?'active':'') ?>"><span><?= $index<$currentStage?'✓':$index+1 ?></span><strong><?= e($stage['label']) ?></strong></div><?php endforeach;?>
    </div>
    <div class="application-stage-mobile">
        <div><span>المرحلة <?= $currentStage+1 ?> من <?= count($stageGroups) ?></span><strong><?= e($stageGroups[$currentStage]['label']) ?></strong></div>
        <div class="stage-progress"><span style="width:<?= e((string)$stageProgress) ?>%"></span></div>
    </div>
</section>

<nav class="workspace-tabs" aria-label="أقسام مساحة الفرصة">
    <a href="#overview" class="active">نظرة عامة</a>
    <a href="#documents">الوثائق <span><?= count($documents) ?></span></a>
    <a href="#interviews">المقابلات <span><?= count($interviews) ?></span></a>
    <a href="#activity">النشاط</a>
</nav>

<div class="application-layout">
    <main class="application-main">
        <section class="action-center" id="overview">
            <span class="action-center-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m13 2-9 11h7l-1 9 9-12h-7l1-8Z"/></svg></span>
            <div><small>الإجراء التالي المقترح</small><h2><?= e($nextAction['title']) ?></h2><p><?= e($nextAction['text']) ?></p></div>
            <a class="btn" href="<?= e($nextAction['href']) ?>"><?= e($nextAction['action']) ?></a>
        </section>

        <section class="enterprise-panel" id="documents">
            <div class="enterprise-panel-head"><div><h2>وثائق التقديم</h2><p>اعرف بالضبط أي نسخة استخدمتها في كل طلب.</p></div><button class="text-action" type="button" data-open-details="upload-document"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>رفع مستند</button></div>
            <div class="document-slots">
                <?php foreach($documentSlots as $slot):$slotDocument=$primaryDocuments[$slot['type']]??null;?>
                <article class="document-slot <?= $slotDocument?'ready':'' ?>">
                    <div class="slot-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6V2Zm8 0v5h5M9 13h6M9 17h6M9 9h2"/></svg></div>
                    <div class="slot-copy"><div><strong><?= e($slot['title']) ?></strong><span><?= e($slot['hint']) ?></span></div><p><?= $slotDocument?e($slotDocument['label']?:$slotDocument['original_name']):e($slot['description']) ?></p><?php if($slotDocument):?><small><?= e(format_file_size((int)$slotDocument['size_bytes'])) ?> · <?= e(date('Y-m-d',strtotime($slotDocument['created_at']))) ?></small><?php endif;?></div>
                    <?php if($slotDocument):?><a class="slot-action" href="job_document.php?id=<?= (int)$slotDocument['id'] ?>" aria-label="تنزيل <?= e($slot['title']) ?>">↓</a><?php else:?><button class="slot-action" type="button" data-open-details="upload-document" aria-label="رفع <?= e($slot['title']) ?>">+</button><?php endif;?>
                </article>
                <?php endforeach;?>
            </div>
            <?php if($documents):?><div class="document-list"><?php foreach($documents as $doc):?><div class="document-row"><span class="file-extension"><?= e(strtoupper(pathinfo($doc['original_name'],PATHINFO_EXTENSION))) ?></span><div><strong><?= e($doc['label']?:$doc['original_name']) ?></strong><small><?= e(document_type_label($doc['document_type'])) ?> · <?= e(format_file_size((int)$doc['size_bytes'])) ?></small></div><div><a href="job_document.php?id=<?= (int)$doc['id'] ?>">تنزيل</a><form method="post"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>"><?= csrf_field() ?><button type="submit" data-confirm="حذف هذا المستند نهائياً؟">حذف</button></form></div></div><?php endforeach;?></div><?php endif;?>
            <details class="upload-disclosure" id="upload-document" <?= isset($errors['document'])||isset($errors['document_type'])?'open':'' ?>>
                <summary><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg></span><div><strong>رفع مستند جديد</strong><small>PDF أو Word، بحد أقصى 8 MB</small></div><b>⌄</b></summary>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?><input type="hidden" name="action" value="upload_document">
                    <div class="upload-form-grid">
                        <div class="field<?= field_class($errors,'document_type') ?>"><label for="document_type">نوع المستند</label><select id="document_type" name="document_type"><?php foreach(job_document_types() as $value=>$label):?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach;?></select><?= field_error($errors,'document_type') ?></div>
                        <div class="field"><label for="document_label">اسم واضح للنسخة</label><input id="document_label" name="label" maxlength="180" placeholder="CV — Data Systems — v2"></div>
                        <div class="field file-field<?= field_class($errors,'document') ?>"><label for="document">اختر الملف</label><input id="document" type="file" name="document" accept=".pdf,.doc,.docx" required><?= field_error($errors,'document') ?></div>
                        <button class="btn" type="submit">رفع وحفظ</button>
                    </div>
                </form>
            </details>
        </section>

        <section class="enterprise-panel" id="interviews">
            <div class="enterprise-panel-head"><div><h2>المقابلات</h2><p>الجولات القادمة، التحضير، والنتائج في مكان واحد.</p></div><a class="text-action" href="interview_form.php?job_id=<?= $id ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>إضافة مقابلة</a></div>
            <?php if(!$interviews):?><div class="compact-empty"><span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 15h.01M12 15h.01"/></svg></span><div><strong>لا توجد مقابلات بعد</strong><p>عند وصول الدعوة، أضف الموعد وخطة التحضير خلال دقيقة.</p></div><a href="interview_form.php?job_id=<?= $id ?>">جدولة مقابلة</a></div><?php else:?><div class="interview-list"><?php foreach($interviews as $interview):?><article class="interview-card"><div class="interview-date"><strong><?= date('d',strtotime($interview['scheduled_at'])) ?></strong><span><?= date('m/Y',strtotime($interview['scheduled_at'])) ?></span><small><?= date('H:i',strtotime($interview['scheduled_at'])) ?></small></div><div class="interview-info"><div><span class="round-chip">الجولة <?= (int)$interview['round_number'] ?></span><span class="<?= badge_class($interview['status']) ?>"><?= e($interview['status']) ?></span></div><h3><?= e($interview['interview_type']) ?></h3><p><?= e($interview['interview_format']) ?><?= $interview['interviewer_name']?' · '.e($interview['interviewer_name']):'' ?></p></div><div class="interview-actions"><?php if($interview['meeting_url']):?><a class="btn small" target="_blank" rel="noopener" href="<?= e($interview['meeting_url']) ?>">دخول الاجتماع</a><?php endif;?><a class="btn small secondary" href="interview_form.php?job_id=<?= $id ?>&id=<?= (int)$interview['id'] ?>">فتح الجولة</a></div></article><?php endforeach;?></div><?php endif;?>
        </section>

        <section class="enterprise-panel" id="activity">
            <div class="enterprise-panel-head"><div><h2>سجل النشاط</h2><p>تاريخ مختصر لكل تغيير مهم في الطلب.</p></div></div>
            <?php if(!$activities):?><div class="compact-empty minimal"><div><strong>لا يوجد نشاط موثّق بعد</strong></div></div><?php else:?><div class="enterprise-activity"><?php foreach($activities as $activity):?><div><span></span><div><strong><?= e($activity['activity_type']) ?></strong><p><?= e($activity['summary']) ?></p></div><time><?= e(date('Y-m-d H:i',strtotime($activity['created_at']))) ?></time></div><?php endforeach;?></div><?php endif;?>
        </section>
    </main>

    <aside class="application-sidebar">
        <section class="control-card" id="stage-control">
            <div class="control-card-head"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></span><div><strong>إدارة الطلب</strong><small>المرحلة والمواعيد</small></div></div>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_stage">
                <div class="field<?= field_class($errors,'status') ?>"><label for="status">المرحلة الحالية</label><select id="status" name="status"><?= select_options(job_statuses(),$job['status']) ?></select><?= field_error($errors,'status') ?></div>
                <div class="field-row"><div class="field<?= field_class($errors,'applied_date') ?>"><label for="applied_date">تاريخ التقديم</label><input id="applied_date" type="date" name="applied_date" value="<?= e($job['applied_date']) ?>"><?= field_error($errors,'applied_date') ?></div><div class="field<?= field_class($errors,'next_followup_date') ?>"><label for="next_followup_date">المتابعة</label><input id="next_followup_date" type="date" name="next_followup_date" value="<?= e($job['next_followup_date']) ?>"><?= field_error($errors,'next_followup_date') ?></div></div>
                <button class="btn block" type="submit">تحديث الطلب</button>
            </form>
        </section>

        <section class="control-card mail-reminder-card" id="email-reminders">
            <div class="reminder-status-row">
                <span class="reminder-status-dot <?= $mailEnabled?'online':'offline' ?>" aria-hidden="true"></span>
                <div><strong>تذكيرات البريد</strong><small><?= $mailEnabled?'متصلة وجاهزة للإرسال':'إعداد SMTP غير مكتمل' ?></small></div>
            </div>
            <?php if($mailEnabled):?>
                <div class="reminder-recipient"><span>الإرسال إلى</span><strong><?= e($mailRecipient) ?></strong></div>
                <div class="reminder-next"><span>المتابعة القادمة</span><strong><?= date_or_dash($job['next_followup_date']) ?></strong></div>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_reminder_now"><button class="btn secondary block" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 9 6 9-6M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>إرسال تذكير الآن</button></form>
                <p class="reminder-help">التشغيل اليومي يرسل ملخصاً واحداً للمتابعات والمقابلات القريبة دون تكرار.</p>
            <?php else:?><p class="reminder-help">أكمل إعدادات البريد في ملف البيئة المحلي لتفعيل الإرسال.</p><?php endif;?>
        </section>

        <section class="control-card">
            <div class="control-card-title"><strong>تفاصيل الفرصة</strong></div>
            <dl class="application-detail-list"><div><dt>المصدر</dt><dd><?= e($job['source'])?:'—' ?></dd></div><div><dt>نوع التعاقد</dt><dd><?= e($job['employment_type'])?:'—' ?></dd></div><div><dt>المكان</dt><dd><?= e($job['location'])?:'—' ?></dd></div><div><dt>الجهة المرتبطة</dt><dd><?= e($job['linked_organization'])?:'—' ?></dd></div></dl>
        </section>

        <?php if($job['contact_name']||$job['contact_url']):?><section class="control-card"><div class="control-card-title"><strong>نقطة الاتصال</strong></div><div class="contact-person"><span><?= e(strtoupper(substr($job['contact_name']?:'R',0,1))) ?></span><div><strong><?= e($job['contact_name']?:'رابط التواصل') ?></strong><?php if($job['contact_url']):?><a target="_blank" rel="noopener" href="<?= e($job['contact_url']) ?>">فتح LinkedIn ↗</a><?php endif;?></div></div></section><?php endif;?>

        <section class="control-card" id="application-notes"><div class="control-card-title"><strong>ملاحظات</strong><a href="job_form.php?id=<?= $id ?>">تعديل</a></div><p class="application-note"><?= nl2br(e($job['notes']))?:'لم تُضف ملاحظات لهذه الفرصة.' ?></p></section>

        <section class="control-card danger-zone" id="danger-zone">
            <div><strong>حذف الفرصة</strong><p>يحذف الطلب ووثائقه ومقابلاته نهائياً.</p></div>
            <button class="danger-link" type="button" data-dialog-open="delete-job-dialog">حذف</button>
        </section>
    </aside>
</div>

<dialog class="delete-dialog" id="delete-job-dialog" aria-labelledby="delete-dialog-title">
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_job">
        <button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button>
        <span class="delete-dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></span>
        <h2 id="delete-dialog-title">حذف <?= e($job['job_title']) ?>؟</h2>
        <p>سيتم حذف الفرصة وكل الوثائق والمقابلات والنشاط المرتبط بها. لا يمكن التراجع عن هذه العملية.</p>
        <div class="field"><label for="delete_confirmation">اكتب <strong>حذف</strong> للتأكيد</label><input id="delete_confirmation" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف" required></div>
        <div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div>
    </form>
</dialog>
<?php require __DIR__.'/includes/layout_bottom.php';
