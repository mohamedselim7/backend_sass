<?php

namespace App\Providers;

use App\Models;
use App\Policies;
use App\Services\Ai\ContentGenerator;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use App\Services\Ai\Contracts\ImageProvider;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\ProviderResolver;
use App\Services\Payments\NullGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Model => policy map, registered explicitly rather than by convention. */
    public array $policies = [];

    public function register(): void
    {
        // The configured gateway is resolved once; adding a provider is a one-line change.
        $this->app->bind(PaymentGateway::class, fn () => match (config('payments.gateway')) {
            'paymob' => new PaymobGateway,
            'easykash' => new \App\Services\Payments\EasyKashGateway,
            default => new NullGateway,
        });

        // AI providers are resolved per-call (provider/model can vary per request or
        // per user), so the resolver itself is the singleton, not a fixed provider.
        $this->app->singleton(ProviderResolver::class);

        $this->app->bind(TextProvider::class, fn ($app) => $app->make(ProviderResolver::class)->text($app['request']?->user()));

        $this->app->bind(ImageProvider::class, fn ($app) => $app->make(ProviderResolver::class)->image($app['request']?->user()));

        $this->app->bind(ContentGeneratorInterface::class, ContentGenerator::class);
    }

    public function boot(): void
    {
        foreach ([
            Models\Brand::class => Policies\BrandPolicy::class,
            Models\ContentGeneration::class => Policies\ContentGenerationPolicy::class,
            Models\ContentPost::class => Policies\ContentPostPolicy::class,
            Models\Design::class => Policies\DesignPolicy::class,
            Models\Goal::class => Policies\GoalPolicy::class,
            Models\ScheduledPost::class => Policies\ScheduledPostPolicy::class,
            Models\SocialAccount::class => Policies\SocialAccountPolicy::class,
            Models\WorkspaceRecord::class => Policies\WorkspaceRecordPolicy::class,
            Models\MarketingAngle::class => Policies\MarketingAnglePolicy::class,
            Models\Prompt::class => Policies\PromptPolicy::class,
            Models\ChatThread::class => Policies\ChatThreadPolicy::class,
            Models\Image::class => Policies\ImagePolicy::class,
            Models\Media::class => Policies\MediaPolicy::class,
            Models\SupportThread::class => Policies\SupportThreadPolicy::class,
        ] as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Admins pass every gate check without each policy repeating the rule.
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        $this->configureVerificationEmail();

        $this->configurePasswordResetEmail();

        $this->configureRateLimits();
    }

    /**
     * The verification mail is branded (iden) and bilingual, and the link
     * points at the signed API route so the SPA can finish the flow.
     */
    private function configureVerificationEmail(): void
    {
        VerifyEmail::toMailUsing(function ($user) {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );

            $locale = in_array($user->locale, ['ar', 'en'], true) ? $user->locale : 'ar';

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject($locale === 'ar' ? 'تأكيد بريدك الإلكتروني' : 'Confirm your email address')
                ->view('emails.verify-email', [
                    'user' => $user,
                    'url' => $url,
                    'locale' => $locale,
                ]);
        });
    }

    /**
     * Branded reset mail (same look as the verification mail); the link opens
     * the SPA /reset-password page, which posts the token back to the API.
     */
    private function configurePasswordResetEmail(): void
    {
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = \App\Support\FrontendUrl::to('reset-password', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

            $locale = in_array($user->locale, ['ar', 'en'], true) ? $user->locale : 'ar';

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject($locale === 'ar' ? 'إعادة تعيين كلمة المرور' : 'Reset your password')
                ->view('emails.reset-password', [
                    'user' => $user,
                    'url' => $url,
                    'locale' => $locale,
                ]);
        });
    }

    private function configureRateLimits(): void
    {
        // Reset emails: 3 per 15 minutes per address and 10 per hour per IP.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(15, 3)->by('reset-email:'.strtolower((string) $request->input('email')))
                ->response(fn () => response()->json([
                    'message' => 'طلبت رابط استعادة كتير. استنى شوية وجرّب تاني.',
                    'error' => 'too_many_reset_requests',
                ], 429)),
            Limit::perHour(10)->by('reset-ip:'.$request->ip())
                ->response(fn () => response()->json([
                    'message' => 'طلبت رابط استعادة كتير. استنى شوية وجرّب تاني.',
                    'error' => 'too_many_reset_requests',
                ], 429)),
        ]);

        // Signed-in callers are limited per account, anonymous ones per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Credential guessing is limited on the email and the IP together.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('email:' . strtolower((string) $request->input('email'))),
            Limit::perMinute(20)->by('ip:' . $request->ip()),
        ]);

        // Generation endpoints are the expensive ones.
        RateLimiter::for('generate', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
