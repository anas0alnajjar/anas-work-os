<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/options.php';
require_auth();
$userId = current_user_id();

$search = trim($_GET['q'] ?? '');
$priority = $_GET['priority'] ?? '';
$status = $_GET['status'] ?? '';
$stage = $_GET['stage'] ?? '';
$stageStatuses = [
    'target' => ['هدف'],
    'conversation' => ['تم التواصل', 'محادثة نشطة'],
    'opportunity' => ['فرصة', 'عرض مرسل'],
    'client' => ['عميل'],
];

$where = ['o.user_id=?']; $params = [$userId];
if ($search !== '') {
    $where[] = '(o.name LIKE ? OR o.category LIKE ? OR o.fit_reason LIKE ?)';
    $like = "%{$search}%"; array_push($params,$like,$like,$like);
}
if ($priority !== '' && in_array($priority, organization_priorities(), true)) {
    $where[] = 'o.priority=?'; $params[] = $priority;
}
if ($status !== '' && in_array($status, organization_statuses(), true)) {
    $where[] = 'o.status=?'; $params[] = $status;
} elseif (isset($stageStatuses[$stage])) {
    $placeholders = implode(',', array_fill(0, count($stageStatuses[$stage]), '?'));
    $where[] = "o.status IN ({$placeholders})";
    array_push($params, ...$stageStatuses[$stage]);
}
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
$total = (int)scalar('SELECT COUNT(*) FROM organizations o'.$whereSql, $params);
$pager = pagination_meta($total, 15);
$limit = (int)$pager['per_page']; $offset = (int)$pager['offset'];
$sql = "SELECT o.*,COUNT(c.id) contacts_count,MIN(c.next_followup_date) next_followup
    FROM organizations o LEFT JOIN contacts c ON c.organization_id=o.id{$whereSql}
    GROUP BY o.id
    ORDER BY FIELD(o.priority,'تركيز الآن','بناء علاقة','مراقبة','مؤجل'),o.name
    LIMIT {$limit} OFFSET {$offset}";
$rows = q($sql, $params)->fetchAll();

$statusCounts = [];
foreach (q('SELECT status,COUNT(*) total FROM organizations WHERE user_id=? GROUP BY status',[$userId])->fetchAll() as $item) $statusCounts[$item['status']] = (int)$item['total'];
$stageCount = static fn(array $statuses): int => array_sum(array_map(static fn($s) => $statusCounts[$s] ?? 0, $statuses));

$pageTitle = 'الجهات';
$pageDescription = 'ابدأ بجهة مستهدفة، ابنِ العلاقة، ثم حوّلها إلى فرصة واضحة';
$active = 'organizations';
require __DIR__.'/includes/layout_top.php';
?>

<section class="workflow-panel" aria-labelledby="org-workflow-title">
    <div class="workflow-heading"><span>مسار العمل</span><strong id="org-workflow-title">من الاستهداف إلى العميل</strong></div>
    <div class="workflow-steps">
        <a class="workflow-step <?= $stage==='target'?'active':'' ?>" href="?stage=target"><span>1</span><div><strong>الاستهداف</strong><small><?= $stageCount($stageStatuses['target']) ?> جهة</small></div></a>
        <a class="workflow-step <?= $stage==='conversation'?'active':'' ?>" href="?stage=conversation"><span>2</span><div><strong>بناء العلاقة</strong><small><?= $stageCount($stageStatuses['conversation']) ?> جهة</small></div></a>
        <a class="workflow-step <?= $stage==='opportunity'?'active':'' ?>" href="?stage=opportunity"><span>3</span><div><strong>فرصة أو عرض</strong><small><?= $stageCount($stageStatuses['opportunity']) ?> جهة</small></div></a>
        <a class="workflow-step <?= $stage==='client'?'active':'' ?>" href="?stage=client"><span>4</span><div><strong>عميل</strong><small><?= $stageCount($stageStatuses['client']) ?> جهة</small></div></a>
    </div>
</section>

<section class="section list-section">
    <form class="filters" method="get" aria-label="بحث وتصفية الجهات">
        <div class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input name="q" value="<?= e($search) ?>" maxlength="180" placeholder="ابحث باسم الجهة أو القطاع..." aria-label="البحث في الجهات">
        </div>
        <select name="priority" aria-label="تصفية حسب الأولوية"><option value="">كل الأولويات</option><?= select_options(organization_priorities(), $priority) ?></select>
        <select name="status" aria-label="تصفية حسب الحالة"><option value="">كل الحالات</option><?= select_options(organization_statuses(), $status) ?></select>
        <button class="btn" type="submit">تطبيق</button>
        <?php if ($search !== '' || $priority !== '' || $status !== '' || $stage !== ''): ?><a class="btn secondary" href="organizations.php">مسح</a><?php endif; ?>
    </form>

    <div class="list-toolbar">
        <div><strong><?= $pager['total'] ?> جهة</strong><span>عرض <?= $pager['from'] ?>–<?= $pager['to'] ?></span></div>
        <a class="btn small" href="organization_form.php">+ إضافة جهة</a>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state"><span>⌕</span><h3>لا توجد جهات مطابقة</h3><p>غيّر معايير البحث أو أضف جهة جديدة إلى مسار العمل.</p><a class="btn small" href="organization_form.php">إضافة جهة</a></div>
    <?php else: ?>
        <div class="table-wrap responsive-table-wrap">
            <table class="responsive-table">
                <thead><tr><th>الجهة</th><th>القطاع</th><th>الأولوية</th><th>الحالة</th><th>الأشخاص</th><th>المتابعة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td data-label="الجهة"><a class="link table-title" href="organization_view.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name']) ?></strong></a></td>
                        <td data-label="القطاع"><?= e($r['category']) ?: '—' ?></td>
                        <td data-label="الأولوية"><span class="<?= badge_class($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                        <td data-label="الحالة"><span class="<?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                        <td data-label="الأشخاص"><?= (int)$r['contacts_count'] ?></td>
                        <td data-label="المتابعة" class="<?= ($r['next_followup'] && strtotime($r['next_followup']) < strtotime(date('Y-m-d'))) ? 'due' : '' ?>"><?= date_or_dash($r['next_followup']) ?></td>
                        <td data-label="الإجراء"><a class="btn small secondary" href="organization_view.php?id=<?= (int)$r['id'] ?>">فتح</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar"><span><?= $pager['total'] ?> نتيجة</span><?= render_pagination($pager) ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__.'/includes/layout_bottom.php';
