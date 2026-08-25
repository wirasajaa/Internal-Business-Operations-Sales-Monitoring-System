<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    /**
     * The source function wraps its result as {body, respon: {is_success, msg}} — every
     * caller must check respon.is_success and surface respon.msg on failure, per the
     * founder's explicit contract (this wrapper is reused by other sales.* functions too).
     *
     * sales.get_sales_order_for_update_status accepts a 13-key filter envelope
     * (find, jenis_order, is_date_so/start_so/end_so, is_date_ad/start_ad/end_ad,
     * finance/ppic/design/purchasing/warehouse/leader_produksi), but confirmed live
     * (2026-08-25) that it never actually parses arg_input into its filter variables —
     * every filter argument is silently ignored no matter what is sent. We still send
     * the full envelope (forward-compatible with a future fix), but the working subset
     * (find, Tgl SO/AD range) is enforced here in PHP instead. jenis_order and the 6
     * department filters are NOT enforced anywhere — their output columns are also
     * hardcoded null by the function itself, so there is no real value to filter on.
     */
    public function index(Request $request)
    {
        $find = $request->query('find');
        $isDateSo = $request->boolean('is_date_so');
        $startSo = $request->query('start_so');
        $endSo = $request->query('end_so');
        $isDateAd = $request->boolean('is_date_ad');
        $startAd = $request->query('start_ad');
        $endAd = $request->query('end_ad');

        $envelope = [
            'body' => [
                'find' => $find,
                'jenis_order' => $request->query('jenis_order'),
                'is_date_so' => $isDateSo,
                'start_so' => $startSo,
                'end_so' => $endSo,
                'is_date_ad' => $isDateAd,
                'start_ad' => $startAd,
                'end_ad' => $endAd,
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

        $data = $this->applyFilters($payload['body'] ?? [], [
            'find' => $find,
            'is_date_so' => $isDateSo,
            'start_so' => $startSo,
            'end_so' => $endSo,
            'is_date_ad' => $isDateAd,
            'start_ad' => $startAd,
            'end_ad' => $endAd,
        ]);

        return response()->json(['data' => array_values($data)]);
    }

    /**
     * The real filtering, since the SQL function itself never applies its own filter
     * arguments (see the note above @index). Only find + date-range have real data to
     * filter on; jenis_order/department filters are intentionally not applied here.
     */
    private function applyFilters(array $rows, array $filters): array
    {
        $find = ($filters['find'] ?? null) !== null && $filters['find'] !== ''
            ? mb_strtolower($filters['find'])
            : null;

        return array_filter($rows, function ($row) use ($filters, $find) {
            if ($find !== null) {
                $haystack = mb_strtolower(($row['transaction_number'] ?? '').' '.($row['customer_name'] ?? ''));
                if (! str_contains($haystack, $find)) {
                    return false;
                }
            }

            if (! empty($filters['is_date_so']) && ! empty($filters['start_so']) && ! empty($filters['end_so'])) {
                if (! $this->dateWithinRange($row['tgl_so'] ?? null, $filters['start_so'], $filters['end_so'])) {
                    return false;
                }
            }

            if (! empty($filters['is_date_ad']) && ! empty($filters['start_ad']) && ! empty($filters['end_ad'])) {
                if (! $this->dateWithinRange($row['tgl_ad'] ?? null, $filters['start_ad'], $filters['end_ad'])) {
                    return false;
                }
            }

            return true;
        });
    }

    private function dateWithinRange(?string $value, string $start, string $end): bool
    {
        if (! $value) {
            return false;
        }

        try {
            return Carbon::parse($value)->startOfDay()
                ->between(Carbon::parse($start)->startOfDay(), Carbon::parse($end)->startOfDay());
        } catch (\Throwable) {
            return false;
        }
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
