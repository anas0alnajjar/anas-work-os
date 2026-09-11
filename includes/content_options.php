<?php
declare(strict_types=1);

function content_platforms(): array { return ['linkedin'=>'LinkedIn','facebook'=>'Facebook','instagram'=>'Instagram']; }
function content_languages(): array { return ['en'=>'English','ar'=>'العربية','mixed'=>'مختلط']; }
function content_formats(): array { return ['text_post'=>'منشور نصي','carousel'=>'Carousel','reel'=>'Reel','story'=>'Story']; }
function content_post_statuses(): array { return ['planned'=>'مخطط','drafting'=>'قيد الكتابة','ready'=>'جاهز','scheduled'=>'مجدول','published'=>'منشور','skipped'=>'متجاوز']; }
function content_topic_statuses(): array { return ['planned'=>'مخطط','active'=>'نشط','completed'=>'مكتمل','archived'=>'مؤرشف']; }

function content_option_tags(array $options, ?string $selected): string {
    $html = '';
    foreach ($options as $value=>$label) {
        $html .= '<option value="'.e((string)$value).'"'.((string)$value===(string)$selected?' selected':'').'>'.e((string)$label).'</option>';
    }
    return $html;
}

function content_status_label(string $status): string { return content_post_statuses()[$status] ?? $status; }
function content_topic_status_label(string $status): string { return content_topic_statuses()[$status] ?? $status; }
function content_platform_label(string $platform): string { return content_platforms()[$platform] ?? $platform; }
function content_format_label(string $format): string { return content_formats()[$format] ?? $format; }

function content_status_class(string $status): string {
    return match ($status) {
        'published' => 'badge success',
        'ready', 'scheduled' => 'badge info',
        'drafting' => 'badge warning',
        'skipped' => 'badge neutral',
        default => 'badge neutral',
    };
}

function content_datetime_input(?string $value): string {
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}

function content_valid_datetime_input(string $value): bool {
    if ($value === '') return true;
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date && $date->format('Y-m-d\TH:i') === $value;
}

function content_database_datetime(string $value): ?string {
    return $value === '' ? null : date('Y-m-d H:i:s', strtotime($value));
}
