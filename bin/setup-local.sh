#!/usr/bin/env bash
# One-shot local bootstrap (requires Docker). Installs WordPress, WooCommerce and the Razorpay plugin,
# activates CirilInfo, then runs the guided setup. Credentials printed at the end are for LOCAL use only.
set -euo pipefail
WP="docker compose run --rm wpcli wp"
until $WP db check >/dev/null 2>&1; do echo "waiting for database..."; sleep 3; done
$WP core install --url=http://localhost:8080 --title="CirilInfo" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
$WP plugin install woocommerce razorpay-for-woocommerce --activate
$WP theme activate cirilinfo
$WP plugin activate cirilinfo-core
$WP rewrite structure '/%postname%/' --hard
$WP eval '
CirilInfo\Seeder::pages_and_menus();
CirilInfo\Seeder::configure_woocommerce();
$r = CirilInfo\Seeder::services_and_packages();
update_option("cirilinfo_setup_done", 1);
echo "Imported {$r["services"]} services, {$r["packages"]} packages\n";'
echo "Site: http://localhost:8080  Admin: http://localhost:8080/wp-admin  (admin / admin)"
echo "Next: WooCommerce > Settings > Payments > Razorpay: add TEST keys (docs/INSTALL.md section 5)."
