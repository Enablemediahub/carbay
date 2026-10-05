# Carbay+

Carbay+ is a Laravel 11, Filament 3 and Livewire SaaS starter for Ghanaian car washing bays. It stores tenants in one MySQL database and scopes tenant-owned Eloquent models by `tenant_id`.

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

Open `/superadmin` for platform administration or `/app` for tenant access. The seeded super-admin is `superadmin@carbayplus.test` with password `password`; change this password before exposing the app beyond local development. To start using `/app`, sign into `/superadmin`, create a tenant at **SaaS management → Tenants**, and provide the initial CEO name, login email and password. Tenant creation provisions the company’s main branch and CEO account automatically. Use those CEO credentials to sign into `/app`. A Super Admin visiting `/app` is redirected back to `/superadmin`; Super Admin access is not granted to tenant data.

Both sign-in pages use the Carbay+ branded Filament theme. If you update Filament, republish its static UI assets with `php artisan filament:assets`, then rebuild the Vite assets with `npm run build`.

In `/superadmin`, use **Global reference data** to manage global vehicle categories, makes, models and services. **Billing → Branch add-on invoices** lets a super admin mark an externally paid branch invoice as paid.

In `/app`, tenant CEOs can review sales totals and branch/worker/service breakdowns, record sales under **Company → Sales**, manage branches and workers, activate global services with vehicle-category-specific price/share overrides, record expenses when `expense_tracking` is enabled, and update company/payment settings. The package `worker_limit` is enforced against active workers across all branches; deactivating a worker releases that seat, and reactivation requires an available seat. With `fraud_flags` enabled, repeated non-empty Mobile Money or Paystack references create a **Payment review** flag without blocking sale entry; company owners and branch managers can mark signals reviewed or dismiss them. This detects duplicate references only and is not a payment-provider verification or a fraud determination. When `worker_pin_login` is enabled, workers can sign in at `/worker/login` with their company email, registered phone and PIN to view their own completed-wash activity; managers can set or reset worker PINs from the Workers resource. PIN sign-in is rate limited and is independent from CEO/manager accounts. Tenants with `reports_export` enabled can download date- and branch-filtered sales and expense CSV reports under **Reports & data**. With `audit_trail` enabled, CEOs and branch managers can review immutable **Activity history** records; managers see only their branch. Sensitive values such as passwords, PINs and Paystack secret keys are excluded from audit snapshots. A manager's exports remain limited to their assigned branch, and expense rows are included only when `expense_tracking` is enabled. Sale totals are calculated server-side from the tenant's active service/category prices; payment methods must be both enabled in company settings and included in the package. Dashboard totals read completed wash sales from `wash_sales` and `wash_sale_items`. Creating a tenant in `/superadmin/tenants` provisions its included main branch automatically. Tenant feature rows override package defaults. A branch within its enabled `multi_branch` limit is included; otherwise the app creates a pending branch add-on invoice and leaves the branch unpaid until an administrator confirms payment.

Tenant-owned models use the `BelongsToTenant` trait. Tenant managers are additionally scoped to their assigned branch for branch-level records; tenant-wide company settings and service pricing are CEO-only.

Run the focused tenant-scope tests with:

```sh
php artisan test
```
