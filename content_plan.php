<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/content_options.php';
require_auth();
$userId = current_user_id();

$search = trim((string)($_GET['q'] ?? ''));
$period = trim((string)($_GET['period'] ?? ''));
$year = (int)($_GET['year'] ?? 0);
$platform = trim((string)($_GET['platform'] ?? ''));
$pillar = trim((string)($_GET['pillar'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

if (!in_array($period,['','backlog','next30','upcoming','past'],true)) $period = '';
if ($year < 2000 || $year > 2100) $year = 0;
if (!array_key_exists($platform, content_platforms())) $platform = '';
if (!array_key_exists($status, content_post_statuses())) $status = '';

$pillars = q('SELECT DISTINCT pillar FROM content_topics WHERE user_id=? ORDER BY pillar',[$userId])->fetchAll(PDO::FETCH_COLUMN);
$availableYears = q('SELECT DISTINCT YEAR(planned_for) content_year FROM content_topics WHERE user_id=? AND planned_for IS NOT NULL ORDER BY content_year DESC',[$userId])->fetchAll(PDO::FETCH_COLUMN);
if ($pillar !== '' && !in_array($pillar, $pillars, true)) $pillar = '';

$where = ['t.user_id=?']; $params = [$userId];
if ($search !== '') { $like = '%'.$search.'%'; $where[] = '(t.title_ar LIKE ? OR t.title_en LIKE ? OR t.core_message LIKE ? OR t.pillar LIKE ?)'; array_push($params,$like,$like,$like,$like); }
if ($period === 'backlog') $where[] = 't.planned_for IS NULL';
if ($period === 'next30') $where[] = 't.planned_for BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)';
if ($period === 'upcoming') $where[] = 't.planned_for >= CURDATE()';
if ($period === 'past') $where[] = 't.planned_for < CURDATE()';
if ($year) { $where[] = 'YEAR(t.planned_for)=?'; $params[] = $year; }
if ($pillar !== '') { $where[] = 't.pillar=?'; $params[] = $pillar; }
if ($platform !== '') {
    $where[] = 'EXISTS(SELECT 1 FROM content_posts fp WHERE fp.topic_id=t.id AND fp.platform=?)';
    $params[] = $platform;
}
if ($status !== '') {
    $where[] = 'EXISTS(SELECT 1 FROM content_posts fs WHERE fs.topic_id=t.id AND fs.post_status=?)';
    $params[] = $status;
}
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

$topics = q("SELECT t.*,
    MAX(CASE WHEN p.platform='linkedin' THEN p.post_status END) linkedin_status,
    MAX(CASE WHEN p.platform='facebook' THEN p.post_status END) facebook_status,
    MAX(CASE WHEN p.platform='instagram' THEN p.post_status END) instagram_status,
    MIN(CASE WHEN p.scheduled_at >= NOW() THEN p.scheduled_at END) nearest_scheduled,
    SUM(CASE WHEN p.post_status='published' THEN 1 ELSE 0 END) published_count,
    COUNT(p.id) post_count
    FROM content_topics t
    LEFT JOIN content_posts p ON p.topic_id=t.id
    {$whereSql}
    GROUP BY t.id
    ORDER BY CASE WHEN t.planned_for IS NULL THEN 1 ELSE 0 END,t.planned_for,t.week_number,t.id", $params)->fetchAll();

$statusCounts = array_fill_keys(array_keys(content_post_statuses()), 0);
foreach (q('SELECT p.post_status,COUNT(*) total FROM content_posts p JOIN content_topics t ON t.id=p.topic_id WHERE t.user_id=? GROUP BY p.post_status',[$userId])->fetchAll() as $item) {
    $statusCounts[$item['post_status']] = (int)$item['total'];
}

$currentTopic = q("SELECT * FROM content_topics WHERE user_id=?
    ORDER BY CASE topic_status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END,
    CASE WHEN planned_for >= CURDATE() THEN 0 WHEN planned_for IS NULL THEN 2 ELSE 1 END,planned_for,week_number,id LIMIT 1",[$userId])->fetch();
$currentPosts = $currentTopic ? q('SELECT * FROM content_posts WHERE topic_id=? ORDER BY FIELD(platform,\'linkedin\',\'facebook\',\'instagram\')', [(int)$currentTopic['id']])->fetchAll() : [];

$pageTitle = 'خطة المحتوى';
$pageDescription = 'موضوع واحد كل أسبوع، وثلاث نسخ جاهزة للإدارة والنشر';
$active = 'content';
require __DIR__.'/includes/layout_top.php';
?>

<section class="content-metrics" aria-label="ملخص إنتاج المحتوى">
    <?php foreach ([
        ['planned','مخطط','بانتظار بدء الكتابة'],
        ['drafting','قيد الكتابة','مسودات تحتاج إكمالاً'],
        ['ready','جاهز','جاهز للجدولة'],
        ['scheduled','مجدول','له موعد نشر'],
        ['published','منشور','تم توثيق رابط نشره'],
    ] as [$key,$label,$hint]): ?>
    <div class="content-metric <?= e($key) ?>"><span><?= e($label) ?></span><strong><?= (int)($statusCounts[$key] ?? 0) ?></strong><small><?= e($hint) ?></small></div>
    <?php endforeach; ?>
</section>

<?php if ($currentTopic): ?>
<section class="content-focus" aria-labelledby="current-topic-title">
    <div class="content-focus-copy">
        <div class="content-kicker"><span>نقطة البدء</span><b><?= $currentTopic['planned_for'] ? e(date('Y-m-d',strtotime($currentTopic['planned_for']))) : ($currentTopic['seed_key'] ? 'الأسبوع '.(int)$currentTopic['week_number'] : 'بنك الأفكار') ?></b></div>
        <h2 id="current-topic-title"><?= e($currentTopic['title_ar']) ?></h2>
        <p><?= e($currentTopic['objective'] ?: 'ابدأ بصياغة الرسالة الأساسية، ثم خصّصها لكل منصة.') ?></p>
        <div class="content-focus-meta"><span><?= e($currentTopic['pillar']) ?></span><span><?= $currentTopic['planned_for'] ? 'الأسبوع '.e(date('W',strtotime($currentTopic['planned_for']))).' · '.e(date('Y',strtotime($currentTopic['planned_for']))) : 'غير مرتبط بموعد تقويمي' ?></span></div>
    </div>
    <div class="content-focus-versions">
        <?php foreach ($currentPosts as $post): ?>
        <a href="content_topic.php?id=<?= (int)$currentTopic['id'] ?>#post-<?= e($post['platform']) ?>">
            <div><strong><?= e(content_platform_label($post['platform'])) ?></strong><small><?= e(content_format_label($post['content_format'])) ?><?= $post['scheduled_at'] ? ' · '.e(date('Y-m-d H:i',strtotime($post['scheduled_at']))) : ' · بلا موعد' ?></small></div>
            <span class="<?= e(content_status_class($post['post_status'])) ?>"><?= e(content_status_label($post['post_status'])) ?></span>
        </a>
        <?php endforeach; ?>
        <a class="content-focus-primary" href="content_topic.php?id=<?= (int)$currentTopic['id'] ?>">فتح مساحة الموضوع <span>←</span></a>
    </div>
</section>
<?php endif; ?>

<section class="section content-plan-section">
    <form class="content-filters" method="get" aria-label="تصفية خطة المحتوى">
        <div class="field content-filter-search"><label for="content-q">بحث</label><input id="content-q" name="q" maxlength="255" value="<?= e($search) ?>" placeholder="فكرة، رسالة أو محور..."></div>
        <div class="field"><label for="period">الإطار الزمني</label><select id="period" name="period"><option value="">كل الخطة</option><option value="backlog"<?= $period==='backlog'?' selected':'' ?>>بنك الأفكار</option><option value="next30"<?= $period==='next30'?' selected':'' ?>>الـ30 يوماً القادمة</option><option value="upcoming"<?= $period==='upcoming'?' selected':'' ?>>كل القادم</option><option value="past"<?= $period==='past'?' selected':'' ?>>مواضيع سابقة</option></select></div>
        <div class="field"><label for="year">السنة</label><select id="year" name="year"><option value="">كل السنوات</option><?php foreach($availableYears as $item): ?><option value="<?= (int)$item ?>"<?= $year===(int)$item?' selected':'' ?>><?= (int)$item ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="platform">المنصة</label><select id="platform" name="platform"><option value="">كل المنصات</option><?= content_option_tags(content_platforms(), $platform) ?></select></div>
        <div class="field"><label for="status">حالة النسخة</label><select id="status" name="status"><option value="">كل الحالات</option><?= content_option_tags(content_post_statuses(), $status) ?></select></div>
        <div class="field content-filter-pillar"><label for="pillar">المحور</label><select id="pillar" name="pillar"><option value="">كل المحاور</option><?php foreach ($pillars as $item): ?><option value="<?= e($item) ?>"<?= $pillar===$item?' selected':'' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
        <div class="content-filter-actions"><button class="btn small" type="submit">تطبيق</button><?php if ($search || $period || $year || $platform || $pillar || $status): ?><a class="btn small secondary" href="content_plan.php">مسح</a><?php endif; ?></div>
    </form>

    <div class="content-list-head"><div><strong>الخطة الأسبوعية</strong><span><?= count($topics) ?> موضوعاً ظاهراً</span></div><a class="btn small secondary" href="content_topic_form.php">+ موضوع إضافي</a></div>

    <?php if (!$topics): ?>
    <div class="empty-state"><span>⌕</span><h3>لا توجد نتائج بهذه الفلاتر</h3><p>امسح الفلاتر أو أضف موضوعاً خارج الخطة الأساسية.</p><a class="btn small" href="content_plan.php">عرض الخطة كاملة</a></div>
    <?php else: ?>
    <div class="content-topic-list">
        <?php foreach ($topics as $topic):
            $statuses = ['linkedin'=>$topic['linkedin_status'],'facebook'=>$topic['facebook_status'],'instagram'=>$topic['instagram_status']];
            $done = (int)$topic['published_count']; $totalPosts = max(1,(int)$topic['post_count']);
        ?>
        <article class="content-topic-card">
            <div class="content-week"><span><?= $topic['planned_for'] ? 'W'.e(date('W',strtotime($topic['planned_for']))) : 'W'.str_pad((string)$topic['week_number'],2,'0',STR_PAD_LEFT) ?></span><small><?= $topic['planned_for'] ? e(date('Y-m-d',strtotime($topic['planned_for']))) : ($topic['seed_key'] ? 'الخطة الأصلية' : 'بنك الأفكار') ?></small></div>
            <div class="content-topic-body">
                <div class="content-topic-title"><div><span><?= e($topic['pillar']) ?></span><h3><?= e($topic['title_ar']) ?></h3></div><span class="badge neutral"><?= e(content_topic_status_label($topic['topic_status'])) ?></span></div>
                <?php if ($topic['evidence_source']): ?><p class="content-evidence"><strong>مرجع الفكرة</strong><?= e($topic['evidence_source']) ?></p><?php endif; ?>
                <div class="content-platform-row">
                    <?php foreach ($statuses as $key=>$postStatus): ?><span><b><?= e(content_platform_label($key)) ?></b><em class="<?= e(content_status_class((string)$postStatus)) ?>"><?= e(content_status_label((string)$postStatus)) ?></em></span><?php endforeach; ?>
                </div>
            </div>
            <div class="content-topic-action">
                <div class="content-progress-label"><span><?= $done ?> من <?= $totalPosts ?> منشور</span><strong><?= (int)round(($done/$totalPosts)*100) ?>%</strong></div>
                <div class="content-progress"><i style="width:<?= (int)round(($done/$totalPosts)*100) ?>%"></i></div>
                <small><?= $topic['nearest_scheduled'] ? 'الموعد القادم: '.e(date('Y-m-d H:i',strtotime($topic['nearest_scheduled']))) : 'لا يوجد نشر مجدول' ?></small>
                <a class="btn small secondary" href="content_topic.php?id=<?= (int)$topic['id'] ?>">إدارة النسخ</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__.'/includes/layout_bottom.php';
