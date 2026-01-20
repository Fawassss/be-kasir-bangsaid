<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Profile",
 *     description="API for managing user profile (Cashier & Admin)"
 * )
 */
class ProfileController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/profile/photo",
     *     summary="Upload or update profile photo",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_photo"},
     *                 @OA\Property(
     *                     property="profile_photo",
     *                     type="string",
     *                     format="binary",
     *                     description="Profile photo file (jpg, jpeg, png, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile photo updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile photo updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="profile_photo", type="string", example="1234567890_abc123.jpg"),
     *                 @OA\Property(property="profile_photo_url", type="string", example="http://localhost:8000/storage/profile_photos/1234567890_abc123.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Delete old photo if exists
            if ($user->profile_photo) {
                $this->deleteProfilePhoto($user->profile_photo);
            }

            // Upload new photo
            $filename = $this->handleProfilePhotoUpload($request->file('profile_photo'));

            $user->update(['profile_photo' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully',
                'data' => [
                    'profile_photo' => $user->profile_photo,
                    'profile_photo_url' => $user->profile_photo_url
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/profile/photo",
     *     summary="Delete profile photo",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile photo deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile photo deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No profile photo to delete"
     *     )
     * )
     */
    public function deletePhoto()
    {
        try {
            $user = Auth::user();

            if (!$user->profile_photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile photo to delete'
                ], 404);
            }

            // Delete photo file
            $this->deleteProfilePhoto($user->profile_photo);

            // Update user record
            $user->update(['profile_photo' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle profile photo upload
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string|null
     */
    private function handleProfilePhotoUpload($file)
    {
        if (!$file) {
            return null;
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/profile_photos', $filename);

        return $filename;
    }

    /**
     * Delete profile photo file
     *
     * @param string|null $filename
     * @return void
     */
    private function deleteProfilePhoto($filename)
    {
        if ($filename && Storage::exists('public/profile_photos/' . $filename)) {
            Storage::delete('public/profile_photos/' . $filename);
        }
    }
}
