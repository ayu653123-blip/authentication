# KONTRAK API — Authentication Service (Kelompok 2)

**Base URL:** `http://127.0.0.1:8102` (langsung) atau `/api/auth/*` via API Gateway Kelompok 1.

**Format request/response:** `application/json`. Response sukses memakai objek `data`, kecuali `/health`. Response gagal memakai `error.code` dan `error.message`.

| Method | Endpoint | Tujuan | Otorisasi | Status sukses |
|---|---|---|---|---|
| GET | `/health` | Cek liveness service | Tidak | 200 |
| POST | `/api/auth/register` | Daftar pengguna | Tidak | 201 |
| POST | `/api/auth/login` | Login & token | Tidak | 200 |
| GET | `/api/auth/me` | Profil pengguna | Bearer | 200 |
| GET | `/api/auth/validate` | Verifikasi token untuk service lain | Bearer | 200 |
| DELETE | `/api/auth/logout` | Cabut token sesi | Bearer | 200 |

## POST /api/auth/register

Header `Content-Type: application/json`.

```json
{"name":"Mahasiswa Demo","nim":"240209500001","prodi":"Pendidikan Teknik Informatika dan Komputer","email":"mahasiswa.demo@example.test","password":"Demo12345!"}
```

Sukses `201 Created`:

```json
{"data":{"user_id":"usr-1234567890abcdef","name":"Mahasiswa Demo","nim":"240209500001","prodi":"Pendidikan Teknik Informatika dan Komputer","email":"mahasiswa.demo@example.test","role":"student","created_at":"2026-10-28T12:00:00+00:00"}}
```

`user_id` dan waktu merupakan **contoh** dan berubah pada setiap pendaftaran. Password tidak dimasukkan ke dalam respons. **NIM merupakan string 6–20 digit** (agar angka nol awal tidak hilang), **prodi wajib** berupa string 2–120 karakter. Nama, NIM, prodi, email, dan password wajib diisi. NIM dan email harus unik. Untuk akun lama yang dibuat sebelum versi ini, `nim` dan `prodi` mungkin masih `null`; daftar dengan identitas baru untuk menguji fitur baru.

## POST /api/auth/login

```json
{"email":"mahasiswa.demo@example.test","password":"Demo12345!"}
```

Sukses `200 OK`:

```json
{"data":{"access_token":"<token-acak>","token_type":"Bearer","expires_in":3600,"user":{"user_id":"usr-1234567890abcdef","name":"Mahasiswa Demo","nim":"240209500001","prodi":"Pendidikan Teknik Informatika dan Komputer","email":"mahasiswa.demo@example.test","role":"student","created_at":"2026-10-28T12:00:00+00:00"}}}
```

Gunakan nilai token sebenarnya dari respons login pada `Authorization: Bearer <token>`. Jangan gunakan literal `<token-acak>`.

## GET /api/auth/me

Header `Authorization: Bearer <token>`.

Sukses `200 OK`: `{"data":{"user_id":"usr-...","name":"Mahasiswa Demo","nim":"240209500001","prodi":"Pendidikan Teknik Informatika dan Komputer","email":"mahasiswa.demo@example.test","role":"student","created_at":"..."}}`.

## GET /api/auth/validate

Header `Authorization: Bearer <token>`.

Sukses `200 OK`:

```json
{"data":{"valid":true,"user_id":"usr-1234567890abcdef","role":"student","expires_at":"2026-10-28T13:00:00+00:00"}}
```

Token hilang, salah, kedaluwarsa, atau sudah logout → `401 Unauthorized`. **Endpoint validasi tetap mengirim `valid`, `user_id`, `role`, dan `expires_at`** agar tetap kompatibel dengan Course/Gateway. Data NIM dan prodi tersedia pada respons register, login, serta GET `/api/auth/me`.

## DELETE /api/auth/logout

Header `Authorization: Bearer <token>`.

Sukses `200 OK`: `{"data":{"message":"Logout berhasil. Token tidak berlaku lagi."}}`.

## GET /health

Sukses `200 OK`: `{"service":"authentication-service","status":"ok","version":"1.0"}`.

## Error

| Status | code | Keterangan |
|---|---|---|
| 400 | `missing_field` | Ada field wajib tidak dikirim |
| 400 | `invalid_field` | Nilai nama, NIM, prodi, email, atau password tidak valid |
| 400 | `invalid_json` | Body bukan objek JSON valid |
| 401 | `invalid_credentials` | Email atau password salah |
| 401 | `missing_token` | Header Bearer token tidak ada |
| 401 | `invalid_token` | Token tidak ditemukan, kedaluwarsa, atau telah dicabut |
| 404 | `endpoint_not_found` | Path tidak tersedia |
| 405 | `method_not_allowed` | HTTP method salah |
| 409 | `email_exists` | Akun dengan email sama telah terdaftar |
| 409 | `nim_exists` | NIM sudah terdaftar |
| 413 | `request_too_large` | Body JSON di atas 16 KiB |
| 415 | `unsupported_media_type` | Content-Type bukan application/json |
| 500 | `internal_error` | Gangguan internal/penyimpanan |

Contoh response gagal:

```json
{"error":{"code":"invalid_credentials","message":"Email atau password salah."}}
```

## Catatan integrasi

- Gateway Kelompok 1 meneruskan method, path, body JSON, header `Content-Type`, dan header `Authorization` tanpa mengubah format.
- Course/Assignment/Submission Service dapat menggunakan `GET /api/auth/validate` untuk verifikasi token yang diterima.
- Monitoring Kelompok 7 dapat memanggil `GET /health`.
- Kontrak ini tidak menyediakan akun admin yang dapat didaftarkan publik, tidak menggunakan database bersama, dan tidak memberi token akses kepada service lain tanpa request autentikasi.
