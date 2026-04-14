# Security Guide

## Authentication

### Session Auth (Web)
- Laravel Sanctum SPA authentication
- CSRF protection on all state-changing requests
- Session stored in Redis (not file-based)
- `HttpOnly` and `Secure` cookie flags in production
- Session timeout: 2 hours of inactivity

### API Token Auth (Developer API)
- Sanctum personal access tokens
- Token scopes: `generate`, `read`, `admin`
- Tokens hashed in database (never stored in plain text)
- Token rotation: users can revoke/regenerate anytime
- Rate limited per plan tier (see Architecture doc)

### OAuth (Google)
- Socialite handles OAuth flow
- State parameter validated to prevent CSRF
- Account linking: match on verified email only

---

## Authorization (RBAC)

Using `spatie/laravel-permission`:

```
Roles:
  user        — Standard subscriber
  admin       — Can access admin dashboard, manage users
  super_admin — Full access including billing adjustments

Permissions (auto-assigned by role):
  generate:images     — Submit generation jobs
  view:own-gallery    — See own generations
  view:public-gallery — Browse public explore feed (Phase 2)
  manage:users        — Admin: list, ban, impersonate users
  manage:presets      — Admin: CRUD style presets
  adjust:credits      — Super admin: manual credit adjustments
  view:metrics        — Admin: analytics dashboard
```

```php
// Usage in controllers
$this->authorize('generate:images');  // Throws 403 if unauthorized

// Usage in policies
public function delete(User $user, Generation $generation): bool
{
    return $user->id === $generation->user_id;  // Own content only
}
```

---

## Input Validation

All inputs go through `FormRequest` classes before reaching controllers:

```php
// app/Http/Requests/GenerateImageRequest.php

public function rules(): array
{
    return [
        'model'           => ['required', 'string', Rule::in(array_keys(config('ai.models')))],
        'prompt'          => ['required', 'string', 'min:3', 'max:1000'],
        'negative_prompt' => ['nullable', 'string', 'max:500'],
        'width'           => ['nullable', 'integer', Rule::in([512, 768, 1024, 1536, 1792, 2048])],
        'height'          => ['nullable', 'integer', Rule::in([512, 768, 1024, 1536, 1792, 2048])],
        'style_preset_id' => ['nullable', 'integer', 'exists:style_presets,id'],
    ];
}
```

**Never** pass raw request data to models or external APIs — always use `$request->validated()`.

---

## Content Moderation

Prompt moderation pipeline before sending to AI provider:

```php
// app/Services/ContentModerationService.php

public function isSafe(string $prompt): bool
{
    // Level 1: Simple keyword blocklist (fast, no API call)
    if ($this->containsBlockedTerms($prompt)) {
        return false;
    }

    // Level 2: OpenAI Moderation API (async, only if passes level 1)
    $response = Http::withToken(config('services.openai.key'))
        ->post('https://api.openai.com/v1/moderations', ['input' => $prompt])
        ->json();

    return !$response['results'][0]['flagged'];
}
```

If moderation fails: generation is rejected, credits not deducted, user sees clear message.

---

## Rate Limiting

```php
// app/Http/Middleware/ThrottleGenerations.php
// Also configured in routes/api.php

Route::middleware(['auth:sanctum', 'throttle:generation'])->group(function () {
    Route::post('/generations', [GenerationController::class, 'store']);
});

// config/throttle.php or RouteServiceProvider
RateLimiter::for('generation', function (Request $request) {
    $limits = [
        'free'    => Limit::perMinute(3),
        'basic'   => Limit::perMinute(10),
        'pro'     => Limit::perMinute(30),
        'creator' => Limit::perMinute(60),
    ];
    $plan = $request->user()?->subscription_plan ?? 'free';
    return $limits[$plan] ?? Limit::perMinute(3);
});
```

---

## Stripe Webhook Security

```php
// app/Http/Middleware/VerifyStripeWebhook.php

public function handle(Request $request, Closure $next): Response
{
    $signature = $request->header('Stripe-Signature');
    $secret    = config('cashier.webhook.secret');

    try {
        \Stripe\Webhook::constructEvent(
            $request->getContent(), $signature, $secret
        );
    } catch (\Exception $e) {
        abort(400, 'Invalid webhook signature');
    }

    return $next($request);
}
```

---

## File Storage Security

- Generated images stored in **private S3 bucket**
- Access via **signed URLs** (expire in 1 hour for display, 5 minutes for download)
- Never expose direct S3 URLs to clients
- Images served through Laravel's media library signed URL generation

```php
// Get temporary signed URL
$url = $generation->getFirstMedia('generations')
    ->getTemporaryUrl(now()->addHour());
```

---

## Data Privacy

- User data: GDPR-compliant data export via `GET /api/v1/account/export`
- Account deletion: cascade-deletes generations, media files from S3, Stripe customer
- Generations: private by default, users opt-in to make public
- Prompts are stored but never used for AI training
- Logs stripped of PII before shipping to log aggregator

---

## Security Headers (Nginx)

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com; frame-src https://js.stripe.com; img-src 'self' data: https://*.amazonaws.com https://*.r2.cloudflarestorage.com; font-src 'self' data:;" always;
```

---

## Secrets Management

- All secrets in `.env` — **never** committed to git
- `.env.example` contains all keys with empty values + comments explaining each
- Production secrets stored in server environment variables (not `.env` file)
- Rotate keys: OpenAI key, Stripe keys, app key quarterly or on suspected breach
- Use GitHub Secrets for CI/CD pipeline credentials

---

## Security Checklist (Pre-Launch)

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] All external URLs use HTTPS
- [ ] Stripe webhook signature verification enabled
- [ ] S3 bucket is private (no public ACL)
- [ ] CORS configured to allow only your domain
- [ ] Admin routes protected by role middleware
- [ ] Laravel Telescope disabled in production
- [ ] Error messages don't leak stack traces to API responses
- [ ] SQL queries use parameter binding (Eloquent handles this)
- [ ] File uploads validated for type + size before processing
- [ ] Rate limiting enabled on all public-facing endpoints
