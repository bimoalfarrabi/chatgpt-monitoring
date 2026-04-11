# Telegram Bot Setup Guide

Panduan ini menjelaskan langkah lengkap dari membuat bot Telegram sampai terkoneksi ke web `chatgpt-monitoring`.

## 1) Prasyarat

- Aplikasi sudah bisa diakses dan Anda sudah punya akun/login.
- Database migration sudah dijalankan (`php spark migrate`).

## 2) Buat Bot Telegram via BotFather

1. Buka Telegram dan cari akun resmi `@BotFather`.
2. Kirim command `/newbot`.
3. Ikuti instruksi:
   - isi nama bot (bebas)
   - isi username bot (wajib diakhiri `bot`)
4. Simpan `bot token` yang diberikan BotFather.

Contoh format token:
`123456789:AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

## 3) Ambil Chat ID Tujuan Notifikasi

### Opsi A: Chat pribadi

1. Buka bot yang baru dibuat.
2. Klik `Start` atau kirim `/start`.
3. Jalankan:
   ```bash
   curl -s "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/getUpdates"
   ```
4. Cari nilai `message.chat.id` (biasanya angka positif).

### Opsi B: Group/supergroup

1. Tambahkan bot ke group target.
2. Kirim satu pesan di group tersebut.
3. Jalankan command `getUpdates` yang sama.
4. Cari `message.chat.id` milik group (biasanya angka negatif, sering format `-100...`).

Jika hasil `getUpdates` kosong, kirim pesan lagi ke bot/group lalu ulangi.

## 4) Hubungkan Bot ke Web (UI)

1. Login ke aplikasi.
2. Buka menu Telegram, atau akses `https://<domain-anda>/telegram`.
3. Isi:
   - `Bot Token`: token dari BotFather
   - `Chat ID`: dari langkah 3
4. Centang `Aktifkan Pengiriman Reminder`.
5. Klik `Simpan Pengaturan`.
6. Klik `Kirim Test Message`.
7. Pastikan pesan test masuk ke chat Telegram Anda.

Catatan:
- Pengaturan Telegram disimpan per user login.
- Anda bisa mengosongkan token/chat ID untuk menonaktifkan sementara.

## 5) Hubungkan via API (Alternatif)

Endpoint Telegram:
- `GET /api/telegram/settings`
- `PUT /api/telegram/settings`
- `POST /api/telegram/test`

Semua endpoint API di atas diproteksi session login (`auth`), jadi request harus membawa cookie session yang valid.

Contoh update settings:
```bash
curl -X PUT "https://<domain-anda>/api/telegram/settings" \
  -H "Content-Type: application/json" \
  -H "Cookie: <session_cookie_login>" \
  -d '{
    "bot_token": "123456789:AA...",
    "chat_id": "-1001234567890",
    "is_active": 1
  }'
```

Contoh kirim test message:
```bash
curl -X POST "https://<domain-anda>/api/telegram/test" \
  -H "Content-Type: application/json" \
  -H "Cookie: <session_cookie_login>" \
  -d '{
    "message": "Test dari API ChatGPT Monitoring"
  }'
```

## 6) Reminder Otomatis via Cron

Command reminder:
```bash
php spark reminders:subscriptions
```

Contoh cron tiap 30 menit:
```cron
*/30 * * * * /usr/local/bin/php /home/viascomy/chatgpt-monitoring/spark reminders:subscriptions >/dev/null 2>&1
```

Catatan operasional:
- Command ini mengirim reminder untuk status `expiring_soon`, `expired`, dan `deactivated`.
- Sistem menulis ke tabel `reminder_logs` untuk mencegah kirim duplicate di hari yang sama.
- Untuk pengiriman dari command ini, sistem mengambil setting Telegram aktif pertama (`is_active = 1`, urut `id` paling kecil).

## 7) Troubleshooting Cepat

- `Bot token/chat id belum diisi.`:
  isi `Bot Token` dan `Chat ID` di halaman Telegram settings.
- `Telegram tidak aktif untuk user ini.`:
  aktifkan checkbox `Aktifkan Pengiriman Reminder`.
- `chat not found`:
  `chat_id` salah, atau bot belum pernah menerima pesan dari chat target.
- `Forbidden: bot was blocked by the user`:
  unblock bot dan kirim `/start` lagi.
