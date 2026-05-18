# ChatGPT Subscription Monitoring

Proyek web berbasis **CodeIgniter 4** untuk monitoring akun + lifecycle subscription/invite, observability usage 9router, dan reminder Telegram.

Dokumen referensi yang diimplementasikan:
- `docs/PRD_subscription.md`
- `docs/API_SPEC.md`
- `docs/DATABASE_subscription.md`
- `docs/UI_WIREFRAME.md`
- `docs/IMPLEMENTATION_PLAN_subscription.md`
- `docs/TELEGRAM_SETUP.md`
- `docs/STRUKTUR_DAN_JEROAN_WEB.md`
- `docs/DOKUMENTASI_INTEGRASI_9ROUTER_GPT_TRACKER.md`

## Stack
- PHP 8.2+ (lokal: `/opt/lampp/bin/php`)
- CodeIgniter 4
- Vite
- Tailwind CSS
- MySQL/MariaDB (XAMPP)

## Fitur
- Akun pengguna + autentikasi session (`register` admin awal, `login` via username/email, `logout`)
- Halaman profile user (update nama, username, email, dan password)
- CRUD Accounts
- CRUD Subscriptions (relasi ke account)
- Ringkasan usage 9router per email akun (`24 jam` dan `7 hari`)
- Dashboard monitoring status invite (`Active`, `Expiring Soon`, `Expired`)
- Telegram settings + test message (per user login)
- Command reminder: `reminders:subscriptions`
- 9router raw usage collector (`router:push-usage`, alias: `router:collect-usage`, `router:sync-accounts`)
- 9router analytics CLI (`router:analytics-summary`)
- 9router shipper endpoint (`POST /api/router/ingest`) + script lokal `scripts/router_log_shipper.php`
- 9router account session analytics (`ai_router_account_sessions`) + ringkasan API `GET /api/router/analytics/summary`

## Setup Lokal (XAMPP + MySQL)
1. Masuk folder project:
   ```bash
   cd /home/viasco/Koding/chatgpt-monitoring
   ```
2. Buat database:
   ```bash
   mysql -u root < database/create_database.sql
   ```
3. Install dependency (sudah menggunakan PHP XAMPP):
   ```bash
   /opt/lampp/bin/php /usr/local/bin/composer install
   ```
4. Install dependency frontend:
   ```bash
   npm install
   ```
5. Pastikan `.env` sudah benar (default sudah disiapkan untuk MySQL lokal).
6. Jalankan migration:
   ```bash
   /opt/lampp/bin/php spark migrate
   ```
7. Jalankan Vite dev server (terminal terpisah):
   ```bash
   npm run dev
   ```
8. Jalankan server development CI4:
   ```bash
   /opt/lampp/bin/php spark serve --host 0.0.0.0 --port 8080
   ```
9. Buka browser: `http://localhost:8080`
10. Jika belum ada user sama sekali, buat akun admin pertama di:
   - `http://localhost:8080/register`
11. Setelah itu login di:
   - `http://localhost:8080/login`
12. Jika update dari versi lama, jalankan migration terbaru agar kolom `username` tersedia.

## Build Frontend Production
```bash
npm run build
```

Output asset akan dibuat ke `public/build` dan otomatis dibaca helper `vite_tags()`.

## API Utama
Base URL: `/api`

Catatan autentikasi:
- Seluruh route web dan API sekarang diproteksi session login.
- Untuk akses API dari browser/Postman, login dulu via `/login` agar session cookie aktif.

### Accounts
- `GET /api/accounts`
- `GET /api/accounts/{id}`
- `POST /api/accounts`
- `PUT /api/accounts/{id}`
- `DELETE /api/accounts/{id}`

### Subscriptions
- `GET /api/subscriptions`
- `GET /api/subscriptions/{id}`
- `POST /api/subscriptions`
- `PUT /api/subscriptions/{id}`
- `DELETE /api/subscriptions/{id}`

### Telegram
- `POST /api/telegram/test`
- `GET /api/telegram/settings`
- `PUT /api/telegram/settings`

### Router Analytics
- `GET /api/router/analytics/summary?provider=codex&days=30`
- `GET /api/router/analytics/charts?provider=codex&days=30&top=10`

CLI summary:
```bash
/opt/lampp/bin/php spark router:analytics-summary --provider=codex --days=30
```

Dashboard:
- Halaman `/` punya panel **Grafik Observability 9router** (token harian, aktivitas per jam, usage per akun, distribusi model, cache ratio, latency) dari endpoint `api/router/analytics/charts`.
- Card/tabel usage di dashboard dan halaman detail akun juga memakai sumber data 9router (bukan input manual lama).

## Reminder Command
Kirim reminder otomatis untuk subscription `expiring_soon` dan `expired`:

```bash
/opt/lampp/bin/php spark reminders:subscriptions
```

Command ini juga menulis log ke tabel `reminder_logs` agar tidak duplicate di hari yang sama.
