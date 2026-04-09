
# UI_WIREFRAME.md — UI Structure

## 1. Dashboard

[ Summary Cards ]
- Total Accounts
- Active Subscription
- Expiring Soon
- Expired

[ Table Accounts ]
| Name | Email | Store | Subscription | Expired | 5H | Weekly |

---

## 2. Account List Page

Table:
- Name
- Email
- Store Source
- Subscription Type
- Invite Expired (IMPORTANT: bukan akun, tapi akses langganan)
- Status
- Actions

---

## 3. Account Detail Page

[ Account Info ]
- Name
- Email
- Store
- Subscription
- Invite Expired Date
- Notes

[ Usage Cards ]
- 5 Hour Usage
- Weekly Usage

[ History Table ]

---

## 4. Usage Update Modal

Fields:
- Remaining %
- Reset time

---

## 5. Telegram Settings Page

Fields:
- Bot Token
- Chat ID
- Active toggle

Button:
- Test Message

---

## UX Notes

- "Expired" harus ditampilkan sebagai:
  - "Invite Expired"
  - bukan "Account Expired"

- Badge:
  - Green: Active
  - Yellow: Expiring Soon
  - Red: Expired

- Usage bar:
  - progress bar horizontal
  - warna berubah berdasarkan %

