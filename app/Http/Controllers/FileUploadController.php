<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {


        $request->validate([
            'files' => 'required',
            'files.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $uploadedFiles = [];

        if ($request->hasFile('files')) {
            $files = is_array($request->file('files')) ? $request->file('files') : [$request->file('files')];

            foreach ($files as $file) {
                try {
                    $path = $file->store('uploads', 'public');
                    $relativePath = 'api/storage/' . $path;
                    $uploadedFiles[] = $relativePath;
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Error uploading file: ' . $e->getMessage(),
                    ], 500);
                }
            }
        }


        return response()->json([
            'message' => 'Files uploaded successfully',
            'urls' => $uploadedFiles,
            'file_count' => count($uploadedFiles),
        ]);
    }
}
