<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Stock Management",
 *     description="API for managing product stock (Admin only)"
 * )
 */
class StockController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/stocks",
     *     summary="Get all product stocks with pagination",
     *     tags={"Stock Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by product name",
     *         required=false,
     *         @OA\Schema(type="string", example="Coffee")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by stock status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "normal"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock data retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin access required"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');
            $categoryId = $request->get('category_id');
            $status = $request->get('status');

            $query = Product::with('category')
                ->select('products.*')
                ->selectRaw('(SELECT MAX(created_at) FROM stock_logs WHERE stock_logs.product_id = products.id AND type = "in") as last_restock');

            // Search by name
            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            // Filter by category
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            // Filter by stock status
            if ($status === 'low') {
                $query->where('stock', '<=', 10); // Low stock threshold
            } elseif ($status === 'normal') {
                $query->where('stock', '>', 10);
            }

            // Order by stock (lowest first to highlight critical items)
            $query->orderBy('stock', 'asc');

            $products = $query->paginate($perPage);

            // Add stock status to each product
            $products->getCollection()->transform(function ($product) {
                $product->stock_status = $this->getStockStatus($product->stock);
                return $product;
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock data retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/stocks/{id}",
     *     summary="Get product stock detail by ID",
     *     tags={"Stock Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock detail retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock detail retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $product = Product::with('category')->findOrFail($id);

            // Get stock summary
            $stockSummary = StockLog::where('product_id', $id)
                ->selectRaw('
                    SUM(CASE WHEN type = "in" THEN quantity ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = "out" THEN quantity ELSE 0 END) as total_out,
                    MAX(CASE WHEN type = "in" THEN created_at ELSE NULL END) as last_restock
                ')
                ->first();

            $product->stock_status = $this->getStockStatus($product->stock);
            $product->stock_summary = [
                'total_in' => $stockSummary->total_in ?? 0,
                'total_out' => $stockSummary->total_out ?? 0,
                'last_restock' => $stockSummary->last_restock,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Stock detail retrieved successfully',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/stocks/{id}/adjust",
     *     summary="Adjust product stock manually (Admin only)",
     *     tags={"Stock Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "quantity"},
     *             @OA\Property(property="type", type="string", enum={"in", "out"}, example="in"),
     *             @OA\Property(property="quantity", type="integer", example=20),
     *             @OA\Property(property="description", type="string", example="Restock dari supplier ABC", description="Optional description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock adjusted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock adjusted successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function adjust(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $product = Product::lockForUpdate()->findOrFail($id);

            $type = $request->type;
            $quantity = $request->quantity;
            $description = $request->description ?? 'Manual stock adjustment';

            // Calculate new stock
            if ($type === 'in') {
                $newStock = $product->stock + $quantity;
            } else { // out
                $newStock = $product->stock - $quantity;

                // Validate stock tidak boleh negatif
                if ($newStock < 0) {
                    throw new \Exception("Insufficient stock. Current stock: {$product->stock}, Requested: {$quantity}");
                }
            }

            // Update stock
            $product->stock = $newStock;
            $product->save();

            // Create stock log
            $stockLog = StockLog::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'description' => $description,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'product' => $product->fresh()->load('category'),
                    'stock_log' => $stockLog,
                    'previous_stock' => $product->stock - ($type === 'in' ? $quantity : -$quantity),
                    'new_stock' => $product->stock,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/stocks/{id}/logs",
     *     summary="Get stock change history for a product",
     *     tags={"Stock Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"in", "out"})
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter by start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter by end date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock logs retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function logs(Request $request, $id)
    {
        try {
            // Verify product exists
            $product = Product::findOrFail($id);

            $perPage = $request->get('per_page', 10);
            $type = $request->get('type');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = StockLog::where('product_id', $id);

            // Filter by type
            if ($type && in_array($type, ['in', 'out'])) {
                $query->where('type', $type);
            }

            // Filter by date range
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Order by latest
            $query->orderBy('created_at', 'desc');

            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Stock logs retrieved successfully',
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'current_stock' => $product->stock,
                    ],
                    'logs' => $logs
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to determine stock status
     */
    private function getStockStatus($stock)
    {
        if ($stock <= 5) {
            return 'critical';
        } elseif ($stock <= 10) {
            return 'low';
        } else {
            return 'normal';
        }
    }
}
