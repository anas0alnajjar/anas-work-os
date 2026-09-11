<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/reminders.php';

$startedAt = new DateTimeImmutable('now',new DateTimeZone('UTC'));
$startedAtTimestamp = microtime(true);
$dryRun = in_array('--dry-run',$argv ?? [],true);
$accounts = q("SELECT id,timezone,reminder_hour FROM users WHERE account_status='active' AND reminders_enabled=1 ORDER BY id")->fetchAll();
$summary = ['status'=>'running','started_at_utc'=>$startedAt->format(DATE_ATOM),'finished_at_utc'=>null,'duration_ms'=>null,'processed'=>count($accounts),'sent'=>0,'skipped'=>0,'failed'=>0,'dry_run'=>$dryRun,'results'=>[]];

foreach ($accounts as $account) {
    $userId = (int)$account['id'];
    try {
        $localNow = new DateTimeImmutable('now',reminder_timezone((string)$account['timezone']));
        if ($dryRun) {
            $contentItems = reminder_collect_content_items($userId,(string)$account['timezone'],0,true);
            $summary['results'][] = ['user_id'=>$userId,'scope'=>'content','status'=>'skipped','reason'=>'dry-run','count'=>count($contentItems),'local_time'=>$localNow->format(DATE_ATOM)];
        } else {
            $contentResult = reminder_send_content_due($userId);
            if ($contentResult['sent']) $summary['sent']++; else $summary['skipped']++;
            $summary['results'][] = ['user_id'=>$userId,'scope'=>'content','status'=>$contentResult['sent']?'sent':'skipped','reason'=>$contentResult['reason'],'count'=>$contentResult['count'],'local_time'=>$localNow->format(DATE_ATOM)];
        }
        if ((int)$localNow->format('G') !== (int)$account['reminder_hour']) {
            $summary['skipped']++;
            $summary['results'][] = ['user_id'=>$userId,'scope'=>'daily','status'=>'skipped','reason'=>'outside-user-hour','current_hour'=>(int)$localNow->format('G'),'reminder_hour'=>(int)$account['reminder_hour'],'local_time'=>$localNow->format(DATE_ATOM)];
            continue;
        }
        if ($dryRun) {
            $items = reminder_collect_items($userId,2,null,(string)$account['timezone']);
            $summary['skipped']++;
            $summary['results'][] = ['user_id'=>$userId,'scope'=>'daily','status'=>'skipped','reason'=>'dry-run','count'=>count($items),'local_time'=>$localNow->format(DATE_ATOM)];
            continue;
        }
        $result = reminder_send_digest(false,null,$userId);
        if ($result['sent']) $summary['sent']++; else $summary['skipped']++;
        $summary['results'][] = ['user_id'=>$userId,'scope'=>'daily','status'=>$result['sent']?'sent':'skipped','reason'=>$result['reason'],'count'=>$result['count'],'local_time'=>$localNow->format(DATE_ATOM)];
    } catch (Throwable $exception) {
        $summary['failed']++;
        $summary['results'][] = ['user_id'=>$userId,'status'=>'failed','reason'=>'delivery-failed','error'=>$exception->getMessage(),'exception'=>get_class($exception)];
        error_log(sprintf('[reminder-cron user=%d] %s: %s in %s:%d',$userId,get_class($exception),$exception->getMessage(),$exception->getFile(),$exception->getLine()));
    }
}

$finishedAt = new DateTimeImmutable('now',new DateTimeZone('UTC'));
$summary['finished_at_utc'] = $finishedAt->format(DATE_ATOM);
$summary['duration_ms'] = (int)round((microtime(true)-$startedAtTimestamp)*1000);
$summary['memory_peak_mb'] = round(memory_get_peak_usage(true)/1024/1024,2);
$summary['status'] = $summary['failed'] ? 'completed_with_errors' : 'success';
echo sprintf("[%s] reminder-cron status=%s processed=%d sent=%d skipped=%d failed=%d dry_run=%s duration_ms=%d\n",$finishedAt->format(DATE_ATOM),$summary['status'],$summary['processed'],$summary['sent'],$summary['skipped'],$summary['failed'],$summary['dry_run']?'true':'false',$summary['duration_ms']);
echo json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
exit($summary['failed'] ? 1 : 0);
