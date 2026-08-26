# Internal Enterprise Management System (ERP Project)

Aplikasi internal berbasis web untuk mengelola pengguna & hak akses (RBAC), serta tracking Sales Order dan status approval lintas departemen secara terpusat. Dibangun sebagai fondasi yang nantinya bisa diperluas ke modul lain (HRIS, payroll, produksi, warehouse, payment, finance).

Referensi requirement lengkap: `dev-doc/BRD_Internal_Enterprise_Management_System.md` dan `dev-doc/PRD_Internal_Enterprise_Management_System.md` (di root repo scaffold).

## Tech Stack

- **Backend:** Laravel 12 (PHP ^8.2), REST API (`routes/api.php`), auth via [Laravel Sanctum](https://laravel.com/docs/sanctum) (token-based, bukan cookie/session)
- **Otorisasi:** [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) (Role & Permission, model many-to-many standar)
- **Frontend:** Vue 3 (Composition API) + Vue Router + Pinia, ada di folder `frontend/`, dibangun dengan Vite dan di-style dengan Tailwind CSS v4
- **Database:** PostgreSQL — aplikasi ini **tidak berdiri sendiri**: login memvalidasi kredensial ke schema `bpms` (tabel `bpms.users`, fungsi `bpms.validate_login_apps`) dan modul Sales Order memanggil fungsi-fungsi di schema `sales` (`sales.get_sales_order_for_update_status`, `sales.set_new_sales_order_for_update_status`, dll). Kedua schema tersebut **eksternal, sudah ada sebelumnya, dan tidak dibuat oleh migration aplikasi ini** — pastikan database yang dipakai memang sudah memiliknya.

## Prasyarat

Sebelum instalasi, pastikan sudah terpasang:

- PHP >= 8.2 beserta ekstensi standar Laravel, dan Composer
- Node.js (versi LTS terbaru yang didukung Vite 7/8, misal Node 20+) beserta npm
- Akses ke database PostgreSQL yang sudah memiliki schema `bpms` dan `sales` seperti dijelaskan di atas (koneksi `pgsql` di `config/database.php`)

## Instalasi & Command Sebelum Aplikasi Bisa Dijalankan

Jalankan urutan berikut dari root folder `development/erp-project/` **sebelum** memakai `dev.bat`:

```bash
# 1. Install dependency backend (PHP)
composer install

# 2. Siapkan file environment
copy .env.example .env
# lalu isi minimal: DB_CONNECTION=pgsql, DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
# (ke database Postgres yang sudah punya schema bpms + sales), dan FRONTEND_URL
# (default http://localhost:5173, dipakai untuk CORS)

# 3. Generate application key
php artisan key:generate

# 4. Jalankan migration
# Catatan: hanya membuat tabel milik aplikasi ini (users, personal_access_tokens,
# permission/role tables, menus, refresh_tokens) — TIDAK menyentuh schema bpms/sales.
php artisan migrate

# 5. Seed data awal
# Membuat Role "Administrator" + permission dashboard.view.
# Tidak ada user yang dibuat otomatis — identitas user selalu berasal dari
# bpms.users lewat login pertama (lihat "Catatan Login" di bawah).
php artisan db:seed

# 6. Install dependency frontend (dijalankan di dalam folder frontend/)
cd frontend
npm install
cd ..
```

Setelah keenam langkah di atas selesai (dan hanya perlu diulang saat pertama kali setup atau setelah ada migration/dependency baru), aplikasi siap dijalankan.

### Menjalankan Aplikasi

Double-click **`dev.bat`** di root `development/erp-project/` (atau jalankan `powershell -ExecutionPolicy Bypass -File .\dev.ps1` dari terminal).

`dev.bat` akan memanggil `dev.ps1`, yang:
- Membuka backend (`php artisan serve`) di jendela console sendiri → `http://127.0.0.1:8000`
- Membuka frontend (`npm run dev` di `frontend/`) di jendela console sendiri → `http://127.0.0.1:5173`
- Menunggu hingga 25 detik lalu menampilkan status `[OK]`/`[FAIL]` untuk masing-masing port

Akses aplikasi lewat browser di **http://localhost:5173**. Menutup salah satu jendela console (atau Ctrl+C di dalamnya) akan menghentikan service tersebut.

> Gunakan `dev.bat`, bukan `composer run dev` — script `dev` di `composer.json` menjalankan `npm run dev` dari root project (skeleton Vite/Blade bawaan Laravel yang tidak dipakai), bukan aplikasi Vue sesungguhnya yang ada di `frontend/`.

### Catatan Login

Login memvalidasi username/password ke `bpms.validate_login_apps` (bukan tabel user lokal). User baru yang login pertama kali otomatis ter-provisioning ke tabel lokal `users` **tanpa role apa pun**. Agar bisa mengakses menu/fitur, seorang Administrator yang sudah ada perlu menetapkan role lewat halaman **Manajemen Akses > Pengguna** — atau, untuk setup pertama kali (belum ada Administrator sama sekali), assign role `Administrator` secara manual lewat database ke user yang baru login tersebut.

## Fitur yang Tersedia Saat Ini

| Fitur | Halaman | Ringkasan |
|---|---|---|
| **Autentikasi** | `/login` | Login terhadap `bpms.users` (bukan tabel lokal), token Sanctum + refresh token, logout. |
| **Dashboard** | `/dashboard` | Halaman utama setelah login (gated permission `dashboard.view`). |
| **Manajemen Menu** | `/settings/menus` | CRUD menu navigasi, hierarki hingga 3 level (self-referencing parent), permission-gated per menu; endpoint `/api/menus/navigation` menyajikan menu sesuai permission user yang login. |
| **Manajemen Pengguna** | `/settings/users` | List/create/update/activate/deactivate user (tidak ada delete, sesuai PRD). Guard: tidak bisa menonaktifkan diri sendiri. |
| **Manajemen Role** | `/settings/roles` | CRUD role + assign multi-permission. Guard: tidak bisa menghapus role Administrator terakhir dari diri sendiri, atau role yang masih dipakai. |
| **Manajemen Permission** | `/settings/permissions` | CRUD permission. Guard: tidak bisa menghapus permission yang masih dipakai role/menu. |
| **Sales Order Monitoring** | `/sales/orders` | List Sales Order dari data real (`sales.get_sales_order_for_update_status`), dengan filter pencarian, filter tanggal Tgl SO/AD (checkbox + rentang, Tgl SO default tercentang 2 bulan terakhir), dan 6 filter status per departemen (Semua/Kosong/berdasarkan id status). Status per baris bisa diubah langsung (menulis ke `sales.set_new_sales_order_for_update_status`), dengan indikator loading + alert sukses/gagal. Kondisi filter tersimpan di `sessionStorage` per tab. |

Semua halaman (kecuali `/login`) berada di balik `auth:sanctum` dan permission masing-masing; sidebar navigasi dibangun otomatis dari `/api/menus/navigation` sesuai permission user.

**Belum tersedia / di luar scope saat ini:** filter berdasarkan `jenis_order` (belum ada sumber data real), permission tulis khusus untuk Sales Order (saat ini masih memakai `sales.view`), dan modul lain di luar Phase 1 (HRIS, payroll, produksi, warehouse, payment, finance — lihat BRD §3.2).

## Testing

```bash
php artisan test
```

Test suite backend berjalan di atas SQLite in-memory (`phpunit.xml`) — fungsi-fungsi `sales.*`/`bpms.*` yang schema-qualified Postgres tidak bisa dipanggil langsung di test, sehingga sebagian test memakai mock (`DB::shouldReceive` / `DB::partialMock`) untuk memverifikasi query yang dibentuk, bukan hasil live dari database.

```bash
cd frontend
npm run build
```

Untuk memastikan build frontend tidak error sebelum deploy.
