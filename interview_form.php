<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$jobId = (int)($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$job = q('SELECT * FROM job_applications WHERE id=? AND user_id=?', [$jobId,$userId])->fetch();
if (!$job) { http_response_code(404); exit('فرصة العمل غير موجودة'); }
$row = $id ? q('SELECT * FROM job_interviews WHERE id=? AND job_application_id=?', [$id,$jobId])->fetch() : null;
if ($id && !$row) { http_response_code(404); exit('المقابلة غير موجودة'); }
$nextRound = (int)scalar('SELECT COALESCE(MAX(round_number),0)+1 FROM job_interviews WHERE job_application_id=?', [$jobId]);
$data = $row ? [
    'round_number'=>$row['round_number'],'interview_type'=>$row['interview_type'],
    'scheduled_at'=>date('Y-m-d\TH:i',strtotime($row['scheduled_at'])),'interview_format'=>$row['interview_format'],
    'interviewer_name'=>$row['interviewer_name'],'meeting_url'=>$row['meeting_url'],'location'=>$row['location'],
    'status'=>$row['status'],'preparation_notes'=>$row['preparation_notes'],'questions'=>$row['questions'],'outcome'=>$row['outcome']
] : [
    'round_number'=>$nextRound,'interview_type'=>'موارد بشرية','scheduled_at'=>'','interview_format'=>'عن بعد',
    'interviewer_name'=>'','meeting_url'=>'','location'=>'','status'=>'مجدولة','preparation_notes'=>'','questions'=>'','outcome'=>''
];
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($data) as $key) $data[$key] = trim((string)($_POST[$key] ?? ''));
    $data['round_number'] = (int)$data['round_number'];
    if ($data['round_number'] < 1 || $data['round_number'] > 20) $errors['round_number'] = 'رقم الجولة يجب أن يكون بين 1 و20.';
    add_choice_error($errors,'interview_type',$data['interview_type'],interview_types(),'نوع المقابلة');
    add_required_error($errors,'scheduled_at',$data['scheduled_at'],'موعد المقابلة');
    $scheduled = DateTime::createFromFormat('Y-m-d\TH:i',$data['scheduled_at']);
    if ($data['scheduled_at'] && (!$scheduled || $scheduled->format('Y-m-d\TH:i')!==$data['scheduled_at'])) $errors['scheduled_at']='أدخل موعداً صحيحاً.';
    add_choice_error($errors,'interview_format',$data['interview_format'],interview_formats(),'طريقة المقابلة');
    add_choice_error($errors,'status',$data['status'],interview_statuses(),'حالة المقابلة');
    add_max_error($errors,'interviewer_name',$data['interviewer_name'],'اسم المحاور',180);
    add_url_error($errors,'meeting_url',$data['meeting_url'],'رابط الاجتماع');
    add_max_error($errors,'meeting_url',$data['meeting_url'],'رابط الاجتماع',500);
    add_max_error($errors,'location',$data['location'],'المكان',220);
    if ($data['interview_format']==='حضوري' && $data['location']==='') $errors['location']='حدد مكان المقابلة الحضورية.';
    if ($data['status']==='مكتملة' && $data['outcome']==='') $errors['outcome']='دوّن نتيجة المقابلة المكتملة للاستفادة منها لاحقاً.';

    if (!$errors) {
        $dbDate = $scheduled->format('Y-m-d H:i:s');
        $values = [$data['round_number'],$data['interview_type'],$dbDate,$data['interview_format'],$data['interviewer_name'],$data['meeting_url'],$data['location'],$data['status'],$data['preparation_notes'],$data['questions'],$data['outcome']];
        if ($id) {
            q('UPDATE job_interviews SET round_number=?,interview_type=?,scheduled_at=?,interview_format=?,interviewer_name=?,meeting_url=?,location=?,status=?,preparation_notes=?,questions=?,outcome=? WHERE id=? AND job_application_id=?', [...$values,$id,$jobId]);
            q('INSERT INTO job_activities(job_application_id,activity_type,summary) VALUES(?,?,?)', [$jobId,'مقابلة','تم تحديث بيانات الجولة '.$data['round_number']]);
            flash('success','تم تحديث تفاصيل المقابلة.');
        } else {
            q('INSERT INTO job_interviews(job_application_id,round_number,interview_type,scheduled_at,interview_format,interviewer_name,meeting_url,location,status,preparation_notes,questions,outcome) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [$jobId,...$values]);
            if (in_array($job['status'],['محفوظة','قيد التحضير','تم التقديم','قيد المراجعة'],true)) q('UPDATE job_applications SET status=? WHERE id=?',['مقابلة',$jobId]);
            q('INSERT INTO job_activities(job_application_id,activity_type,status_from,status_to,summary) VALUES(?,?,?,?,?)', [$jobId,'مقابلة',$job['status'],'مقابلة','تمت إضافة الجولة '.$data['round_number'].': '.$data['interview_type']]);
            flash('success','تمت إضافة المقابلة ونقل الطلب إلى مرحلة المقابلات.');
        }
        redirect('job_view.php?id='.$jobId.'#interviews');
    }
}

$pageTitle = $id ? 'تفاصيل المقابلة' : 'إضافة مقابلة';
$pageDescription = $job['job_title'].' · '.$job['company'];
$active = 'jobs';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<div class="form-layout interview-layout">
    <aside class="form-guide interview-guide">
        <span class="guide-kicker">الجولة <?= (int)$data['round_number'] ?></span>
        <h2>حوّل الدعوة إلى خطة تحضير</h2>
        <p>الموعد وحده لا يكفي. دوّن من سيقابلك، الأسئلة المتوقعة، والنقاط التي يجب إثباتها.</p>
        <div class="interview-guide-job"><small>الفرصة</small><strong><?= e($job['job_title']) ?></strong><span><?= e($job['company']) ?></span></div>
    </aside>
    <section class="section form-card">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $jobId ?>"><input type="hidden" name="id" value="<?= $id ?>">
            <fieldset class="form-section">
                <legend><span>01</span><div><strong>الموعد والجولة</strong><small>متى وكيف ومع من؟</small></div></legend>
                <div class="form-grid">
                    <div class="field<?= field_class($errors,'round_number') ?>"><label for="round_number">رقم الجولة <em>*</em></label><input id="round_number" type="number" name="round_number" min="1" max="20" value="<?= (int)$data['round_number'] ?>" required><?= field_error($errors,'round_number') ?></div>
                    <div class="field<?= field_class($errors,'interview_type') ?>"><label for="interview_type">نوع المقابلة <em>*</em></label><select id="interview_type" name="interview_type"><?= select_options(interview_types(),$data['interview_type']) ?></select><?= field_error($errors,'interview_type') ?></div>
                    <div class="field<?= field_class($errors,'scheduled_at') ?>"><label for="scheduled_at">التاريخ والوقت <em>*</em></label><input id="scheduled_at" type="datetime-local" name="scheduled_at" value="<?= e($data['scheduled_at']) ?>" required><?= field_error($errors,'scheduled_at') ?></div>
                    <div class="field<?= field_class($errors,'interview_format') ?>"><label for="interview_format">الطريقة <em>*</em></label><select id="interview_format" name="interview_format"><?= select_options(interview_formats(),$data['interview_format']) ?></select><?= field_error($errors,'interview_format') ?></div>
                    <div class="field<?= field_class($errors,'interviewer_name') ?>"><label for="interviewer_name">اسم المحاوِر</label><input id="interviewer_name" name="interviewer_name" value="<?= e($data['interviewer_name']) ?>" maxlength="180" placeholder="الاسم والمنصب"><?= field_error($errors,'interviewer_name') ?></div>
                    <div class="field<?= field_class($errors,'status') ?>"><label for="status">حالة الجولة</label><select id="status" name="status"><?= select_options(interview_statuses(),$data['status']) ?></select><?= field_error($errors,'status') ?></div>
                    <div class="field full<?= field_class($errors,'meeting_url') ?>"><label for="meeting_url">رابط الاجتماع</label><input id="meeting_url" type="url" name="meeting_url" value="<?= e($data['meeting_url']) ?>" maxlength="500" inputmode="url" placeholder="Google Meet / Teams / Zoom"><?= field_error($errors,'meeting_url') ?></div>
                    <div class="field full<?= field_class($errors,'location') ?>"><label for="location">المكان أو تفاصيل الدخول</label><input id="location" name="location" value="<?= e($data['location']) ?>" maxlength="220" placeholder="العنوان، رقم الاتصال، أو تعليمات الدخول"><?= field_error($errors,'location') ?></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>02</span><div><strong>التحضير</strong><small>ما الذي يجب أن تكون جاهزاً لإثباته؟</small></div></legend>
                <div class="form-grid">
                    <div class="field full"><label for="preparation_notes">خطة التحضير</label><textarea id="preparation_notes" name="preparation_notes" placeholder="قصص STAR، مشاريع مشابهة، أرقام يجب مراجعتها، ونقاط قوة مرتبطة بالدور..."><?= e($data['preparation_notes']) ?></textarea></div>
                    <div class="field full"><label for="questions">أسئلة متوقعة وأسئلتي لهم</label><textarea id="questions" name="questions" placeholder="كل سؤال في سطر، مع نقاط الإجابة أو ما تريد معرفته عن الفريق..."><?= e($data['questions']) ?></textarea></div>
                </div>
            </fieldset>
            <fieldset class="form-section">
                <legend><span>03</span><div><strong>النتيجة والتعلّم</strong><small>يُملأ بعد انتهاء الجولة</small></div></legend>
                <div class="field full<?= field_class($errors,'outcome') ?>"><label for="outcome">ماذا حدث وما الخطوة التالية؟</label><textarea id="outcome" name="outcome" placeholder="الأسئلة الفعلية، جودة الإجابات، الإشارات الإيجابية، وما يجب تحسينه..."><?= e($data['outcome']) ?></textarea><?= field_error($errors,'outcome') ?></div>
            </fieldset>
            <div class="form-actions"><button class="btn" type="submit"><?= $id?'حفظ تفاصيل الجولة':'إضافة المقابلة' ?></button><a class="btn secondary" href="job_view.php?id=<?= $jobId ?>#interviews">إلغاء</a></div>
        </form>
    </section>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';
