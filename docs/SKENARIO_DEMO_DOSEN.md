# Skenario Demonstrasi Mini-LMS — Kelompok 2 (Authentication)

## A. Demo fungsi milik Kelompok 2

Siapkan PHP XAMPP dan Postman. Jalankan `Jalankan_Authentication.bat` (port 8102), kemudian impor Postman collection.

| No. | Aksi | Status | Yang ditunjukkan ke dosen |
|---|---|---|---|
| 1 | GET `/health` | 200 | Service hidup dan mengembalikan JSON |
| 2 | POST `/api/auth/register` | 201 | Pengguna baru (NIM dan prodi) berhasil didaftarkan |
| 3 | POST `/api/auth/login` | 200 | Token diterima setelah password benar |
| 4 | GET `/api/auth/me` dengan Bearer | 200 | Profil termasuk NIM/prodi dapat diambil menggunakan token |
| 5 | GET `/api/auth/validate` dengan Bearer | 200 | Token valid untuk service lain |
| 6 | DELETE `/api/auth/logout` | 200 | Token dicabut |
| 7 | GET `/api/auth/me` dengan token lama | 401 | Token lama tidak berlaku |

Uji error tambahan: password salah → `401`; email/NIM pendaftaran duplikat → `409`; request tanpa field `nim` → `400`.

## B. Demo komunikasi lewat Gateway (simulasi)

Lakukan ini hanya bila service resmi dari kelompok lain belum tersedia:

1. Nyalakan tiga terminal terpisah:
   - `authentication/Jalankan_Authentication.bat` → 8102.
   - `authentication/examples/gateway_demo/Jalankan_Gateway_Demo.bat` → 8101.
   - `authentication/examples/course_demo/Jalankan_Course_Demo.bat` → 8103.
2. Gunakan folder **Demo Integrasi Gateway → Course** di Postman.
3. POST `/api/auth/register` melalui Gateway 8101. Bila email contoh sudah terdaftar (409), lanjutkan login.
4. POST `/api/auth/login` melalui Gateway 8101. Salin/otomatis simpan token.
5. GET `/api/courses` melalui Gateway 8101 memakai Bearer token.
6. Jelaskan bahwa Course Demo mengirim GET `/api/auth/validate` ke Authentication 8102 sebelum memberikan data mata kuliah.
7. Logout melalui Gateway. Ulangi GET `/api/courses` dan tunjukkan `401 Unauthorized`.

**Hasil yang diharapkan:** Client → Gateway Demo → Course Demo → Authentication Service dan respons kembali ke client. Contoh ini membuktikan jalur jaringan lokal dan verifikasi token, **belum** membuktikan integrasi dengan kode asli kelompok lain.

## C. Demo bersama antarkelompok (yang diminta dosen)

1. Kelompok 1 menjalankan gateway asli dan memasang route Authentication ke host/port Kelompok 2.
2. Kelompok 2 menjalankan service auth dan menunjukkan route GET, POST, DELETE.
3. Kelompok 3 menjalankan Course asli serta memanggil endpoint validasi token Kelompok 2.
4. Kelompok 7 memanggil `/health` Kelompok 2.
5. Kelompok 4, 5, dan 6 menyesuaikan alur assignment, submission, dan notifikasi dengan service mereka; koneksi antarservice perlu diverifikasi bersama.
6. Tunjukkan **request dan response nyata**, bukan hanya gambar arsitektur.

## Kalimat untuk presentasi

> Kelompok 2 bertanggung jawab menyediakan Authentication Service. Pengguna melakukan registrasi dan login melalui API Gateway. Jika kredensial sesuai, Authentication Service menerbitkan Bearer token. Saat pengguna mengakses Course Service, token diperiksa kembali melalui endpoint validasi Authentication. Pengguna dapat melakukan logout untuk mencabut token. Selain itu, Monitoring Service dapat mengecek status dasar layanan melalui endpoint /health.

## Bukti uji

Rekam/screenshot URL, HTTP method, Body (kecuali password/token nyata), HTTP status code, dan JSON response. Sembunyikan token aktif serta password nyata pada screenshot yang dibagikan secara publik.
