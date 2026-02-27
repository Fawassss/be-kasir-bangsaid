<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Transactions",
 *     description="API for managing transactions (Cashier & Admin)"
 * )
 */
class TransactionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/transactions",
     *     summary="Get all transactions with pagination",
     *     tags={"Transactions"},
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
     *         name="date",
     *         in="query",
     *         description="Filter by specific date (Y-m-d) - for single day filter",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-21")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter by start date (Y-m-d) - for date range",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter by end date (Y-m-d) - for date range",
     *         required=false,
     *         @OA\Schema(type="string", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method",
     *         required=false,
     *         @OA\Schema(type="string", enum={"cash", "qris", "debit"})
     *     ),
     *     @OA\Parameter(
     *         name="cashier_id",
     *         in="query",
     *         description="Filter by cashier ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by order number, cashier name, or cashier username",
     *         required=false,
     *         @OA\Schema(type="string", example="fawas")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transactions retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $date = $request->get('date');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $paymentMethod = $request->get('payment_method');
            $cashierId = $request->get('cashier_id');
            $search = $request->get('search');

            $query = Transaction::with(['cashier:id,name,username', 'items']);

            // Filter by specific date (single day) - takes priority
            if ($date) {
                $query->whereDate('created_at', '=', $date);
            }
            // Filter by date range (if no specific date is provided)
            else {
                if ($startDate) {
                    $query->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->whereDate('created_at', '<=', $endDate);
                }
            }

            // Filter by payment method
            if ($paymentMethod && in_array($paymentMethod, ['cash', 'qris', 'debit'])) {
                $query->where('payment_method', $paymentMethod);
            }

            // Filter by cashier ID
            if ($cashierId) {
                $query->where('cashier_id', $cashierId);
            }

            // Search by order number, cashier name, or cashier username
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('cashier', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('username', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Order by latest
            $query->orderBy('created_at', 'desc');

            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transactions",
     *     summary="Create a new transaction",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_name", "payment_method", "items"},
     *             @OA\Property(property="customer_name", type="string", example="Budi Santoso", description="Customer's name"),
     *             @OA\Property(property="payment_method", type="string", enum={"cash", "qris", "debit"}, example="cash"),
     *             @OA\Property(property="cash_received", type="integer", example=100000, description="Required if payment_method is cash"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1, description="Product ID (null for custom items)"),
     *                     @OA\Property(property="is_custom", type="boolean", example=false, description="Is this a custom item?"),
     *                     @OA\Property(property="custom_name", type="string", example="Gule Setengah Porsi", description="Required if is_custom=true"),
     *                     @OA\Property(property="custom_price", type="integer", example=15000, description="Required if is_custom=true"),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="note", type="string", example="Extra sugar")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transaction created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transaction created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'payment_method' => 'required|in:cash,qris,debit',
            'cash_received' => 'required_if:payment_method,cash|nullable|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.is_custom' => 'nullable|boolean',
            'items.*.custom_name' => 'required_if:items.*.is_custom,true|string|max:255',
            'items.*.custom_price' => 'required_if:items.*.is_custom,true|integer|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
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

            $totalPrice = 0;
            $itemsData = [];

            // Validate stock and calculate total
            foreach ($request->items as $item) {
                // Check if this is a custom item
                if (isset($item['is_custom']) && $item['is_custom'] === true) {
                    // Custom item - no product_id needed
                    $subtotal = $item['custom_price'] * $item['quantity'];
                    $totalPrice += $subtotal;

                    $itemsData[] = [
                        'product' => null, // No product reference
                        'product_name' => $item['custom_name'],
                        'price' => $item['custom_price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                        'note' => $item['note'] ?? null,
                    ];
                } else {
                    // Regular product item - existing logic
                    if (!isset($item['product_id'])) {
                        throw new \Exception("Product ID is required for non-custom items");
                    }

                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if (!$product) {
                        throw new \Exception("Product with ID {$item['product_id']} not found");
                    }

                    if (!$product->is_active) {
                        throw new \Exception("Product '{$product->name}' is not active");
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product '{$product->name}'. Available: {$product->stock}, Requested: {$item['quantity']}");
                    }

                    $subtotal = $product->price * $item['quantity'];
                    $totalPrice += $subtotal;

                    $itemsData[] = [
                        'product' => $product,
                        'product_name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                        'note' => $item['note'] ?? null,
                    ];
                }
            }

            // Validate cash payment
            $cashReceived = null;
            $changeAmount = null;

            if ($request->payment_method === 'cash') {
                $cashReceived = $request->cash_received;

                if ($cashReceived < $totalPrice) {
                    throw new \Exception("Insufficient cash. Total: {$totalPrice}, Received: {$cashReceived}");
                }

                $changeAmount = $cashReceived - $totalPrice;
            }

            // Generate order number
            $orderNumber = Transaction::generateOrderNumber();

            // Create transaction
            $transaction = Transaction::create([
                'order_number' => $orderNumber,
                'customer_name' => $request->customer_name,
                'cashier_id' => Auth::id(),
                'payment_method' => $request->payment_method,
                'total_price' => $totalPrice,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
            ]);

            // Create transaction items and reduce stock
            foreach ($itemsData as $itemData) {
                // Create transaction item
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $itemData['product'] ? $itemData['product']->id : null,
                    'product_name' => $itemData['product_name'],
                    'price' => $itemData['price'],
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $itemData['subtotal'],
                    'note' => $itemData['note'],
                ]);

                // Only reduce stock if this is a regular product (not custom)
                if ($itemData['product']) {
                    $product = $itemData['product'];

                    // Reduce stock
                    $product->decrement('stock', $itemData['quantity']);

                    // Create stock log
                    StockLog::create([
                        'product_id' => $product->id,
                        'type' => 'out',
                        'quantity' => $itemData['quantity'],
                        'description' => "Transaction: {$orderNumber}",
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $transaction->load(['cashier:id,name,username', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/{id}",
     *     summary="Get transaction by ID",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transaction retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $transaction = Transaction::with(['cashier:id,name,username', 'items.product'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => $transaction
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
