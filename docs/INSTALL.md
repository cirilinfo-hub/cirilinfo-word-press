# Installation, configuration & deployment

## 1. Requirements
PHP 8.1+ (8.2 recommended) with `mysqli`, `mbstring`, `curl`, `gd`/`imagick`, `zip`, `openssl` · MySQL 5.7+/MariaDB 10.4+ ·
HTTPS · WordPress 6.4+ · WooCommerce 8+ · a real SMTP account · (production) Apache or Nginx.

## 2. Install
1. Install WordPress normally (database, `wp-config.php`). Set permalinks to **Post name**.
2. Copy `wp-content/plugins/cirilinfo-core` and `wp-content/themes/cirilinfo` into the site (or deploy this repo’s `wp-content`).
3. Install & activate **WooCommerce** and **Razorpay for WooCommerce** (Plugins → Add New). Optional: Cashfree, PayU official plugins.
4. Activate the **CirilInfo** theme and the **CirilInfo Core** plugin (creates DB tables `wp_ci_transactions`, `wp_ci_subscribers`, roles, the protected upload folder).
5. WP-CLI alternative:
   ```bash
   wp plugin install woocommerce razorpay-for-woocommerce --activate
   wp theme activate cirilinfo && wp plugin activate cirilinfo-core
   wp eval 'CirilInfo\Seeder::pages_and_menus(); CirilInfo\Seeder::configure_woocommerce(); CirilInfo\Seeder::services_and_packages();'
   ```
6. In wp-admin open **CirilInfo → Setup** and run the guided steps (pages & menus → WooCommerce → services & packages). The **Health check** table shows anything still missing.

### Database
Tables are created with `dbDelta` on activation / version change; nothing to import. Back up both core tables and CPT data (see §9).

## 3. Company settings – *CirilInfo → Settings*
* **Company**: name, legal name, phone, email (used in header/footer/schema/invoices), WhatsApp number (digits, e.g. `919876543210`), address, business **state code** (e.g. `KL`; decides CGST+SGST vs IGST), GSTIN, Google Maps embed URL, social links.
* **GST & Invoices**: enable GST, rate (default 18 %), scope (India only / all), invoice prefix, terms, bank-transfer details. Saving syncs the rate into **WooCommerce → Settings → Tax**.
* **Analytics & Chat**: GA4 ID, Search Console token, Meta Pixel ID, cookie-consent gating, live-chat embed (Tawk.to / Crisp / Tidio snippet).
* **SEO**: default description and share image. Per page/post/service SEO title, description, noindex boxes are in the editor.

## 4. Services, packages & prices – *CirilInfo → Services / Packages*
* A **Service** has icon, tagline, benefits, process steps and optional **add-ons** (price, “repeats each period”).
* A **Package** belongs to a service and tier (Basic/Professional/Enterprise) and has: price, discounted price, billing model (**one-time / monthly / yearly / custom quote**), features (one per line), revisions, delivery, support, “most popular”, active.
  Use *identical wording* for a feature in several tiers so the comparison table shows ticks.
* Saving a package creates/updates a hidden WooCommerce product; deactivating it removes it from sale. “Custom” billing → quote only.

## 5. Payments
### Razorpay (primary)
1. Create a Razorpay account → **Test mode** → *Settings → API Keys* → generate **Key ID + Key Secret**.
2. WooCommerce → Settings → Payments → **Razorpay** → enable, paste test keys, keep *Test mode* on. Enable the methods you want in your Razorpay dashboard (UPI, cards, netbanking, wallets).
3. **Webhook**: the plugin settings page shows a *Webhook URL* and secret field. In Razorpay Dashboard → *Settings → Webhooks → Add*, paste that URL, set a secret, tick the events the plugin lists (payment captured/failed, order paid, refund processed), and save the same secret in the plugin. Webhooks make payments succeed even if the customer closes the browser.
4. Test with Razorpay’s sandbox cards/UPI (`success@razorpay`). Confirm: order → *Processing*, CirilInfo → Payments shows a `success` row, customer gets “Payment received” email with invoice link, a Project appears.
5. Go live: switch keys to live, disable test mode, re-create the webhook with the live secret.

### Cashfree / PayU
Install the vendor’s official WooCommerce plugin, enter sandbox credentials, configure their webhook per vendor docs. No code changes needed – the ledger is gateway-agnostic.

### Bank transfer (NEFT/IMPS)
WooCommerce → Payments → *Direct bank transfer*: enter account details. Orders stay **On hold**; when funds arrive set the order to **Processing** → ledger records the payment (`manual` reference), invoice and project advance. Bank details also print on unpaid invoices.

### Refunds, retries, duplicates
* Refund: open the WooCommerce order → *Refund* (gateway refunds if supported). Appears under CirilInfo → Payments as `refund`.
* Failed payment: logged as `failed`, admin emailed, customer sees *Retry payment* under *My Payments*.
* Duplicate: a second *different* payment reference on a paid order is stored as `duplicate` and alerts the admin.

### Security rules
Never paste secret keys into theme files, page content or JavaScript. Keep keys only in the gateway plugin settings (server-side) or `wp-config.php`. Card, CVV and UPI PIN data never reach the site.

## 6. Email & SMTP – *Settings → Email & SMTP*
Enter host/port/encryption/user and from-address; put the password in `wp-config.php` as `define('CIRILINFO_SMTP_PASS','…')` (preferred) or in the field (stored AES-encrypted). Use **Send test email**. Edit all CirilInfo emails under *Email templates* (placeholders in `{braces}`); WooCommerce order emails under WooCommerce → Settings → Emails. For deliverability set SPF/DKIM/DMARC for your domain.

## 7. Roles
| Role | Can |
|---|---|
| Administrator / **CirilInfo Manager** | everything in CirilInfo menu, orders, customers |
| **CirilInfo Team Member** | see/edit only **projects assigned to them**, upload deliverables, message customers |
| Customer | own dashboard only |
Assign users under Users → role.

## 8. Customer journey (what to expect)
Service page → choose package/add-ons/duration → *Buy now* → cart (coupon) → checkout (billing, GSTIN, requirements, terms, payment) → payment → emails + invoice → Project “Awaiting Requirements” → customer submits requirements/files → admin assigns → In Progress → Review → Completed/Delivered (deliverables downloadable, feedback form). Guests can check out; they get an account (email verified) to track projects.

## 9. Production deployment checklist
1. Server: PHP-FPM 8.2, OPcache on, Redis/Memcached object cache if available, HTTPS (Let’s Encrypt), HTTP/2.
2. Add `config/wp-config-additions.php.sample` settings; **Nginx**: include `config/nginx-snippet.conf` (the `cirilinfo-private` deny rule is mandatory on Nginx — `.htaccess` is ignored there); **Apache**: `config/htaccess-snippet.txt`.
3. Caching: a page-cache plugin is fine, but **exclude** cart, checkout, my-account and `?add-to-cart`; forms refresh their nonces by AJAX so cached pages still work. Add an image optimiser (WebP/AVIF) and keep lazy-loading on.
4. Real cron: `DISABLE_WP_CRON` + system cron every 5 min (used by the campaign mailer).
5. Settings → Reading: allow search engines. Submit `https://yourdomain/wp-sitemap.xml` in Search Console. Verify GA4 receives `purchase`, `generate_lead`.
6. Run **CirilInfo → Setup → Health check** – all green. Run a complete test order in sandbox, then a live ₹1 order and refund it.
7. Review and replace the template legal pages; remove `[SAMPLE]` demo content (Setup → Remove demo content).

## 10. Backup & recovery
* **What**: database (all tables incl. `wp_ci_*`) + `wp-content/uploads` (incl. `cirilinfo-private/` – customer files!) + `wp-config.php`.
* **When**: daily DB, daily/weekly files, keep 14–30 days off-site (S3/Backblaze/Drive) via UpdraftPlus/host snapshots, or cron:
  ```bash
  mysqldump --single-transaction -u USER -p DB | gzip > /backups/db-$(date +%F).sql.gz
  tar czf /backups/uploads-$(date +%F).tgz wp-content/uploads
  ```
* **Restore**: fresh WP + same plugins/theme → import DB → restore uploads → `wp search-replace 'old' 'new' --dry-run` if the domain changed → `wp cache flush && wp rewrite flush`. Test restores quarterly.
* Retain payment-ledger and invoice data per your statutory period (GST: generally 6 years – confirm with your accountant).

## 11. Updating
Back up → stage → update WooCommerce/gateway plugins on staging first → run a sandbox order → deploy. The plugin runs its schema upgrade automatically (`cirilinfo_db_version`).
