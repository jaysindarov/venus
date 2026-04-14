# Technology Stack

## Decision Summary

All technology choices prioritize developer velocity (Laravel + Vue expertise), operational simplicity (avoid over-engineering at MVP stage), and a clear upgrade path as the product scales.

---

## Backend

### Laravel 11
- **Why**: Rapid API development, built-in queues, elegant ORM, strong ecosystem
- **Version**: Laravel 11.x (PHP 8.3+)
- **Key packages**:
  - `laravel/ai` — **Primary AI integration layer** (Laravel AI SDK, first-party)
  - `laravel/sanctum` — API token authentication
  - `laravel/socialite` — Google / GitHub OAuth
  - `laravel/cashier-stripe` — Subscription billing via Stripe
  - `spatie/laravel-permission` — RBAC (roles: user, admin, super_admin)
  - `spatie/laravel-media-library` — Image storage & conversions
  - `spatie/laravel-activitylog` — Audit log
  - `spatie/laravel-query-builder` — Filter/sort/paginate API queries
  - `league/flysystem-aws-s3-v3` — S3 file storage
  - `predis/predis` — Redis client
  - `laravel/horizon` — Queue monitoring dashboard
  - `laravel/telescope` — Debug/dev tooling (dev only)

### Database — MySQL 8.0
- Primary relational store for users, subscriptions, generations
- MySQL 8 JSON columns for flexible metadata (generation params)
- Read replica for analytics queries in production

### Cache & Queues — Redis 7
- Cache: session, model cache, rate limiting counters
- Queue driver: Redis with Horizon for monitoring
- Queue workers: separate pools per priority (`ai_generation`, `notifications`, `default`)

### Search — Meilisearch (Phase 2)
- Community gallery search (prompts, tags, styles)
- Laravel Scout integration

---

## AI Integration

### Primary: Laravel AI SDK (`laravel/ai`)
- **Why**: Official first-party Laravel package, Laravel-native API, built-in failover, testing fakes, queue integration — no custom HTTP client maintenance
- **Image generation providers supported**: OpenAI (DALL-E 3), Google Gemini Imagen, xAI (Grok Imagine)
- **MVP models**: DALL-E 3, Gemini Imagen, Grok Imagine (all via SDK)

### Extensibility Layer: Custom `ImageGeneratorInterface`
- **Why**: The SDK does not support every provider (Replicate, fal.ai, Stability AI are outside its current scope)
- **Pattern**: All generators — SDK and custom — implement the same interface. The rest of the app is completely decoupled from which tier handles a model
- **Future models**: Flux Dev, SDXL, Flux Schnell (Replicate / fal.ai) — add by implementing interface + one config entry. Zero changes to controllers or jobs.
- See `AI_INTEGRATION.md` for full implementation details

### Provider Tier Summary

| Tier | Integration | Models (MVP) | Models (Future) |
|---|---|---|---|
| **Tier 1 — SDK** | `laravel/ai` | DALL-E 3, Gemini Imagen, Grok Imagine | Any new model the SDK adds |
| **Tier 2 — Custom** | `ImageGeneratorInterface` + `Http::facade` | — (none at MVP) | Flux Dev, SDXL, Flux Schnell, etc. |

---

## Frontend

### Vue 3 + Inertia.js
- **Why Inertia**: Eliminates API layer for server-rendered pages, keeps Laravel routing, Vue for interactivity — the ideal Laravel + Vue stack
- **Build tool**: Vite
- **State management**: Pinia
- **Routing**: Inertia (server-side) + Vue Router (SPA sub-routes if needed)

### Key Frontend Packages
- `@inertiajs/vue3` — Inertia adapter
- `pinia` — State management
- `@vueuse/core` — Vue composition utilities
- `axios` — HTTP client (for non-Inertia API calls, e.g. polling)
- `tailwindcss` — Utility-first CSS
- `headlessui/vue` — Accessible UI primitives
- `@heroicons/vue` — Icon set
- `vue-toastification` — Toast notifications
- `@tanstack/vue-query` — Server state / data fetching (Phase 2)
- `konva` / `fabric.js` — Canvas-based image editor (Phase 2)

---

## Infrastructure

### Development
- **Docker** (Laravel Sail) — MySQL, Redis, Meilisearch in containers
- **Mailpit** — Email testing locally

### Production
- **Server**: DigitalOcean / Hetzner VPS (Ubuntu 22.04) or AWS EC2
- **Web server**: Nginx + PHP-FPM
- **Process manager**: Supervisor (queue workers, scheduler)
- **SSL**: Let's Encrypt (Certbot)
- **CDN**: Cloudflare (static assets, DDoS protection)
- **Object Storage**: AWS S3 or Cloudflare R2 (generated images)
- **Monitoring**: Sentry (errors), UptimeRobot (uptime), Laravel Horizon (queues)
- **Logs**: Laravel Log + Papertrail or Logtail

### CI/CD
- **GitHub Actions** — test, lint, deploy pipeline
- **Deployer PHP** or **Envoyer** — zero-downtime deployment

---

## Payment

- **Stripe** — subscriptions, one-time top-ups, customer portal
- **Laravel Cashier (Stripe)** — handles webhooks, invoices, trials
- **Stripe Billing Portal** — self-service plan changes, cancellation

---

## Email

- **Mailgun** or **AWS SES** for transactional email
- **Laravel Mail** + **Mailable** classes
- Emails: welcome, email verification, password reset, credit low warning, invoice

---

## Architecture Decision Records (ADRs)

### ADR-001: Inertia.js over full SPA
**Decision**: Use Inertia.js rather than decoupled Vue SPA + Laravel API
**Rationale**: Avoids duplication of routing logic, simpler auth (session-based), faster initial development. Can migrate to full API later if mobile app requires it.

### ADR-002: Credit system vs. direct API calls
**Decision**: Abstract all AI costs into a unified credit system
**Rationale**: Shields users from provider price volatility, enables easy plan changes, provides clear UX for consumption.

### ADR-003: Queue-based generation
**Decision**: All AI generation is queued and async, not synchronous HTTP
**Rationale**: AI API calls can take 5–30 seconds. Synchronous HTTP requests would timeout and degrade UX. Frontend polls for status updates.

### ADR-004: Laravel AI SDK as primary AI integration
**Decision**: Use `laravel/ai` (official Laravel AI SDK) as the primary integration layer for image generation
**Rationale**: First-party package with Laravel-native API, built-in failover across providers, official testing fakes, and queue integration out of the box. Eliminates maintaining custom HTTP clients for OpenAI, Gemini, and xAI. SDK is currently in beta but stabilizing rapidly; its expressive API aligns perfectly with Laravel conventions.

### ADR-005: ImageGeneratorInterface for future extensibility
**Decision**: Wrap the Laravel AI SDK behind a custom `ImageGeneratorInterface`, alongside any future non-SDK providers
**Rationale**: The SDK's image generation support is currently limited to OpenAI, Gemini, and xAI. Providers like Replicate and fal.ai (which host Flux, SDXL, and other open-source models critical for model variety) are not supported. The interface ensures zero controller/job changes are needed when adding new providers — only a new implementation class and a config entry.

### ADR-006: Spatie Media Library for image storage
**Decision**: Use `spatie/laravel-media-library` over custom storage logic
**Rationale**: Handles conversions (thumbnails), S3 integration, URL signing out of the box. Reduces significant custom code.
