<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

function sync_contact_activity_dates(int $contactId, int $userId): void {
    $latest = q(
        'SELECT a.activity_date,a.next_followup_date FROM contact_activities a
         JOIN contacts c ON c.id=a.contact_id
         JOIN organizations o ON o.id=c.organization_id
         WHERE a.contact_id=? AND o.user_id=?
         ORDER BY a.activity_date DESC,a.id DESC LIMIT 1',
        [$contactId,$userId]
    )->fetch();
    q(
        'UPDATE contacts c JOIN organizations o ON o.id=c.organization_id
         SET c.last_contact_date=?,c.next_followup_date=?
         WHERE c.id=? AND o.user_id=?',
        [$latest['activity_date'] ?? null,$latest['next_followup_date'] ?? null,$contactId,$userId]
    );
}

$id = (int)($_GET['id'] ?? 0);
$c = q('SELECT c.*,o.name organization,o.id organization_id FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE c.id=? AND o.user_id=?', [$id,$userId])->fetch();
if (!$c) { http_response_code(404); exit('الشخص غير موجود'); }
$errors = [];
$editingActivityId = max(0,(int)($_GET['edit_activity'] ?? $_POST['activity_id'] ?? 0));
$editingActivity = null;
if ($editingActivityId) {
    $editingActivity = q(
        'SELECT a.* FROM contact_activities a
         JOIN contacts c ON c.id=a.contact_id
         JOIN organizations o ON o.id=c.organization_id
         WHERE a.id=? AND a.contact_id=? AND o.user_id=?',
        [$editingActivityId,$id,$userId]
    )->fetch();
    if (!$editingActivity) { http_response_code(404); exit('التفاعل غير موجود'); }
}
$activity = [
    'activity_type' => $editingActivity['activity_type'] ?? 'رسالة',
    'activity_date' => $editingActivity['activity_date'] ?? date('Y-m-d'),
    'new_status' => $c['status'],
    'summary' => $editingActivity['summary'] ?? '',
    'next_action' => $editingActivity['next_action'] ?? '',
    'next_followup_date' => $editingActivity['next_followup_date'] ?? ($c['next_followup_date'] ?? ''),
];

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save_activity');

    if ($action === 'delete_contact') {
        if (trim((string)($_POST['delete_confirmation'] ?? '')) !== 'حذف الشخص') {
            flash('error','اكتب عبارة حذف الشخص كما هي لتأكيد العملية.');
            redirect('contact_view.php?id='.$id.'#contact-danger-zone');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $locked = q(
                'SELECT c.id FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE c.id=? AND o.user_id=? FOR UPDATE',
                [$id,$userId]
            )->fetch();
            if (!$locked) throw new RuntimeException('Contact ownership check failed.');
            q('DELETE FROM contacts WHERE id=?',[$id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[contact-delete] '.$exception->getMessage());
            flash('error','تعذر حذف الشخص. لم تُجرَ أي تغييرات.');
            redirect('contact_view.php?id='.$id.'#contact-danger-zone');
        }
        flash('success','تم حذف الشخص وسجل تفاعلاته بالكامل.');
        redirect('contacts.php');
    }

    if ($action === 'delete_activity') {
        $deleteActivityId = max(0,(int)($_POST['activity_id'] ?? 0));
        if (trim((string)($_POST['delete_confirmation'] ?? '')) !== 'حذف') {
            flash('error','اكتب كلمة حذف كما هي لتأكيد العملية.');
            redirect('contact_view.php?id='.$id.'#activity-'.$deleteActivityId);
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $locked = q(
                'SELECT a.id FROM contact_activities a
                 JOIN contacts c ON c.id=a.contact_id
                 JOIN organizations o ON o.id=c.organization_id
                 WHERE a.id=? AND a.contact_id=? AND o.user_id=? FOR UPDATE',
                [$deleteActivityId,$id,$userId]
            )->fetch();
            if (!$locked) throw new RuntimeException('Activity ownership check failed.');
            q('DELETE FROM contact_activities WHERE id=? AND contact_id=?',[$deleteActivityId,$id]);
            sync_contact_activity_dates($id,$userId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[contact-activity-delete] '.$exception->getMessage());
            flash('error','تعذر حذف التفاعل. لم تُجرَ أي تغييرات.');
            redirect('contact_view.php?id='.$id);
        }
        flash('success','تم حذف التفاعل وتحديث مواعيد العلاقة تلقائياً.');
        redirect('contact_view.php?id='.$id);
    }

    foreach (array_keys($activity) as $key) $activity[$key] = trim((string)($_POST[$key] ?? ''));
    add_required_error($errors, 'activity_type', $activity['activity_type'], 'نوع التفاعل');
    add_choice_error($errors, 'activity_type', $activity['activity_type'], activity_types(), 'نوع التفاعل');
    add_required_error($errors, 'activity_date', $activity['activity_date'], 'التاريخ');
    add_date_error($errors, 'activity_date', $activity['activity_date'], 'التاريخ');
    add_choice_error($errors, 'new_status', $activity['new_status'], contact_statuses(), 'الحالة');
    add_required_error($errors, 'summary', $activity['summary'], 'ملخص التفاعل');
    add_date_error($errors, 'next_followup_date', $activity['next_followup_date'], 'موعد المتابعة');
    if (!in_array($activity['new_status'], ['لا متابعة','مغلق'], true) && $activity['next_followup_date'] === '') {
        $errors['next_followup_date'] = 'حدد موعداً للمتابعة، أو غيّر الحالة إلى «لا متابعة» أو «مغلق».';
    }
    if ($activity['activity_date'] && $activity['next_followup_date'] && $activity['next_followup_date'] < $activity['activity_date']) {
        $errors['next_followup_date'] = 'موعد المتابعة يجب أن يكون بعد تاريخ التفاعل.';
    }
    if (!$errors) {
        if (in_array($activity['new_status'], ['لا متابعة','مغلق'], true)) $activity['next_followup_date'] = '';
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($editingActivityId) {
                q('UPDATE contact_activities SET activity_type=?,activity_date=?,summary=?,next_action=?,next_followup_date=? WHERE id=? AND contact_id=?',[
                    $activity['activity_type'],$activity['activity_date'],$activity['summary'],$activity['next_action'],$activity['next_followup_date']?:null,$editingActivityId,$id
                ]);
            } else {
                q('INSERT INTO contact_activities(contact_id,activity_type,activity_date,summary,next_action,next_followup_date) VALUES(?,?,?,?,?,?)', [
                    $id,$activity['activity_type'],$activity['activity_date'],$activity['summary'],$activity['next_action'],$activity['next_followup_date']?:null
                ]);
            }
            q('UPDATE contacts c JOIN organizations o ON o.id=c.organization_id SET c.status=? WHERE c.id=? AND o.user_id=?',[$activity['new_status'],$id,$userId]);
            sync_contact_activity_dates($id,$userId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        flash('success', $editingActivityId?'تم تحديث التفاعل ومواعيد العلاقة.':'تم توثيق التفاعل وإضافة المتابعة إلى قائمة العمل.');
        redirect('contact_view.php?id='.$id);
    }
}

$acts = q('SELECT * FROM contact_activities WHERE contact_id=? ORDER BY activity_date DESC,id DESC', [$id])->fetchAll();
$pageTitle = $c['name'];
$pageDescription = 'وثّق ما حدث، ثم أغلق كل تفاعل بخطوة وموعد تاليين';
$active = 'contacts';
require __DIR__.'/includes/layout_top.php';
?>
<?= error_summary($errors) ?>
<section class="section person-summary">
    <div class="person-header">
        <div class="person-identity"><span class="person-avatar"><?= e(strtoupper(substr($c['name'], 0, 1))) ?></span><div><h2><?= e($c['name']) ?></h2><p><?= e($c['job_title']) ?: 'المنصب غير محدد' ?> · <a class="link" href="organization_view.php?id=<?= (int)$c['organization_id'] ?>"><?= e($c['organization']) ?></a></p></div></div>
        <div class="section-head-actions">
            <?php if ($c['email']): ?><a class="btn small secondary" href="mailto:<?= e($c['email']) ?>">بريد</a><?php endif; ?>
            <?php if ($c['profile_url']): ?><a class="btn small secondary" target="_blank" rel="noopener" href="<?= e($c['profile_url']) ?>">الحساب ↗</a><?php endif; ?>
            <a class="btn small" href="contact_form.php?id=<?= $id ?>">تعديل</a>
        </div>
    </div>
    <div class="detail-grid compact-details">
        <div class="detail"><small>نوع العلاقة</small><strong><?= e($c['relationship_type']) ?></strong></div>
        <div class="detail"><small>الحالة الحالية</small><strong><span class="<?= badge_class($c['status']) ?>"><?= e($c['status']) ?></span></strong></div>
        <div class="detail"><small>آخر تواصل</small><strong><?= date_or_dash($c['last_contact_date']) ?></strong></div>
        <div class="detail"><small>المتابعة القادمة</small><strong class="<?= ($c['next_followup_date'] && strtotime($c['next_followup_date']) < strtotime(date('Y-m-d'))) ? 'due' : '' ?>"><?= date_or_dash($c['next_followup_date']) ?></strong></div>
    </div>
    <?php if ($c['notes']): ?><div class="context-note"><strong>سياق العلاقة</strong><p><?= nl2br(e($c['notes'])) ?></p></div><?php endif; ?>
</section>

<div class="grid-2 contact-workspace">
    <section class="section interaction-form" id="interaction-form">
        <div class="section-head"><div><h2><?= $editingActivityId?'تعديل التفاعل':'سجّل التفاعل الحالي' ?></h2><p><?= $editingActivityId?'صحّح التفاصيل وسيُعاد احتساب مواعيد العلاقة':'لن يُحفظ التفاعل النشط دون موعد متابعة' ?></p></div><span class="step-chip">إجراء → نتيجة → متابعة</span></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_activity"><input type="hidden" name="activity_id" value="<?= $editingActivityId ?>">
            <div class="form-grid">
                <div class="field<?= field_class($errors, 'activity_type') ?>"><label for="activity_type">نوع التفاعل <em>*</em></label><select id="activity_type" name="activity_type" required aria-invalid="<?= isset($errors['activity_type'])?'true':'false' ?>"><?= select_options(activity_types(), $activity['activity_type']) ?></select><?= field_error($errors, 'activity_type') ?></div>
                <div class="field<?= field_class($errors, 'activity_date') ?>"><label for="activity_date">التاريخ <em>*</em></label><input id="activity_date" type="date" name="activity_date" value="<?= e($activity['activity_date']) ?>" required aria-invalid="<?= isset($errors['activity_date'])?'true':'false' ?>"><?= field_error($errors, 'activity_date') ?></div>
                <div class="field full<?= field_class($errors, 'summary') ?>"><label for="summary">ماذا حدث؟ <em>*</em></label><textarea id="summary" name="summary" required placeholder="اكتب النتيجة الفعلية للتواصل باختصار..." aria-invalid="<?= isset($errors['summary'])?'true':'false' ?>"><?= e($activity['summary']) ?></textarea><?= field_error($errors, 'summary') ?></div>
                <div class="field full"><label for="next_action">ما الخطوة التالية؟</label><textarea id="next_action" name="next_action" placeholder="مثال: إرسال نموذج أعمال مخصص أو التحضير لاتصال..."><?= e($activity['next_action']) ?></textarea></div>
                <div class="field<?= field_class($errors, 'new_status') ?>"><label for="new_status">الحالة بعد التفاعل <em>*</em></label><select id="new_status" name="new_status" required aria-invalid="<?= isset($errors['new_status'])?'true':'false' ?>"><?= select_options(contact_statuses(), $activity['new_status']) ?></select><?= field_error($errors, 'new_status') ?></div>
                <div class="field<?= field_class($errors, 'next_followup_date') ?>"><label for="next_followup_date">موعد المتابعة <em>*</em></label><input id="next_followup_date" type="date" name="next_followup_date" value="<?= e($activity['next_followup_date']) ?>" aria-invalid="<?= isset($errors['next_followup_date'])?'true':'false' ?>"><?= field_error($errors, 'next_followup_date') ?></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><?= $editingActivityId?'حفظ التعديلات':'حفظ وإضافة المتابعة' ?></button><?php if($editingActivityId):?><a class="btn secondary" href="contact_view.php?id=<?= $id ?>#activity-<?= $editingActivityId ?>">إلغاء</a><?php endif;?></div>
        </form>
    </section>
    <section class="section activity-history">
        <div class="section-head"><div><h2>سجل العلاقة</h2><p><?= count($acts) ?> تفاعل موثّق</p></div></div>
        <?php if (!$acts): ?>
            <div class="empty-state compact"><span>＋</span><h3>لا يوجد تواصل موثّق</h3><p>سجّل أول تفاعل ليبدأ الخط الزمني للعلاقة.</p></div>
        <?php else: ?>
            <div class="timeline"><?php foreach ($acts as $a): ?><article class="timeline-item" id="activity-<?= (int)$a['id'] ?>"><div class="timeline-meta"><div><span class="<?= badge_class($a['activity_type']) ?>"><?= e($a['activity_type']) ?></span><time><?= e($a['activity_date']) ?></time></div><div class="timeline-actions"><a href="contact_view.php?id=<?= $id ?>&edit_activity=<?= (int)$a['id'] ?>#interaction-form">تعديل</a><button type="button" data-dialog-open="delete-activity-<?= (int)$a['id'] ?>">حذف</button></div></div><p><?= nl2br(e($a['summary'])) ?></p><?php if ($a['next_action']): ?><small><strong>الخطوة التالية:</strong> <?= e($a['next_action']) ?></small><?php endif; ?><?php if($a['next_followup_date']):?><small class="timeline-followup"><strong>المتابعة:</strong> <?= e($a['next_followup_date']) ?></small><?php endif;?></article><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</div>
<section class="section danger-zone contact-danger-zone" id="contact-danger-zone"><div><strong>حذف الشخص وسجل العلاقة</strong><p>يحذف بيانات الشخص وكل التفاعلات المرتبطة به نهائياً، ولا يحذف الجهة.</p></div><button class="danger-link" type="button" data-dialog-open="delete-contact-dialog">حذف الشخص</button></section>
<?php foreach($acts as $a): ?><dialog class="delete-dialog" id="delete-activity-<?= (int)$a['id'] ?>" aria-labelledby="delete-activity-title-<?= (int)$a['id'] ?>"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= (int)$a['id'] ?>"><button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button><span class="delete-dialog-icon" aria-hidden="true">!</span><h2 id="delete-activity-title-<?= (int)$a['id'] ?>">حذف هذا التفاعل؟</h2><p>سيُحذف التفاعل ويُعاد احتساب آخر تواصل وموعد المتابعة تلقائياً. لا يمكن التراجع.</p><div class="field"><label for="delete-activity-confirmation-<?= (int)$a['id'] ?>">اكتب <strong>حذف</strong> للتأكيد</label><input id="delete-activity-confirmation-<?= (int)$a['id'] ?>" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف" required></div><div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div></form></dialog><?php endforeach; ?>
<dialog class="delete-dialog" id="delete-contact-dialog" aria-labelledby="delete-contact-title"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_contact"><button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button><span class="delete-dialog-icon" aria-hidden="true">!</span><h2 id="delete-contact-title">حذف <?= e($c['name']) ?>؟</h2><p>سيُحذف الشخص وكل سجل العلاقة المرتبط به نهائياً. لن تُحذف الجهة.</p><div class="field"><label for="delete-contact-confirmation">اكتب <strong>حذف الشخص</strong> للتأكيد</label><input id="delete-contact-confirmation" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف الشخص" required></div><div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div></form></dialog>
<?php require __DIR__.'/includes/layout_bottom.php';
