# Mini-LMS — Authentication Service (Kelompok 2)

## Deskripsi

Authentication Service merupakan bagian dari proyek Mini-LMS yang menangani **pendaftaran mahasiswa (nama, NIM, prodi, email, password), login, verifikasi token, profil pengguna, dan logout** melalui HTTP API. Service ini dibuat menggunakan **PHP** dan menyimpan data prototipe pada berkas JSON lokal. Pengguna mengakses sistem melalui API Gateway (Kelompok 1), sedangkan service lain dapat memeriksa token menggunakan endpoint validasi pada Authentication Service.

## Ruang Lingkup dan Fitur

- **POST `/api/auth/register`** — mendaftarkan akun mahasiswa baru dengan NIM dan prodi.
- **POST `/api/auth/login`** — memeriksa email dan password lalu memberikan Bearer token (tanpa mengisi NIM/prodi saat login).
- **GET `/api/auth/me`** — mengambil profil pengguna yang sedang login, termasuk NIM dan prodi.
- **GET `/api/auth/validate`** — memvalidasi token untuk kebutuhan service lain, misalnya Course Service.
- **DELETE `/api/auth/logout`** — mencabut token yang sedang digunakan.
- **GET `/health`** — memastikan service dapat merespons (untuk Monitoring Service).

Dengan demikian terdapat operasi **POST, GET, dan DELETE**, memenuhi ketentuan minimal tiga fungsi API.

## Teknologi

PHP **7.4 atau lebih baru**, XAMPP (menggunakan PHP dari XAMPP), HTTP, JSON, dan Postman. Apache dan MySQL **tidak wajib**. `password_hash()` digunakan untuk hash password dan token yang disimpan hanya berupa hash SHA-256. PHP built-in web server digunakan hanya untuk pengembangan dan demonstrasi lokal.

## Struktur Proyek

```text
authentication/
├── public/
│   └── index.php
├── src/
│   └── functions.php
├── data/
│   └── .gitkeep            # users.json dan sessions.json dibuat otomatis
├── postman/
│   └── Authentication_Mini_LMS.postman_collection.json
├── screenshots/             # Bukti uji dari Postman (diisi sendiri)
├── docs/
│   ├── KONTRAK_API.md
│   ├── INTEGRASI_ANTAR_KELOMPOK.md
│   └── SKENARIO_DEMO_DOSEN.md
├── examples/
│   ├── gateway_demo/        # Simulasi, bukan kode Kelompok 1
│   └── course_demo/         # Simulasi, bukan kode Kelompok 3
├── Jalankan_Authentication.bat
├── .gitignore
└── README.md
```

## Cara Menjalankan Authentication Service

1. Ekstrak folder `authentication` ke `C:\xampp\htdocs\authentication`.
2. Buka `C:\xampp\htdocs\authentication`.
3. Klik dua kali `Jalankan_Authentication.bat`. **Biarkan CMD tetap terbuka**.
4. Buka `http://127.0.0.1:8102/health` di Postman atau browser. Respons yang diharapkan:

```json
{"service":"authentication-service","status":"ok","version":"1.0"}
```

Cara manual dari Command Prompt:

```bat
cd /d C:\xampp\htdocs\authentication
C:\xampp\php\php.exe -S 127.0.0.1:8102 -t public public/index.php
```

**Base URL Authentication (langsung):** `http://127.0.0.1:8102`.

## Pengujian melalui Postman

**Perhatian setelah upgrade:** Jika sebelumnya sudah mengimpor collection Postman versi lama, impor collection baru dari ZIP ini dan gunakan collection yang berlabel **(NIM dan Prodi)**. Mengubah request di Postman saja tidak cukup tanpa mengganti PHP pada `public/` dan `src/`.

Impor `postman/Authentication_Mini_LMS.postman_collection.json`. Jalankan sesuai urutan nomor. Pada request login, token otomatis disimpan ke variabel Postman `auth_token` dan dipakai pada request yang membutuhkan `Authorization: Bearer {{auth_token}}`.

| Urutan | Pengujian | Status yang diharapkan |
|---|---|---|
| 00 | GET `/health` | `200 OK` |
| 01 | POST `/api/auth/register` | `201 Created` |
| 02 | POST `/api/auth/login` | `200 OK` dan access token |
| 03 | GET `/api/auth/me` (Bearer) | `200 OK` |
| 04 | GET `/api/auth/validate` (Bearer) | `200 OK`, `valid: true` |
| 05 | DELETE `/api/auth/logout` (Bearer) | `200 OK` |
| 06 | GET `/api/auth/me` sesudah logout | `401 Unauthorized` |

**Catatan:** Email **dan NIM** harus unik. Jika request Register dikirim lagi menggunakan email/NIM yang sama, status `409 Conflict` adalah hasil yang benar. Jika kamu sebelumnya mendaftarkan akun pada versi lama, datanya tetap bisa dipakai untuk login, tetapi NIM dan prodi pada profil akun lama akan bernilai `null`. Untuk mencoba fitur NIM/prodi, daftar akun baru dengan email **dan NIM berbeda**. Jangan menghapus data akun lama tanpa cadangan.

### Contoh pendaftaran

```http
POST http://127.0.0.1:8102/api/auth/register
Content-Type: application/json
```

```json
{
  "name": "Mahasiswa Demo",
  "nim": "240209500001",
  "prodi": "Pendidikan Teknik Informatika dan Komputer",
  "email": "mahasiswa.demo@example.test",
  "password": "Demo12345!"
}
```

### Contoh login

```http
POST http://127.0.0.1:8102/api/auth/login
Content-Type: application/json
```

```json
{
  "email": "mahasiswa.demo@example.test",
  "password": "Demo12345!"
}
```

Respons Register, Login dan `GET /api/auth/me` menampilkan `nim` serta `prodi`; respons `GET /api/auth/validate` tetap mengembalikan `valid`, `user_id`, `role`, dan `expires_at` untuk integrasi antarkelompok. Respons login mengandung `access_token` yang kemudian dipakai pada request terlindungi. **Jangan unggah token asli dan password nyata ke GitHub atau screenshot publik.** Kata sandi di atas hanya untuk demonstrasi lokal.

## Alur Koneksi Antarkelompok

Sesuai arsitektur proyek: **Client → Gateway Kelompok 1 → Authentication Kelompok 2**. Saat client membuka Course, **Course Kelompok 3** dapat bertanya ke Authentication untuk memvalidasi Bearer token. **Monitoring Kelompok 7** memanggil `GET /health` Authentication.

```text
Client / Postman
    │
    ▼
API Gateway Kelompok 1 (contoh port 8101)
    │
    ├── POST/GET/DELETE /api/auth/*  →  Authentication Kelompok 2 (8102)
    │
    └── GET /api/courses           →  Course Kelompok 3 (8103)
                                          │
                                          └── GET 8102/api/auth/validate (Bearer token)

Monitoring Kelompok 7  ─── GET 8102/health ───► Authentication Kelompok 2
```

**Integrasi asli** membutuhkan Gateway Kelompok 1, Course Kelompok 3, dan Monitoring Kelompok 7 yang berjalan pada host/port sebenarnya. Lihat `docs/INTEGRASI_ANTAR_KELOMPOK.md`.

## Demonstrasi Lokal Tanpa Menunggu Kelompok Lain

Folder `examples/` menyediakan **simulasi Gateway dan Course**, hanya untuk mengetes pola komunikasi. **Ini bukan implementasi resmi dari kelompok 1 atau 3.**

1. Jalankan `Jalankan_Authentication.bat` pada port **8102**.
2. Jalankan `examples/gateway_demo/Jalankan_Gateway_Demo.bat` pada port **8101**.
3. Jalankan `examples/course_demo/Jalankan_Course_Demo.bat` pada port **8103**.
4. Di Postman, gunakan folder **Demo Integrasi Gateway → Course** di collection: register, login, lalu `GET /api/courses` dengan token melalui Gateway.
5. Course Demo akan memanggil Authentication untuk validasi token. Setelah logout, akses Course dengan token lama ditolak (`401`).

Langkah terperinci ada di `docs/SKENARIO_DEMO_DOSEN.md`.

## Penyimpanan dan Keamanan Dasar

- `data/users.json` menyimpan ID, nama, NIM, prodi, email, peran mahasiswa, serta **hash password**, bukan password asli.
- `data/sessions.json` menyimpan **hash token** dan waktu kedaluwarsa. Token berlaku selama **3600 detik (1 jam)** atau sampai logout.
- Data JSON ditempatkan di **luar folder `public/`** sehingga tidak dilayani sebagai file statis oleh web server pengembangan.
- `.gitignore` mencegah data akun dan sesi lokal ikut diunggah ke GitHub.
- Data disimpan memakai file lock sederhana untuk mengurangi benturan ketika beberapa request menulis bersamaan.

## Batasan

Ini adalah **prototipe untuk tugas kuliah**, bukan sistem autentikasi produksi. Belum menerapkan database, HTTPS, pengiriman email verifikasi, reset password, rate limiting, otorisasi granular per peran, audit terpusat, ataupun integrasi layanan produksi. Penggunaan token Bearer memerlukan HTTPS jika nanti diterapkan melalui jaringan publik. PHP built-in server sebaiknya dipakai untuk pengujian lokal saja.

## Identitas & kontribusi kelompok

Isi sesuai hasil kerja sebenarnya sebelum pengumpulan. Contoh pembagian peran yang dapat disesuaikan: **(1) implementasi endpoint PHP, (2) integrasi Gateway/validasi token, (3) pengujian Postman serta dokumentasi**. Nama/NIM dan tugas setiap anggota perlu dicatat secara akurat; pembagian tersebut belum dinyatakan sebagai kontribusi anggota tertentu.

Bukti pengujian Postman dapat dimasukkan ke folder `screenshots/` setelah dilakukan di komputer kelompok.
