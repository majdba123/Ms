<?php

namespace App\Services\Profile;

use App\Models\Profile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function storeProfile($user, $data)
    {
        // معالجة تخزين الصورة
        if (isset($data['image'])) {
            $imageFile = $data['image'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'profile_image/' . $imageName;
            $imageUrl = asset('storage/profile_image/' . $imageName);
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
            $data['image'] = $imageUrl;
        }

        $data['user_id'] = $user->id;
        return Profile::create($data);
    }

    public function updateProfile($user, $data)
    {
        $profile = Profile::find($user->Profile->id);
        if (!$profile) {
            return null;
        }

        // معالجة تخزين الصورة
        if (isset($data['image'])) {
            // حذف الصورة القديمة
            if ($profile->image) {
                $oldImagePath = str_replace(asset('storage'), 'public', $profile->image);
                Storage::delete($oldImagePath);
            }

            // تخزين الصورة الجديدة
            $imageFile = $data['image'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'profile_image/' . $imageName;
            $imageUrl = asset('storage/profile_image/' . $imageName);
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
            $data['image'] = $imageUrl;
        }

        $profile->update($data);
        return $profile;
    }
}
