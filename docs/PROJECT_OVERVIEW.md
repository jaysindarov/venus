# Project Overview

## Vision Statement

VisionaryAI is a credit-based AI image generation SaaS platform that gives creators, marketers, and designers access to multiple state-of-the-art AI models through a single, beautiful interface. Users pay for a subscription tier that grants monthly credits, which are consumed per generation.

---

## Target Audience

| Segment | Description | Primary Need |
|---|---|---|
| **Indie Creators** | Content creators, YouTubers, streamers | Fast thumbnails, social content |
| **Marketing Teams** | Agencies, in-house marketers | Ad visuals, campaign assets at scale |
| **Designers** | Freelancers, product designers | Rapid concept iteration |
| **Developers** | Building products on top of AI | API access, programmatic generation |
| **Hobbyists** | Art enthusiasts | Exploration, community sharing |

---

## Core Feature Set

### Phase 1 — MVP
- **Text-to-Image** generation with 2–3 AI models
- **Credit-based subscription** (Free, Basic, Pro tiers)
- **User authentication** (email/password + Google OAuth)
- **Generation history** with gallery view
- **Basic prompt editor** with style presets
- **Image download** (PNG/JPG/WebP)
- **Admin dashboard** (user management, usage stats)

### Phase 2 — Growth
- **Image-to-Image** transformation
- **Inpainting / outpainting** editor
- **Image upscaling** (2x, 4x)
- **Style presets library** (curated prompt templates)
- **Public gallery / community explore feed**
- **Collections** (user-created folders)
- **API access** for Pro+ users
- **Referral program**

### Phase 3 — Scale
- **Video generation** (text-to-video, image-to-video)
- **Background removal**
- **Bulk generation** (batch prompts)
- **Team workspaces** (shared credits, collaboration)
- **White-label / API reseller** tier
- **Model fine-tuning** (LoRA for custom styles)
- **Mobile app** (React Native or Flutter)

---

## Business Model

### Subscription Tiers

| Plan | Price | Monthly Credits | Target User |
|---|---|---|---|
| **Free** | $0 | 50 credits | Trial / exploration |
| **Basic** | $9/mo | 1,000 credits | Casual creators |
| **Pro** | $29/mo | 4,000 credits | Regular producers |
| **Creator** | $79/mo | 12,000 credits | Power users |
| **Enterprise** | Custom | Custom | Teams & agencies |

### Credit Costs (approximate)
- Standard image (512×512): 1 credit
- HD image (1024×1024): 2 credits
- Ultra image (2048×2048): 4 credits
- Image upscale: 1 credit
- Image-to-image: 2 credits

### Additional Revenue
- **Credit top-ups** (one-time purchase, no subscription required)
- **API access** (metered, per-credit billing for high-volume)
- **Affiliate / referral** (10% revenue share)

---

## Success Metrics (KPIs)

| Metric | 3-Month Target | 6-Month Target |
|---|---|---|
| Registered Users | 1,000 | 10,000 |
| Paying Subscribers | 50 | 500 |
| Monthly Recurring Revenue | $1,000 | $12,000 |
| Daily Active Users | 100 | 1,500 |
| Images Generated / Day | 500 | 8,000 |
| Churn Rate | < 10% | < 7% |

---

## Competitive Differentiation

1. **Model agnostic** — swap/add AI providers without user disruption
2. **Transparent credit system** — users always know what they'll spend
3. **Laravel + Vue** = fast iteration, maintainable codebase
4. **Developer-first API** — clean REST API from day one
5. **Privacy-first** — generations never used for model training
