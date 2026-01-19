<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="API for sales reports (Admin only)"
 * )
 */
class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/reports/summary",
     *     summary="Get sales summary report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sales summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales summary retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_transactions", type="integer", example=150),
     *                 @OA\Property(property="total_revenue", type="integer", example=5000000),
     *                 @OA\Property(property="total_items_sold", type="integer", example=450),
     *                 @OA\Property(property="payment_methods", type="object",
     *                     @OA\Property(property="cash", type="integer", example=80),
     *                     @OA\Property(property="qris", type="integer", example=50),
     *                     @OA\Property(property="debit", type="integer", example=20)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function summary(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = Transaction::query();

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $totalTransactions = $query->count();
            $totalRevenue = $query->sum('total_price');

            // Get total items sold
            $itemsQuery = TransactionItem::query();
            if ($startDate || $endDate) {
                $itemsQuery->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->whereDate('created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('created_at', '<=', $endDate);
                    }
                });
            }
            $totalItemsSold = $itemsQuery->sum('quantity');

            // Get payment method breakdown
            $paymentMethods = Transaction::query();
            if ($startDate) {
                $paymentMethods->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $paymentMethods->whereDate('created_at', '<=', $endDate);
            }
            $paymentMethodsData = $paymentMethods
                ->select('payment_method', DB::raw('count(*) as count'))
                ->groupBy('payment_method')
                ->pluck('count', 'payment_method')
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Sales summary retrieved successfully',
                'data' => [
                    'total_transactions' => $totalTransactions,
                    'total_revenue' => $totalRevenue,
                    'total_items_sold' => $totalItemsSold,
                    'payment_methods' => [
                        'cash' => $paymentMethodsData['cash'] ?? 0,
                        'qris' => $paymentMethodsData['qris'] ?? 0,
                        'debit' => $paymentMethodsData['debit'] ?? 0,
                    ],
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sales summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/products",
     *     summary="Get product sales report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product sales report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product sales report retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="product_name", type="string", example="Espresso"),
     *                     @OA\Property(property="total_quantity", type="integer", example=50),
     *                     @OA\Property(property="total_revenue", type="integer", example=1250000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function productReport(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = TransactionItem::query()
                ->select(
                    'product_id',
                    'product_name',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(subtotal) as total_revenue')
                )
                ->whereNotNull('product_id');

            if ($startDate || $endDate) {
                $query->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->whereDate('created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('created_at', '<=', $endDate);
                    }
                });
            }

            $products = $query
                ->groupBy('product_id', 'product_name')
                ->orderBy('total_quantity', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Product sales report retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/top-products",
     *     summary="Get top selling products",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of top products to return",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Top products retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="product_name", type="string", example="Espresso"),
     *                     @OA\Property(property="total_quantity", type="integer", example=50),
     *                     @OA\Property(property="total_revenue", type="integer", example=1250000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function topProducts(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = TransactionItem::query()
                ->select(
                    'product_id',
                    'product_name',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(subtotal) as total_revenue')
                )
                ->whereNotNull('product_id');

            if ($startDate || $endDate) {
                $query->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->whereDate('created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('created_at', '<=', $endDate);
                    }
                });
            }

            $products = $query
                ->groupBy('product_id', 'product_name')
                ->orderBy('total_quantity', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Top products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve top products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/daily-sales",
     *     summary="Get daily sales report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daily sales report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daily sales report retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="date", type="string", example="2026-01-15"),
     *                     @OA\Property(property="total_transactions", type="integer", example=25),
     *                     @OA\Property(property="total_revenue", type="integer", example=750000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function dailySales(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = Transaction::query()
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('SUM(total_price) as total_revenue')
                );

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $dailySales = $query
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daily sales report retrieved successfully',
                'data' => $dailySales
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
