# Carbay+

Carbay+ is a Laravel 11, Filament 3 and Livewire SaaS starter for Ghanaian car washing bays. It stores tenants in one MySQL database and scopes tenant-owned Eloquent models by `tenant_id`. The database queue table is `queue_jobs`, reserving `jobs` for car-wash jobs; keep `DB_QUEUE_TABLE` unset or set to `queue_jobs`.

## Local setup (XAMPP)

1. Start Apache and MySQL in XAMPP and create a MySQL database named `carbayplus` using `utf8mb4`.
2. Copy `.env.example` to `.env` if needed and set the MySQL host, port, database, username and password. The default XAMPP configuration uses `root` with no password.
3. Enable PHP extensions `pdo_mysql`, `mbstring`, `openssl`, `intl` and `zip`.
4. Install dependencies and initialize the database:

   ```sh
   composer install
   php artisan key:generate
   php artisan migrate --seed
   npm install
   npm run build
   php artisan serve
   ```

The root URL (`/`) is the public portal landing page, with separate entry cards for Workers, Managers, and company Admins/CEOs. Workers sign in at `/worker/login`; Managers and company Admins/CEOs use `/app/login`, with access determined by their account role. Open `/superadmin` for platform administration. The seeded Super Admin can sign in at `/superadmin` with phone `0241786330` and password `1234`; change this password before exposing the app beyond local development. The sign-in form also accepts email addresses. To start using `/app`, sign into `/superadmin`, create a tenant at **SaaS management → Tenants**, and provide the initial CEO name, login email and password. Tenant creation provisions the company’s main branch and CEO account automatically. Use those CEO credentials to sign into `/app`. A Super Admin visiting `/app` is redirected back to `/superadmin`; Super Admin access is not granted to tenant data.

Both sign-in pages use the Carbay+ branded Filament theme. If you update Filament, republish its static UI assets with `php artisan filament:assets`, then rebuild the Vite assets with `npm run build`.

In `/superadmin`, use **Global reference data** to manage global vehicle categories, makes, models and services. **Billing → Branch add-on invoices** lets a super admin mark an externally paid branch invoice as paid.

In `/app`, tenant CEOs can review sales totals and branch/worker/service breakdowns, record sales under **Company → Sales**, manage branches and workers, activate global services with vehicle-category-specific price/share overrides, record expenses when `expense_tracking` is enabled, and update company/payment settings. Managers also have mobile-first **New wash job**, **Today's jobs**, cash reconciliation, casual worker check-in, and payout approval screens. Jobs store vehicle, client, services, payment, and worker assignments; worker shares are tracked in wallets and scheduled payouts appear in the approval queue. The package `worker_limit` is enforced against active workers across all branches; deactivating a worker releases that seat, and reactivation requires an available seat. With `fraud_flags` enabled, repeated non-empty Mobile Money or Paystack references create a **Payment review** flag without blocking sale entry; company owners and branch managers can mark signals reviewed or dismiss them. This detects duplicate references only and is not a payment-provider verification or a fraud determination. When `worker_pin_login` is enabled, workers can sign in at `/worker/login` with their company email, registered phone and PIN to view their wallet, earnings and wash history and request payouts; managers can set or reset worker PINs and payout schedules from the Workers resource. PIN sign-in is rate limited and uses a separate worker session guard. The PWA manifest and service worker avoid caching authenticated Manager and Worker pages. Tenants with `reports_export` enabled can download date- and branch-filtered sales and expense CSV reports under **Reports & data**. With `audit_trail` enabled, CEOs and branch managers can review immutable **Activity history** records; managers see only their branch. Sensitive values such as passwords, PINs and Paystack secret keys are excluded from audit snapshots. A manager's exports remain limited to their assigned branch, and expense rows are included only when `expense_tracking` is enabled. Sale totals are calculated server-side from the tenant's active service/category prices; payment methods must be both enabled in company settings and included in the package. Dashboard totals read completed wash sales from `wash_sales` and `wash_sale_items`. Creating a tenant in `/superadmin/tenants` provisions its included main branch automatically. Tenant feature rows override package defaults. A branch within its enabled `multi_branch` limit is included; otherwise the app creates a pending branch add-on invoice and leaves the branch unpaid until an administrator confirms payment.

New Wash Job's **Scan plate live** opens the device camera and continuously reads a plate without taking or uploading a photo. OCR runs in the browser; the Tesseract worker and core are loaded from jsDelivr and the English language data is downloaded from the Tesseract data CDN (a connection is needed the first time and when the browser cache is empty). Camera access requires permission and a secure context (HTTPS or localhost). Video frames stay on the device. The scanner recognizes Ghanaian plate formats and asks managers to confirm scans below 70% confidence; the plate remains editable. An optional job photo can still be attached separately. The legacy authenticated `/manager/plate-scan` endpoint uses Plate Recognizer and requires `PLATE_RECOGNIZER_API_TOKEN`, `PLATE_RECOGNIZER_ENDPOINT`, and `PLATE_RECOGNIZER_REGIONS`; it is rate-limited and its scans are saved in `plate_scans`. Tenant Paystack keys are stored encrypted; enabling Paystack requires the `paystack` package feature, the tenant's Paystack toggle, and a valid key. The signed `/payments/paystack/webhook` endpoint confirms GHS charges and credits worker wallets only after successful payment. Paystack worker transfers are opt-in under Company settings and require a recipient code for each payout.

**Company → Subscription** shows plan usage and invoices. The daily `carbay:billing-cycle` command generates the current monthly/yearly subscription invoice and suspends a tenant after the configured grace period (`CARBAY_BILLING_GRACE_DAYS`, default 7); schedule it with Laravel's scheduler. Super Admins can adjust a tenant's plan and mark subscription or branch add-on invoices paid. The PWA caches a small offline New wash job draft page only; it stores plate and notes locally and never caches authenticated company data. Restore those details online and finish services, workers, and payment before submitting. English is the default interface locale; `resources/lang/tw.json` is an initial Twi translation stub.

The `/app` and `/superadmin` admin panels adapt to screen size: phones use a compact header, thumb-friendly bottom navigation, and a **More** menu for the signed-in user's available pages, while desktop keeps the full Filament sidebar. On supported mobile browsers, install Carbay+ from the browser menu or **Add to Home Screen** to launch the responsive panel in standalone app mode. The mobile shell respects iOS safe-area insets and does not cache authenticated admin pages.

Tenant-owned models use the `BelongsToTenant` trait. Tenant managers are additionally scoped to their assigned branch for branch-level records; tenant-wide company settings and service pricing are CEO-only.

Run the focused tenant-scope tests with:

```sh
php artisan test
```
