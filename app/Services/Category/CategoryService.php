<?php
namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
class CategoryService
{

    public function create(array $data)
    {
        if (isset($data['imag']) && $data['imag'] instanceof \Illuminate\Http\UploadedFile) {
            $imageFile = $data['imag'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'categories/' . $imageName;

            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

            // استخدم `url()` بدلاً من `asset()` إذا كنت تستخدم route مخصص
            $data['imag'] = url('api/storage/' . $imagePath);  // مثال: http://127.0.0.1:8000/api/storage/categories/xyz.jpg
        }

        return Category::create($data);
    }

    public function update(Category $category, array $data)
    {
        if (isset($data['imag'])) {
            // إذا كانت الصورة عبارة عن ملف جديد
            if ($data['imag'] instanceof \Illuminate\Http\UploadedFile) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($category->imag) {
                    $this->deleteOldImage($category->imag);
                }

                // تخزين الصورة الجديدة
                $imageFile = $data['imag'];
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'categories/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                $data['imag'] = asset('api/storage/' . $imagePath);
            }
            // إذا كانت القيمة null (إزالة الصورة)
            elseif (is_null($data['imag'])) {
                if ($category->imag) {
                    $this->deleteOldImage($category->imag);
                }
                $data['imag'] = null;
            }
        }

        $category->update($data);
        return $category;
    }

    protected function deleteOldImage(string $imageUrl)
    {
        try {
            $basePath = asset('api/storage');
            $relativePath = str_replace($basePath, '', $imageUrl);
            $relativePath = ltrim($relativePath, '/');

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete old image: " . $e->getMessage());
        }
    }
    public function delete(Category $category)
    {
        return $category->delete();
    }

    public function getAll($type = null)
    {
        if ($type !== null) {
            return Category::where('type', $type)->get();
        }
        return Category::all();
    }

    public function getById($id)
    {
        return Category::findOrFail($id);
    }


    public function getByType(int $type)
    {
        return Category::where('type', $type)->get();
    }
}
