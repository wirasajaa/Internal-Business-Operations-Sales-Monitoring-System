# Product Requirements Document (PRD)

## Internal Enterprise Management System — Phase 1 MVP

**Version:** 1.0  
**Date:** 22 August 2026  
**Release:** Phase 1 / MVP  
**Backend:** Laravel 12  
**Database:** PostgreSQL  
**Frontend:** Vue.js

---

## 1. Product Vision

Membangun platform internal modular yang menjadi pusat pengelolaan identitas pengguna, hak akses, dan tracking transaksi penjualan lintas departemen.

MVP harus memprioritaskan:

1. Fondasi RBAC yang aman.
2. User Management.
3. Role & Permission Management.
4. Sales Management.
5. Cross-Departmental Approval Tracking.
6. Search, filtering, dan pagination.
7. Arsitektur yang siap dikembangkan.

---

## 2. Product Principles

### Permission First

Setiap aksi sensitif harus memiliki permission eksplisit.

### Least Privilege

User hanya mendapatkan akses yang diperlukan.

### Modular

Domain User/RBAC dan Sales harus dipisahkan secara konseptual.

### Workflow Visibility

Status lintas departemen harus terlihat pada satu transaksi.

### Performance by Default

Filtering, pagination, indexing strategy, dan query efficiency harus dipertimbangkan sejak awal.

### Server-Side Security

Frontend authorization bukan security boundary. Semua authorization wajib divalidasi di backend.

### Future Ready

Phase 1 harus dapat berkembang ke Phase 2 tanpa coupling yang tidak perlu.

---

## 3. User Personas

| Persona | Goal | Key Actions |
|---|---|---|
| Administrator | Mengontrol akses sistem | CRUD user, role, permission |
| Sales User | Memantau transaksi | Search, filter, melihat status |
| Department Approver | Memproses workflow | Melihat transaksi dan update status department |
| Management | Monitoring | Melihat progress transaksi |

---

# 4. Functional Requirements

## 4.1 Authentication & User Management

### FR-AUTH-001 — Login

User dapat login menggunakan credential yang valid.

**Acceptance Criteria:**

- Credential valid menghasilkan authenticated session.
- Credential invalid ditolak.
- Error response tidak membocorkan informasi sensitif.

### FR-AUTH-002 — Logout

User dapat logout.

**Acceptance Criteria:**

- Session/token diinvalidasi.
- Endpoint terproteksi tidak dapat digunakan setelah logout.

### FR-USER-001 — User List

Administrator dapat melihat daftar user.

### FR-USER-002 — Create User

Administrator dapat membuat user baru.

### FR-USER-003 — Update User

Administrator dapat memperbarui data user.

### FR-USER-004 — Activate / Deactivate User

Administrator dapat mengaktifkan atau menonaktifkan user.

User nonaktif tidak boleh memperoleh akses sesuai policy authentication.

---

## 4.2 Role & Permission Management

### FR-RBAC-001 — Role Management

Administrator dapat:

- Create role.
- Read role.
- Update role.
- Delete role jika tidak melanggar data integrity.

### FR-RBAC-002 — Permission Management

Administrator dapat:

- Create permission.
- Read permission.
- Update permission.
- Delete permission jika tidak melanggar dependency.

### FR-RBAC-003 — Assign Permission to Role

Administrator dapat menghubungkan permission dengan role.

### FR-RBAC-004 — Assign Role to User

Administrator dapat memberikan role kepada user.

### FR-RBAC-005 — Backend Authorization

Backend harus menolak request apabila user tidak memiliki permission.

### FR-RBAC-006 — Frontend Authorization

Frontend harus menyembunyikan atau men-disable action yang tidak diizinkan.

Frontend authorization hanya merupakan UX control dan bukan security boundary.

---

# 5. Sales Management

## 5.1 Filter & Search

### FR-SALES-001 — Jenis Order

User dapat memfilter berdasarkan Jenis Order.

### FR-SALES-002 — Tanggal SO

User dapat melakukan filter berdasarkan date range:

- Start Date.
- End Date.

Filter harus diperlakukan sebagai satu rentang tanggal.

### FR-SALES-003 — Tanggal AD

User dapat melakukan filter berdasarkan date range:

- Start Date.
- End Date.

### FR-SALES-004 — Finance Status

User dapat memfilter berdasarkan status Finance.

### FR-SALES-005 — PPIC Status

User dapat memfilter berdasarkan status PPIC.

### FR-SALES-006 — Design Status

User dapat memfilter berdasarkan status Design.

### FR-SALES-007 — Purchasing Status

User dapat memfilter berdasarkan status Purchasing.

### FR-SALES-008 — Warehouse Status

User dapat memfilter berdasarkan status Warehouse.

### FR-SALES-009 — Leader Produksi Status

User dapat memfilter berdasarkan status Leader Produksi.

### FR-SALES-010 — Global Search

User dapat melakukan pencarian keyword terhadap field yang telah ditentukan untuk Sales.

Field yang termasuk Global Search harus dikonfirmasi sebelum implementation final.

### FR-SALES-011 — Page Limit

Default page limit:

```text
100 records
```

Pilihan limit lainnya harus ditentukan melalui product configuration.

### FR-SALES-012 — Pagination

Sales list wajib menggunakan pagination.

Frontend tidak boleh meminta seluruh dataset sekaligus.

---

# 6. Sales Tracking Table

## 6.1 Core Sales Columns

| ID | Column | Requirement |
|---|---|---|
| FR-TABLE-001 | Sales | Anchor/parent transaction |
| FR-TABLE-002 | Nomor SO | Menampilkan identifier Sales Order |
| FR-TABLE-003 | Sales Person | Menampilkan nama Sales Person |
| FR-TABLE-004 | Jenis Order | Menampilkan jenis order |
| FR-TABLE-005 | Tanggal SO | Menampilkan tanggal Sales Order |
| FR-TABLE-006 | Tanggal AD | Menampilkan tanggal AD |

Contoh Nomor SO:

```text
SO-xxxxx
```

Contoh Jenis Order:

```text
Stock
```

---

## 6.2 Approval Columns

| Department | Behavior |
|---|---|
| Finance | Menampilkan status dan control sesuai permission |
| PPIC | Menampilkan status dan control sesuai permission |
| Design | Menampilkan status dan control sesuai permission |
| Purchasing | Menampilkan status dan control sesuai permission |
| Warehouse | Menampilkan status dan control sesuai permission |
| Leader Produksi | Menampilkan status dan control sesuai permission |

Approval control dapat menggunakan dropdown/select sesuai desain UI.

---

# 7. Approval Workflow

MVP menggunakan model **department-based approval state**.

Setiap departemen memiliki state/status sendiri:

```text
Sales Order
 ├── Finance
 ├── PPIC
 ├── Design
 ├── Purchasing
 ├── Warehouse
 └── Leader Produksi
```

## 7.1 Workflow Rules

1. Setiap department memiliki current status.
2. Status department bersifat independen kecuali aturan bisnis menentukan dependency.
3. User hanya dapat mengubah status jika memiliki permission.
4. Authorization wajib dilakukan di backend.
5. Frontend harus menyesuaikan control berdasarkan permission.
6. Update status harus divalidasi.
7. Update status harus transactional.
8. Perubahan status sebaiknya dapat dicatat dalam audit trail.
9. Agent tidak boleh mengarang status atau transition rule yang belum ditentukan.

---

# 8. Recommended Permission Model

Permission baseline yang direkomendasikan:

| Resource | Permissions |
|---|---|
| Users | `users.view`, `users.create`, `users.update`, `users.delete` |
| Roles | `roles.view`, `roles.create`, `roles.update`, `roles.delete` |
| Permissions | `permissions.view`, `permissions.create`, `permissions.update`, `permissions.delete` |
| Sales | `sales.view`, `sales.search`, `sales.update` |
| Finance | `sales.finance.view`, `sales.finance.update` |
| PPIC | `sales.ppic.view`, `sales.ppic.update` |
| Design | `sales.design.view`, `sales.design.update` |
| Purchasing | `sales.purchasing.view`, `sales.purchasing.update` |
| Warehouse | `sales.warehouse.view`, `sales.warehouse.update` |
| Production | `sales.production.view`, `sales.production.update` |

> Permission naming di atas adalah baseline teknis dan harus dikonfirmasi sebelum migration/seeding.

---

# 9. Logical Data Model

## User

Merepresentasikan account pengguna.

## Role

Merepresentasikan kumpulan authorization.

## Permission

Merepresentasikan aksi yang diperbolehkan.

## Sales Order

Merepresentasikan transaksi utama Sales.

Minimal memiliki:

- Sales Order Number.
- Sales Person.
- Order Type.
- SO Date.
- AD Date.

## Sales Approval Status

Merepresentasikan status approval per department.

Minimal terkait dengan:

- Sales Order.
- Department.
- Current Status.
- Updated By.
- Updated At.

## Approval Audit — Recommended

Menyimpan:

- Sales Order.
- Department.
- Actor/User.
- Previous Status.
- New Status.
- Reason, jika diwajibkan.
- Timestamp.

---

# 10. Backend / API Requirements

## 10.1 Authorization

Authorization wajib dilakukan pada backend.

Contoh:

```text
User Request
     |
     v
Authentication
     |
     v
Authorization / Permission Check
     |
     +---- Unauthorized ---> 403
     |
     v
Validation
     |
     v
Business Logic
     |
     v
Database
```

## 10.2 Sales List API

Endpoint Sales List minimal harus mendukung:

- Pagination.
- Page limit.
- Global search.
- Order type filter.
- SO date range.
- AD date range.
- Finance status.
- PPIC status.
- Design status.
- Purchasing status.
- Warehouse status.
- Leader Produksi status.

## 10.3 Sales Approval API

Update approval harus:

1. Authenticate user.
2. Check permission.
3. Validate transaction.
4. Validate target status.
5. Persist update secara transactional.
6. Record audit event jika audit trail diaktifkan.
7. Return consistent API response.

## 10.4 Performance

Backend harus:

- Menghindari N+1 query.
- Menggunakan pagination.
- Melakukan filtering di database.
- Menggunakan indexing berdasarkan query pattern.
- Menghindari loading seluruh dataset.
- Menggunakan eager loading/join secara tepat.
- Menghindari query berulang yang tidak diperlukan.

---

# 11. Frontend / UX Requirements

## Filter Panel

Filter panel berada di bagian atas Sales page.

Minimal berisi:

- Jenis Order.
- Tanggal SO — date range.
- Tanggal AD — date range.
- Finance status.
- PPIC status.
- Design status.
- Purchasing status.
- Warehouse status.
- Leader Produksi status.
- Global Search.
- Page Limit.

## Table

Table menampilkan:

- Sales.
- Nomor SO.
- Sales Person.
- Jenis Order.
- Tanggal SO.
- Tanggal AD.
- Finance.
- PPIC.
- Design.
- Purchasing.
- Warehouse.
- Leader Produksi.

## UI States

Frontend harus memiliki state untuk:

- Loading.
- Empty data.
- API error.
- Validation error.
- Unauthorized action.
- Successful update.
- Failed update.

---

# 12. Non-Functional Requirements

## Security

- Authentication.
- Server-side authorization.
- Input validation.
- Secure session/token handling.
- Protection terhadap common web vulnerabilities.
- Least privilege.

## Performance

Sales table harus menggunakan pagination dan database-level filtering.

Implementasi harus dirancang untuk dataset yang dapat berkembang besar.

## Scalability

Modul harus dapat dikembangkan ke:

- HRIS.
- Payroll.
- Production.
- Warehouse.
- Multi-branch.
- Payment.
- Finance.

## Reliability

Approval update harus atomic dan konsisten.

## Maintainability

Business logic tidak boleh ditempatkan secara berlebihan pada:

- Controller.
- Vue component.
- Database trigger tanpa kebutuhan yang jelas.

Gunakan separation of concerns yang jelas.

## Observability

Sistem harus menyediakan:

- Error logging.
- Application logging.
- Audit logging untuk event bisnis penting jika dibutuhkan.

---

# 13. MVP Acceptance Criteria

MVP dianggap selesai apabila:

1. Admin dapat login.
2. Admin dapat membuat user.
3. Admin dapat mengubah user.
4. Admin dapat mengaktifkan/nonaktifkan user.
5. Admin dapat membuat role.
6. Admin dapat mengelola permission.
7. Permission dapat diberikan kepada role.
8. Role dapat diberikan kepada user.
9. Backend menolak akses tanpa permission.
10. Sales page menampilkan core Sales fields.
11. Sales page menyediakan filter Jenis Order.
12. Sales page menyediakan date range Tgl SO.
13. Sales page menyediakan date range Tgl AD.
14. Sales page menyediakan filter enam status department.
15. Global Search tersedia.
16. Pagination tersedia.
17. Default page limit adalah 100.
18. User dengan permission dapat memperbarui status department.
19. User tanpa permission tidak dapat memperbarui status department melalui API.
20. Status approval tersimpan secara konsisten.
21. Phase 2 tidak menjadi bagian dari acceptance criteria MVP.

---

# 14. Out of Scope

Tidak diimplementasikan dalam Phase 1:

- HRIS.
- Payroll.
- Production Management.
- Warehouse Management.
- Multi-warehouse.
- Payment Processing.
- Advanced Finance.
- Advanced reporting yang belum didefinisikan.
- Integrasi eksternal yang belum disepakati.

---

# 15. AI Agent Implementation Guardrails

Bagian ini merupakan **aturan wajib untuk AI coding agent**.

## 15.1 Scope Control

Agent hanya boleh mengimplementasikan fitur yang termasuk dalam scope aktif.

Agent **tidak boleh**:

- Menambahkan fitur bisnis baru tanpa persetujuan.
- Mengimplementasikan Phase 2 pada Phase 1.
- Mengubah workflow tanpa requirement.
- Membuat role bisnis baru berdasarkan asumsi.
- Membuat status approval baru berdasarkan asumsi.

## 15.2 Ambiguous Requirement

Jika requirement ambigu:

```text
DO NOT GUESS
DO NOT IMPLEMENT BUSINESS ASSUMPTION
ASK FOR CLARIFICATION
```

Contoh:

> Jika status approval Finance belum didefinisikan, agent tidak boleh menentukan sendiri status seperti Pending, Approved, Rejected, atau Revision sebagai aturan final.

## 15.3 Technology Constraint

Gunakan:

```text
Backend  : Laravel 12
Database : PostgreSQL
Frontend : Vue.js
```

Agent tidak boleh mengganti stack tanpa instruksi eksplisit.

## 15.4 Security Constraint

Semua operasi sensitif harus memiliki server-side authorization.

Menyembunyikan tombol di Vue **tidak cukup**.

## 15.5 Architecture Changes

Jika diperlukan perubahan arsitektur besar:

1. Jelaskan masalah.
2. Jelaskan solusi yang diusulkan.
3. Jelaskan impact.
4. Jelaskan alternatif.
5. Tunggu approval sebelum melakukan perubahan yang berdampak besar.

## 15.6 Database Changes

Agent tidak boleh mengubah schema secara sembarangan.

Perubahan database harus:

- Memiliki alasan.
- Memiliki migration.
- Mempertimbangkan existing data.
- Mempertimbangkan foreign key.
- Mempertimbangkan index.
- Mempertimbangkan performance.

---

# 16. Open Questions / Clarifications Required

1. Apa saja status approval valid untuk Finance?
2. Apa saja status approval valid untuk PPIC?
3. Apa saja status approval valid untuk Design?
4. Apa saja status approval valid untuk Purchasing?
5. Apa saja status approval valid untuk Warehouse?
6. Apa saja status approval valid untuk Leader Produksi?
7. Apakah approval berjalan sequential atau parallel?
8. Apa arti Opsi 1 dan Opsi 2 pada mockup?
9. Siapa yang membuat Sales Order?
10. Apakah Sales Order berasal dari sistem eksternal?
11. Field apa saja yang termasuk Global Search?
12. Apakah user dapat memiliki multiple roles?
13. Apakah permission dapat diberikan langsung kepada user?
14. Apakah terdapat rejection?
15. Apakah terdapat revision?
16. Apakah approval dapat di-rollback?
17. Apakah perubahan approval membutuhkan reason?
18. Apakah approval wajib memiliki audit trail?
19. Apakah terdapat branch/company data boundary?
20. Berapa pilihan page limit yang tersedia?
21. Apakah dashboard/report termasuk Phase 1?

---

# 17. Delivery Priority

| Priority | Scope |
|---|---|
| P0 | Authentication |
| P0 | User Management |
| P0 | Role Management |
| P0 | Permission Management |
| P0 | Server-side Authorization |
| P0 | Sales List |
| P0 | Sales Filter |
| P0 | Sales Search |
| P0 | Sales Pagination |
| P0 | Six Department Approval |
| P1 | Approval Audit Trail |
| P1 | Enhanced UX |
| P1 | Advanced filtering/sorting |
| Future | HRIS |
| Future | Payroll |
| Future | Production |
| Future | Warehouse |
| Future | Payment |
| Future | Advanced Finance |

---

# 18. Definition of Done

Feature dianggap selesai apabila:

- Requirement telah diimplementasikan.
- Backend validation tersedia.
- Backend authorization tersedia.
- Database migration tersedia jika diperlukan.
- API response konsisten.
- Frontend state telah ditangani.
- Error handling tersedia.
- Tidak terdapat unauthorized mutation path.
- Test yang relevan tersedia.
- Tidak menambahkan scope di luar PRD.
- Acceptance criteria terpenuhi.

