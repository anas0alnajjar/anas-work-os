<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$stage = $_GET['stage'] ?? '';
$stageStatuses = [
    'prepare' => ['محفوظة', 'قيد التحضير'],
    'applied' => ['تم التقديم', 'قيد المراجعة'],
    'interview' => ['مقابلة', 'اختبار'],
    'result' => ['عرض وظيفي', 'مقبول', 'مرفوض', 'منسحب', 'مغلق'],
];

$where = ['user_id=?']; $params = [$userId];
if ($search !== '') {
    $like = "%{$search}%";
    $where[] = '(company LIKE ? OR job_title LIKE ? OR location LIKE ?)';
    array_push($params,$like,$like,$like);
}
if ($status !== '' && in_array($status, job_statuses(), true)) {
    $where[] = 'status=?'; $params[] = $status;
} elseif (isset($stageStatuses[$stage])) {
    $placeholders = implode(',', array_fill(0, count($stageStatuses[$stage]), '?'));
    $where[] = "status IN ({$placeholders})";
    array_push($params, ...$stageStatuses[$stage]);
}
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
$total = (int)scalar('SELECT COUNT(*) FROM job_applications'.$whereSql, $params);
$pager = pagination_meta($total, 15);
$limit = (int)$pager['per_page']; $offset = (int)$pager['offset'];
$rows = q("SELECT j.*,
    (SELECT COUNT(*) FROM job_documents d WHERE d.job_application_id=j.id) documents_count,
    (SELECT COUNT(*) FROM job_interviews i WHERE i.job_application_id=j.id) interviews_count,
    (SELECT MIN(i.scheduled_at) FROM job_interviews i WHERE i.job_application_id=j.id AND i.status='مجدولة') next_interview
    FROM job_applications j{$whereSql}
    ORDER BY CASE WHEN j.next_followup_date IS NULL THEN 1 ELSE 0 END,j.next_followup_date ASC,j.updated_at DESC
    LIMIT {$limit} OFFSET {$offset}", $params)->fetchAll();

$statusCounts = [];
foreach (q('SELECT status,COUNT(*) total FROM job_applications WHERE user_id=? GROUP BY status',[$userId])->fetchAll() as $item) $statusCounts[$item['status']] = (int)$item['total'];
$stageCount = static fn(array $statuses): int => array_sum(array_map(static fn($s) => $statusCounts[$s] ?? 0, $statuses));

$pageTitle = 'فرص العمل';
$pageDescription = 'سجّل الفرصة مرة واحدة، ثم انقلها بين المراحل حتى تصل إلى نتيجة';
$active = 'jobs';
require __DIR__.'/includes/layout_top.php';
?>

<section class="workflow-panel" aria-labelledby="job-workflow-title">
    <div class="workflow-heading"><span>مسار التقديم</span><strong id="job-workflow-title">من الحفظ إلى القرار النهائي</strong></div>
    <div class="workflow-steps">
        <a class="workflow-step <?= $stage==='prepare'?'active':'' ?>" href="?stage=prepare"><span>1</span><div><strong>حفظ وتحضير</strong><small><?= $stageCount($stageStatuses['prepare']) ?> فرصة</small></div></a>
        <a class="workflow-step <?= $stage==='applied'?'active':'' ?>" href="?stage=applied"><span>2</span><div><strong>تم التقديم</strong><small><?= $stageCount($stageStatuses['applied']) ?> فرصة</small></div></a>
        <a class="workflow-step <?= $stage==='interview'?'active':'' ?>" href="?stage=interview"><span>3</span><div><strong>مقابلة واختبار</strong><small><?= $stageCount($stageStatuses['interview']) ?> فرصة</small></div></a>
        <a class="workflow-step <?= $stage==='result'?'active':'' ?>" href="?stage=result"><span>4</span><div><strong>النتيجة</strong><small><?= $stageCount($stageStatuses['result']) ?> فرصة</small></div></a>
    </div>
</section>

<section class="section list-section">
    <form class="filters" method="get" aria-label="بحث وتصفية فرص العمل">
        <div class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input name="q" value="<?= e($search) ?>" maxlength="220" placeholder="وظيفة، جهة أو مكان..." aria-label="البحث في فرص العمل">
        </div>
        <select name="status" aria-label="تصفية حسب الحالة"><option value="">كل الحالات</option><?= select_options(job_statuses(), $status) ?></select>
        <button class="btn" type="submit">تطبيق</button>
        <?php if ($search !== '' || $status !== '' || $stage !== ''): ?><a class="btn secondary" href="jobs.php">مسح</a><?php endif; ?>
    </form>

    <div class="list-toolbar">
        <div><strong><?= $pager['total'] ?> فرصة</strong><span>عرض <?= $pager['from'] ?>–<?= $pager['to'] ?></span></div>
        <a class="btn small" href="job_form.php">+ تسجيل فرصة</a>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state"><span>＋</span><h3>لا توجد فرص في هذه المرحلة</h3><p>أضف رابط الإعلان والبيانات الأساسية، ثم حرّك الفرصة بين مراحل التقديم.</p><a class="btn small" href="job_form.php">تسجيل فرصة</a></div>
    <?php else: ?>
        <div class="table-wrap responsive-table-wrap">
            <table class="responsive-table">
                <thead><tr><th>الوظيفة والجهة</th><th>الحالة</th><th>التقديم</th><th>المتابعة</th><th>الوثائق</th><th>المقابلة القادمة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $j): ?>
                    <tr>
                        <td data-label="الفرصة"><a class="link table-title" href="job_view.php?id=<?= (int)$j['id'] ?>"><strong><?= e($j['job_title']) ?></strong></a><br><small class="muted"><?= e($j['company']) ?><?= $j['location']?' · '.e($j['location']):'' ?></small></td>
                        <td data-label="الحالة"><span class="<?= badge_class($j['status']) ?>"><?= e($j['status']) ?></span></td>
                        <td data-label="تاريخ التقديم"><?= date_or_dash($j['applied_date']) ?></td>
                        <td data-label="المتابعة" class="<?= ($j['next_followup_date'] && strtotime($j['next_followup_date']) < strtotime(date('Y-m-d'))) ? 'due' : '' ?>"><?= date_or_dash($j['next_followup_date']) ?></td>
                        <td data-label="الوثائق"><span class="asset-count"><?= (int)$j['documents_count'] ?> ملف</span></td>
                        <td data-label="المقابلة"><?= $j['next_interview'] ? e(date('Y-m-d H:i',strtotime($j['next_interview']))) : ((int)$j['interviews_count'].' جولة') ?></td>
                        <td data-label="الإجراء"><a class="btn small secondary" href="job_view.php?id=<?= (int)$j['id'] ?>">مساحة الفرصة</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar"><span><?= $pager['total'] ?> نتيجة</span><?= render_pagination($pager) ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__.'/includes/layout_bottom.php';
