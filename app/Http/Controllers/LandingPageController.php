<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\LandingConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LandingPageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/landing-page",
     *     summary="Get all data for landing page",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Landing page data retrieved successfully"
     *     )
     * )
     */
    public function index()
    {
        try {
            // 1. Get Landing Configuration (Hero, About)
            $config = LandingConfiguration::firstOrCreate(['id' => 1]);

            // 2. Get Total Products
            $totalProducts = Product::where('is_active', true)->count();

            // 3. Get Favorite Products (Top 5 Best Sellers)
            $favoriteProducts = Product::with('category')
                ->withSum('transactionItems', 'quantity')
                ->where('is_active', true)
                ->orderBy('transaction_items_sum_quantity', 'desc')
                ->limit(5)
                ->get();

            // 4. Get All Products (Special Menu)
            $allProducts = Product::with('category')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Landing page data retrieved successfully',
                'data' => [
                    'cms_content' => $config,
                    'total_products' => $totalProducts,
                    'favorite_products' => $favoriteProducts,
                    'special_menu' => $allProducts,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve landing page data',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
