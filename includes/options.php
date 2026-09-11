<?php
function organization_priorities(): array { return ['تركيز الآن','بناء علاقة','مراقبة','مؤجل']; }
function organization_statuses(): array { return ['هدف','تم التواصل','محادثة نشطة','فرصة','عرض مرسل','عميل','مؤجل','مغلق']; }
function relationship_types(): array { return ['فرصة مباشرة','صاحب قرار','مؤثر / إحالة','شريك / إحالة','شبكة مهنية','مراقبة فرصة']; }
function contact_statuses(): array { return ['لم أتواصل','جاهز للإرسال','تم الإرسال','تم القبول','تم الرد','اجتماع','عرض مرسل','لا متابعة','مغلق']; }
function activity_types(): array { return ['طلب اتصال','رسالة','رد','مكالمة','اجتماع','متابعة','إحالة','عرض','ملاحظة']; }
function job_statuses(): array { return ['محفوظة','قيد التحضير','تم التقديم','قيد المراجعة','مقابلة','اختبار','عرض وظيفي','مقبول','مرفوض','منسحب','مغلق']; }
function job_document_types(): array {
    return ['cv'=>'السيرة الذاتية','cover_letter'=>'خطاب التغطية','portfolio'=>'ملف الأعمال','certificate'=>'شهادة','other'=>'مستند آخر'];
}
function interview_types(): array { return ['مكالمة تمهيدية','موارد بشرية','تقنية','دراسة حالة','مدير التوظيف','مقابلة نهائية','أخرى']; }
function interview_formats(): array { return ['عن بعد','هاتف','حضوري']; }
function interview_statuses(): array { return ['مجدولة','مكتملة','مؤجلة','ملغاة']; }
function document_type_label(string $type): string { return job_document_types()[$type] ?? 'مستند'; }
function format_file_size(int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1).' MB';
    return max(1, (int)ceil($bytes / 1024)).' KB';
}
function select_options(array $options, ?string $selected): string {
    $html=''; foreach($options as $o){ $s=$o===$selected?' selected':''; $html.='<option value="'.e($o).'"'.$s.'>'.e($o).'</option>'; } return $html;
}
