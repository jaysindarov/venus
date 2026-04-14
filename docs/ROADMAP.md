# Development Roadmap

## Methodology
- **Sprint length**: 2 weeks
- **Estimation**: Story points (1 SP ≈ 0.5–1 day of focused work for 1 developer)
- **Priority**: MoSCoW (Must / Should / Could / Won't for this phase)

---

## Phase 0 — Foundation (Week 1–2) · ~10 SP

> **Goal**: Skeleton project running locally with CI passing. No features, just infrastructure.

### Tasks
- [ ] Initialize Laravel 11 project with Sail (Docker)
- [ ] Install & configure Inertia.js + Vue 3 + Vite
- [ ] Configure Tailwind CSS + base layout component
- [ ] Set up MySQL schema migrations structure
- [ ] Configure Redis (cache + queue driver)
- [ ] Set up GitHub repository + branch strategy (`main`, `develop`, `feature/*`)
- [ ] Set up GitHub Actions: run tests + lint on PR
- [ ] Configure `.env.example` with all required keys documented
- [ ] Set up Sentry (error tracking) SDK
- [ ] Set up Laravel Telescope (dev only)
- [ ] Deploy skeleton to staging server

### Deliverable
Empty app that deploys, passes CI, and connects to all services.

---

## Phase 1 — MVP (Week 3–10) · ~80 SP

> **Goal**: Paying users can register, subscribe, and generate images. End-to-end working product.

### Sprint 1 (Week 3–4): Auth & User Management · 18 SP
- [ ] User registration (email + password)
- [ ] Email verification
- [ ] Login / logout
- [ ] Password reset flow
- [ ] Google OAuth via Socialite
- [ ] User profile page (name, avatar, password change)
- [ ] Admin: user list, ban/unban, impersonate

### Sprint 2 (Week 5–6): Subscription & Credits · 20 SP
- [ ] Stripe integration with Laravel Cashier
- [ ] Pricing page (Free / Basic / Pro / Creator)
- [ ] Checkout flow (Stripe hosted or Elements)
- [ ] Webhook handler (subscription created/updated/cancelled)
- [ ] Credit ledger system (credits table, deduction on generation)
- [ ] Monthly credit reset via scheduled job
- [ ] Billing portal (Stripe Customer Portal redirect)
- [ ] Invoice page
- [ ] Credit top-up (one-time purchase)
- [ ] Low credit email notification (< 20% remaining)

### Sprint 3 (Week 7–8): AI Generation Core · 24 SP
- [ ] `ImageGenerationService` interface + provider abstraction
- [ ] OpenAI DALL-E 3 provider implementation
- [ ] Replicate provider implementation (SDXL / Flux)
- [ ] `GenerateImage` queued Job
- [ ] Webhook / polling for async generation status
- [ ] Generation model + migrations
- [ ] Credit deduction on job dispatch (reserve) + refund on failure
- [ ] Image storage to S3 via Media Library
- [ ] Thumbnail generation (400×400 WebP)
- [ ] Generation prompt history stored with params

### Sprint 4 (Week 9–10): Generator UI + Gallery · 18 SP
- [ ] Image generator page (prompt input, model selector, aspect ratio, style presets)
- [ ] Real-time generation status (polling + progress indicator)
- [ ] Generated image display + download button
- [ ] Generation history page (paginated gallery grid)
- [ ] Image detail modal (full-size view, metadata, download options)
- [ ] Error states (failed generation, insufficient credits)
- [ ] Dashboard (credits remaining, usage chart, recent generations)

### Phase 1 Deliverable
A deployable SaaS where users can sign up, subscribe, and generate images. Suitable for public beta.

---

## Phase 2 — Growth (Week 11–20) · ~90 SP

> **Goal**: Expand feature set to retain users and grow organic traffic.

### Image Editing Suite · 28 SP
- [ ] Image-to-image transformation
- [ ] Inpainting (mask + fill)
- [ ] Outpainting (expand canvas)
- [ ] Image upscaling (2x/4x via Real-ESRGAN)
- [ ] Background removal
- [ ] Canvas editor (Fabric.js or Konva)

### Community & Discovery · 22 SP
- [ ] Public gallery / explore feed (opt-in)
- [ ] Make generation public/private toggle
- [ ] Like / save other users' images
- [ ] Collections (folders to organize saved images)
- [ ] User public profile page
- [ ] Trending / new / top filters
- [ ] Meilisearch integration for prompt search

### Developer API · 18 SP
- [ ] API token management UI
- [ ] REST API: generate, status, history, credits
- [ ] API rate limiting (per plan tier)
- [ ] API documentation (Scribe or Swagger)
- [ ] Webhook support (notify on generation complete)

### Platform · 22 SP
- [ ] Style presets library (admin-managed prompt templates)
- [ ] Negative prompt support
- [ ] Batch generation (up to 4 images per prompt)
- [ ] Referral program (custom link, credit rewards)
- [ ] Admin analytics dashboard (revenue, DAU, generation count)

---

## Phase 3 — Scale (Week 21+)

> **Goal**: New verticals, team features, enterprise deals.

- [ ] Video generation (text-to-video, image-to-video)
- [ ] Team workspaces (shared credit pool, member roles)
- [ ] White-label API tier
- [ ] LoRA fine-tuning (custom model training on user images)
- [ ] Mobile app (Vue Native or Capacitor)
- [ ] Enterprise SSO (SAML/OIDC)
- [ ] SLA monitoring & status page

---

## Git Branch Strategy

```
main          ← production-ready code only, tagged releases
develop       ← integration branch, auto-deploys to staging
feature/*     ← individual feature branches, PR into develop
hotfix/*      ← emergency production fixes, PR into main + develop
release/*     ← release preparation branches
```

## Definition of Done (DoD)

A task is "done" when:
1. Feature works as specified
2. Unit tests written and passing
3. Feature test (happy path + key error paths) written and passing
4. No new Sentry errors in staging
5. PR reviewed and approved
6. Documentation updated if API changed
7. Merged to `develop` and staging deployment successful
