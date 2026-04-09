# ChatGPT Subscription Monitoring

Proyek web berbasis **CodeIgniter 4** untuk monitoring akun + lifecycle subscription/invite, usage per subscription, dan reminder Telegram.

Dokumen referensi yang diimplementasikan:
- `docs/PRD_subscription.md`
- `docs/API_SPEC.md`
- `docs/DATABASE_subscription.md`
- `docs/UI_WIREFRAME.md`
- `docs/IMPLEMENTATION_PLAN_subscription.md`

## Stack
- PHP 8.2+ (lokal: `/opt/lampp/bin/php`)
- CodeIgniter 4
- MySQL/MariaDB (XAMPP)

## Fitur
- CRUD Accounts
- CRUD Subscriptions (relasi ke account)
- Usage per subscription (`5h` dan `weekly`)
- History perubahan usage
- Dashboard monitoring status invite (`Active`, `Expiring Soon`, `Expired`)
- Telegram settings + test message
- Command reminder: `reminders:subscriptions`

## Setup Lokal (XAMPP + MySQL)
1. Masuk folder project:
   ```bash
   cd /home/viasco/Koding/chatgpt-monitoring/monitoring-web
   ```
2. Buat database:
   ```bash
   mysql -u root < database/create_database.sql
   ```
3. Install dependency (sudah menggunakan PHP XAMPP):
   ```bash
   /opt/lampp/bin/php /usr/local/bin/composer install
   ```
4. Pastikan `.env` sudah benar (default sudah disiapkan untuk MySQL lokal).
5. Jalankan migration:
   ```bash
   /opt/lampp/bin/php spark migrate
   ```
6. Jalankan server development:
   ```bash
   /opt/lampp/bin/php spark serve --host 0.0.0.0 --port 8080
   ```
7. Buka browser: `http://localhost:8080`

## API Utama
Base URL: `/api`

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

### Account Usage
- `POST /api/account-usages/{id}/update`

Body contoh:
```json
{
  "remaining_percent": 70,
  "reset_at": "2026-04-20 14:00:00"
}
```

### Telegram
- `POST /api/telegram/test`
- `GET /api/telegram/settings`
- `PUT /api/telegram/settings`

## Reminder Command
Kirim reminder otomatis untuk subscription `expiring_soon` dan `expired`:

```bash
/opt/lampp/bin/php spark reminders:subscriptions
```

Command ini juga menulis log ke tabel `reminder_logs` agar tidak duplicate di hari yang sama.
