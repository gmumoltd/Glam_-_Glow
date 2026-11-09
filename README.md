# Valmier Enterprise — Retail Intelligence Platform

A retail management system for clothing/apparel sellers: a role-gated owner
back office (dashboard, inventory, POS, marketing, analytics) plus a public
customer-facing shop with a cart and WhatsApp checkout. Built for **Valmier
Enterprise**, designed so the storefront name/logo/branding can be swapped
per-business from Settings without touching code.

Visual design follows the "Retail Intelligence Platform" design system
(Tailwind CDN, Material Symbols icons, Manrope/Inter/JetBrains Mono, navy +
green + violet palette).

## Stack

- PHP 8+ (PDO, sessions, bcrypt via `password_hash`)
- MySQL / MariaDB
- Tailwind CSS via CDN (`cdn.tailwindcss.com`) + Google Fonts + Material Symbols
- Vanilla JS — no bundler, no build step
- No frontend framework; every page is server-rendered PHP

## Setup — local (XAMPP)

1. **Create the database and app user:**
   ```bash
   mysql -u root -e "
     CREATE DATABASE valmier_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     CREATE USER 'valmier_app'@'localhost' IDENTIFIED BY 'ChangeMe2026!';
     GRANT ALL PRIVILEGES ON valmier_db.* TO 'valmier_app'@'localhost';
     FLUSH PRIVILEGES;
   "
   ```
   Using different credentials? Export `VALMIER_DB_HOST`, `VALMIER_DB_NAME`,
   `VALMIER_DB_USER`, `VALMIER_DB_PASS` before running PHP, or edit
   `config/database.php` directly.

2. **Import the schema:**
   ```bash
   mysql -u root valmier_db < database/schema.sql
   ```
   `schema.sql` is a single, self-contained file — every table (categories,
   products, variants, images, customers, orders, order_items, campaigns,
   users, store_settings, social_links) plus seeded apparel categories and
   store settings. No separate migrations to run.

3. **Seed the owner + salesperson login accounts:**
   ```bash
   php database/seed_users.php
   ```
   This is safe to re-run — it skips any email that already exists.

4. **Make sure `uploads/` is writable** by the web server user — it holds
   product photos (`uploads/products/`), product videos
   (`uploads/videos/`), and the shop logo (`uploads/logo/`).

5. **Run it** — via XAMPP/Apache pointed at this folder, or standalone:
   ```bash
   php -S localhost:8080
   ```

## Setup — InfinityFree hosting

1. In the InfinityFree control panel, create a MySQL database (it will be
   named something like `epizXXXXXXX_valmier`) and note the host, database
   name, username, and password it gives you.
2. Open phpMyAdmin for that database and use **Import** to upload
   `database/schema.sql` as-is — it has no `CREATE DATABASE` statement, so it
   runs directly against the database InfinityFree already created for you.
3. Edit `config/database.php` and replace the fallback `DB_HOST` / `DB_NAME`
   / `DB_USER` / `DB_PASS` values with the ones from step 1 (InfinityFree
   doesn't let you set PHP environment variables on shared hosting, so
   editing the file directly is the simplest path).
4. Upload the whole project folder via FTP into `htdocs/` on your
   InfinityFree account, then run `database/seed_users.php` once (visit it
   in a browser, or via SSH if your plan includes it) to create the login
   accounts below.
5. Update `BASE_URL` in `config/app.php` to match the folder/domain you
   deployed to.

## Login credentials (seeded)

| Role        | Email                  | Password       |
|-------------|-------------------------|----------------|
| Owner       | owner@valmier.co.ke     | Owner@2026     |
| Salesperson | cashier@valmier.co.ke   | Cashier@2026   |

**Change these before this ever sits anywhere public.** The login page does
not display these credentials in the UI — they're documented here only.

## Roles

- **Owner** — full access: Dashboard, Inventory, POS, Calculators, Marketing,
  Sales Feed, Settings.
- **Salesperson** — POS only. Redirected away from every other admin page.
  Their POS view includes a daily mini-dashboard (today's sales,
  transactions, items sold, average sale, recent sales) scoped to sales
  *they* processed.
- **Customer (no login)** — the public shop. Reaches the login page only via
  a small "Staff Login" link in the header.

## Routing

`index.php` is a router, not a page: guests land on the public `shop.php`,
an Owner is sent to `dashboard.php`, a Salesperson to `pos.php`. This means
anyone with the base URL sees the storefront first — no login wall.

## Structure

```
config/database.php    PDO connection (reads TENIKA2_DB_* env vars, falls back to local defaults)
config/app.php          Business name / currency / base URL constants
includes/auth.php       Session auth: current_user(), require_login(), require_role(), get_store_settings()
includes/functions.php  Data helpers, money formatting, stock status, social link rendering
includes/shop_cart.php  Session-based cart for the PUBLIC shop (separate from any admin session state)
includes/layout-header.php / layout-footer.php   Shared admin shell (sidebar nav, filtered by role)

dashboard.php           Owner Command Center — sales, profit, health score, channel mix, top products
inventory.php           Product list with stock-level bars, discount ribbons, low-stock banner
product-form.php        Add/edit product: variants (upsert-safe — never breaks past orders), multi-image
                        upload with cover/reorder controls, video upload, discount %, on-sale toggle
product-view.php        Owner-side image carousel viewer for a single product
pos.php                 Cashier POS: click-to-cart catalog, checkout, daily stats, recent sales
calculators.php         Profit / margin / markup / discount / bulk pricing / break-even tools
marketing.php           Coupon / SMS / WhatsApp campaign records
sales-feed.php          All orders, filterable by channel (walk-in/website/whatsapp/tiktok)
settings.php            Business name, logo, WhatsApp number, social/marketplace links
login.php / logout.php  Auth

shop.php                Public storefront: search + category/price/size filters, discount ribbons,
                        per-variant Add to Cart, instant Buy on WhatsApp
product-detail.php      Public product page: image gallery with fullscreen lightbox, video, full
                        description, color/size picker, Add to Cart / Buy on WhatsApp
cart.php                Public cart: adjust quantities, remove items, Checkout via WhatsApp (opens a
                        consolidated wa.me message, then clears the cart)

uploads/products/       Product photos
uploads/videos/         Product videos
uploads/logo/           Shop logo
database/schema.sql, migration_002.sql, migration_003.sql, seed_users.php
```

## Notable design decisions

- **Variant edits never blind-delete.** `product-form.php` upserts variants
  by ID and only deletes rows that were actually removed in the UI — and
  even then, skips the delete if that variant already has order history
  (foreign key), rather than crashing the save.
- **Discounted price is authoritative everywhere.** POS, the public shop,
  the product detail page, and the cart all compute the same
  `effective_price` (server-side in `get_variant()`), so what a customer
  sees is what actually gets charged.
- **Social links are data, not hardcoded markup.** Any number of
  platform/URL pairs can be added from Settings; URLs missing a scheme are
  auto-prefixed with `https://` so a bare handle like `myshop` doesn't
  silently become a broken relative link.
