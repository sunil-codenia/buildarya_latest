# Buildarya - Enterprise Building Construction MIS & ERP Platform

Buildarya is a comprehensive, multi-tenant Building Construction Management Information System (MIS) and Enterprise Resource Planning (ERP) platform built with **Laravel 8**. It enables construction firms, contractors, and project managers to streamline project lifecycles, resource allocation, inventory tracking, financial vouchers, site bills, workforce attendance, task management, and document workflows across multiple sites.

---

## 🌟 Architecture & Highlights

- **Dynamic Multi-Tenant Database Architecture**: Operates on a master database (`buildarya`) for company onboarding, subscription management, and central authentication, alongside isolated company tenant databases (`comp_db_conn_name`) for complete client data isolation.
- **RESTful API Engine (v1)**: Fully equips web clients and mobile applications (Flutter/React Native) with protected endpoints backed by Laravel Sanctum authentication.
- **Automated Messaging & Notifications**: Integrated with **Firebase Cloud Messaging (FCM)** for push alerts and **Meta WhatsApp Business API** for automated daily attendance check-in/check-out and task deadline reminders.
- **Financial & Operations Suite**: Handles site-to-site cash transfers, payment voucher approval pipelines, material stock conversions/reconciliation, machinery maintenance/depreciation, and sales invoicing.

---

## 🛠️ Technology Stack

- **Framework**: Laravel 8 (PHP 7.4 / PHP 8.1+)
- **Database**: MySQL / MariaDB (Multi-tenant dynamic connection pool)
- **API Authentication**: Laravel Sanctum (`auth:sanctum`)
- **Document & PDF Generation**: `barryvdh/laravel-dompdf`, `maatwebsite/excel`
- **Push Notifications**: Firebase Admin SDK (FCM API v1)
- **Automated Reminders**: Meta WhatsApp Graph API

---

## 🚀 Core Features & Modules

### 1. Site & Financial Management
- **Site Directory**: Create, track, and manage construction sites across regions.
- **Payment Vouchers**: Multi-stage approval workflow (`Pending` -> `Verified` -> `Paid` / `Rejected`).
- **Site Cash Transfers**: Direct cash allocations and balance auditing between sites.
- **Site Bills**: Contractor bill submissions, item entry tracking, party balance calculation.

### 2. Materials & Stock Control
- **Material Catalog & SKUs**: Master material records with multi-unit conversion rules.
- **Supplier Directory**: Manage material vendors, purchase orders, and payment histories.
- **Material Entries**: Track pending, verified, and returned site material receipts with image attachments.
- **Stock Transfer & Reconciliation**: Inter-site material transfers, stock audits, consumption, and wastage logs.

### 3. Assets & Heavy Machinery
- **Machinery Management**: Asset tracking, log sheets, service/maintenance history, and document storage.
- **Asset Heads**: Group machinery by category, compute depreciation, and log asset sales/transfers.

### 4. Workforce & Attendance Tracking
- **Mobile Clock-In / Clock-Out**: Geo-location tagged attendance logging.
- **Attendance Summaries**: Daily, weekly, and monthly attendance reports with manual adjustment capability.
- **Automated Reminders**: Scheduled WhatsApp messages for check-in (8:45 AM) and check-out (6:15 PM).

### 5. Tasks & Team Collaboration
- **Task Management**: Create, assign, categorize, and track task progress with deadline enforcement.
- **Task Chat Threads**: Discussion threads attached directly to specific tasks.
- **Automated Task Reminders**: WhatsApp notifications sent daily at 12:00 PM for pending tasks.

### 6. Document Vault
- **Document Indexing**: Organized document repository categorized by heads and site metadata.
- **Approval Workflow**: Verification, approval, and rejection of site document submissions.
- **PDF Export**: Generate downloadable site, document, and financial reports.

### 7. Multi-Tenant SaaS Administration
- **Company Onboarding**: Self-service or admin company registration (`/api/register_company`).
- **Dynamic Database Bootstrapping**: Automated schema creation via `apply_migration.php`.
- **Subscription Plans**: SaaS plan management and automated billing invoices.

---

## 📁 Directory Structure

```
buildarya_latest/
├── app/
│   ├── Console/Commands/       # WhatsApp reminder crons & scheduler jobs
│   ├── Http/
│   │   ├── Controllers/        # Web Controllers (Dashboard, SaaS, Documents)
│   │   │   └── api/            # API Controllers (ApiAttendance, ApiTask, ApiManagement, etc.)
│   │   ├── Middleware/         # Tenant bootstrap & authentication middleware
│   │   └── helpers.php         # Custom system helper functions
│   └── Models/                 # Eloquent ORM Models
├── config/                     # Application, database, & FCM configuration
├── database/                   # Schema migrations, factories, and seeds
├── routes/
│   ├── api.php                 # Comprehensive RESTful API routes (/api/v1)
│   └── web.php                 # Web interface routes
└── storage/                    # Uploaded media, documents, and log files
```

---

## ⚙️ Environment Configuration

Create a `.env` file in the root directory:

```env
APP_NAME=Buildarya
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=http://localhost

# Primary Master Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=buildarya
DB_USERNAME=root
DB_PASSWORD=secret

# Firebase Push Notifications
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json

# WhatsApp Business API Config
WHATSAPP_TOKEN=your_meta_whatsapp_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_TEMPLATE_ATTENDANCE_CHECKIN=attendance_checkin_template
WHATSAPP_TEMPLATE_TASK_REMINDER=task_reminder_template
WHATSAPP_TEMPLATE_CHECKOUT=checkout_template
```

---

## 💻 Setup & Installation

1. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

2. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

3. **Run Database Migrations & Seeds**:
   ```bash
   php artisan migrate --seed
   ```

4. **Configure Storage Links**:
   ```bash
   php artisan storage:link
   ```

5. **Start Development Server**:
   ```bash
   php artisan serve
   ```

6. **Configure Console Scheduler (CRON)**:
   Add to server crontab:
   ```cron
   * * * * * cd /path-to-buildarya && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## 🔑 Key API Route Groups (`/api/v1`)

| Endpoint Prefix | Description | Auth Required |
| :--- | :--- | :--- |
| `POST /api/v1/login` | Authenticate user & retrieve Sanctum bearer token | ❌ |
| `GET /api/v1/dashboard` | Key performance stats & metrics summary | ✅ (Tenant) |
| `POST /api/v1/attendance/clock-in` | Record user clock-in attendance with GPS | ✅ (Tenant) |
| `GET/POST /api/v1/tasks` | Task creation, listing, status updates | ✅ (Tenant) |
| `GET/POST /api/v1/materials/entries` | Material entry submissions & approval pipeline | ✅ (Tenant) |
| `GET/POST /api/v1/payment-vouchers` | Create, list, approve & pay financial vouchers | ✅ (Tenant) |
| `GET/POST /api/v1/documents` | Upload and verify site-related documents | ✅ (Tenant) |

---

## 🔒 License & Support

Proprietary software maintained by RSG / Buildarya Team. All rights reserved.

