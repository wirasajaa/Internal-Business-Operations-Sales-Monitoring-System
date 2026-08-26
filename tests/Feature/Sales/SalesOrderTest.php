<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    /*
     * The test suite runs against in-memory SQLite (phpunit.xml), so the real
     * `sales.get_sales_order_for_update_status` Postgres function cannot be called
     * here — it was verified live against the real Postgres DB during development
     * (see artifacts/test/sales-order-real-data-wiring-qa-evidence.md). These tests
     * mock DB::select to exercise this controller's own logic: the respon.is_success
     * check and respon.msg surfacing, which is the actual new code this slice adds.
     */
    private function makeUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create(['password' => 'password', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name]));
        }

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    /**
     * @index fires a second read-only query (sales_order_progress) whenever the response
     * body is non-empty — this mocks that second call so existing-order tests don't need
     * to care about the department-status-override feature specifically.
     */
    private function mockProgressLookup(array $rows = []): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::pattern('/FROM sales\.sales_order_progress/'), \Mockery::any())
            ->andReturn($rows);
    }

    public function test_returns_body_when_source_reports_success(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => [['id' => 'SO001', 'transaction_number' => 'TRX-001', 'created_by' => 'wira']],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);
        $this->mockProgressLookup();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', 'SO001');
    }

    public function test_surfaces_source_error_message_when_not_success(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => null,
            'respon' => ['is_success' => false, 'msg' => 'Terjadi kesalahan pada sumber data.'],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Terjadi kesalahan pada sumber data.');
    }

    /*
     * sales.get_sales_order_for_update_status is confirmed fixed (2026-08-25) — it now
     * really applies its own filter arguments, so this controller just forwards query
     * params into the envelope and trusts the function's result as-is (no PHP-side
     * re-filtering). These tests assert the envelope is built correctly from query params,
     * using a Mockery argument matcher on the DB::select binding (the actual filtering
     * behavior itself was verified live against the real Postgres DB, not here).
     */
    private function assertEnvelopeBody(callable $assertion, ?string $payload = null): void
    {
        $payload ??= json_encode(['body' => [], 'respon' => ['is_success' => true, 'msg' => '']]);

        DB::shouldReceive('select')
            ->once()
            ->with(
                'SELECT sales.get_sales_order_for_update_status(?::json) AS payload',
                \Mockery::on(function ($bindings) use ($assertion) {
                    $body = json_decode($bindings[0], true)['body'] ?? [];

                    return $assertion($body);
                })
            )
            ->andReturn([(object) ['payload' => $payload]]);
    }

    public function test_forwards_find_and_date_range_query_params_into_the_sql_envelope(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->assertEnvelopeBody(fn ($body) => $body['find'] === 'Pelanggan A'
            && $body['is_date_so'] === true
            && $body['start_so'] === '2026-08-01'
            && $body['end_so'] === '2026-08-10'
            && $body['is_date_ad'] === false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?find=Pelanggan+A&is_date_so=1&start_so=2026-08-01&end_so=2026-08-10')
            ->assertStatus(200);
    }

    public function test_forwards_department_status_query_params_into_the_sql_envelope(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->assertEnvelopeBody(fn ($body) => $body['finance'] === 'BGS-260824-00000002'
            && $body['ppic'] === 'kosong'
            && $body['design'] === 'semua');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?finance=BGS-260824-00000002&ppic=kosong')
            ->assertStatus(200);
    }

    public function test_department_status_query_params_default_to_semua_when_absent(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->assertEnvelopeBody(function ($body) {
            foreach (['finance', 'ppic', 'design', 'purchasing', 'warehouse', 'leader_produksi'] as $key) {
                if (($body[$key] ?? null) !== 'semua') {
                    return false;
                }
            }

            return true;
        });

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(200);
    }

    public function test_returns_source_body_as_is_without_php_side_refiltering(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => [
                ['id' => 'SO001', 'transaction_number' => 'TRX-001'],
                ['id' => 'SO002', 'transaction_number' => 'TRX-002'],
            ],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);
        $this->mockProgressLookup();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?find=irrelevant-since-the-sql-function-filters-now')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /*
     * sales.get_sales_order_for_update_status aliases finance/ppic/etc. from
     * sales_order_progress.id instead of .status_id (confirmed live 2026-08-25) — @index
     * overrides those 6 fields with the real status_id via one extra read-only query,
     * scoped to only the order ids already in the response.
     */
    public function test_overrides_department_status_fields_with_the_real_status_id(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => [
                // These finance/ppic values simulate the function's own (wrong) pa.id output.
                ['id' => 'SO001', 'transaction_number' => 'TRX-001', 'finance' => 'WRONG-PROGRESS-ROW-ID', 'ppic' => 'WRONG-2'],
            ],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::pattern('/FROM sales\.sales_order_progress/'), ['SO001'])
            ->andReturn([
                (object) ['sales_order_id' => 'SO001', 'type' => 'finance', 'status_id' => 'REAL-STATUS-ID-1'],
            ]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.0.finance', 'REAL-STATUS-ID-1')
            ->assertJsonPath('data.0.ppic', null);
    }

    public function test_skips_the_progress_lookup_query_when_there_are_no_orders(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode(['body' => [], 'respon' => ['is_success' => true, 'msg' => '']]);
        // Only ->once() total is set up — a second (unexpected) query would fail this test,
        // proving no extra DB round-trip happens when there is nothing to look up.
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_listing_sales_orders_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders')
            ->assertStatus(403);
    }

    /*
     * sales.get_master_sales_order_status's own `type` parameter is broken upstream (see
     * SalesOrderController@statuses) — the controller always calls it unfiltered and groups
     * by `type` itself, so these tests mock the unfiltered shape and assert the grouping.
     */
    public function test_order_statuses_are_grouped_by_type(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => [
                ['id' => 'S1', 'type' => 'finance', 'name' => 'DP 10 %', 'sort_order' => 1],
                ['id' => 'S2', 'type' => 'finance', 'name' => 'Lunas', 'sort_order' => 2],
                ['id' => 'S3', 'type' => 'ppic', 'name' => 'Done', 'sort_order' => 1],
            ],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/order-statuses')
            ->assertStatus(200)
            ->assertJsonPath('data.finance.0.name', 'DP 10 %')
            ->assertJsonPath('data.finance.1.name', 'Lunas')
            ->assertJsonPath('data.ppic.0.name', 'Done');
    }

    public function test_order_statuses_surfaces_source_error_message_when_not_success(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => null,
            'respon' => ['is_success' => false, 'msg' => 'Terjadi kesalahan pada sumber data.'],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/order-statuses')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Terjadi kesalahan pada sumber data.');
    }

    public function test_order_statuses_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/order-statuses')
            ->assertStatus(403);
    }

    /*
     * sales.set_new_sales_order_for_update_status uses `respon.result` (NOT
     * `respon.is_success` like every other sales.* function here) — confirmed from its
     * source. Confirmed live 2026-08-25 (rolled-back transaction) that it currently fails
     * in every real case; these tests exercise this controller's own is-success/failure
     * handling via mocks, so the success path is provably correct once the external bug
     * is fixed, without depending on that fix actually existing yet.
     */
    public function test_update_status_succeeds_when_source_reports_result_true(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode(['body' => null, 'respon' => ['result' => true, 'msg' => '']]);

        DB::shouldReceive('select')
            ->once()
            ->with(
                'SELECT sales.set_new_sales_order_for_update_status(?::json) AS payload',
                \Mockery::on(function ($bindings) {
                    $body = json_decode($bindings[0], true)['body'] ?? [];

                    return $body['sales_order_id'] === 'SO001'
                        && $body['type'] === 'finance'
                        && $body['status_id'] === 'BGS-260824-00000002';
                })
            )
            ->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/sales/orders/SO001/status', ['type' => 'finance', 'status_id' => 'BGS-260824-00000002'])
            ->assertStatus(200);
    }

    public function test_update_status_surfaces_error_when_source_reports_result_false(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode(['body' => null, 'respon' => ['result' => false, 'msg' => 'Gagal update.']]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/sales/orders/SO001/status', ['type' => 'finance', 'status_id' => 'BGS-260824-00000002'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gagal update.');
    }

    public function test_update_status_falls_back_to_generic_message_when_source_result_is_null_with_no_msg(): void
    {
        // Reproduces the real confirmed bug: first-time set with no existing progress row
        // makes the source function's own UPDATE...INTO assign NULL (not FALSE), and its
        // own var_msg is never actually set to anything — so respon looks like
        // {"result": null, "msg": ""}. Our fallback message must still apply.
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode(['body' => null, 'respon' => ['result' => null, 'msg' => '']]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/sales/orders/SO001/status', ['type' => 'finance', 'status_id' => 'BGS-260824-00000002'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gagal menyimpan status sales order.');
    }

    public function test_update_status_surfaces_a_clean_error_when_the_source_query_throws(): void
    {
        // Reproduces the real confirmed bug: updating an existing progress row makes the
        // source function's own INSERT (column-count mismatch) throw a hard SQL error.
        // Must not leak a raw 500/stack trace to the client.
        $user = $this->makeUserWithPermissions(['sales.view']);
        DB::shouldReceive('select')->once()->andThrow(new \Illuminate\Database\QueryException(
            'pgsql', 'SELECT sales.set_new_sales_order_for_update_status(?::json) AS payload', [], new \Exception('INSERT has more target columns than expressions')
        ));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/sales/orders/SO001/status', ['type' => 'finance', 'status_id' => 'BGS-260824-00000002'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gagal menyimpan status sales order.');
    }

    public function test_update_status_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/sales/orders/SO001/status', ['type' => 'finance', 'status_id' => 'x'])
            ->assertStatus(403);
    }
}
