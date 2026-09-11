<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$stats = [
    'orgs' => (int) scalar('SELECT COUNT(*) FROM organizations WHERE user_id=?',[$userId]),
    'contacts' => (int) scalar('SELECT COUNT(*) FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE o.user_id=?',[$userId]),
    'due' => (int) scalar("SELECT COUNT(*) FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE o.user_id=? AND c.next_followup_date IS NOT NULL AND c.next_followup_date <= CURDATE() AND c.status NOT IN ('مغلق','لا متابعة')",[$userId]),
    'jobs' => (int) scalar("SELECT COUNT(*) FROM job_applications WHERE user_id=? AND status NOT IN ('مقبول','مرفوض','منسحب','مغلق')",[$userId]),
];

$followups = q("SELECT c.id,c.name person,o.name organization,c.status,c.next_followup_date
    FROM contacts c
    JOIN organizations o ON o.id=c.organization_id
    WHERE o.user_id=? AND c.next_followup_date IS NOT NULL AND c.status NOT IN ('مغلق','لا متابعة')
    ORDER BY c.next_followup_date ASC LIMIT 8",[$userId])->fetchAll();

$jobs = q("SELECT id,company,job_title,status,next_followup_date
    FROM job_applications WHERE user_id=?
    ORDER BY COALESCE(next_followup_date,'9999-12-31'),updated_at DESC LIMIT 7",[$userId])->fetchAll();

$today = strtotime(date('Y-m-d'));
$focusMessage = $stats['due'] > 0
    ? "لديك {$stats['due']} متابعة مستحقة. ابدأ بها قبل إضافة مهام جديدة."
    : 'لا توجد متابعات متأخرة اليوم. راجع الفرص النشطة وحدد الخطوة التالية لكل فرصة.';

$pageTitle = 'لوحة التحكم';
$pageDescription = 'مركز قيادة يومي للعلاقات المهنية وطلبات العمل';
$active = 'dashboard';
require __DIR__.'/includes/layout_top.php';
?>

<section class="dashboard-intro" aria-labelledby="daily-focus-title">
    <div class="dashboard-intro-content">
        <span class="dashboard-intro-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
        </span>
        <div>
            <h2 id="daily-focus-title">تركيز اليوم</h2>
            <p><?= e($focusMessage) ?></p>
        </div>
    </div>
    <div class="quick-actions">
        <a class="btn small secondary" href="contact_form.php">إضافة شخص</a>
        <a class="btn small" href="job_form.php">تسجيل فرصة</a>
    </div>
</section>

<div class="cards">
    <article class="card metric">
        <div class="metric-copy"><span>جهات مستهدفة</span><strong><?= $stats['orgs'] ?></strong><small>ضمن شبكة الفرص الحالية</small></div>
        <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 21V8l8-5 8 5v13M8 21v-4h8v4M8 11h.01M12 11h.01M16 11h.01"/></svg></span>
    </article>
    <article class="card metric network">
        <div class="metric-copy"><span>علاقات مهنية</span><strong><?= $stats['contacts'] ?></strong><small>أشخاص ضمن الجهات المسجلة</small></div>
        <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87"/></svg></span>
    </article>
    <article class="card metric urgent">
        <div class="metric-copy"><span>متابعات مستحقة</span><strong><?= $stats['due'] ?></strong><small><?= $stats['due'] ? 'تحتاج إجراءً اليوم' : 'لا يوجد تأخير حالياً' ?></small></div>
        <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
    </article>
    <article class="card metric opportunity">
        <div class="metric-copy"><span>طلبات نشطة</span><strong><?= $stats['jobs'] ?></strong><small>قيد التحضير أو المتابعة</small></div>
        <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 6V4h6v2M4 8h16v12H4V8Zm0 5h16M10 13v2h4v-2"/></svg></span>
    </article>
</div>

<div class="grid-2">
    <section class="section">
        <div class="section-head">
            <div><h2>قائمة المتابعة</h2><p>مرتبة حسب الموعد، والأكثر إلحاحاً أولاً</p></div>
            <a class="link" href="contacts.php">عرض الجميع</a>
        </div>
        <?php if (!$followups): ?>
            <div class="empty">لا توجد متابعات مجدولة. أضف موعداً من صفحة الشخص لتظهر المهمة هنا.</div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($followups as $f):
                    $followupTime = strtotime($f['next_followup_date']);
                    $isOverdue = $followupTime < $today;
                    $isToday = $followupTime === $today;
                    $dateLabel = $isOverdue ? 'متأخر' : ($isToday ? 'اليوم' : date('d/m', $followupTime));
                ?>
                    <a class="task-item" href="contact_view.php?id=<?= (int)$f['id'] ?>">
                        <span class="task-date <?= $isOverdue ? 'overdue' : '' ?>"><?= e($dateLabel) ?></span>
                        <span class="task-copy"><strong><?= e($f['person']) ?></strong><small><?= e($f['organization']) ?></small></span>
                        <span class="<?= badge_class($f['status']) ?>"><?= e($f['status']) ?></span>
                        <svg class="task-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 18-6-6 6-6"/></svg>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section-head">
            <div><h2>مسار فرص العمل</h2><p>آخر الفرص التي تحتاج قراراً أو متابعة</p></div>
            <a class="link" href="jobs.php">عرض الكل</a>
        </div>
        <?php if (!$jobs): ?>
            <div class="empty">لم تسجل فرص عمل بعد. ابدأ بإضافة أول فرصة من رابط الإعلان.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>الفرصة</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td><a class="link" href="job_view.php?id=<?= (int)$j['id'] ?>"><strong><?= e($j['job_title']) ?></strong></a><br><small class="muted"><?= e($j['company']) ?></small></td>
                            <td><span class="<?= badge_class($j['status']) ?>"><?= e($j['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__.'/includes/layout_bottom.php';
