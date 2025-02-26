<?php
namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function create(array $data)
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data)
    {
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
