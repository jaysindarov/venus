# API Specification

## Overview
- **Base URL**: `https://yourdomain.com/api/v1`
- **Auth**: Bearer token (Sanctum) via `Authorization: Bearer {token}` header
- **Content-Type**: `application/json`
- **Versioning**: URL-based (`/api/v1/`)
- **Pagination**: Cursor-based for feeds, page-based for admin

## Response Envelope

### Success
```json
{
  "data": { ... },
  "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 98 }
}
```

### Error
```json
{
  "message": "Human-readable error message",
  "errors": { "field": ["Validation error detail"] },
  "code": "INSUFFICIENT_CREDITS"
}
```

### Error Codes
| Code | HTTP Status | Description |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Missing or invalid token |
| `FORBIDDEN` | 403 | Action not allowed for this user |
| `INSUFFICIENT_CREDITS` | 422 | Not enough credits to generate |
| `SUBSCRIPTION_REQUIRED` | 403 | Feature requires paid plan |
| `RATE_LIMITED` | 429 | Too many requests |
| `AI_PROVIDER_ERROR` | 502 | Upstream AI API failure |
| `VALIDATION_ERROR` | 422 | Input validation failed |

---

## Authentication Endpoints

### `POST /auth/register`
```json
// Request
{ "name": "Jane Doe", "email": "jane@example.com", "password": "secret123", "password_confirmation": "secret123" }

// Response 201
{ "data": { "user": { "id": "uuid", "name": "Jane Doe", "email": "jane@example.com" }, "token": "sanctum_token" } }
```

### `POST /auth/login`
```json
// Request
{ "email": "jane@example.com", "password": "secret123" }

// Response 200
{ "data": { "user": { ... }, "token": "sanctum_token" } }
```

### `POST /auth/logout`
> Auth required. Revokes current token.
```json
// Response 200
{ "message": "Logged out successfully" }
```

### `POST /auth/forgot-password`
```json
// Request
{ "email": "jane@example.com" }
// Response 200
{ "message": "Password reset link sent to your email." }
```

### `POST /auth/reset-password`
```json
// Request
{ "token": "reset_token", "email": "jane@example.com", "password": "newpassword", "password_confirmation": "newpassword" }
```

### `GET /auth/me`
> Returns authenticated user with subscription + credit info.
```json
// Response 200
{
  "data": {
    "id": "uuid",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "avatar_url": "https://...",
    "subscription": { "plan": "pro", "status": "active", "renews_at": "2024-02-01" },
    "credits": { "balance": 3450, "monthly_allowance": 4000, "resets_at": "2024-02-01" }
  }
}
```

---

## Generation Endpoints

### `POST /generations`
> Dispatches async generation job. Deducts credits immediately.

```json
// Request
{
  "model": "dall-e-3",           // Required: model slug
  "prompt": "A sunset over...", // Required: max 1000 chars
  "negative_prompt": "blurry",  // Optional
  "width": 1024,                // Optional: 512|768|1024|1536|2048
  "height": 1024,               // Optional
  "style_preset_id": 3,         // Optional
  "num_images": 1               // Optional: 1-4 (Pro+)
}

// Response 202 Accepted
{
  "data": {
    "id": "uuid",
    "status": "queued",
    "credits_cost": 2,
    "estimated_seconds": 15,
    "poll_url": "/api/v1/generations/uuid/status"
  }
}
```

### `GET /generations/{uuid}/status`
> Poll for generation status. Call every 2–3 seconds.

```json
// Response 200
{
  "data": {
    "id": "uuid",
    "status": "completed",     // queued | processing | completed | failed
    "image_url": "https://s3.../image.jpg",
    "thumbnail_url": "https://s3.../thumb.webp",
    "completed_at": "2024-01-15T10:23:45Z"
  }
}
```

### `GET /generations`
> Paginated list of current user's generations.

**Query params**: `?status=completed&page=1&per_page=20&sort=newest`

```json
// Response 200
{
  "data": [
    {
      "id": "uuid",
      "prompt": "A sunset...",
      "model": "dall-e-3",
      "status": "completed",
      "thumbnail_url": "https://...",
      "image_url": "https://...",
      "credits_cost": 2,
      "params": { "width": 1024, "height": 1024 },
      "created_at": "2024-01-15T10:23:45Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 10, "total": 184 }
}
```

### `GET /generations/{uuid}`
> Single generation detail.

### `DELETE /generations/{uuid}`
> Soft delete a generation (removes from gallery, frees storage async).

### `PATCH /generations/{uuid}/visibility`
```json
// Request
{ "is_public": true }
```

---

## Models Endpoint

### `GET /models`
> List of available AI models with their credit costs.

```json
// Response 200
{
  "data": [
    {
      "slug": "dall-e-3",
      "name": "DALL-E 3",
      "provider": "openai",
      "description": "High quality, accurate prompt following",
      "credits_per_image": { "512": 1, "1024": 2, "1792": 4 },
      "max_resolution": 1792,
      "supports_negative_prompt": false,
      "available_on_plans": ["basic", "pro", "creator"]
    }
  ]
}
```

---

## Credits Endpoints

### `GET /credits/balance`
```json
// Response 200
{
  "data": {
    "balance": 3450,
    "monthly_allowance": 4000,
    "used_this_month": 550,
    "resets_at": "2024-02-01T00:00:00Z"
  }
}
```

### `GET /credits/ledger`
> Paginated credit transaction history.

```json
// Response 200
{
  "data": [
    { "type": "confirm", "amount": -2, "balance_after": 3450, "description": "Image generation", "created_at": "..." },
    { "type": "grant", "amount": 4000, "balance_after": 4000, "description": "Monthly Pro credits", "created_at": "..." }
  ]
}
```

---

## Billing Endpoints

### `GET /billing/plans`
> Public. Returns all active plans.

### `POST /billing/subscribe`
```json
// Request
{ "plan_slug": "pro", "billing_period": "monthly", "payment_method_id": "pm_xxx" }

// Response 200
{ "data": { "subscription_id": "sub_xxx", "status": "active" } }
```

### `POST /billing/topup`
```json
// Request
{ "credits": 500, "payment_method_id": "pm_xxx" }
```

### `GET /billing/portal`
> Returns a Stripe Customer Portal URL for self-service billing management.

```json
// Response 200
{ "data": { "url": "https://billing.stripe.com/..." } }
```

### `GET /billing/invoices`
> List of past invoices via Cashier.

---

## Style Presets

### `GET /style-presets`
> Public endpoint. Returns active style presets.

---

## Admin Endpoints
> Require `admin` role. Prefix: `/api/v1/admin/`

### `GET /admin/users` — paginated user list with filters
### `GET /admin/users/{id}` — user detail with usage stats
### `PATCH /admin/users/{id}/ban` — ban/unban user
### `GET /admin/generations` — all generations with filters
### `GET /admin/stats` — dashboard metrics (DAU, revenue, generation count)
### `POST /admin/style-presets` — create style preset
### `PATCH /admin/style-presets/{id}` — update style preset
### `POST /admin/credits/adjust` — manually adjust user credits

---

## Webhook Endpoints (Internal)

### `POST /webhooks/stripe`
> Handles: `invoice.paid`, `customer.subscription.updated`, `customer.subscription.deleted`

### `POST /webhooks/replicate`
> Handles async generation completion from Replicate

### `POST /webhooks/fal`
> Handles async generation completion from fal.ai
