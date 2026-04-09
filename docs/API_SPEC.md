
# API_SPEC.md — API Specification

## Base URL
/api

---

## Accounts

### GET /api/accounts
Get all accounts

### GET /api/accounts/{id}
Get account detail

### POST /api/accounts
Create account

### PUT /api/accounts/{id}
Update account

### DELETE /api/accounts/{id}
Delete account

---

## Account Usage

### POST /api/account-usages/{id}/update
Update usage

Body:
{
  "remaining_percent": 70,
  "reset_at": "2026-04-20 14:00:00"
}

---

## Telegram

### POST /api/telegram/test
Send test message

---

## Notes
- Expired = masa invite / subscription habis
- Tidak mempengaruhi akun utama, hanya akses langganan
