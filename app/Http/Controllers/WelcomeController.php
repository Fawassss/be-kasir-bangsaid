<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WelcomeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api",
     *     summary="Get a welcome text",
     *     tags={"Welcome"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function index() {
        return response()->json(['message' => 'Welcome'], Response::HTTP_OK);
    }
}