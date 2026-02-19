<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:4096']
        ]);

        $file = $request->file('file');
        $filename = sprintf('%s_%s', time(), $file->getClientOriginalName());
        $path = $file->storeAs('uploads', $filename, 'public');

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'path' => $path,
            'size' => $file->getSize()
        ]);
    }
}
