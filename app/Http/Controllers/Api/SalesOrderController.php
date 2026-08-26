<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    /**
     * The source function wraps its result as {body, respon: {is_success, msg}} — every
     * caller must check respon.is_success and surface respon.msg on failure, per the
     * founder's explicit contract (this wrapper is reused by other sales.* functions too).
     *
     * sales.get_sales_order_for_update_status accepts a 13-key filter envelope (find,
     * jenis_order, is_date_so/start_so/end_so, is_date_ad/start_ad/end_ad, finance/ppic/
     * design/purchasing/warehouse/leader_produksi). Confirmed live 2026-08-24 that it
     * silently ignored every filter argument; confirmed live again 2026-08-25 that it has
     * since been fixed externally (find/date-range/department-status filtering, the
     * latter via status id with 'semua'/'kosong' special values, all now work correctly)
     * — so filtering is trusted to the SQL function directly, no PHP-side re-filtering.
     * jenis_order's output is still hardcoded null by the function, so that one filter
     * remains decorative on the frontend regardless of what we send here.
     */
    public function index(Request $request)
    {
        $envelope = [
            'body' => [
                'find' => $request->query('find'),
                'jenis_order' => $request->query('jenis_order'),
                'is_date_so' => $request->boolean('is_date_so'),
                'start_so' => $request->query('start_so'),
                'end_so' => $request->query('end_so'),
                'is_date_ad' => $request->boolean('is_date_ad'),
                'start_ad' => $request->query('start_ad'),
                'end_ad' => $request->query('end_ad'),
                'finance' => $request->query('finance', 'semua'),
                'ppic' => $request->query('ppic', 'semua'),
                'design' => $request->query('design', 'semua'),
                'purchasing' => $request->query('purchasing', 'semua'),
                'warehouse' => $request->query('warehouse', 'semua'),
                'leader_produksi' => $request->query('leader_produksi', 'semua'),
            ],
        ];

        $rows = DB::select('SELECT sales.get_sales_order_for_update_status(?::json) AS payload', [json_encode($envelope)]);
        $payload = json_decode($rows[0]->payload ?? 'null', true);

        $isSuccess = $payload['respon']['is_success'] ?? false;

        if (! $isSuccess) {
            return response()->json([
                'message' => $payload['respon']['msg'] ?? 'Gagal mengambil data sales order.',
            ], 422);
        }

        return response()->json(['data' => $this->withCurrentDepartmentStatuses($payload['body'] ?? [])]);
    }

    /**
     * sales.get_sales_order_for_update_status aliases finance/ppic/design/purchasing/
     * warehouse/leader_produksi from sales_order_progress.id instead of .status_id
     * (confirmed live 2026-08-25, real example: SO001's finance progress row has
     * id=BGS-260825-00000046 but the real current status_id is BGS-260824-00000002) — so
     * those values can never match a sales.master_sales_order_status option, and the
     * per-row select can't pre-select the real current status.
     *
     * Not fixable in the external function from our side. Overrides those 6 fields with
     * the real status_id via exactly one extra read-only query, scoped only to the order
     * ids already in this response (never the whole table, never per-row/N+1) — so this
     * adds a single lightweight query per page load, not one per order.
     */
    private function withCurrentDepartmentStatuses(array $orders): array
    {
        if (empty($orders)) {
            return $orders;
        }

        $orderIds = array_values(array_unique(array_column($orders, 'id')));
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $progress = DB::select(
            "SELECT sales_order_id, type, status_id FROM sales.sales_order_progress WHERE sales_order_id IN ({$placeholders}) AND is_deleted = false",
            $orderIds
        );

        $currentByOrderAndType = [];
        foreach ($progress as $row) {
            $currentByOrderAndType[$row->sales_order_id][$row->type] = $row->status_id;
        }

        $departmentTypes = ['finance', 'ppic', 'design', 'purchasing', 'warehouse', 'leader_produksi'];

        foreach ($orders as &$order) {
            $lookup = $currentByOrderAndType[$order['id']] ?? [];
            foreach ($departmentTypes as $type) {
                $order[$type] = $lookup[$type] ?? null;
            }
        }

        return $orders;
    }

    /**
     * Persists one department's status for one order via sales.set_new_sales_order_for_
     * update_status. This function's wrapper uses `respon.result` (NOT `respon.is_success`
     * like every other sales.* function here — confirmed from its source, a different key
     * per function, not a typo on our side).
     *
     * Confirmed live 2026-08-25 (in a rolled-back transaction, no data changed) that this
     * function currently fails in every possible case:
     *  - First-time set (no existing sales_order_progress row — true for every order right
     *    now): its `UPDATE ... RETURNING TRUE INTO var_is_success` matches 0 rows, so
     *    PL/pgSQL assigns NULL (not FALSE) to var_is_success, so the INSERT branch that
     *    should create the row never runs. Returns {"result": null}, nothing persisted.
     *  - Update of an existing row: the UPDATE itself succeeds, but the function then
     *    unconditionally tries to INSERT anyway, which has a column-count bug (4 target
     *    columns, 3 values) — throws a hard SQL error, rolling back the whole call
     *    (including the UPDATE that had just succeeded), since one function call is one
     *    atomic statement.
     * Wired anyway per the founder's explicit decision (chat, 2026-08-25): this endpoint
     * will surface a clear failure today, and will start working automatically once the
     * schema owner fixes the function — no further code change needed on our side then.
     */
    public function updateStatus(Request $request, string $id)
    {
        $type = (string) $request->input('type');
        $statusId = (string) $request->input('status_id');

        $envelope = [
            'body' => [
                'sales_order_id' => $id,
                'type' => $type,
                'status_id' => $statusId,
            ],
        ];

        try {
            $rows = DB::select('SELECT sales.set_new_sales_order_for_update_status(?::json) AS payload', [json_encode($envelope)]);
        } catch (\Throwable $e) {
            // The "update an existing row" path throws a hard SQL error (column-count bug
            // in the function's own INSERT) rather than returning a graceful respon.result
            // — confirmed live 2026-08-25. Caught here so the frontend always gets a clean
            // JSON error instead of a raw 500/stack trace; the real error is logged for
            // our own visibility, not exposed to the client.
            report($e);

            return response()->json([
                'message' => 'Gagal menyimpan status sales order.',
            ], 422);
        }

        $payload = json_decode($rows[0]->payload ?? 'null', true);
        $result = $payload['respon']['result'] ?? false;

        if (! $result) {
            return response()->json([
                'message' => $payload['respon']['msg'] ?: 'Gagal menyimpan status sales order.',
            ], 422);
        }

        return response()->json(['message' => 'Status berhasil disimpan.']);
    }

    /**
     * Department status options, grouped by `type` (finance/ppic/design/purchasing/
     * warehouse/leader_produksi). The source function's own `type` filter parameter is
     * broken (extracts JSON via `->` instead of `->>`, so it never matches any row) —
     * worked around by fetching everything unfiltered and grouping here instead.
     */
    public function statuses()
    {
        $rows = DB::select('SELECT sales.get_master_sales_order_status(null) AS payload');
        $payload = json_decode($rows[0]->payload ?? 'null', true);

        $isSuccess = $payload['respon']['is_success'] ?? false;

        if (! $isSuccess) {
            return response()->json([
                'message' => $payload['respon']['msg'] ?? 'Gagal mengambil data status sales order.',
            ], 422);
        }

        $grouped = collect($payload['body'] ?? [])->groupBy('type')->map(function ($items) {
            return $items->values();
        });

        return response()->json(['data' => $grouped]);
    }
}
