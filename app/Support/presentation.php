<?php

function base_url(string $path = ''): string
{
    return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
}

function site_url(string $path = ''): string
{
    return base_url($path);
}

function server(): bool
{
    $mode = config('nyota.assets_mode', 'legacy');
    if ($mode !== 'legacy') {
        return $mode === 'local';
    }
    $host = (string) parse_url(config('app.url'), PHP_URL_HOST);

    return $host === 'localhost' || str_contains($host, '192.168.') || str_contains($host, '127.0.0.') || str_ends_with($host, '.test');
}

function word_limiter(?string $text, int $limit = 100): string
{
    $words = preg_split('/\s+/', trim((string) $text));

    return implode(' ', array_slice($words, 0, $limit)).(count($words) > $limit ? '&#8230;' : '');
}

function mailto(string $email): string
{
    return '<a href="mailto:'.e($email).'">'.e($email).'</a>';
}

function event_description(?string $text): string
{
    return str_replace(['&lt;p&gt;', '&lt;/p&gt;', '&lt;br&gt;', '&lt;br/&gt;'], ['<p>', '</p>', '<br>', '<br>'], e((string) $text));
}
