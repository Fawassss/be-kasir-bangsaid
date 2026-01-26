<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
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

            // Get payment method breakdown with count and total
            $paymentMethods = Transaction::query();
            if ($startDate) {
                $paymentMethods->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $paymentMethods->whereDate('created_at', '<=', $endDate);
            }
            $paymentMethodsData = $paymentMethods
                ->select(
                    'payment_method',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_price) as total')
                )
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->map(function ($item) use ($totalTransactions) {
                    $percentage = $totalTransactions > 0
                        ? round(($item->count / $totalTransactions) * 100, 2)
                        : 0;

                    return [
                        'count' => $item->count,
                        'total' => $item->total,
                        'percentage' => $percentage
                    ];
                })
                ->toArray();

            // Calculate average per transaction
            $averagePerTransaction = $totalTransactions > 0
                ? round($totalRevenue / $totalTransactions, 2)
                : 0;

            // Get daily sales for trend chart
            $dailySalesQuery = Transaction::query()
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_price) as revenue')
                );

            if ($startDate) {
                $dailySalesQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $dailySalesQuery->whereDate('created_at', '<=', $endDate);
            }

            $dailySalesData = $dailySalesQuery
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Format date based on range
            $formattedDailySales = $dailySalesData->map(function ($item) use ($startDate, $endDate) {
                $date = \Carbon\Carbon::parse($item->date);

                // Determine format based on date range
                if ($startDate && $endDate) {
                    $start = \Carbon\Carbon::parse($startDate);
                    $end = \Carbon\Carbon::parse($endDate);
                    $diffInDays = $start->diffInDays($end);

                    if ($diffInDays <= 31) {
                        // Same month or <= 31 days: show day only (01, 02, 03)
                        $formattedDate = $date->format('d');
                    } elseif ($diffInDays <= 365) {
                        // <= 1 year: show month (Jan, Feb, Mar)
                        $formattedDate = $date->format('M');
                    } else {
                        // > 1 year: show year (2025, 2026)
                        $formattedDate = $date->format('Y');
                    }
                } else {
                    // Default: show day
                    $formattedDate = $date->format('d');
                }

                return [
                    'date' => $formattedDate,
                    'revenue' => $item->revenue
                ];
            });

            // Get category performance
            $categoryPerformanceQuery = TransactionItem::query()
                ->select(
                    'categories.name as category_name',
                    DB::raw('SUM(transaction_items.quantity) as items_sold'),
                    DB::raw('SUM(transaction_items.subtotal) as revenue')
                )
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereNotNull('transaction_items.product_id')
                ->whereNotNull('categories.id');

            if ($startDate || $endDate) {
                $categoryPerformanceQuery->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->whereDate('created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('created_at', '<=', $endDate);
                    }
                });
            }

            $categoryPerformance = $categoryPerformanceQuery
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('revenue', 'desc')
                ->get();

            // Get top products for dashboard
            $topProductsQuery = TransactionItem::query()
                ->select(
                    'transaction_items.product_id',
                    'transaction_items.product_name',
                    'categories.name as category_name',
                    DB::raw('SUM(transaction_items.subtotal) as revenue')
                )
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereNotNull('transaction_items.product_id');

            if ($startDate || $endDate) {
                $topProductsQuery->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->whereDate('created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('created_at', '<=', $endDate);
                    }
                });
            }

            $topProducts = $topProductsQuery
                ->groupBy('transaction_items.product_id', 'transaction_items.product_name', 'categories.name')
                ->orderBy('revenue', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Sales summary retrieved successfully',
                'data' => [
                    'total_transactions' => $totalTransactions,
                    'total_revenue' => $totalRevenue,
                    'total_items_sold' => $totalItemsSold,
                    'average_per_transaction' => $averagePerTransaction,
                    'payment_methods' => [
                        'cash' => $paymentMethodsData['cash'] ?? ['count' => 0, 'total' => 0, 'percentage' => 0],
                        'qris' => $paymentMethodsData['qris'] ?? ['count' => 0, 'total' => 0, 'percentage' => 0],
                        'debit' => $paymentMethodsData['debit'] ?? ['count' => 0, 'total' => 0, 'percentage' => 0],
                    ],
                    'daily_sales' => $formattedDailySales,
                    'category_performance' => $categoryPerformance,
                    'top_products' => $topProducts
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
     *     path="/api/admin/reports/payment-methods",
     *     summary="Get payment method report",
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
     *         description="Payment method report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment method report retrieved successfully"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_transactions", type="integer", example=395),
     *                 @OA\Property(property="total_revenue", type="integer", example=12180000)
     *             ),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="payment_method", type="string", example="cash"),
     *                     @OA\Property(property="transaction_count", type="integer", example=145),
     *                     @OA\Property(property="total_received", type="integer", example=12500000),
     *                     @OA\Property(property="percentage", type="number", example=36.7)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function paymentMethodReport(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $perPage = $request->get('per_page', 15);

            $query = Transaction::query();

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Get summary
            $totalTransactions = $query->count();
            $totalRevenue = $query->sum('total_price');

            // Get payment method breakdown with pagination
            $paymentMethodsQuery = Transaction::query();
            if ($startDate) {
                $paymentMethodsQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $paymentMethodsQuery->whereDate('created_at', '<=', $endDate);
            }

            $paymentMethodsData = $paymentMethodsQuery
                ->select(
                    'payment_method',
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('SUM(total_price) as total_received')
                )
                ->groupBy('payment_method')
                ->get()
                ->map(function ($item) use ($totalTransactions) {
                    $item->percentage = $totalTransactions > 0
                        ? round(($item->transaction_count / $totalTransactions) * 100, 2)
                        : 0;
                    return $item;
                });

            // Use Laravel's LengthAwarePaginator for proper pagination structure
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $total = $paymentMethodsData->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = $paymentMethodsData->slice($offset, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                $paginatedItems,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment method report retrieved successfully',
                'summary' => [
                    'total_transactions' => $totalTransactions,
                    'total_revenue' => $totalRevenue,
                ],
                'data' => $paginator
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment method report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/categories",
     *     summary="Get category sales report",
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
     *         description="Category sales report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category sales report retrieved successfully"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_products_sold", type="integer", example=1045),
     *                 @OA\Property(property="total_revenue", type="integer", example=14000000),
     *                 @OA\Property(property="average_per_item", type="number", example=13397.13)
     *             ),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="Makanan"),
     *                     @OA\Property(property="products_sold", type="integer", example=320),
     *                     @OA\Property(property="transaction_count", type="integer", example=145),
     *                     @OA\Property(property="total_revenue", type="integer", example=8500000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function categoryReport(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $perPage = $request->get('per_page', 15);

            $query = TransactionItem::query()
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('SUM(transaction_items.quantity) as products_sold'),
                    DB::raw('COUNT(DISTINCT transaction_items.transaction_id) as transaction_count'),
                    DB::raw('SUM(transaction_items.subtotal) as total_revenue')
                )
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereNotNull('transaction_items.product_id')
                ->whereNotNull('categories.id');

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

            $allCategories = $query
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('total_revenue', 'desc')
                ->get();

            // Calculate summary from all data
            $totalProductsSold = $allCategories->sum('products_sold');
            $totalRevenue = $allCategories->sum('total_revenue');
            $averagePerItem = $totalProductsSold > 0
                ? round($totalRevenue / $totalProductsSold, 2)
                : 0;

            // Use Laravel's LengthAwarePaginator for proper pagination structure
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $total = $allCategories->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = $allCategories->slice($offset, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                $paginatedItems,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'message' => 'Category sales report retrieved successfully',
                'summary' => [
                    'total_products_sold' => $totalProductsSold,
                    'total_revenue' => $totalRevenue,
                    'average_per_item' => $averagePerItem,
                ],
                'data' => $paginator
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category sales report',
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
            $perPage = $request->get('per_page', 15);

            $query = TransactionItem::query()
                ->select(
                    'transaction_items.product_id',
                    'transaction_items.product_name',
                    'categories.name as category_name',
                    DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                    DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
                    DB::raw('ROUND(SUM(transaction_items.subtotal) / SUM(transaction_items.quantity), 2) as average_price')
                )
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereNotNull('transaction_items.product_id');

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

            $allProducts = $query
                ->groupBy('transaction_items.product_id', 'transaction_items.product_name', 'categories.name')
                ->orderBy('total_quantity', 'desc')
                ->get();

            // Calculate summary from all data
            $totalProductsSold = $allProducts->sum('total_quantity');
            $totalRevenue = $allProducts->sum('total_revenue');
            $averagePerItem = $totalProductsSold > 0
                ? round($totalRevenue / $totalProductsSold, 2)
                : 0;

            // Use Laravel's LengthAwarePaginator for proper pagination structure
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $total = $allProducts->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = $allProducts->slice($offset, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                $paginatedItems,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'message' => 'Product sales report retrieved successfully',
                'summary' => [
                    'total_products_sold' => $totalProductsSold,
                    'total_revenue' => $totalRevenue,
                    'average_per_item' => $averagePerItem,
                ],
                'data' => $paginator
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
