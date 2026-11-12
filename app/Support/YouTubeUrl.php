<?php

namespace App\Support;

/**
 * Turns the YouTube links a school pastes into a video id. Only the id is ever
 * stored or rendered, so a link can never smuggle another host, a query string
 * or a script into the embed the website builds.
 */
final class YouTubeUrl
{
    private const HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
        'youtu.be',
        'www.youtu.be',
    ];

    /** A YouTube video id is 11 url-safe characters and nothing else. */
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * The video id behind a watch, share, embed, shorts or live link,
     * or null when the link is not a YouTube video at all.
     */
    public static function videoId(?string $url): ?string
    {
        $candidate = trim((string) $url);

        if ($candidate === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);

        if ($parts === false
            || ! isset($parts['host'])
            || ! in_array(strtolower($parts['host']), self::HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true))) {
            return null;
        }

        $id = self::idFromQuery($parts['query'] ?? '') ?? self::idFromPath($parts['path'] ?? '');

        return $id !== null && preg_match(self::ID_PATTERN, $id) === 1 ? $id : null;
    }

    /**
     * The page a visitor can open, built from the id alone, so the stored link
     * is never echoed back into an href unescaped.
     */
    public static function watchUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v='.$videoId;
    }

    /** The player frame for our own embed, with only the id interpolated. */
    public static function embedUrl(string $videoId, array $params = []): string
    {
        $query = http_build_query($params + ['rel' => '0']);

        return 'https://www.youtube.com/embed/'.$videoId.($query === '' ? '' : '?'.$query);
    }

    /** The thumbnail YouTube serves for an id; 404s fall back to a placeholder. */
    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://img.youtube.com/vi/'.$videoId.'/hqdefault.jpg';
    }

    private static function idFromQuery(string $query): ?string
    {
        parse_str($query, $parsed);
        $value = $parsed['v'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function idFromPath(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $part): bool => $part !== ''));

        if ($segments === []) {
            return null;
        }

        $first = strtolower($segments[0]);

        if ($first === 'youtu.be' || in_array($first, ['embed', 'shorts', 'live', 'v'], true)) {
            return $segments[1] ?? null;
        }

        // youtu.be/ID arrives with the host already stripped, so the only
        // segment left is the id itself.
        return count($segments) === 1 ? $segments[0] : null;
    }
}
