# REST API TKJPanel

API token-based (Laravel Sanctum) untuk otomasi & bahan ajar (PRD §9).

## Autentikasi

Buat token di panel: menu **REST API** → beri nama + centang *ability*. Token ditampilkan
**sekali**. Kirim di header:

```
Authorization: Bearer <token>
```

Base URL: `https://<panel>/api/v1`

## Ability

| Ability | Akses |
|---|---|
| `sites:read` | Lihat situs |
| `sites:write` | Buat situs |
| `databases:read` | Lihat database |

Endpoint menolak dengan **403** bila token tidak punya ability yang diperlukan, **401** bila
token tidak valid/absen.

## Endpoint

### `GET /me`
Info pengguna pemilik token.
```bash
curl -H "Authorization: Bearer <token>" https://<panel>/api/v1/me
```
```json
{ "data": { "id": 1, "name": "...", "role": "user", "linux_username": "budi",
  "token_abilities": ["sites:read"] } }
```

### `GET /sites`  ·  `GET /sites/{id}`  *(sites:read)*
Daftar/detail situs dalam lingkup pemilik token.

### `POST /sites`  *(sites:write)*
```bash
curl -X POST -H "Authorization: Bearer <token>" \
     -H "Content-Type: application/json" \
     -d '{"domain":"situs.sch.id","php_version":"8.3"}' \
     https://<panel>/api/v1/sites
```
Membuat situs (dengan enforcement kuota) dan mengantre `create_vhost` ke agent. → **201**.

### `GET /databases`  *(databases:read)*
Daftar database dalam lingkup pemilik token.

## Catatan

- Semua data **dilingkupi** ke pemilik token (super admin/reseller melihat sesuai hierarki).
- Cabut token kapan saja dari menu **REST API**.
