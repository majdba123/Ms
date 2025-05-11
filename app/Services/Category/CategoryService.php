<?php
namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
class CategoryService
{
    public function create(array $data)
    {
        $imagePath = null;

        if (isset($data['imag']) && $data['imag'] instanceof \Illuminate\Http\UploadedFile) {
            $imageFile = $data['imag'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'categories/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

            // استبدال كائن الصورة بمسار الصورة في المصفوفة
            $data['imag'] = $imagePath ? asset('storage/' . $imagePath) : null;
        }

        return Category::create($data);
    }

    public function update(Category $category, array $data)
    {
        if (isset($data['imag']) && $data['imag'] instanceof \Illuminate\Http\UploadedFile) {
            // حذف الصورة القديمة إذا كانت موجودة
            if ($category->imag) {
                $oldImagePath = str_replace(asset('storage/'), '', $category->imag);
                Storage::disk('public')->delete($oldImagePath);
            }

            // تخزين الصورة الجديدة
            $imageFile = $data['imag'];
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'categories/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

            $data['imag'] = asset('storage/' . $imagePath);
        }

        $category->update($data);
        return $category;
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
