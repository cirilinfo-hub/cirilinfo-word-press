# Testing & verification report

## Automated
* `php tests/pricing-test.php` – 24 unit tests (discount rules, term clamping, add-ons, GST maths, CGST/SGST split, ₹ formatting). **Pass.**
* `php -l` on every PHP file, `node --check` on all JS. **Pass.**
* WordPress Coding Standards, security sniffs (EscapeOutput, NonceVerification, ValidatedSanitizedInput, PreparedSQL, DevelopmentFunctions): **clean** (one documented false positive).

## Verified on a real WordPress 7.1 + MariaDB 10.11 instance (WP_DEBUG on, debug.log clean)
Plugin activation (tables, roles, private upload dir) · seeding (11 pages, 3 menus, 14 services, 42 packages, 10 FAQs) ·
all public pages load (home, about, services, pricing, portfolio, blog, contact, FAQ, service detail, legal, search, 404) ·
valid JSON-LD (Organization, WebSite, FAQPage, Service, Breadcrumb), meta/OG tags, robots.txt rules, sitemap, noindex rules ·
contact/quote form pipeline over HTTP (nonce, honeypot, time-trap, validation, rate-limit, JSON + no-JS) ·
secure uploads (PDF accepted; PHP and PHP-disguised-as-JPG rejected; randomised names; authenticated download with `nosniff`/`attachment`; anonymous download redirected to login) ·
admin screens for every CPT + Overview/Settings/Setup/Marketing (no PHP notices) · meta-box saving and sanitisation ·
project status/milestone/assignment/message logic · role scoping (team member limited to assigned projects; customers/other users denied) ·
private post types absent from REST and front-end · user-enumeration blocking · security headers · AES settings encryption round-trip · newsletter double opt-in records.

## NOT verified here – you must test in staging
The build sandbox could not download WooCommerce or any payment gateway (WordPress.org is blocked there), so these paths are
**written against WooCommerce’s documented API but have not been executed**:
* package → hidden product sync, add-to-cart with add-ons/term, server-side re-pricing, cart/checkout display, coupons
* GST in the WooCommerce tax table; checkout custom fields (requirements, GSTIN, opt-in); order → project creation
* My Account endpoints (dashboard, projects, payments, invoices, tickets, enquiries), email verification login block
* payment ledger hooks, invoice rendering with real orders, refunds, failed-payment flow
* Razorpay sandbox payment and webhook (needs your keys)
* delivery of real email (needs SMTP)

### Staging acceptance checklist
1. Fresh install per `docs/INSTALL.md`; Setup → Health check all green.
2. Service page: choose Professional + add-on + (for monthly) 3 months → cart shows correct lines; total = (unit×term + add-ons) + 18 % GST. Change price in admin → cart re-prices.
3. Apply coupon; guest checkout with GSTIN; place order with Razorpay **test** card/UPI → order *Processing*; webhook arrives; ledger row `success`; emails; invoice opens and shows CGST/SGST (same state) or IGST.
4. Close the browser before redirect (webhook-only completion) → still marked paid.
5. Fail a payment → `failed` row, admin email, *Retry payment* works. Pay twice → `duplicate` flagged.
6. Refund from the order → `refund` row. Bank transfer order → On hold → mark Processing → project advances.
7. Customer: register → verification email → cannot log in until verified → dashboard shows project; submit requirements + file; raise ticket; reply; download deliverable. Another customer cannot open those URLs/files (IDOR check).
8. Team member sees only assigned projects. Cached-page forms still submit (nonce refresh).
9. Lighthouse (mobile) ≥ 90 performance/accessibility; no console errors; crawl for broken links.
