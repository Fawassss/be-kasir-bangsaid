<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Landing Configuration",
 *     description="API for managing landing page sections (Admin only)"
 * )
 */
class LandingConfigurationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/landing-configurations",
     *     summary="Get landing page configuration",
     *     tags={"Landing Configuration"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Landing configuration retrieved successfully"
     *     )
     * )
     */
    public function index()
    {
        $config = LandingConfiguration::firstOrCreate(['id' => 1]);
        return response()->json([
            'success' => true,
            'message' => 'Landing configuration retrieved successfully',
            'data' => $config
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/landing-configurations",
     *     summary="Update landing page configuration",
     *     tags={"Landing Configuration"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="hero_image", type="string", format="binary"),
     *                 @OA\Property(property="hero_description", type="string"),
     *                 @OA\Property(property="about_image_1", type="string", format="binary"),
     *                 @OA\Property(property="about_image_2", type="string", format="binary"),
     *                 @OA\Property(property="about_image_3", type="string", format="binary"),
     *                 @OA\Property(property="about_image_4", type="string", format="binary"),
     *                 @OA\Property(property="about_description", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Landing configuration updated successfully"
     *     )
     * )
     */
    public function update(Request $request)
    {
        $config = LandingConfiguration::firstOrCreate(['id' => 1]);

        $validator = Validator::make($request->all(), [
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'hero_description' => 'nullable|string',
            'about_image_1' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'about_image_2' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'about_image_3' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'about_image_4' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'about_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only(['hero_description', 'about_description']);

            $images = [
                'hero_image',
                'about_image_1',
                'about_image_2',
                'about_image_3',
                'about_image_4'
            ];

            foreach ($images as $field) {
                if ($request->hasFile($field)) {
                    // Delete old image if exists
                    if ($config->$field) {
                        // Handle both full URL and relative path formats
                        $oldPath = $config->$field;
                        if (str_starts_with($oldPath, url('storage/'))) {
                            $oldPath = str_replace(url('storage/'), '', $oldPath);
                        }
                        
                        Storage::disk('public')->delete($oldPath);
                    }

                    $file = $request->file($field);
                    $name = time() . '_' . $field . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    // This returns 'landing-configurations/filename.ext'
                    $path = $file->storeAs('landing-configurations', $name, 'public');
                    $data[$field] = $path;
                }
            }

            $config->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Landing configuration updated successfully',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update landing configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
