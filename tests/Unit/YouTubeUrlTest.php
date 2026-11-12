<?php

namespace Tests\Unit;

use App\Support\YouTubeUrl;
use PHPUnit\Framework\TestCase;

class YouTubeUrlTest extends TestCase
{
    public static function linkProvider(): array
    {
        return [
            'watch query' => ['https://www.youtube.com/watch?v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'share link' => ['https://youtu.be/AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'share link with tracking' => ['https://youtu.be/AbCdEfGhIjK?si=xyz123&t=42', 'AbCdEfGhIjK'],
            'embed path' => ['https://www.youtube.com/embed/AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'shorts' => ['https://www.youtube.com/shorts/AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'live' => ['https://www.youtube.com/live/AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'mobile host' => ['https://m.youtube.com/watch?v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'music host' => ['https://music.youtube.com/watch?v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'nocookie embed' => ['https://www.youtube-nocookie.com/embed/AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'no scheme' => ['youtube.com/watch?v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'http scheme' => ['http://www.youtube.com/watch?v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'query first' => ['https://www.youtube.com/watch?app=desktop&v=AbCdEfGhIjK', 'AbCdEfGhIjK'],
            'trailing slash' => ['https://youtu.be/AbCdEfGhIjK/', 'AbCdEfGhIjK'],
        ];
    }

    /**
     * @dataProvider linkProvider
     */
    public function test_a_youtube_link_yields_its_video_id(string $url, string $expected): void
    {
        $this->assertSame($expected, YouTubeUrl::videoId($url));
    }

    public static function rejectedProvider(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'plain words' => ['my lesson video'],
            'other site' => ['https://vimeo.com/12345678'],
            'other site wearing the name' => ['https://youtube.com.evil.test/watch?v=AbCdEfGhIjK'],
            'subdomain of an attacker' => ['https://evil.youtube.com.example.org/watch?v=AbCdEfGhIjK'],
            'channel page' => ['https://www.youtube.com/@dreamrootschool'],
            'playlist without a video' => ['https://www.youtube.com/playlist?list=PLaaaaaaaaaaaa'],
            'homepage' => ['https://www.youtube.com/'],
            'id too short' => ['https://youtu.be/AbCdEf'],
            'id with a bad character' => ['https://youtu.be/AbCdEfGhIj.!' /* 13 chars but illegal */],
            'credentials in the url' => ['https://user:pass@www.youtube.com/watch?v=AbCdEfGhIjK'],
            'odd port' => ['https://www.youtube.com:8080/watch?v=AbCdEfGhIjK'],
            'file scheme' => ['file:///www.youtube.com/watch?v=AbCdEfGhIjK'],
        ];
    }

    /**
     * @dataProvider rejectedProvider
     */
    public function test_a_link_that_is_not_one_playable_video_is_turned_into_nothing(?string $url): void
    {
        $this->assertNull(YouTubeUrl::videoId($url));
    }

    public function test_the_embed_is_built_from_the_id_alone(): void
    {
        $id = YouTubeUrl::videoId('https://www.youtube.com/watch?v=AbCdEfGhIjK&list=PLsecret');

        $this->assertSame('https://www.youtube.com/embed/AbCdEfGhIjK?rel=0', YouTubeUrl::embedUrl($id));
        $this->assertSame('https://www.youtube.com/watch?v=AbCdEfGhIjK', YouTubeUrl::watchUrl($id));
        $this->assertSame('https://img.youtube.com/vi/AbCdEfGhIjK/hqdefault.jpg', YouTubeUrl::thumbnailUrl($id));
    }
}
