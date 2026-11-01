# CirilInfo – Digital Services Marketplace on WordPress

A WordPress + WooCommerce platform for **CirilInfo** (web development & digital marketing): visitors browse services,
compare Basic/Professional/Enterprise packages, request quotes, register, pay online (Razorpay and other Indian gateways),
and track their projects; the team runs everything from one admin dashboard.

```
wp-content/
  plugins/cirilinfo-core/   ← all business logic (CPTs, pricing, cart, checkout, projects, tickets, invoices, ledger, SEO, email…)
  themes/cirilinfo/         ← presentation only (templates, CSS, JS, self-hosted font) – replaceable
docs/INSTALL.md             ← install, payment gateways, SMTP, deployment, backup & recovery
docs/TESTING.md             ← what was verified, manual acceptance checklist
config/                     ← wp-config additions, Nginx/Apache snippets
bin/setup-local.sh + docker-compose.yml   ← one-command local stack
tests/pricing-test.php      ← dependency-free unit tests (php tests/pricing-test.php)
```

WordPress core, WooCommerce and the gateway plugins are **not** committed – install them normally (see `docs/INSTALL.md`).

## Architecture

| Concern | Where |
|---|---|
| Services, packages, portfolio, testimonials, FAQs, clients, banners, team | CPTs in `class-post-types.php`, edited through `class-meta-boxes.php` |
| Pricing maths (discounts, terms, add-ons, GST, ₹ formatting) | `class-pricing.php` – pure PHP, unit-tested |
| Package ⇄ WooCommerce | `class-packages.php` syncs each package to a **hidden virtual product**; `class-cart.php` re-prices every cart line **server-side** from the database (the browser can never set a price) |
| Checkout extras (requirements, GSTIN, opt-in), GST ⇄ WooCommerce tax table | `class-checkout.php` |
| Order → Project workflow, statuses, milestones, deliverables, messages | `class-projects.php` |
| Payment ledger (de-duplicated, duplicate-payment alerts, refunds, reports, CSV) | `class-transactions.php` |
| GST tax invoices, sequential numbers, CGST/SGST vs IGST | `class-invoices.php` |
| Customer dashboard (projects, payments, invoices, tickets, enquiries, notifications), email verification | `class-account.php` |
| Enquiry / quote / ticket / requirements / feedback / newsletter forms | `class-forms.php` (+ `class-uploads.php` private files) |
| Email templates + SMTP | `class-mailer.php`, `class-settings.php` |
| SEO, schema, sitemap, robots, GA4/Meta Pixel/consent, breadcrumbs | `class-seo.php` |
| Admin overview, setup/health check | `class-admin.php` |
| Roles (`ci_manager`, `ci_team`), team scoping | `class-roles.php` |

### Payments – how it works (important)
Payment capture, signature verification and webhooks are handled by the **official gateway plugins**
(*Razorpay for WooCommerce*, Cashfree, PayU). They call WooCommerce’s `payment_complete()`; CirilInfo reacts to those
events: it records every attempt in its own ledger (unique per order + reference, so **duplicate payments are detected
and flagged**), issues the invoice number, emails customer/admin, advances the project, and supports refunds made from the
WooCommerce order screen. No card/CVV/UPI PIN ever touches this code, and no secret keys appear in front-end code.

## Quick start
```bash
docker compose up -d && ./bin/setup-local.sh     # http://localhost:8080  (admin/admin – local only)
php tests/pricing-test.php
```
Production: see **docs/INSTALL.md**. Then `wp-admin → CirilInfo → Setup` and run the three guided steps.

## Pricing is data, not code
All prices in the seeded catalogue are **indicative placeholders** (the four examples from the brief use the figures you gave;
the rest are suggestions). Edit under *CirilInfo → Packages*; GST under *CirilInfo → Settings → GST & Invoices*.

## Known limitations (read before launch)
* **Recurring billing is prepaid-term, not auto-debit.** Monthly/yearly packages are sold for a chosen number of periods
  (1/3/6/12 months, 1–3 years). There are no automatic renewals; use a renewal order or add a subscriptions plugin.
* **Invoices are printable HTML** (browser *Save as PDF*), not server-generated PDFs.
* Legal pages (Terms, Privacy, Refund) are templates – have them reviewed. Demo testimonials/portfolio are clearly labelled `[SAMPLE]`.
* The classic `[woocommerce_checkout]` shortcode is used (not the Checkout block) – required for the custom checkout fields.
* See `docs/TESTING.md` for exactly what has and has not been verified in a live WooCommerce + gateway environment.
