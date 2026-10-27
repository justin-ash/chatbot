# Telegram Anonymous Random Chatbot (Laravel 12)

A complete, production-ready anonymous language-matched Telegram chatbot built with **Laravel 12**, **MySQL (`telegrambot`)**, and **Telegram Stars (`XTR`)** billing.

## Features Implemented

- **Anonymous Language Matching:** Exact language-code FIFO queue matching (`MatchingService`) with database transactions, row-level locks (`lockForUpdate`), and deterministic user-ID lock ordering to prevent race conditions and deadlocks.
- **Strict Identity Protection:** Relays plain-text messages via `sendMessage` (`protect_content => true`) without ever exposing Telegram usernames, display names, profile photos, or numeric IDs to chat partners. Stores message metadata only (never message bodies) by default.
- **Daily Free Quota & Paid Credit Ledger:**
  - **10 free successful matches** per user per UTC calendar day (`FREE_MATCHES_PER_DAY=10`).
  - Credits are consumed **only when a match is successfully created** (waiting in the queue without a match never costs a credit).
  - Immutable `credit_ledger` table tracks all purchase grants, match spends, refunds, and admin adjustments.
- **Telegram Stars (`XTR`) Payment Flow:**
  - `/buy` generates a cryptographically unique `invoice_payload` (`pack50_<random>`) and sends an invoice in `XTR` Stars for **50 additional match credits** (`PAID_PACK_CREDITS=50`, `PAID_PACK_STARS=100`).
  - Prompt `pre_checkout_query` validation (<10s deadline).
  - Transactional, idempotent `successful_payment` verification (checks user, currency `XTR`, amount, payload, and unique `telegram_payment_charge_id` so duplicate webhooks never double-credit).
  - Controlled Stars refund workflow (`refundStarPayment`) in the Admin Console with credit reversal and audit logging.
- **Safety, Moderation & Admin Console:**
  - User commands: `/start`, `/find`, `/next`, `/stop`, `/language`, `/balance`, `/buy`, `/report`, `/block`, `/help`, `/privacy`, `/support`.
  - Bidirectional block list exclusion during matching (`blocked_users`).
  - Telegram Admin commands (`/admin`, `/stats`, `/reports`, `/ban`, `/unban`, `/mute`, `/grant`) gated by `TELEGRAM_ADMIN_IDS` and `admin_users`.
  - Web Admin Dashboard (`/admin`) with RBAC, user inspection, moderation queue, Stars payment management/refunds, runtime configuration (`bot_settings`), and append-only `audit_logs`.

---

## Quick Start (Ready to Deploy)

### 1. Database & Environment
The `.env` file is already pre-configured for your MySQL database:
- **Database:** `telegrambot`
- **Username:** `root`
- **Password:** `root`

Open [`.env`](file:///c:/xampp2/htdocs/telegramchatbot/.env) and paste your Telegram Bot token from `@BotFather`:

```dotenv
TELEGRAM_BOT_TOKEN=123456789:AAH_YOUR_REAL_BOT_TOKEN_HERE
TELEGRAM_ADMIN_IDS=YOUR_NUMERIC_TELEGRAM_USER_ID
```

### 2. Run Locally (Long Polling Mode)
For local testing on XAMPP without an HTTPS domain, run the built-in long-polling command:

```bash
php artisan telegram:poll
```

### 3. Production Webhook Registration (HTTPS)
When deployed behind an HTTPS domain (`APP_URL=https://your-domain.com`), register the webhook and secret token automatically:

```bash
php artisan telegram:webhook:setup
```

Check webhook status anytime:
```bash
php artisan telegram:webhook:setup --info
```

### 4. Scheduled Cleanup & Queue Worker
Run the cleanup command manually or via cron (`* * * * * php /path/to/artisan schedule:run`):
```bash
php artisan telegram:cleanup
```

If `TELEGRAM_QUEUE_UPDATES=true` is enabled in `.env`, run the queue worker:
```bash
php artisan queue:work --tries=3
```

---

## Web Admin Console

- **URL:** `http://localhost/telegramchatbot/public/admin` (or `http://127.0.0.1:8000/admin` via `php artisan serve`)
- **Default Super Admin Email:** `admin@telegrambot.local`
- **Default Password:** `Admin@123456` *(change immediately before production launch)*

---

## Running Automated Tests

```bash
php artisan test
```
"# chatbot" 
