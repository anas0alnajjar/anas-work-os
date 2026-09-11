<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$stage = $_GET['stage'] ?? '';
$stageStatuses = [
    'ready' => ['لم أتواصل', 'جاهز للإرسال'],
    'sent' => ['تم الإرسال', 'تم القبول'],
    'engaged' => ['تم الرد', 'اجتماع', 'عرض مرسل'],
    'closed' => ['لا متابعة', 'مغلق'],
];

$params = [$userId]; $where = ['o.user_id=?'];
if ($search !== '') {
    $like = "%{$search}%";
    $where[] = '(c.name LIKE ? OR c.job_title LIKE ? OR o.name LIKE ?)';
    array_push($params,$like,$like,$like);
}
if ($status !== '' && in_array($status, contact_statuses(), true)) {
    $where[] = 'c.status=?'; $params[] = $status;
} elseif (isset($stageStatuses[$stage])) {
    $placeholders = implode(',', array_fill(0, count($stageStatuses[$stage]), '?'));
    $where[] = "c.status IN ({$placeholders})";
    array_push($params, ...$stageStatuses[$stage]);
}
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
$total = (int)scalar('SELECT COUNT(*) FROM contacts c JOIN organizations o ON o.id=c.organization_id'.$whereSql, $params);
$pager = pagination_meta($total, 15);
$limit = (int)$pager['per_page']; $offset = (int)$pager['offset'];
$sql = "SELECT c.*,o.name organization FROM contacts c
    JOIN organizations o ON o.id=c.organization_id{$whereSql}
    ORDER BY CASE WHEN c.next_followup_date IS NULL THEN 1 ELSE 0 END,c.next_followup_date,c.name
    LIMIT {$limit} OFFSET {$offset}";
$rows = q($sql, $params)->fetchAll();

$statusCounts = [];
foreach (q('SELECT c.status,COUNT(*) total FROM contacts c JOIN organizations o ON o.id=c.organization_id WHERE o.user_id=? GROUP BY c.status',[$userId])->fetchAll() as $item) $statusCounts[$item['status']] = (int)$item['total'];
$stageCount = static fn(array $statuses): int => array_sum(array_map(static fn($s) => $statusCounts[$s] ?? 0, $statuses));

$pageTitle = 'الأشخاص والمتابعة';
$pageDescription = 'كل علاقة يجب أن تنتقل إلى تواصل موثق ثم خطوة تالية محددة';
$active = 'contacts';
require __DIR__.'/includes/layout_top.php';
?>

<section class="workflow-panel" aria-labelledby="contact-workflow-title">
    <div class="workflow-heading"><span>مسار التواصل</span><strong id="contact-workflow-title">من الشخص المستهدف إلى نتيجة واضحة</strong></div>
    <div class="workflow-steps">
        <a class="workflow-step <?= $stage==='ready'?'active':'' ?>" href="?stage=ready"><span>1</span><div><strong>تحضير التواصل</strong><small><?= $stageCount($stageStatuses['ready']) ?> شخص</small></div></a>
        <a class="workflow-step <?= $stage==='sent'?'active':'' ?>" href="?stage=sent"><span>2</span><div><strong>تم الإرسال</strong><small><?= $stageCount($stageStatuses['sent']) ?> شخص</small></div></a>
        <a class="workflow-step <?= $stage==='engaged'?'active':'' ?>" href="?stage=engaged"><span>3</span><div><strong>تفاعل فعلي</strong><small><?= $stageCount($stageStatuses['engaged']) ?> شخص</small></div></a>
        <a class="workflow-step <?= $stage==='closed'?'active':'' ?>" href="?stage=closed"><span>4</span><div><strong>إغلاق المسار</strong><small><?= $stageCount($stageStatuses['closed']) ?> شخص</small></div></a>
    </div>
</section>

<section class="section list-section">
    <form class="filters" method="get" aria-label="بحث وتصفية الأشخاص">
        <div class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input name="q" value="<?= e($search) ?>" maxlength="180" placeholder="اسم، منصب أو جهة..." aria-label="البحث في الأشخاص">
        </div>
        <select name="status" aria-label="تصفية حسب الحالة"><option value="">كل الحالات</option><?= select_options(contact_statuses(), $status) ?></select>
        <button class="btn" type="submit">تطبيق</button>
        <?php if ($search !== '' || $status !== '' || $stage !== ''): ?><a class="btn secondary" href="contacts.php">مسح</a><?php endif; ?>
    </form>

    <div class="list-toolbar">
        <div><strong><?= $pager['total'] ?> شخص</strong><span>عرض <?= $pager['from'] ?>–<?= $pager['to'] ?></span></div>
        <a class="btn small" href="contact_form.php">+ إضافة شخص</a>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state"><span>⌕</span><h3>لا توجد علاقات مطابقة</h3><p>غيّر الفلاتر أو أضف شخصاً جديداً داخل إحدى الجهات.</p><a class="btn small" href="contact_form.php">إضافة شخص</a></div>
    <?php else: ?>
        <div class="table-wrap responsive-table-wrap">
            <table class="responsive-table">
                <thead><tr><th>الشخص</th><th>الجهة والمنصب</th><th>نوع العلاقة</th><th>الحالة</th><th>آخر تواصل</th><th>المتابعة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $c): ?>
                    <tr>
                        <td data-label="الشخص"><a class="link table-title" href="contact_view.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['name']) ?></strong></a></td>
                        <td data-label="الجهة"><strong><?= e($c['organization']) ?></strong><br><small class="muted"><?= e($c['job_title']) ?: 'المنصب غير محدد' ?></small></td>
                        <td data-label="نوع العلاقة"><?= e($c['relationship_type']) ?></td>
                        <td data-label="الحالة"><span class="<?= badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
                        <td data-label="آخر تواصل"><?= date_or_dash($c['last_contact_date']) ?></td>
                        <td data-label="المتابعة" class="<?= ($c['next_followup_date'] && strtotime($c['next_followup_date']) < strtotime(date('Y-m-d'))) ? 'due' : '' ?>"><?= date_or_dash($c['next_followup_date']) ?></td>
                        <td data-label="الإجراء"><a class="btn small secondary" href="contact_view.php?id=<?= (int)$c['id'] ?>">فتح</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar"><span><?= $pager['total'] ?> نتيجة</span><?= render_pagination($pager) ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__.'/includes/layout_bottom.php';
