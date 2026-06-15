<?php

namespace App\Http;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AnonymousModeMiddleware;
use App\Http\Middleware\AuditAdminActions;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CounselorMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\LogSlowHttpRequests;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RequireCounselorTwoFactor;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StudentMiddleware;
use App\Http\Middleware\TrackDeviceSession;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyIntegrationSignature;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    protected $middleware = [
        TrustProxies::class,
        ForceHttps::class,
        HandleCors::class,
        SecurityHeaders::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
            LogSlowHttpRequests::class,
        ],
    ];

    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'role' => RoleMiddleware::class,
        'admin' => AdminMiddleware::class,
        'counselor' => CounselorMiddleware::class,
        'student' => StudentMiddleware::class,
        'anonymous' => AnonymousModeMiddleware::class,
        'track.device_session' => TrackDeviceSession::class,
        'session.timeout' => EnforceSessionTimeout::class,
        'audit.admin' => AuditAdminActions::class,
        'audit.access' => AuditDataAccess::class,
        'counselor.2fa' => RequireCounselorTwoFactor::class,
        'verify.integration.signature' => VerifyIntegrationSignature::class,
    ];
}
