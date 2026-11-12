<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The single gate a lesson body passes through on its way in and out.
 *
 * The rich text editor produces markup, and markup is the one place where a
 * stored value becomes executable in someone else's browser, so nothing is kept
 * unless this allowlist names it: no style attribute, no class, no script, no
 * iframe. Links are restricted to http, https and mailto and always carry
 * rel="noopener noreferrer"; pictures may point at our own uploads folder or an
 * absolute https address.
 */
final class HtmlContent
{
    /** Tags the editor may produce; every one of them is attribute-free. */
    private const TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'sub', 'sup',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'caption',
    ];

    /** Tags that need attributes, and the only attributes they keep. */
    private const TAG_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    /**
     * Anything the browser would act on rather than show. The default action
     * keeps the text of an unknown tag, so these are named to lose their
     * contents entirely instead of printing a script as a paragraph.
     */
    private const DROPPED = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'form', 'input',
        'textarea', 'select', 'button', 'link', 'meta', 'base', 'title', 'head', 'body', 'html',
        'svg', 'math', 'template', 'slot', 'noscript', 'marquee', 'details', 'audio', 'video', 'source',
    ];

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        // A script or style block is removed with what it holds before the tag
        // gate runs, so its contents cannot come back as visible text.
        $input = preg_replace('#<(script|style|noscript|template)\b.*?</\1>\s*#is', '', trim((string) $html)) ?? '';

        $clean = $this->sanitizer()->sanitize($input);

        return $clean === false ? '' : trim($clean);
    }

    /**
     * The body with its markup removed, for a list preview or a search index.
     */
    public function plainText(?string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
    }

    public function excerpt(?string $html, int $limit = 150): string
    {
        return Str::limit($this->plainText($html), $limit);
    }

    public function wordCount(?string $html): int
    {
        $text = $this->plainText($html);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * A pasted link, kept only when it points somewhere a browser may open.
     */
    public function safeUrl(?string $url): ?string
    {
        $candidate = trim((string) $url);

        if ($candidate === '' || mb_strlen($candidate) > 300) {
            return null;
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);

        if ($parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return null;
        }

        return filter_var($candidate, FILTER_VALIDATE_URL) === false ? null : $candidate;
    }

    /**
     * Reference links are stored one per line; each line is checked again on the
     * way out, so a hand-edited column cannot plant a javascript: address.
     *
     * @return list<string>
     */
    public function linkLines(?string $stored): array
    {
        $lines = [];

        foreach (preg_split('/\R/', (string) $stored) ?: [] as $line) {
            $url = $this->safeUrl($line);

            if ($url !== null) {
                $lines[] = $url;
            }
        }

        return $lines;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            // An unrecognised tag loses its name but keeps what it holds, so a
            // pasted <font> or <section> still shows its words as plain text.
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeMedias(true)
            ->forceHttpsUrls(false)
            ->withMaxInputLength(200_000)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        foreach (self::DROPPED as $element) {
            $config = $config->dropElement($element);
        }

        foreach (self::TAGS as $element) {
            $config = $config->allowElement($element, []);
        }

        foreach (self::TAG_ATTRIBUTES as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}
