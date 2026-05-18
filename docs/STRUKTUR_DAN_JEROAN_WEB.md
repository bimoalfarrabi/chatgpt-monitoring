# Struktur & Jeroan Aplikasi Web

Dokumen ini menjelaskan struktur source code dan alur internal aplikasi **ChatGPT Subscription Monitoring** secara teknis, dari request masuk sampai data tersimpan, dengan fokus observability usage dari 9router.

## 1) Gambaran Arsitektur

Aplikasi ini dibangun dengan pola **monolith MVC** menggunakan **CodeIgniter 4**:

- `public/index.php` sebagai front controller.
- Routing terpusat di `app/Config/Routes.php`.
- Controller terbagi jadi dua jalur:
  - `WebController` dan `AuthController` untuk halaman HTML.
  - `App\Controllers\Api\*` untuk endpoint JSON.
- Data access via `Model` CodeIgniter (`App\Models\*`).
- Aturan bisnis utama dipusatkan di service:
  - `SubscriptionStatusService`
  - `TelegramService`
- UI server-rendered di `app/Views`, dengan enhancement JavaScript ringan + Tailwind/Vite.

## 2) Struktur Direktori Penting

```text
app/
  Commands/                 # Command CLI (scheduler reminder)
  Config/                   # Routing, filter, db, session, app config
  Controllers/
    AuthController.php      # Login/register/logout
    WebController.php       # Seluruh halaman dashboard/accounts/profile/telegram
    Api/                    # Endpoint JSON
  Database/Migrations/      # Evolusi skema database
  Filters/                  # AuthFilter, GuestFilter
  Helpers/vite_helper.php   # Bridge CI4 <-> Vite dev/build
  Models/                   # ORM model tiap tabel
  Services/                 # Logika bisnis reusable
  Views/                    # Template UI server-rendered

resources/
  css/app.css               # Tailwind + base style custom
  js/app.js                 # Interaksi global UI (table cards, user menu)
scripts/
  router_log_shipper.php    # Parser + shipper log 9router (lokal -> ingest HTTPS)

public/
  index.php                 # Entry point web
  build/                    # Asset hasil vite build

docs/                       # Dokumen produk/DB/API/deploy
```

## 3) Lifecycle Request (Web)

1. Request masuk ke `public/index.php`.
2. Bootstrapping CI4 (`Boot::bootWeb($paths)`).
3. Route dicocokkan di `app/Config/Routes.php`.
4. Filter dijalankan:
   - `auth`: wajib login (`session('logged_in')`)
   - `guest`: hanya untuk user belum login
5. Controller proses input + validasi.
6. Model baca/tulis ke MySQL.
7. Response berupa:
   - HTML view + flash message (jalur web), atau
   - JSON (jalur API).

## 4) Routing Utama

### Auth (guest)

- `GET /login`, `POST /login`
- `GET /register`, `POST /register`
- `POST /logout` (auth)

### Web (auth)

- Dashboard: `GET /`
- Accounts:
  - `GET /accounts`
  - `POST /accounts/create`
  - `GET /accounts/{id}`
  - `POST /accounts/{id}/update-name`
  - `POST /accounts/{id}/update-password`
  - `POST /accounts/{id}/delete`
- Subscription actions:
  - `POST /subscriptions/{id}/update`
  - `POST /subscriptions/{id}/renew`
  - `POST /subscriptions/{id}/deactivate`
  - `POST /subscriptions/{id}/workspace/create`
  - `POST /subscriptions/{id}/plus/update-deactivated`
- History async: `GET /accounts/{id}/history/{section}`
- Profile:
  - `GET /profile`
  - `POST /profile/update`
- Telegram:
  - `GET /telegram`
  - `POST /telegram/settings`
  - `POST /telegram/test`

### API (auth)

- Accounts CRUD: `/api/accounts`
- Subscriptions CRUD: `/api/subscriptions`
- Router analytics:
  - `GET /api/router/analytics/summary`
  - `GET /api/router/analytics/charts`
- Telegram:
  - `GET /api/telegram/settings`
  - `PUT /api/telegram/settings`
  - `POST /api/telegram/test`

### API (tanpa session auth)

- `POST /api/router/ingest`
  - endpoint khusus collector/shipper 9router
  - validasi akses via `router.ingestKey` (header `X-Router-Ingest-Key`) jika key diaktifkan

## 5) Autentikasi & Session

Autentikasi bersifat session-based:

- Login menerima `identity` (username/email) + `password`.
- Password diverifikasi dengan `password_verify` ke `users.password_hash`.
- Session yang diset saat login:
  - `user_id`, `user_name`, `username`, `user_email`, `logged_in=true`
- `AuthFilter`:
  - untuk web: redirect ke `/login`
  - untuk API: JSON `401 Unauthorized`
- `GuestFilter` mencegah user login mengakses `/login` & `/register`.

## 6) Skema Data Inti

Tabel inti:

- `users`: akun login dashboard.
- `accounts`: akun ChatGPT yang dimonitor.
- `subscriptions`: lifecycle akun free/pro/plus per account.
- `subscription_renewal_histories`: histori perpanjangan +1 bulan.
- `telegram_settings`: konfigurasi bot Telegram per user login (unik `user_id`).
- `reminder_logs`: anti-duplikasi reminder harian.
- `ai_router_accounts`: mapping `provider + router_account_ref` -> email akun.
- `ai_router_usage_events`: event usage mentah dari log 9router.
- `ai_router_collector_states`: cursor collector lokal (incremental parse).
- `ai_router_account_sessions`: agregasi sesi/account (request, token, latency, first/last seen).

Tabel lama (masih ada, bukan sumber utama dashboard usage):
- `account_usages`
- `account_usage_histories`

Relasi penting:

- `accounts (1) -> (N) subscriptions`
- `subscriptions (1) -> (N) subscription_renewal_histories`
- `users (1) -> (1) telegram_settings`
- `ai_router_accounts (1) -> (N) ai_router_usage_events`
- `ai_router_accounts (1) -> (N) ai_router_account_sessions`

## 7) Aturan Bisnis Inti Subscription

Aturan di-encode terutama pada `SubscriptionStatusService` + helper private di controller.

### a) Jenis akun

- `free`: tidak ada lifecycle expire bulanan.
- `pro`: workspace, butuh tipe `personal_invite` atau `seller_account`.
- `plus`: dipaksa sebagai `seller_account`, durasi 1 bulan dipaksa aktif.

### b) Penentuan status

`resolveStatus(expiredAt, isWorkspaceDeactivated)`:

- `deactivated` jika workspace dinonaktifkan.
- `expired` jika `expired_at < now`.
- `expiring_soon` jika `expired_at <= now + 3 hari`.
- selain itu `active`.

### c) Perhitungan expired

`expired_at` dihitung dari `subscribed_at + 1 bulan` hanya jika `is_one_month_duration = true`.

### d) Catatan penggunaan kuota

Aturan `usageTypes(accountType, proAccountType)` untuk `account_usages` masih ada di domain subscription (kompatibilitas data lama), namun:

- dashboard utama
- panel observability
- ringkasan usage pada detail akun

sekarang memakai sumber `ai_router_usage_events` (9router) yang diagregasi per email akun.

## 8) Perilaku Otomatis di Balik Layar

Ada beberapa proses otomatis yang berjalan saat data dibaca/ditampilkan:

1. **Normalisasi subscription saat read**
   - `enrichedSubscriptions()` di `WebController`
   - `normalizeSubscription()` / `normalizeSubscriptionRow()` di API controller
   - Fungsi ini bisa *mengupdate record subscription* saat mendeteksi field tidak sinkron (status/expired/type/workspace).

2. **Ingest event usage 9router**
   - Endpoint `POST /api/router/ingest` menerima batch event dari collector/shipper lokal.
   - Event disimpan append-only ke `ai_router_usage_events`.
   - Mapping akun (`provider + router_account_ref`) di-update otomatis ke `ai_router_accounts`.
   - Session agregat (`ai_router_account_sessions`) ikut di-update (request/token/latency/last status/email).

3. **Sync tipe usage subscription (legacy compatibility)**
   - `syncUsagesForSubscription()` masih menambah/menghapus row `account_usages` agar domain subscription tetap konsisten untuk data lama.

## 9) Alur Fitur Kritis

### a) Create account

- Validasi form account.
- Jika tipe workspace (`pro/plus`), wajib `store_source` + `subscription_type`.
- `free` dibuatkan subscription default otomatis:
  - `store_source=free_account`
  - `subscription_type=Free Weekly`
  - status `active`
- Semua create subscription akan disinkronkan usage-nya.

### b) Renew subscription (+1 bulan)

- Hanya untuk workspace non-deactivated.
- Base perhitungan:
  - pakai `expired_at` lama jika masih di masa depan, atau
  - pakai waktu sekarang jika sudah lewat.
- Simpan histori ke `subscription_renewal_histories`.

### c) Workspace deactivated flow

- Workspace bisa di-set deactivated.
- Dari deactivated:
  - `pro`: dapat membuat workspace baru (tetap simpan histori lama).
  - `plus`: dapat update data akun + buat subscription baru tanpa hapus account.

### d) Telegram reminder flow

- `spark reminders:subscriptions` ambil semua subscription, hitung status terbaru.
- Reminder dikirim untuk `expiring_soon`, `expired`, `deactivated`.
- Cek `reminder_logs` agar per `account + subscription + status` tidak terkirim dua kali di hari yang sama.
- `TelegramService` memilih setting aktif:
  - jika dipanggil dengan `userId`, pakai setting user itu.
  - jika tanpa `userId` (command), pakai row aktif pertama.

### e) Flow observability 9router

- 9router berjalan di mesin lokal dan menulis log.
- `scripts/router_log_shipper.php` membaca log incremental (pakai file state/cursor).
- Script mengirim batch JSON ke `POST /api/router/ingest` (HTTPS).
- Server memproses event dan menyimpan ke tabel `ai_router_*`.
- Dashboard memanggil:
  - `GET /api/router/analytics/summary`
  - `GET /api/router/analytics/charts`
  untuk merender grafik token, aktivitas, cache ratio, latency, usage akun, dan distribusi model.

## 10) Layer Frontend

### a) Rendering

UI bersifat server-side rendered via CI4 Views:

- `layouts/main.php` sebagai shell utama.
- halaman utama:
  - `dashboard.php`
  - `accounts/index.php`
  - `accounts/detail.php`
  - `telegram/settings.php`
  - `profile/index.php`
  - `auth/login.php`, `auth/register.php`

### b) JavaScript

- `resources/js/app.js`: interaksi global (dropdown user menu, data-label responsive table).
- Halaman `dashboard` dan `accounts/detail` memiliki inline JS besar untuk:
  - fetch data observability dari endpoint analytics 9router
  - render chart SVG custom (tanpa library chart eksternal)
  - AJAX pagination histori (workspace & renewal)
  - dynamic form behavior (show/hide/required)
  - quick copy password/email

### c) Styling

- Tailwind + custom tokens di `tailwind.config.js`.
- Font dari Google Fonts di `resources/css/app.css`.
- Vibe desain memakai palet netral + aksen (`accent`, `danger`, `success`, `gold`).

## 11) Build & Asset Pipeline

- Dev: Vite HMR (`npm run dev`, port `5173`).
- Prod: `npm run build` -> output ke `public/build`.
- `vite_tags()` helper:
  - mode development: inject `@vite/client` jika dev server reachable.
  - mode production: baca `public/build/.vite/manifest.json` lalu inject CSS/JS hashed.

## 12) Background Job / Scheduler

Command:

- `php spark reminders:subscriptions`
- `php spark router:push-usage` (collector log lokal, jika mode pull lokal dipakai)
- `php spark router:sync-accounts`
- `php spark router:analytics-summary`

Fungsi:

- hitung ulang status lifecycle,
- kirim Telegram reminder,
- simpan jejak pengiriman di `reminder_logs`.
- ingest/sinkron data usage 9router untuk observability.

## 13) Testing Saat Ini

Test sudah ada tapi masih dasar:

- `UsageResetNormalizationTest` (coverage domain usage lama)
- `HealthTest` (sanity app path dan baseURL)
- contoh bawaan CI4 untuk database/session.

Area yang paling layak ditambah test:

- rule branching create/update subscription (free/pro/plus)
- flow deactivated -> create workspace baru
- API contract test (status code + payload shape)
- reminder command (duplikasi harian + format message)
- parser log 9router + ingest controller (valid/invalid payload)
- agregasi analytics router (`summary/charts`) untuk edge case data kosong

## 14) Catatan Teknis & Hotspot

1. `WebController` sangat besar (banyak concern di satu class); ini pusat logika utama sekarang.
2. Beberapa proses read melakukan write (normalisasi status/subscription), jadi halaman read bisa mengubah DB.
3. Form chart dan dynamic behavior ditulis inline per view; maintainability JS akan lebih baik jika diekstrak ke module per halaman.
4. Integrasi Telegram masih via cURL manual tanpa retry/backoff.
5. Endpoint ingest 9router harus dijaga dengan `router.ingestKey` saat dipublikasikan di internet.

## 15) File Referensi Kode (Paling Penting)

- Entry point: `public/index.php`
- Routing: `app/Config/Routes.php`
- Filter auth: `app/Filters/AuthFilter.php`, `app/Filters/GuestFilter.php`
- Controller web utama: `app/Controllers/WebController.php`
- Controller auth: `app/Controllers/AuthController.php`
- API: `app/Controllers/Api/*.php`
- Service bisnis: `app/Services/SubscriptionStatusService.php`, `app/Services/TelegramService.php`
- Command scheduler: `app/Commands/SendSubscriptionReminders.php`
- Model: `app/Models/*.php`
- Migration: `app/Database/Migrations/*.php`
- Layout/UI: `app/Views/layouts/main.php`, `app/Views/dashboard.php`, `app/Views/accounts/*.php`
- Asset build bridge: `app/Helpers/vite_helper.php`
