# Spawn Cost Breakdown

Last updated: 2026-02-05

## Hetzner VPS Pricing (Current)

### New Generation (cpx*2 series) - EU Locations
| Server | vCPU | RAM | Storage | EU Monthly | Notes |
|--------|------|-----|---------|------------|-------|
| cpx22 | 2 | 4 GB | 80 GB | $6.99 | ✅ Available |
| cpx32 | 4 | 8 GB | 160 GB | $11.99 | ✅ Available |
| cpx42 | 8 | 16 GB | 320 GB | $21.99 | ✅ Available |

### Old Generation (cpx*1 series) - US Locations
| Server | vCPU | RAM | Storage | US Monthly | Notes |
|--------|------|-----|---------|------------|-------|
| cpx21 | 3 | 4 GB | 80 GB | $9.99 | ⚠️ Deprecated in EU |
| cpx31 | 4 | 8 GB | 160 GB | $17.99 | ⚠️ Deprecated in EU |
| cpx41 | 8 | 16 GB | 240 GB | $33.49 | ⚠️ Deprecated in EU |

**Recommendation**: Use EU locations (fsn1, nbg1, hel1) with new cpx*2 series for best pricing.

## Claude API Pricing (Pass-Through)

We pass through AI costs at cost. No markup on credits.

| Model | Input | Output | Typical Turn |
|-------|-------|--------|--------------|
| Claude Opus 4.5 | $15/MTok | $75/MTok | ~$0.075 |

With $10 credits: ~133 turns/month average

## Transaction Costs

Stripe: 2.9% + $0.30 per transaction

| Price Point | Stripe Fee | Net Received |
|-------------|------------|--------------|
| $20/month | $0.88 | $19.12 |
| $25/month | $1.03 | $23.97 |
| $50/month | $1.75 | $48.25 |
| $100/month | $3.20 | $96.80 |

## Tier Analysis

### Option A: Current Pricing ($25/$50/$100)

| Tier | Price | VPS (EU) | Credits | Stripe | **Margin** |
|------|-------|----------|---------|--------|------------|
| Starter | $25 | $6.99 | $10 | $1.03 | **$6.98** (28%) |
| Pro | $50 | $11.99 | $20 | $1.75 | **$16.26** (33%) |
| Business | $100 | $21.99 | $40 | $3.20 | **$34.81** (35%) |

### Option B: $20 Starter Pricing ($20/$50/$100)

| Tier | Price | VPS (EU) | Credits | Stripe | **Margin** |
|------|-------|----------|---------|--------|------------|
| Starter | $20 | $6.99 | $10 | $0.88 | **$2.13** (11%) |
| Pro | $50 | $11.99 | $20 | $1.75 | **$16.26** (33%) |
| Business | $100 | $21.99 | $40 | $3.20 | **$34.81** (35%) |

## $20 Starter Viability Analysis

**Can we do $20/month?** Yes, but margins are thin.

Breakdown:
- Customer pays: $20.00
- Stripe takes: -$0.88
- VPS costs: -$6.99
- Credits included: -$10.00
- **Net margin: $2.13/month (10.6%)**

### Considerations

**Pros:**
- Clean $20 price point (psychological)
- Lower barrier to entry
- Pro/Business tiers have healthy margins
- Extra credit purchases have margin too

**Cons:**
- Very thin margin on Starter
- No buffer for support time
- One bad month (chargebacks, etc.) wipes margin

### Alternative: Reduce Starter Credits

| Scenario | Price | VPS | Credits | Margin |
|----------|-------|-----|---------|--------|
| $20 / $10 credits | $20 | $6.99 | $10 | $2.13 (11%) |
| $20 / $5 credits | $20 | $6.99 | $5 | $7.13 (36%) |
| $20 / $7 credits | $20 | $6.99 | $7 | $5.13 (26%) |

$20 with $5-7 credits gives healthier margins while keeping the price point.

## Other Costs (Not Monthly)

- Domain registration: ~$12-15/year (passed to customer with markup)
- Domain renewal: ~$12-15/year (passed to customer with markup)
- SSL: Free (Let's Encrypt)
- Setup/provisioning: Automated (no marginal cost)

## Recommendation

**For $20 Starter tier to work profitably:**

1. Use EU servers (cpx22 @ $6.99)
2. Consider reducing included credits to $5-7
3. OR accept thin margins on Starter, knowing Pro/Business have healthy margins
4. Extra credit purchases provide additional margin

The $20 price point is ACHIEVABLE but requires either:
- Lower included credits, OR
- Acceptance of ~10% margin (viable if volume is high)

---

*Single source of truth: Update this document when costs change.*
