<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    /**
     * The source function wraps its result as {body, respon: {is_success, msg}} — every
     * caller must check respon.is_success and surface respon.msg on failure, per the
     * founder's explicit contract (this wrapper is reused by other sales.* functions too).
     */
    public function index()
    {
        $rows = DB::select('SELECT sales.get_sales_order_for_update_status(null) AS payload');
        $payload = json_decode($rows[0]->payload ?? 'null', true);

        $isSuccess = $payload['respon']['is_success'] ?? false;

        if (! $isSuccess) {
            return response()->json([
                'message' => $payload['respon']['msg'] ?? 'Gagal mengambil data sales order.',
            ], 422);
        }

        return response()->json(['data' => $payload['body'] ?? []]);
    }
}
