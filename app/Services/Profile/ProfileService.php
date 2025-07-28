<?php

namespace App\Services\Profile;

use App\Models\Profile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function storeProfile($user, array $data)
    {
        if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
            $imageFile = $data['image'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'profile_images/' . $imageName;

            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

            // Use url() instead of asset() if you're using custom routes
            $data['image'] = url('api/storage/' . $imagePath);
        }

        $data['user_id'] = $user->id;
        return Profile::create($data);
    }

    public function updateProfile($user, array $data)
    {
        $profile = Profile::find($user->Profile->id);
        if (!$profile) {
            return null;
        }

        if (isset($data['image'])) {
            // If the image is a new file
            if ($data['image'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old image if exists
                if ($profile->image) {
                    $this->deleteOldImage($profile->image);
                }

                // Store new image
                $imageFile = $data['image'];
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'profile_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                $data['image'] = url('api/storage/' . $imagePath);
            }
            // If the value is null (remove image)
            elseif (is_null($data['image'])) {
                if ($profile->image) {
                    $this->deleteOldImage($profile->image);
                }
                $data['image'] = null;
            }
        }

        $profile->update($data);
        return $profile;
    }

    protected function deleteOldImage(string $imageUrl)
    {
        try {
            $basePath = url('api/storage');
            $relativePath = str_replace($basePath, '', $imageUrl);
            $relativePath = ltrim($relativePath, '/');

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete old profile image: " . $e->getMessage());
        }
    }
}
