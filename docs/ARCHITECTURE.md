# System Architecture

## Overview

VisionaryAI uses a monolithic Laravel application with queue-based async AI generation. This is intentionally simple for MVP and can be decomposed into microservices as scale demands.

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENTS                              │
│   Browser (Vue/Inertia)    Mobile (Phase 3)    API Users    │
└────────────────┬────────────────────────────────────────────┘
                 │ HTTPS
┌────────────────▼───────────────────────────────────────────┐
│                    CLOUDFLARE CDN / WAF                     │
│              (DDoS, static assets, SSL termination)         │
└────────────────┬───────────────────────────────────────────┘
                 │
┌────────────────▼───────────────────────────────────────────┐
│                     NGINX + PHP-FPM                         │
│                  Laravel 11 Application                     │
│                                                             │
│  ┌──────────────┐  ┌─────────────┐  ┌──────────────────┐  │
│  │  Web Routes  │  │  API Routes │  │  Webhook Routes  │  │
│  │  (Inertia)   │  │  (/api/v1)  │  │  (Stripe,       │  │
│  └──────────────┘  └─────────────┘  │   Replicate)    │  │
│                                     └──────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                   Service Layer                       │  │
│  │  ImageGenerationService  SubscriptionService          │  │
│  │  CreditService           UserService                  │  │
│  └──────────────────────────────────────────────────────┘  │
└──────┬──────────────┬───────────────┬──────────────────────┘
       │              │               │
┌──────▼───┐  ┌───────▼──────┐  ┌───▼──────────────────────┐
│  MySQL 8 │  │   Redis 7    │  │   Laravel Queue Workers  │
│          │  │              │  │                          │
│  Primary │  │  Cache       │  │  ai_generation (x3)      │
│  + Read  │  │  Sessions    │  │  notifications (x1)      │
│  Replica │  │  Queues      │  │  default (x2)            │
│  (prod)  │  │  Rate limits │  └──────────┬───────────────┘
└──────────┘  └──────────────┘             │
                                           │ HTTP
              ┌────────────────────────────▼───────────────┐
              │              AI PROVIDERS                   │
              │                                             │
              │  OpenAI API    Replicate API    fal.ai      │
              │  (DALL-E 3)    (SDXL, Flux)    (fast inf.) │
              └─────────────────────────────────────────────┘

              ┌───────────────────────────────────────────┐
              │              FILE STORAGE                  │
              │  AWS S3 / Cloudflare R2                   │
              │  (Generated images, user uploads)          │
              └───────────────────────────────────────────┘
```

---

## Image Generation Flow

```
User submits prompt
        │
        ▼
POST /generate
        │
        ▼
GenerationController
  1. Validate request
  2. Check user credits (CreditService::canAfford)
  3. Reserve credits (status: reserved)
  4. Create Generation record (status: queued)
  5. Dispatch GenerateImageJob to Redis queue
  6. Return {generation_id, status: queued}
        │
        ▼ (async, queue worker)
GenerateImageJob
  1. Resolve AI provider (based on model param)
  2. Call provider API (may take 5–60s)
  3. On success:
     a. Download image from provider URL
     b. Store to S3 via MediaLibrary
     c. Create image variants (thumbnail, WebP)
     d. Update Generation: status=completed, media_id
     e. Confirm credit deduction (reserved → deducted)
     f. Fire GenerationCompleted event
  4. On failure:
     a. Update Generation: status=failed, error_message
     b. Refund reserved credits
     c. Fire GenerationFailed event

        │
        ▼ (frontend polls)
GET /generations/{id}/status
  → Returns {status, image_url, thumbnail_url}
  → Frontend displays result when status=completed
```

---

## Credit System Flow

```
Plan Subscription
    │
    ▼
Monthly credit grant (scheduler: 1st of month)
    │
    ▼
Credit Ledger (append-only log)
  ┌─────────────────────────────────────┐
  │ type: grant    | amount: +4000      │
  │ type: reserve  | amount: -2         │
  │ type: confirm  | amount: 0 (settle) │
  │ type: refund   | amount: +2         │
  │ type: topup    | amount: +500       │
  └─────────────────────────────────────┘
    │
    ▼
Current balance = SUM(amount) where user_id = X
```

---

## Directory Structure

```
app/
├── Console/
│   └── Commands/              # Artisan commands
├── Events/
│   ├── GenerationCompleted.php
│   └── GenerationFailed.php
├── Exceptions/
│   ├── InsufficientCreditsException.php
│   └── AIProviderException.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/              # Fortify / custom auth controllers
│   │   ├── Api/               # Versioned API controllers
│   │   │   └── V1/
│   │   ├── GenerationController.php
│   │   ├── GalleryController.php
│   │   ├── BillingController.php
│   │   └── Admin/
│   ├── Middleware/
│   │   ├── CheckCredits.php
│   │   └── EnsureSubscriptionActive.php
│   └── Requests/              # Form requests (validation)
│       ├── GenerateImageRequest.php
│       └── UpdateProfileRequest.php
├── Jobs/
│   └── GenerateImageJob.php
├── Listeners/
│   ├── SendGenerationNotification.php
│   └── LogGenerationActivity.php
├── Models/
│   ├── User.php
│   ├── Generation.php
│   ├── CreditLedger.php
│   ├── StylePreset.php
│   └── Plan.php
├── Services/
│   ├── AI/
│   │   ├── Contracts/
│   │   │   └── ImageGeneratorInterface.php
│   │   ├── OpenAIImageGenerator.php
│   │   ├── ReplicateImageGenerator.php
│   │   └── FalAIImageGenerator.php
│   ├── ImageGenerationService.php
│   ├── CreditService.php
│   └── SubscriptionService.php
├── Enums/
│   ├── GenerationStatus.php
│   ├── CreditTransactionType.php
│   └── PlanTier.php
└── Policies/
    ├── GenerationPolicy.php
    └── UserPolicy.php

resources/js/
├── components/
│   ├── ui/                    # Base UI components (Button, Modal, Input)
│   ├── generation/            # GeneratorForm, GenerationCard, StatusBadge
│   ├── gallery/               # GalleryGrid, ImageModal
│   └── billing/               # PricingCard, CreditMeter
├── pages/                     # Inertia page components
│   ├── Auth/
│   ├── Dashboard.vue
│   ├── Generate.vue
│   ├── Gallery.vue
│   ├── Billing/
│   └── Admin/
├── stores/
│   ├── auth.js
│   ├── generation.js
│   └── credits.js
├── composables/
│   ├── useGeneration.js       # Poll generation status
│   ├── useCredits.js
│   └── useToast.js
└── lib/
    ├── axios.js               # Configured Axios instance
    └── utils.js
```

---

## Caching Strategy

| Data | Cache Layer | TTL | Invalidation |
|---|---|---|---|
| User credit balance | Redis | 5 min | On ledger write |
| Style presets list | Redis | 24h | On admin update |
| Generation status | Redis | 1h | On status change |
| User session | Redis | 2h | On logout |
| Rate limit counters | Redis | Per window | Auto-expire |

---

## Rate Limiting

| Endpoint | Free | Basic | Pro | Creator |
|---|---|---|---|---|
| `POST /generate` | 5/min | 20/min | 60/min | 120/min |
| `GET /generations` | 60/min | 60/min | 120/min | 240/min |
| API token endpoints | 10/min | 30/min | 100/min | 500/min |
