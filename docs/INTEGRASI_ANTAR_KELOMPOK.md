# Integrasi Authentication (Kelompok 2) dengan Kelompok Lain

## Peta arsitektur

```text
Client
  └── Kelompok 1: API Gateway (:8101)
        ├── Kelompok 2: Authentication (:8102)
        └── Kelompok 3: Course (:8103)
                └── Kelompok 4: Assignment (:8104)
                        └── Kelompok 5: Submission/File (:8105)
                                └── Kelompok 6: Notification (:8106)

Kelompok 7: Monitoring (:8107) memeriksa /health masing-masing service
```

Port `8101–8107` adalah **konvensi contoh dari materi perkuliahan**. Jika menggunakan komputer berbeda, `127.0.0.1` di satu komputer **tidak menunjuk komputer kelompok lain**. Tukar IP/hostname LAN dan port aktual serta pastikan koneksi diizinkan. Jangan membuka demo ini ke internet tanpa pengamanan.

## Kewajiban Kelompok 2

Menjalankan Authentication Service di port 8102 dan menyediakan:

- `POST /api/auth/register` (201), `POST /api/auth/login` (200).
- `GET /api/auth/me`, `GET /api/auth/validate` (200 bila token valid).
- `DELETE /api/auth/logout` (200).
- `GET /health` (200).

**Contoh pengujian validasi untuk kelompok lain:**

```http
GET http://127.0.0.1:8102/api/auth/validate
Authorization: Bearer TOKEN_DARI_LOGIN
```

Respons sukses memberikan `data.valid = true`, `data.user_id`, `data.role`, dan `data.expires_at`. NIM dan prodi tersedia pada respons Register, Login, dan GET `/api/auth/me`; validasi sengaja tetap menggunakan format lama untuk kompatibilitas antarkelompok. Respons gagal `401` berarti akses harus ditolak.

## Yang perlu disepakati dengan Kelompok 1 (Gateway)

Gateway perlu membuat route berikut:

| Route Client → Gateway | Tujuan Authentication |
|---|---|
| `POST /api/auth/register` | `POST http://HOST_AUTH:8102/api/auth/register` |
| `POST /api/auth/login` | `POST http://HOST_AUTH:8102/api/auth/login` |
| `GET /api/auth/me` | `GET http://HOST_AUTH:8102/api/auth/me` |
| `GET /api/auth/validate` | `GET http://HOST_AUTH:8102/api/auth/validate` |
| `DELETE /api/auth/logout` | `DELETE http://HOST_AUTH:8102/api/auth/logout` |

Header `Authorization` harus diteruskan. Gateway tidak boleh mengganti status `401` menjadi `200` pada kondisi error.

## Yang perlu disepakati dengan Kelompok 3 (Course)

Ketika Course mendapat `GET /api/courses` dengan Bearer token dari client, Course dapat memanggil Authentication `GET /api/auth/validate`. Jika respons `200`, Course melayani request. Jika `401`, tolak request. Jika Authentication tidak dapat dihubungi, berikan respons kegagalan dependency (misalnya `503`) dan jangan menganggap token valid.

Contoh alur:

```text
1. POST Gateway /api/auth/login
2. Gateway → Authentication; client menerima access_token
3. GET Gateway /api/courses (Bearer access_token)
4. Gateway → Course
5. Course → Authentication GET /api/auth/validate (Bearer access_token)
6. Authentication → 200 valid=true
7. Course → data mata kuliah → Gateway → Client
```

## Yang perlu disepakati dengan Kelompok 7 (Monitoring)

Monitoring meminta `GET http://HOST_AUTH:8102/health`. `200 OK` membuktikan endpoint merespons, tetapi belum cukup untuk menjamin seluruh jalur login dan penyimpanan sehat. Pengujian login perlu dilakukan secara terpisah bila dibutuhkan.

## Penting

Folder `examples/gateway_demo/` dan `examples/course_demo/` hanya simulasi komunikasi. Saat presentasi integrasi sungguhan, **ganti kedua simulasi dengan service resmi milik kelompok 1 dan 3**. Jangan menyatakan kelompok-kelompok telah terhubung jika belum diuji melalui alamat layanan aslinya.
