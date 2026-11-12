<?php

use App\Exceptions\PermanentDeleteDenied;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdmissionApplicant;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureStaffWorkspace;
use App\Http\Middleware\EnsureStudentPortal;
use App\Http\Middleware\ResolveSchoolContext;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        // Route model binding must run after the school context has been
        // resolved, otherwise the global school scope can use a stale context.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveSchoolContext::class,
        );

        $middleware->alias([
            'active.account' => EnsureAccountIsActive::class,
            'school.context' => ResolveSchoolContext::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'staff.workspace' => EnsureStaffWorkspace::class,
            'student.portal' => EnsureStudentPortal::class,
            'admission.applicant' => EnsureAdmissionApplicant::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(
            fn (Request $request) => route($request->user()?->homeRouteName() ?? 'dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    __('The given data was invalid.'),
                    422,
                    $e->errors(),
                    'validation_error'
                );
            }
        });

        // A refused permanent delete is a policy answer, not a crash: the admin
        // needs to be told which records are holding it.
        $exceptions->render(function (PermanentDeleteDenied $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 409, ['delete' => $e->reasons()], 'delete_blocked');
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Unauthenticated.'), 401, [], 'unauthenticated');
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : __('This action is unauthorized.'),
                    403,
                    [],
                    'forbidden'
                );
            }
        });

        // Laravel converts AuthorizationException to AccessDeniedHttpException
        // before custom renderers run, so both paths need the API envelope.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : __('This action is unauthorized.'),
                    403,
                    [],
                    'forbidden'
                );
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Resource not found.'), 404, [], 'not_found');
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(__('Resource not found.'), 404, [], 'not_found');
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    __('Too many requests. Please try again later.'),
                    429,
                    [],
                    'rate_limited'
                )->withHeaders($e->getHeaders());
            }
        });
    })->create();
