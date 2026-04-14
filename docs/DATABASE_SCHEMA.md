# Database Schema

## Conventions
- All tables use `snake_case`
- Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT` named `id`
- Timestamps: `created_at`, `updated_at` on all tables
- Soft deletes: `deleted_at` where noted
- UUID column added to user-facing resources (for public URLs)
- JSON columns for flexible metadata (MySQL 8.0+)

---

## Entity Relationship Overview

```
users ──< generations ──< generation_media
  │
  ├──< credit_ledgers
  ├──< subscriptions (via Cashier)
  ├──< api_tokens
  └──< collections ──< collection_items >── generations
```

---

## Table Definitions

### `users`
```sql
id                  BIGINT UNSIGNED PK
uuid                CHAR(36) UNIQUE NOT NULL
name                VARCHAR(255)
email               VARCHAR(255) UNIQUE NOT NULL
email_verified_at   TIMESTAMP NULL
password            VARCHAR(255) NULL          -- null for OAuth-only users
avatar_url          VARCHAR(500) NULL
role                ENUM('user','admin','super_admin') DEFAULT 'user'
stripe_id           VARCHAR(255) NULL INDEX    -- Cashier
pm_type             VARCHAR(255) NULL
pm_last_four        VARCHAR(4) NULL
trial_ends_at       TIMESTAMP NULL
is_banned           TINYINT(1) DEFAULT 0
banned_reason       TEXT NULL
last_active_at      TIMESTAMP NULL
created_at, updated_at
deleted_at          TIMESTAMP NULL             -- soft delete
```

### `social_accounts`
```sql
id                  BIGINT UNSIGNED PK
user_id             BIGINT UNSIGNED FK → users.id
provider            VARCHAR(50)                -- 'google', 'github'
provider_user_id    VARCHAR(255)
token               TEXT NULL
refresh_token       TEXT NULL
UNIQUE(provider, provider_user_id)
created_at, updated_at
```

### `plans`
```sql
id                  BIGINT UNSIGNED PK
slug                VARCHAR(50) UNIQUE          -- 'free', 'basic', 'pro', 'creator'
name                VARCHAR(100)
monthly_credits     INT UNSIGNED
stripe_monthly_id   VARCHAR(255) NULL           -- Stripe Price ID
stripe_yearly_id    VARCHAR(255) NULL
monthly_price       DECIMAL(10,2) DEFAULT 0
yearly_price        DECIMAL(10,2) DEFAULT 0
features            JSON                        -- {"api_access": true, "priority_queue": true}
max_resolution      INT UNSIGNED DEFAULT 1024
is_active           TINYINT(1) DEFAULT 1
sort_order          TINYINT UNSIGNED DEFAULT 0
created_at, updated_at
```

### `subscriptions` (managed by Laravel Cashier)
```sql
id                  BIGINT UNSIGNED PK
user_id             BIGINT UNSIGNED FK → users.id
type                VARCHAR(255)               -- 'default'
stripe_id           VARCHAR(255) UNIQUE
stripe_status       VARCHAR(255)
stripe_price        VARCHAR(255) NULL
quantity            INT NULL
trial_ends_at       TIMESTAMP NULL
ends_at             TIMESTAMP NULL
created_at, updated_at
```

### `credit_ledgers`
```sql
id                  BIGINT UNSIGNED PK
user_id             BIGINT UNSIGNED FK → users.id INDEX
generation_id       BIGINT UNSIGNED FK → generations.id NULL
type                ENUM('grant','reserve','confirm','refund','topup','manual_adjust')
amount              INT NOT NULL               -- positive = add, negative = deduct
balance_after       INT UNSIGNED               -- running total snapshot
description         VARCHAR(255) NULL
metadata            JSON NULL                  -- e.g. {"plan":"pro","period":"2024-01"}
created_at          TIMESTAMP
-- No updated_at: ledger is append-only, never modified
INDEX(user_id, created_at)
```

### `generations`
```sql
id                  BIGINT UNSIGNED PK
uuid                CHAR(36) UNIQUE NOT NULL   -- public-facing ID
user_id             BIGINT UNSIGNED FK → users.id INDEX
model               VARCHAR(100)               -- 'dall-e-3', 'sdxl', 'flux-dev'
provider            VARCHAR(50)                -- 'openai', 'replicate', 'fal'
prompt              TEXT NOT NULL
negative_prompt     TEXT NULL
params              JSON                       -- {width, height, steps, cfg_scale, seed, style_preset}
status              ENUM('queued','processing','completed','failed') DEFAULT 'queued' INDEX
credits_cost        TINYINT UNSIGNED DEFAULT 2
provider_job_id     VARCHAR(255) NULL          -- External job ID for polling
error_message       TEXT NULL
is_public           TINYINT(1) DEFAULT 0 INDEX
views_count         INT UNSIGNED DEFAULT 0
likes_count         INT UNSIGNED DEFAULT 0
completed_at        TIMESTAMP NULL
created_at, updated_at
INDEX(user_id, status)
INDEX(is_public, created_at)
```

### `generation_media` (via Spatie Media Library — `media` table)
> Spatie creates its own `media` table. Generations are the "model" owner.
> Conversions registered: `thumbnail` (400×400 WebP), `preview` (800px WebP), `full` (original)

### `style_presets`
```sql
id                  BIGINT UNSIGNED PK
slug                VARCHAR(100) UNIQUE
name                VARCHAR(100)
description         TEXT NULL
prompt_suffix       TEXT                       -- appended to user prompt
negative_prompt     TEXT NULL
thumbnail_url       VARCHAR(500) NULL
category            VARCHAR(50)                -- 'artistic', 'photographic', 'anime', etc.
is_active           TINYINT(1) DEFAULT 1
sort_order          SMALLINT UNSIGNED DEFAULT 0
created_at, updated_at
```

### `collections`
```sql
id                  BIGINT UNSIGNED PK
uuid                CHAR(36) UNIQUE
user_id             BIGINT UNSIGNED FK → users.id
name                VARCHAR(255)
description         TEXT NULL
is_public           TINYINT(1) DEFAULT 0
cover_generation_id BIGINT UNSIGNED FK → generations.id NULL
items_count         INT UNSIGNED DEFAULT 0    -- denormalized counter
created_at, updated_at
deleted_at
```

### `collection_items`
```sql
id                  BIGINT UNSIGNED PK
collection_id       BIGINT UNSIGNED FK → collections.id
generation_id       BIGINT UNSIGNED FK → generations.id
added_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
UNIQUE(collection_id, generation_id)
```

### `generation_likes`
```sql
id                  BIGINT UNSIGNED PK
user_id             BIGINT UNSIGNED FK → users.id
generation_id       BIGINT UNSIGNED FK → generations.id
created_at          TIMESTAMP
UNIQUE(user_id, generation_id)
```

### `api_tokens` (via Laravel Sanctum — `personal_access_tokens` table)
> Sanctum creates its own table. Tokens are scoped: `['generate', 'read', 'admin']`

### `activity_log` (via Spatie Activity Log)
> Auto-managed. Logs: login, generation, plan change, admin actions.

---

## Key Migration Order

1. `users`
2. `social_accounts`
3. `plans`
4. `subscriptions` (Cashier migration)
5. `personal_access_tokens` (Sanctum migration)
6. `credit_ledgers`
7. `generations`
8. `media` (Spatie Media Library migration)
9. `style_presets`
10. `collections`
11. `collection_items`
12. `generation_likes`
13. `activity_log` (Spatie migration)

---

## Indexes Summary

Critical indexes for query performance:

```sql
-- Generation listing (user gallery)
INDEX generations(user_id, status, created_at DESC)

-- Public explore feed
INDEX generations(is_public, status, created_at DESC)
INDEX generations(is_public, likes_count DESC)  -- trending

-- Credit balance calculation
INDEX credit_ledgers(user_id)

-- Admin user search
INDEX users(email)
INDEX users(stripe_id)
```
