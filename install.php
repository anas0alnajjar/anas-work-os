<?php
require __DIR__.'/includes/bootstrap.php';
if (empty($config['install_enabled'])) { http_response_code(404); exit; }
$error=''; $success='';
try {
    $sql=file_get_contents(__DIR__.'/sql/install.sql');
    foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $statement){ db()->exec($statement); }
    $installed=(int)scalar('SELECT COUNT(*) FROM users')>0;
    if(is_post() && !$installed){
        verify_csrf();
        $username=trim($_POST['username']??''); $password=$_POST['password']??'';
        if(strlen($username)<3 || strlen($password)<8) throw new RuntimeException('اسم المستخدم 3 أحرف على الأقل وكلمة المرور 8 أحرف على الأقل.');
        q('INSERT INTO users(username,password_hash) VALUES(?,?)',[$username,password_hash($password,PASSWORD_DEFAULT)]);
        if(!empty($_POST['seed'])){
            $seed=require __DIR__.'/includes/seed_data.php';
            $orgStmt=db()->prepare('INSERT INTO organizations(name,category,priority,status,website,fit_reason,first_offer,notes) VALUES(?,?,?,?,?,?,?,?)');
            $orgIds=[];
            foreach($seed['organizations'] as $o){$orgStmt->execute([$o['name'],$o['category'],$o['priority'],$o['status'],$o['website'],$o['fit_reason'],$o['first_offer'],$o['notes']]);$orgIds[$o['name']]=(int)db()->lastInsertId();}
            $cStmt=db()->prepare('INSERT INTO contacts(organization_id,name,job_title,profile_url,relationship_type,status,last_contact_date,next_followup_date,notes) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach($seed['contacts'] as $c){$cStmt->execute([$orgIds[$c['organization']],$c['name'],$c['job_title'],$c['profile_url'],$c['relationship_type'],$c['status'],$c['last_contact_date'],$c['next_followup_date'],$c['notes']]);}
        }
        $success='تم التثبيت بنجاح. يمكنك الآن تسجيل الدخول.'; $installed=true;
    }
} catch(Throwable $e){$error=$e->getMessage();$installed=false;}
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت Anas Work OS</title><link rel="stylesheet" href="assets/style.css"></head><body class="login-page"><div class="login-card"><h1>تثبيت Anas Work OS</h1><p>أنشئ حساب الإدارة وأدخل البيانات الحالية تلقائياً.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert success"><?=e($success)?></div><a class="btn" href="login.php">الذهاب لتسجيل الدخول</a><?php elseif($installed):?><div class="alert success">النظام مثبت مسبقاً.</div><a class="btn" href="login.php">تسجيل الدخول</a><?php else:?><form method="post"><?=csrf_field()?><div class="field"><label>اسم المستخدم</label><input name="username" required autocomplete="username"></div><div class="field"><label>كلمة المرور</label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div><label style="display:flex;gap:8px;align-items:center;margin:16px 0"><input style="width:auto" type="checkbox" name="seed" value="1" checked>إضافة الجهات الثلاثين والأشخاص الحاليين</label><button class="btn" type="submit">تثبيت النظام</button></form><?php endif;?></div></body></html>
