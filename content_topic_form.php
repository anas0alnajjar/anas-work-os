<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/content_options.php';
require_auth();
$userId = current_user_id();

$data = [
    'title_ar'=>'','title_en'=>'','pillar'=>'','planned_for'=>'','objective'=>'','core_message'=>'',
    'evidence_source'=>'','truthfulness_note'=>'','internal_notes'=>'',
];
$errors = [];
$existingPillars = q('SELECT DISTINCT pillar FROM content_topics WHERE user_id=? ORDER BY pillar',[$userId])->fetchAll(PDO::FETCH_COLUMN);

if (is_post()) {
    verify_csrf();
    foreach ($data as $key=>$default) $data[$key] = trim((string)($_POST[$key] ?? $default));
    add_required_error($errors,'title_ar',$data['title_ar'],'الفكرة الرئيسية');
    add_required_error($errors,'pillar',$data['pillar'],'محور المحتوى');
    add_required_error($errors,'core_message',$data['core_message'],'الخلاصة التي سيخرج بها القارئ');
    add_max_error($errors,'title_ar',$data['title_ar'],'الفكرة الرئيسية',255);
    add_max_error($errors,'title_en',$data['title_en'],'الصياغة الإنجليزية',255);
    add_max_error($errors,'pillar',$data['pillar'],'محور المحتوى',100);
    add_date_error($errors,'planned_for',$data['planned_for'],'تاريخ التخطيط');

    if (!$errors) {
        $planningDate = $data['planned_for'] !== '' ? new DateTimeImmutable($data['planned_for']) : new DateTimeImmutable('today');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q('INSERT INTO content_topics(user_id,title_ar,title_en,pillar,month_number,week_number,planned_for,objective,core_message,evidence_source,truthfulness_note,topic_status,internal_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $userId,$data['title_ar'],$data['title_en'] ?: $data['title_ar'],$data['pillar'],(int)$planningDate->format('n'),(int)$planningDate->format('W'),
                $data['planned_for'] ?: null,$data['objective'],$data['core_message'],$data['evidence_source'],$data['truthfulness_note'],'planned',$data['internal_notes'],
            ]);
            $topicId = (int)$pdo->lastInsertId();
            $statement = $pdo->prepare('INSERT INTO content_posts(topic_id,platform,language,content_format,post_status) VALUES(?,?,?,?,?)');
            foreach ([['linkedin','en','text_post'],['facebook','ar','text_post'],['instagram','ar','carousel']] as [$platform,$language,$format]) {
                $statement->execute([$topicId,$platform,$language,$format,'planned']);
            }
            $pdo->commit();
            flash('success','تم بناء مساحة الموضوع. الخطوة التالية: صياغة النسخ الثلاث ثم جدولة كل نسخة.');
            redirect('content_topic.php?id='.$topicId.'&created=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[content-topic-create] '.$exception->getMessage());
            $errors['general'] = 'تعذر إنشاء الموضوع. لم يتم حفظ أي سجل جزئي.';
        }
    }
}

$pageTitle = 'إنشاء موضوع محتوى';
$pageDescription = 'حوّل فكرة واحدة قابلة للإثبات إلى مساحة عمل لثلاث منصات';
$active = 'content';
require __DIR__.'/includes/layout_top.php';
?>

<section class="content-create-hero">
    <div>
        <a class="content-back" href="content_plan.php">→ العودة إلى خطة المحتوى</a>
        <span class="content-create-kicker">CONTENT BRIEF</span>
        <h2>ابنِ أصل الفكرة مرة واحدة.<br><em>التكييف لكل منصة يأتي بعدها.</em></h2>
        <p>هذه الصفحة لا تطلب منك كتابة المنشورات. المطلوب قرار تحريري واضح: ماذا ستقول، لماذا يهم، وما الدليل الذي يمنع المحتوى العام أو المبالغ فيه.</p>
    </div>
    <div class="content-create-output" aria-label="المخرجات التي سينشئها النظام">
        <span>بعد الحفظ</span>
        <strong>3 مساحات تحرير</strong>
        <div><i>in</i><b>LinkedIn</b><small>English · Text</small></div>
        <div><i>f</i><b>Facebook</b><small>العربية · نص</small></div>
        <div><i>ig</i><b>Instagram</b><small>العربية · Carousel</small></div>
    </div>
</section>

<nav class="content-create-flow" aria-label="مسار إنشاء المحتوى">
    <div class="active"><span>01</span><p><strong>Content Brief</strong><small>الفكرة والدليل والموعد</small></p></div>
    <i>←</i>
    <div><span>02</span><p><strong>Platform Drafts</strong><small>صياغة النسخ الثلاث</small></p></div>
    <i>←</i>
    <div><span>03</span><p><strong>Schedule & Publish</strong><small>جدولة وتوثيق النشر</small></p></div>
</nav>

<?= error_summary($errors) ?>
<form class="content-brief-layout" method="post">
    <?= csrf_field() ?>
    <div class="content-brief-main">
        <section class="content-brief-section">
            <header><span>01</span><div><strong>قرار المحتوى</strong><small>إذا لم تكن الفكرة واضحة هنا، ستصبح النسخ الثلاث ضوضاء مختلفة.</small></div></header>
            <div class="content-brief-fields">
                <div class="field full<?= field_class($errors,'title_ar') ?>">
                    <label for="title_ar">ما الفكرة التي تريد إثباتها؟ <em>*</em></label>
                    <textarea id="title_ar" name="title_ar" class="content-idea-input" maxlength="255" required placeholder="مثال: لوحة المعلومات لا تصلح عملية بيانات مكسورة"><?= e($data['title_ar']) ?></textarea>
                    <div class="field-support"><span>اكتب موقفاً أو درساً، لا عنواناً عاماً مثل «أهمية البيانات».</span><b>255 حرفاً</b></div><?= field_error($errors,'title_ar') ?>
                </div>
                <div class="field full<?= field_class($errors,'core_message') ?>">
                    <label for="core_message">ما الخلاصة الواحدة التي سيخرج بها القارئ؟ <em>*</em></label>
                    <textarea id="core_message" name="core_message" required placeholder="جملة واحدة واضحة يمكن الدفاع عنها..."><?= e($data['core_message']) ?></textarea>
                    <small class="field-hint">هذه هي الرسالة المرجعية التي تمنع اختلاف النسخ عن بعضها.</small><?= field_error($errors,'core_message') ?>
                </div>
                <div class="field full"><label for="objective">لماذا تنشر هذا الموضوع الآن؟ <span class="optional">اختياري</span></label><textarea id="objective" name="objective" placeholder="فرصة مهنية، خدمة تريد إبرازها، نقاش متكرر، أو تجربة تستحق المشاركة..."><?= e($data['objective']) ?></textarea></div>
            </div>
        </section>

        <section class="content-brief-section">
            <header><span>02</span><div><strong>سياج المصداقية</strong><small>افصل بين ما تعرفه، وما جربته، وما لا يجوز ادعاؤه.</small></div></header>
            <div class="content-brief-fields two-columns">
                <div class="field<?= field_class($errors,'pillar') ?>">
                    <label for="pillar">محور المحتوى <em>*</em></label>
                    <input id="pillar" name="pillar" list="content-pillars" maxlength="100" required value="<?= e($data['pillar']) ?>" placeholder="Data Systems & Operations">
                    <datalist id="content-pillars"><?php foreach($existingPillars as $item): ?><option value="<?= e($item) ?>"><?php endforeach; ?></datalist>
                    <?= field_error($errors,'pillar') ?>
                </div>
                <div class="field<?= field_class($errors,'planned_for') ?>">
                    <label for="planned_for">أسبوع التنفيذ <span class="optional">اختياري</span></label>
                    <input id="planned_for" type="date" name="planned_for" value="<?= e($data['planned_for']) ?>">
                    <small class="field-hint">أي تاريخ وأي سنة. اتركه فارغاً ليبقى في بنك الأفكار.</small><?= field_error($errors,'planned_for') ?>
                </div>
                <div class="field full"><label for="evidence_source">ما الدليل أو التجربة التي ستبني عليها؟</label><textarea id="evidence_source" name="evidence_source" placeholder="مشروع نفذته، عملية أدرتها، رقم موثق، خطأ تعلمت منه، أو ملاحظة ميدانية..."><?= e($data['evidence_source']) ?></textarea></div>
                <div class="field full content-truth-field"><label for="truthfulness_note">ما الحد الذي لن تتجاوزه في الادعاء؟</label><textarea id="truthfulness_note" name="truthfulness_note" placeholder="مثال: المشروع كان prototype ولم يحقق traction مثبتة؛ لا أصف lead على أنه عميل..."><?= e($data['truthfulness_note']) ?></textarea><small class="field-hint">ملاحظة داخلية لا تظهر في المنشور.</small></div>
                <div class="field full"><label for="title_en">صياغة إنجليزية أولية <span class="optional">اختياري</span></label><input id="title_en" name="title_en" maxlength="255" dir="ltr" value="<?= e($data['title_en']) ?>" placeholder="Leave empty if the English framing is not ready yet"><small class="field-hint">يمكنك تعديلها لاحقاً قبل كتابة LinkedIn.</small><?= field_error($errors,'title_en') ?></div>
                <div class="field full"><label for="internal_notes">ملاحظات للعمل <span class="optional">اختياري</span></label><textarea id="internal_notes" name="internal_notes" placeholder="مصادر، أمثلة، رابط مرجعي، أو زاوية تريد اختبارها..."><?= e($data['internal_notes']) ?></textarea></div>
            </div>
        </section>
    </div>

    <aside class="content-brief-sidebar">
        <div class="content-brief-checklist">
            <span class="content-create-kicker">QUALITY GATE</span>
            <h3>قبل إنشاء المساحة</h3>
            <ul><li><i>✓</i><span><strong>فكرة محددة</strong><small>موقف أو درس، وليس مجالاً عاماً.</small></span></li><li><i>✓</i><span><strong>خلاصة واحدة</strong><small>يمكن للقارئ تذكرها بعد دقيقة.</small></span></li><li><i>✓</i><span><strong>دليل صادق</strong><small>الخبرة والنتيجة موصوفتان بدقة.</small></span></li><li><i>✓</i><span><strong>موعد مرن</strong><small>أي سنة، أو بنك الأفكار بلا موعد.</small></span></li></ul>
        </div>
        <div class="content-brief-next"><span>ما الذي سيحدث؟</span><p>ينشئ النظام الموضوع وثلاث نسخ فارغة. لن ينشر شيئاً تلقائياً، ولن يكرر الفكرة.</p></div>
        <button class="btn content-create-submit" type="submit"><span>إنشاء مساحة الموضوع</span><b>←</b></button>
        <a class="content-cancel" href="content_plan.php">إلغاء والعودة للخطة</a>
    </aside>
</form>
<?php require __DIR__.'/includes/layout_bottom.php';
