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

    public function test_returns_body_when_source_reports_success(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $payload = json_encode([
            'body' => [['id' => 'SO001', 'transaction_number' => 'TRX-001', 'created_by' => 'wira']],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);

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
     * sales.get_sales_order_for_update_status never actually applies its own filter
     * arguments (confirmed live, 2026-08-25 — see plan/sales-order-filter-wiring-
     * 2026-08-25.md), so it always returns the full unfiltered set regardless of what
     * we send. These tests mock that full unfiltered shape and assert the controller's
     * own PHP-side find/date-range filtering (the actual new logic this slice adds).
     */
    private function mockUnfilteredThreeOrders(): void
    {
        $payload = json_encode([
            'body' => [
                ['id' => 'SO001', 'transaction_number' => 'TRX-001', 'customer_name' => 'PT Pelanggan A', 'tgl_so' => '2026-08-01', 'tgl_ad' => '2026-08-15'],
                ['id' => 'SO002', 'transaction_number' => 'TRX-002', 'customer_name' => 'PT Pelanggan B', 'tgl_so' => '2026-08-10', 'tgl_ad' => '2026-08-20'],
                ['id' => 'SO003', 'transaction_number' => 'TRX-003', 'customer_name' => 'CV Lainnya', 'tgl_so' => '2026-09-01', 'tgl_ad' => '2026-09-15'],
            ],
            'respon' => ['is_success' => true, 'msg' => ''],
        ]);
        DB::shouldReceive('select')->once()->andReturn([(object) ['payload' => $payload]]);
    }

    public function test_filters_by_find_against_transaction_number_and_customer_name(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->mockUnfilteredThreeOrders();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?find=Pelanggan+A')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'SO001');
    }

    public function test_filters_by_tgl_so_range(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->mockUnfilteredThreeOrders();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?is_date_so=1&start_so=2026-08-01&end_so=2026-08-10')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'SO001')
            ->assertJsonPath('data.1.id', 'SO002');
    }

    public function test_filters_by_tgl_ad_range(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->mockUnfilteredThreeOrders();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?is_date_ad=1&start_ad=2026-09-01&end_ad=2026-09-30')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'SO003');
    }

    public function test_combines_find_and_date_range_filters_with_and_logic(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->mockUnfilteredThreeOrders();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?find=Pelanggan&is_date_so=1&start_so=2026-08-05&end_so=2026-08-31')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'SO002');
    }

    public function test_filters_matching_nothing_returns_empty_array_not_error(): void
    {
        $user = $this->makeUserWithPermissions(['sales.view']);
        $this->mockUnfilteredThreeOrders();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/sales/orders?find=TidakAda')
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
}
