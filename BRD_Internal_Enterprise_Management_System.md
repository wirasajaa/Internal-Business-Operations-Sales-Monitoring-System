# Business Requirements Document (BRD)

## Internal Enterprise Management System

**Version:** 1.0  
**Date:** 22 August 2026  
**Release:** Phase 1 / MVP  
**Backend:** Laravel 12  
**Database:** PostgreSQL  
**Frontend:** Vue.js

---

## 1. Executive Summary

Perusahaan membutuhkan aplikasi internal berbasis web untuk mengelola pengguna, hak akses, transaksi penjualan, dan proses approval lintas departemen secara terpusat.

Sistem harus menyediakan kontrol akses berbasis peran (**Role-Based Access Control / RBAC**), meningkatkan visibilitas proses operasional, serta menyediakan satu sumber informasi untuk tracking Sales Order dan status approval antar-departemen.

Dokumen BRD ini mendefinisikan kebutuhan bisnis, batasan scope, aturan bisnis, serta hasil yang diharapkan dari pengembangan sistem.

---

## 2. Business Objectives

1. Memusatkan pengelolaan user, role, dan permission dalam satu sistem.
2. Meningkatkan keamanan melalui RBAC dan permission eksplisit.
3. Menyediakan single source of truth untuk tracking Sales Order.
4. Menampilkan status approval lintas departemen dalam satu tampilan.
5. Mengurangi proses manual dan komunikasi lintas departemen yang tidak terstruktur.
6. Membangun fondasi sistem yang dapat diperluas ke HRIS, payroll, produksi, warehouse, payment, dan finance.

---

## 3. Business Scope

### 3.1 Phase 1 — MVP / High Priority

#### User Management & RBAC

- User authentication.
- User management.
- Role management.
- Permission management.
- Assignment role kepada user.
- Assignment permission kepada role.
- Authorization berdasarkan role dan permission.

#### Sales Management

- Filter berdasarkan Jenis Order.
- Filter rentang Tanggal SO.
- Filter rentang Tanggal AD.
- Filter status approval Finance.
- Filter status approval PPIC.
- Filter status approval Design.
- Filter status approval Purchasing.
- Filter status approval Warehouse.
- Filter status approval Leader Produksi.
- Global search.
- Pagination.
- Page limit dengan default 100 record.

#### Sales Tracking & Approval

Sales table harus menampilkan:

- Nomor SO.
- Nama Sales Person.
- Jenis Order.
- Tanggal SO.
- Tanggal AD.
- Status Finance.
- Status PPIC.
- Status Design.
- Status Purchasing.
- Status Warehouse.
- Status Leader Produksi.

Setiap departemen hanya dapat melakukan perubahan status apabila user memiliki permission yang sesuai.

### 3.2 Phase 2 — Future Scope

Fitur berikut **bukan bagian dari MVP**:

- Data Pegawai / HRIS.
- Payroll.
- Data Produksi.
- Manajemen Operasional.
- Warehouse Management.
- Multi-branch / Multi-warehouse.
- Payment Processing.
- Advanced Finance.

---

## 4. Stakeholders & Business Roles

| Role | Business Responsibility |
|---|---|
| System Administrator | Mengelola user, role, permission, dan konfigurasi akses |
| Sales / Sales Person | Memantau transaksi Sales Order |
| Finance | Memproses approval Finance |
| PPIC | Memproses approval PPIC |
| Design | Memproses approval Design |
| Purchasing | Memproses approval Purchasing |
| Warehouse | Memproses approval Warehouse |
| Leader Produksi | Memproses approval produksi |
| Management | Memantau progress transaksi lintas departemen |

---

## 5. Business Requirements

| ID | Requirement | Priority |
|---|---|---|
| BR-001 | Sistem menyediakan authentication dan user management | Must |
| BR-002 | Sistem menyediakan role dan permission eksplisit | Must |
| BR-003 | Sistem menyediakan data Sales Order terpusat | Must |
| BR-004 | Sistem menyediakan filter Jenis Order, Tgl SO, dan Tgl AD | Must |
| BR-005 | Sistem menyediakan status approval enam departemen | Must |
| BR-006 | Perubahan status dibatasi berdasarkan permission | Must |
| BR-007 | Sistem menyediakan global search dan pagination | Should |
| BR-008 | Default page limit adalah 100 | Should |
| BR-009 | Arsitektur siap dikembangkan ke Phase 2 | Should |

---

## 6. Business Process — Sales

1. Sales Order tersedia di sistem.
2. User membuka modul Sales.
3. User menggunakan filter atau global search untuk menemukan transaksi.
4. Sistem menampilkan transaksi dalam Sales Tracking Table.
5. Departemen yang memiliki kewenangan dapat memperbarui status approval masing-masing.
6. Sistem melakukan authorization sebelum perubahan diterima.
7. Status setiap departemen dapat dipantau secara terpusat.

---

## 7. Business Rules

1. Setiap user harus memiliki identitas login yang unik.
2. Role menentukan kumpulan permission user.
3. Permission harus dapat dibatasi berdasarkan fitur dan/atau aksi.
4. User tidak boleh mengubah approval departemen tanpa permission yang sesuai.
5. Status approval setiap departemen bersifat independen kecuali terdapat aturan workflow yang secara eksplisit menentukan dependensi.
6. Tanggal SO dan Tanggal AD dapat digunakan sebagai filter rentang tanggal.
7. Data Sales wajib menggunakan pagination.
8. Default page limit adalah 100 record.
9. Phase 2 bukan bagian dari acceptance scope Phase 1.
10. Agent atau developer tidak boleh membuat aturan bisnis baru tanpa persetujuan stakeholder.

---

## 8. Non-Functional Business Expectations

### Security

- Authentication wajib diterapkan.
- Authorization harus dilakukan di backend.
- Least privilege harus menjadi prinsip akses.

### Performance

- Dataset besar harus ditangani menggunakan pagination.
- Filtering harus dilakukan di database.
- Implementasi harus menghindari query yang tidak diperlukan.

### Scalability

Arsitektur harus memungkinkan penambahan modul Phase 2 tanpa redesign fundamental.

### Maintainability

Business logic harus dipisahkan dengan jelas antara authentication/RBAC, Sales, dan workflow.

### Auditability

Perubahan penting, khususnya perubahan approval, sebaiknya dapat ditelusuri berdasarkan user dan waktu perubahan.

---

## 9. Phase 1 Success Criteria

Phase 1 dianggap memenuhi tujuan bisnis apabila:

- Admin dapat mengelola user.
- Admin dapat mengelola role.
- Admin dapat mengelola permission.
- User hanya dapat mengakses fitur yang diizinkan.
- User dapat mencari transaksi Sales.
- User dapat melakukan filtering transaksi.
- Sales Order ditampilkan dalam tracking table.
- Enam departemen dapat melihat status approval.
- User yang berwenang dapat memperbarui status approval.
- User tanpa permission tidak dapat memperbarui status.
- Pagination tersedia dengan default 100 record.

---

## 10. Out of Scope — Phase 1

Tidak termasuk dalam MVP:

- HRIS.
- Payroll.
- Production Management.
- Warehouse Management.
- Multi-warehouse.
- Payment Processing.
- Advanced Accounting / Finance.
- Modul lain yang tidak tercantum dalam scope Phase 1.

Technical dependency yang mutlak diperlukan agar fitur Phase 1 berjalan tetap diperbolehkan, tetapi tidak boleh berkembang menjadi fitur bisnis baru tanpa persetujuan.

---

## 11. Assumptions & Dependencies

1. Mockup Sales merupakan referensi UX/UI, bukan kontrak pixel-perfect.
2. Definisi final status approval harus dikonfirmasi sebelum workflow diimplementasikan.
3. Definisi Opsi 1 / Opsi 2 pada mockup harus dikonfirmasi.
4. Sumber data Sales Order harus ditentukan.
5. Struktur master data Sales harus tersedia atau disepakati.
6. Role dan permission final harus disepakati stakeholder.
7. Laravel 12, PostgreSQL, dan Vue.js merupakan technology baseline.

---

## 12. Open Business Questions

Sebelum implementation final, stakeholder perlu menentukan:

1. Apa saja status valid untuk setiap departemen?
2. Apakah approval harus berurutan atau dapat berjalan paralel?
3. Apa arti Opsi 1 dan Opsi 2 pada UI?
4. Siapa yang dapat membuat Sales Order?
5. Apakah Sales Order berasal dari input manual atau sistem eksternal?
6. Field apa saja yang dapat dicari melalui Global Search?
7. Apakah satu user dapat memiliki multiple roles?
8. Apakah permission dapat diberikan langsung ke user?
9. Apakah approval mendukung reject, revision, atau rollback?
10. Apakah perubahan approval wajib menyimpan alasan?
11. Apakah seluruh perubahan approval wajib memiliki audit trail?
12. Berapa pilihan page limit yang diperbolehkan selain 100?
13. Apakah terdapat batas akses berdasarkan branch/company?
14. Apakah dashboard/reporting termasuk Phase 1?

---

## 13. Change Control

Setelah BRD disetujui:

- Perubahan scope harus diperlakukan sebagai Change Request.
- Perubahan harus dievaluasi terhadap timeline, complexity, database impact, security impact, dan performance.
- Fitur Phase 2 tidak boleh dimasukkan ke Phase 1 hanya karena dianggap mudah untuk dibuat.
- Requirement ambigu harus diklarifikasi sebelum implementation.
