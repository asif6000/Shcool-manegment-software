<?php

namespace App\Providers;

use App\Services\TeachingScope;
use App\Support\ExtensionMimeTypeDetector;
use App\Support\ForeignKeyMap;
use App\Support\SchoolContext;
use App\View\Composers\AppShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\LocalFilesystemAdapter as LocalFilesystem;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchoolContext::class);
        // The foreign key graph is read from information_schema once per request
        // and shared by every delete the request performs.
        $this->app->singleton(ForeignKeyMap::class);
        // The timetable lookup behind a teacher's permissions is memoised for the
        // length of one request, so one instance has to serve the whole request.
        // ResolveSchoolContext clears it as each request begins.
        $this->app->singleton(TeachingScope::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer(
            ['layouts.app', 'layouts.applicant', 'layouts.guest'],
            AppShellComposer::class,
        );

        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        // Mirrors Laravel's local driver but supplies a MIME detector that does
        // not need ext-fileinfo, which is disabled on the production host.
        Storage::extend('school_local', function ($app, array $config): LocalFilesystem {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE,
            );

            $links = ($config['links'] ?? null) === 'skip'
                ? LocalAdapter::SKIP_LINKS
                : LocalAdapter::DISALLOW_LINKS;

            $adapter = new LocalAdapter(
                $config['root'],
                $visibility,
                $config['lock'] ?? LOCK_EX,
                $links,
                new ExtensionMimeTypeDetector,
            );

            $driver = new Flysystem($adapter, Arr::only($config, [
                'directory_visibility',
                'disable_asserts',
                'retain_visibility',
                'temporary_url',
                'url',
                'visibility',
            ]));

            return (new LocalFilesystem($driver, $adapter, $config))
                ->diskName('school_uploads')
                ->shouldServeSignedUrls($config['serve'] ?? false, fn () => $app['url']);
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perMinute(3)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('push-subscribe', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('admission-apply', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                'admission-apply|'.($request->user()?->getKey() ?? $request->ip())
            );
        });

        // Self-service signup is the only unauthenticated write endpoint, so it is
        // capped per address and per applicant.
        //
        // The identity cap has to key on the mobile number. It used to read
        // $request->input('email'), and the register form asks for a mobile
        // number and a password and never an email — so that lookup returned
        // null for every visitor and the whole site shared one bucket of five
        // attempts an hour. The sixth applicant anywhere, on any address, got a
        // 429 page that blamed them for the fifth.
        //
        // The number is normalised here because this middleware runs before
        // the FormRequest, which is what normally strips spaces and dashes.
        //
        // The address caps are looser than they look: a school's families reach
        // the site through a handful of carrier NAT gateways, so one address can
        // legitimately carry a whole intake day, and fifteen a day used to be
        // shared by everyone behind one mobile-broadband exit node.
        //
        // None of them count a request the server itself failed. While the
        // registration page was throwing, every attempt spent quota the
        // applicant could not see the reason for, and the daily cap locked a
        // genuine family out for twenty-four hours over somebody else's bug.
        RateLimiter::for('signup', function (Request $request): array {
            $ip = (string) $request->ip();
            $mobile = (string) preg_replace('/[\s\-]/', '', (string) $request->input('mobile_number'));

            $worthCounting = fn (SymfonyResponse $response): bool => $response->getStatusCode() < 500;

            return [
                Limit::perMinute(6)->by('signup-ip|'.$ip)->after($worthCounting),
                Limit::perDay(40)->by('signup-ip-daily|'.$ip)->after($worthCounting),
                Limit::perHour(5)->by('signup-mobile|'.$mobile)->after($worthCounting),
            ];
        });

        // The website polls live data every ~20s, so allow about ten concurrent
        // visitors behind one address before throttling.
        RateLimiter::for('website-live', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) $request->ip());
        });

        // Portal writes are per-account submissions; cap them so a broken form
        // or a scripted client cannot flood the teachers' review queues.
        RateLimiter::for('homework-submit', function (Request $request): Limit {
            return Limit::perMinute(10)->by('homework-submit|'.($request->user()?->getKey() ?? $request->ip()));
        });

        RateLimiter::for('student-leave', function (Request $request): Limit {
            return Limit::perMinute(5)->by('student-leave|'.($request->user()?->getKey() ?? $request->ip()));
        });

        // Profile pictures are stored on the public disk, so the rate is per
        // account rather than per address: enough for changing one's mind a few
        // times, not enough to fill the disk with scripted uploads.
        RateLimiter::for('profile-photo', function (Request $request): Limit {
            return Limit::perMinute(10)->by('profile-photo|'.($request->user()?->getKey() ?? $request->ip()));
        });

        // One scanner may mark a whole class in a minute, but a script must not
        // be able to hammer the endpoint with guessed card tokens.
        RateLimiter::for('attendance-scan', function (Request $request): Limit {
            return Limit::perMinute(60)->by('attendance-scan|'.($request->user()?->getKey() ?? $request->ip()));
        });

        // A terminal may flush a whole day of queued punches at once. The key
        // combines the device with the address so a leaked address cannot be
        // used to brute-force secrets by inventing new device IDs.
        RateLimiter::for('attendance-device', function (Request $request): array {
            return [
                Limit::perMinute(120)->by('attendance-device|'.$request->ip()),
                Limit::perMinute(120)->by('attendance-device-id|'.($request->header('X-Device-Id') ?: $request->ip())),
            ];
        });
    }
}
