<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/content_options.php';
require_auth();
$userId = current_user_id();

$id = (int)($_GET['id'] ?? $_POST['topic_id'] ?? 0);
$topic = $id ? q('SELECT * FROM content_topics WHERE id=? AND user_id=?', [$id,$userId])->fetch() : null;
if (!$topic) { http_response_code(404); exit('موضوع المحتوى غير موجود.'); }

$topicErrors = [];
$postErrors = [];
$editingPostId = 0;

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete_topic') {
        $confirmation = trim((string)($_POST['delete_confirmation'] ?? ''));
        if ($confirmation !== 'حذف الموضوع') {
            flash('error','اكتب عبارة حذف الموضوع كما هي لتأكيد العملية.');
            redirect('content_topic.php?id='.$id.'#content-danger-zone');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lockedTopic = q('SELECT id FROM content_topics WHERE id=? AND user_id=? FOR UPDATE',[$id,$userId])->fetch();
            if (!$lockedTopic) throw new RuntimeException('Content topic was not found during deletion.');
            q('DELETE FROM content_topics WHERE id=? AND user_id=?',[$id,$userId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[content-topic-delete] '.$exception->getMessage());
            flash('error','تعذر حذف الموضوع. لم يتم تغيير أي بيانات.');
            redirect('content_topic.php?id='.$id.'#content-danger-zone');
        }
        flash('success','تم حذف الموضوع ونسخه الثلاث المرتبطة به.');
        redirect('content_plan.php');
    }

    if ($action === 'update_topic') {
        $topicData = [
            'title_ar'=>trim((string)($_POST['title_ar'] ?? '')),
            'title_en'=>trim((string)($_POST['title_en'] ?? '')),
            'pillar'=>trim((string)($_POST['pillar'] ?? '')),
            'planned_for'=>trim((string)($_POST['planned_for'] ?? '')),
            'objective'=>trim((string)($_POST['objective'] ?? '')),
            'core_message'=>trim((string)($_POST['core_message'] ?? '')),
            'evidence_source'=>trim((string)($_POST['evidence_source'] ?? '')),
            'truthfulness_note'=>trim((string)($_POST['truthfulness_note'] ?? '')),
            'topic_status'=>trim((string)($_POST['topic_status'] ?? '')),
            'internal_notes'=>trim((string)($_POST['internal_notes'] ?? '')),
        ];
        add_required_error($topicErrors, 'title_ar', $topicData['title_ar'], 'العنوان العربي');
        add_required_error($topicErrors, 'title_en', $topicData['title_en'], 'العنوان الإنجليزي');
        add_required_error($topicErrors, 'pillar', $topicData['pillar'], 'محور المحتوى');
        add_max_error($topicErrors, 'title_ar', $topicData['title_ar'], 'العنوان العربي', 255);
        add_max_error($topicErrors, 'title_en', $topicData['title_en'], 'العنوان الإنجليزي', 255);
        add_max_error($topicErrors, 'pillar', $topicData['pillar'], 'محور المحتوى', 100);
        add_date_error($topicErrors, 'planned_for', $topicData['planned_for'], 'أسبوع التنفيذ');
        add_choice_error($topicErrors, 'topic_status', $topicData['topic_status'], array_keys(content_topic_statuses()), 'حالة الموضوع');

        if (!$topicErrors) {
            $planningDate = $topicData['planned_for'] !== '' ? new DateTimeImmutable($topicData['planned_for']) : null;
            $monthNumber = $planningDate ? (int)$planningDate->format('n') : (int)$topic['month_number'];
            $weekNumber = $planningDate ? (int)$planningDate->format('W') : (int)$topic['week_number'];
            q('UPDATE content_topics SET title_ar=?,title_en=?,pillar=?,month_number=?,week_number=?,planned_for=?,objective=?,core_message=?,evidence_source=?,truthfulness_note=?,topic_status=?,internal_notes=? WHERE id=? AND user_id=?', [
                $topicData['title_ar'],$topicData['title_en'],$topicData['pillar'],$monthNumber,$weekNumber,$topicData['planned_for'] ?: null,
                $topicData['objective'],$topicData['core_message'],$topicData['evidence_source'],$topicData['truthfulness_note'],
                $topicData['topic_status'],$topicData['internal_notes'],$id,$userId,
            ]);
            flash('success', 'تم تحديث أساس الموضوع. أصبحت الرسالة المرجعية موحدة للنسخ الثلاث.');
            redirect('content_topic.php?id='.$id.'#topic-foundation');
        }
        $topic = array_merge($topic, $topicData);
    }

    if ($action === 'update_post' || $action === 'mark_published') {
        $editingPostId = (int)($_POST['post_id'] ?? 0);
        $storedPost = q('SELECT * FROM content_posts WHERE id=? AND topic_id=?', [$editingPostId,$id])->fetch();
        if (!$storedPost) { http_response_code(404); exit('نسخة المنصة غير موجودة.'); }

        $postData = [
            'language'=>trim((string)($_POST['language'] ?? '')),
            'content_format'=>trim((string)($_POST['content_format'] ?? '')),
            'platform_angle'=>trim((string)($_POST['platform_angle'] ?? '')),
            'post_title'=>trim((string)($_POST['post_title'] ?? '')),
            'draft_text'=>trim((string)($_POST['draft_text'] ?? '')),
            'visual_note'=>trim((string)($_POST['visual_note'] ?? '')),
            'call_to_action'=>trim((string)($_POST['call_to_action'] ?? '')),
            'post_status'=>trim((string)($_POST['post_status'] ?? '')),
            'scheduled_at'=>trim((string)($_POST['scheduled_at'] ?? '')),
            'custom_reminder_at'=>trim((string)($_POST['custom_reminder_at'] ?? '')),
            'published_url'=>trim((string)($_POST['published_url'] ?? '')),
        ];
        add_choice_error($postErrors, 'language', $postData['language'], array_keys(content_languages()), 'اللغة');
        add_choice_error($postErrors, 'content_format', $postData['content_format'], array_keys(content_formats()), 'التنسيق');
        add_choice_error($postErrors, 'post_status', $postData['post_status'], array_keys(content_post_statuses()), 'حالة النسخة');
        add_max_error($postErrors, 'post_title', $postData['post_title'], 'عنوان النسخة', 255);
        add_url_error($postErrors, 'published_url', $postData['published_url'], 'رابط المنشور');
        add_max_error($postErrors, 'published_url', $postData['published_url'], 'رابط المنشور', 500);
        if (!content_valid_datetime_input($postData['scheduled_at'])) $postErrors['scheduled_at'] = 'أدخل موعد نشر صحيحاً.';
        if (!content_valid_datetime_input($postData['custom_reminder_at'])) $postErrors['custom_reminder_at'] = 'أدخل موعد تذكير صحيحاً.';
        if ($postData['post_status'] === 'scheduled' && $postData['scheduled_at'] === '') $postErrors['scheduled_at'] = 'موعد النشر مطلوب عندما تكون الحالة مجدول.';
        if (($postData['post_status'] === 'published' || $action === 'mark_published') && $postData['published_url'] === '') $postErrors['published_url'] = 'ألصق رابط المنشور لتوثيق النشر.';

        if (!$postErrors) {
            $finalStatus = $action === 'mark_published' ? 'published' : $postData['post_status'];
            $publishedAt = $finalStatus === 'published' ? ($storedPost['published_at'] ?: date('Y-m-d H:i:s')) : null;
            q('UPDATE content_posts SET language=?,content_format=?,platform_angle=?,post_title=?,draft_text=?,visual_note=?,call_to_action=?,post_status=?,scheduled_at=?,custom_reminder_at=?,published_at=?,published_url=?,reminder_sent_at=NULL WHERE id=? AND topic_id=?', [
                $postData['language'],$postData['content_format'],$postData['platform_angle'],$postData['post_title'],$postData['draft_text'],
                $postData['visual_note'],$postData['call_to_action'],$finalStatus,content_database_datetime($postData['scheduled_at']),
                content_database_datetime($postData['custom_reminder_at']),$publishedAt,$postData['published_url'] ?: null,$editingPostId,$id,
            ]);
            flash('success', $action === 'mark_published' ? 'تم توثيق النشر وحفظ الرابط.' : 'تم حفظ نسخة '.content_platform_label($storedPost['platform']).'.');
            redirect('content_topic.php?id='.$id.'#post-'.$storedPost['platform']);
        }
    }
}

$posts = q("SELECT * FROM content_posts WHERE topic_id=? ORDER BY FIELD(platform,'linkedin','facebook','instagram')", [$id])->fetchAll();
if ($editingPostId && $postErrors) {
    foreach ($posts as &$item) if ((int)$item['id'] === $editingPostId) $item = array_merge($item, $postData);
    unset($item);
}
$publishedCount = count(array_filter($posts, static fn($post) => $post['post_status'] === 'published'));

$pageTitle = 'مساحة الموضوع';
$pageDescription = 'ثبّت الفكرة أولاً، ثم حرّر وجدول كل منصة من بطاقتها';
$active = 'content';
require __DIR__.'/includes/layout_top.php';
?>

<section class="content-topic-hero">
    <div>
        <a class="content-back" href="content_plan.php">→ العودة إلى الخطة</a>
        <div class="content-kicker"><?php if($topic['planned_for']): ?><span><?= e(date('Y-m-d',strtotime($topic['planned_for']))) ?></span><b>الأسبوع <?= e(date('W',strtotime($topic['planned_for']))) ?></b><?php else: ?><span><?= $topic['seed_key'] ? 'الخطة الأصلية' : 'بنك الأفكار' ?></span><b><?= $topic['seed_key'] ? 'الأسبوع '.(int)$topic['week_number'] : 'بلا موعد' ?></b><?php endif; ?></div>
        <h2><?= e($topic['title_ar']) ?></h2>
        <p lang="en" dir="ltr"><?= e($topic['title_en']) ?></p>
        <div class="content-focus-meta"><span><?= e($topic['pillar']) ?></span><span><?= e(content_topic_status_label($topic['topic_status'])) ?></span></div>
    </div>
    <div class="content-completion"><strong><?= $publishedCount ?>/<?= count($posts) ?></strong><span>نسخ منشورة</span><div><i style="width:<?= count($posts) ? (int)round(($publishedCount/count($posts))*100) : 0 ?>%"></i></div></div>
</section>

<section class="content-basis-strip" aria-label="مرجع صياغة الموضوع">
    <div><span>الرسالة الأساسية</span><p><?= e($topic['core_message'] ?: 'لم تُحدد بعد — افتح أساس الموضوع وأضف الرسالة الواحدة التي تربط النسخ الثلاث.') ?></p></div>
    <div><span>الدليل</span><p><?= e($topic['evidence_source'] ?: 'لا يوجد دليل مسجل بعد.') ?></p></div>
    <div class="truth"><span>حدود الادعاء</span><p><?= e($topic['truthfulness_note'] ?: 'لا توجد ملاحظة مسجلة؛ راجع دقة أي أرقام أو نتائج قبل النشر.') ?></p></div>
</section>

<?= error_summary($topicErrors) ?>
<details class="section content-foundation" id="topic-foundation"<?= $topicErrors?' open':'' ?>>
    <summary><div><span>01</span><div><strong>أساس الموضوع والادعاءات</strong><small>المرجع المشترك قبل كتابة أي نسخة</small></div></div><b>تحرير</b></summary>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="topic_id" value="<?= $id ?>"><input type="hidden" name="action" value="update_topic">
        <div class="form-grid">
            <div class="field full<?= field_class($topicErrors,'title_ar') ?>"><label for="title_ar">العنوان العربي <em>*</em></label><input id="title_ar" name="title_ar" maxlength="255" required value="<?= e($topic['title_ar']) ?>"><?= field_error($topicErrors,'title_ar') ?></div>
            <div class="field full<?= field_class($topicErrors,'title_en') ?>"><label for="title_en">العنوان الإنجليزي <em>*</em></label><input id="title_en" name="title_en" maxlength="255" required dir="ltr" value="<?= e($topic['title_en']) ?>"><?= field_error($topicErrors,'title_en') ?></div>
            <div class="field full<?= field_class($topicErrors,'pillar') ?>"><label for="pillar">محور المحتوى <em>*</em></label><input id="pillar" name="pillar" maxlength="100" required value="<?= e($topic['pillar']) ?>"><?= field_error($topicErrors,'pillar') ?></div>
            <div class="field full<?= field_class($topicErrors,'planned_for') ?>"><label for="planned_for">أسبوع التنفيذ <span class="optional">اختياري</span></label><input id="planned_for" type="date" name="planned_for" value="<?= e($topic['planned_for']) ?>"><small class="field-hint">اختر أي تاريخ وأي سنة، أو اتركه فارغاً ليبقى في بنك الأفكار.</small><?= field_error($topicErrors,'planned_for') ?></div>
            <div class="field full"><label for="objective">الهدف المهني</label><textarea id="objective" name="objective"><?= e($topic['objective']) ?></textarea></div>
            <div class="field full"><label for="core_message">الرسالة الأساسية</label><textarea id="core_message" name="core_message" placeholder="ما الفكرة الواحدة التي يجب أن يتذكرها القارئ؟"><?= e($topic['core_message']) ?></textarea></div>
            <div class="field full"><label for="evidence_source">الدليل أو مصدر الخبرة</label><textarea id="evidence_source" name="evidence_source"><?= e($topic['evidence_source']) ?></textarea></div>
            <div class="field full"><label for="truthfulness_note">حدود الادعاء</label><textarea id="truthfulness_note" name="truthfulness_note" placeholder="ما الذي يجب ألا نبالغ أو ندّعيه؟"><?= e($topic['truthfulness_note']) ?></textarea><small class="field-hint">هذا الحقل داخلي ويحمي المصداقية أثناء الصياغة.</small></div>
            <div class="field"><label for="topic_status">حالة الموضوع</label><select id="topic_status" name="topic_status"><?= content_option_tags(content_topic_statuses(),$topic['topic_status']) ?></select></div>
            <div class="field"><label for="internal_notes">ملاحظة داخلية</label><textarea id="internal_notes" name="internal_notes"><?= e($topic['internal_notes']) ?></textarea></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">حفظ أساس الموضوع</button></div>
    </form>
</details>

<div class="content-editor-heading"><div><span>02</span><div><strong>نسخ المنصات</strong><small>حرّر كل نسخة، ثم انقلها إلى جاهز أو مجدول</small></div></div><span><?= count($posts) ?> نسخ</span></div>

<div class="content-post-editors">
<?php foreach ($posts as $post): $errors = (int)$post['id']===$editingPostId ? $postErrors : []; ?>
    <article class="content-post-card platform-<?= e($post['platform']) ?>" id="post-<?= e($post['platform']) ?>">
        <header>
            <div class="content-platform-identity"><span><?= strtoupper(substr($post['platform'],0,2)) ?></span><div><strong><?= e(content_platform_label($post['platform'])) ?></strong><small><?= e(content_format_label($post['content_format'])) ?> · <?= e(content_languages()[$post['language']] ?? $post['language']) ?></small></div></div>
            <span class="<?= e(content_status_class($post['post_status'])) ?>"><?= e(content_status_label($post['post_status'])) ?></span>
        </header>
        <?= error_summary($errors) ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="topic_id" value="<?= $id ?>"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
            <div class="content-editor-grid">
                <div class="content-writing-pane">
                    <div class="form-grid">
                        <div class="field<?= field_class($errors,'language') ?>"><label>اللغة</label><select name="language"><?= content_option_tags(content_languages(),$post['language']) ?></select><?= field_error($errors,'language') ?></div>
                        <div class="field<?= field_class($errors,'content_format') ?>"><label>التنسيق</label><select name="content_format"><?= content_option_tags(content_formats(),$post['content_format']) ?></select><?= field_error($errors,'content_format') ?></div>
                        <div class="field full"><label>زاوية المنصة</label><textarea name="platform_angle" class="compact-textarea"><?= e($post['platform_angle']) ?></textarea></div>
                        <div class="field full<?= field_class($errors,'post_title') ?>"><label>عنوان النسخة</label><input name="post_title" maxlength="255" value="<?= e($post['post_title']) ?>" placeholder="Hook أو عنوان داخلي يساعدك أثناء التحرير"><?= field_error($errors,'post_title') ?></div>
                        <div class="field full"><div class="field-label-row"><label for="draft-<?= (int)$post['id'] ?>">النص النهائي للنشر</label><button type="button" class="copy-action" data-copy-target="draft-<?= (int)$post['id'] ?>">نسخ النص</button></div><textarea id="draft-<?= (int)$post['id'] ?>" name="draft_text" class="content-draft" dir="auto" placeholder="اكتب النسخة بصيغة قابلة للنسخ والنشر مباشرة..."><?= e($post['draft_text']) ?></textarea></div>
                        <div class="field full"><label>ملاحظة بصرية</label><textarea name="visual_note" class="compact-textarea" placeholder="صورة، Carousel، تسجيل شاشة، أو لا شيء..."><?= e($post['visual_note']) ?></textarea></div>
                        <div class="field full"><label>الدعوة إلى الإجراء (CTA)</label><input name="call_to_action" value="<?= e($post['call_to_action']) ?>" placeholder="سؤال، تواصل، قراءة الرابط، أو حفظ المنشور"></div>
                    </div>
                </div>
                <aside class="content-publish-pane">
                    <h3>الجاهزية والنشر</h3>
                    <div class="field<?= field_class($errors,'post_status') ?>"><label>حالة النسخة</label><select name="post_status"><?= content_option_tags(content_post_statuses(),$post['post_status']) ?></select><?= field_error($errors,'post_status') ?></div>
                    <div class="field<?= field_class($errors,'scheduled_at') ?>"><label>موعد النشر</label><input type="datetime-local" name="scheduled_at" value="<?= e(content_datetime_input($post['scheduled_at'])) ?>"><?= field_error($errors,'scheduled_at') ?><small class="field-hint">مطلوب عند اختيار «مجدول».</small></div>
                    <div class="field<?= field_class($errors,'custom_reminder_at') ?>"><label>تذكير مخصص</label><input type="datetime-local" name="custom_reminder_at" value="<?= e(content_datetime_input($post['custom_reminder_at'])) ?>"><?= field_error($errors,'custom_reminder_at') ?></div>
                    <div class="field<?= field_class($errors,'published_url') ?>"><label>رابط المنشور</label><input type="url" name="published_url" maxlength="500" dir="ltr" inputmode="url" value="<?= e($post['published_url']) ?>" placeholder="https://..."><?= field_error($errors,'published_url') ?></div>
                    <?php if ($post['published_at']): ?><div class="content-published-note"><span>تم توثيق النشر</span><strong><?= e(date('Y-m-d H:i',strtotime($post['published_at']))) ?></strong></div><?php endif; ?>
                    <div class="content-publish-actions"><button class="btn" type="submit" name="action" value="update_post">حفظ النسخة</button><button class="btn secondary" type="submit" name="action" value="mark_published">توثيق كمنشور</button></div>
                </aside>
            </div>
        </form>
    </article>
<?php endforeach; ?>
</div>
<section class="control-card danger-zone content-danger-zone" id="content-danger-zone">
    <div><strong>حذف الموضوع</strong><p>يحذف الموضوع ومسودات LinkedIn وFacebook وInstagram التابعة له. لا يؤثر في أي جهة أو فرصة عمل.</p></div>
    <button class="danger-link" type="button" data-dialog-open="delete-content-topic-dialog">حذف</button>
</section>
<dialog class="delete-dialog" id="delete-content-topic-dialog" aria-labelledby="delete-content-topic-title">
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="topic_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_topic">
        <button class="dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button>
        <span class="delete-dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></span>
        <h2 id="delete-content-topic-title">حذف هذا الموضوع؟</h2>
        <p>سيتم حذف الموضوع وكل النصوص والمواعيد وروابط النشر المرتبطة بنسخه الثلاث. لا يمكن التراجع عن العملية.</p>
        <div class="field"><label for="delete_topic_confirmation">اكتب <strong>حذف الموضوع</strong> للتأكيد</label><input id="delete_topic_confirmation" name="delete_confirmation" autocomplete="off" data-confirm-phrase="حذف الموضوع" required></div>
        <div class="dialog-actions"><button class="btn secondary" type="button" data-dialog-close>إلغاء</button><button class="btn danger" type="submit" data-confirm-submit disabled>حذف نهائياً</button></div>
    </form>
</dialog>
<?php require __DIR__.'/includes/layout_bottom.php';
