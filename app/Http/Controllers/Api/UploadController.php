<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        'zip', 'dwg', 'dxf',
    ];

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'File type not allowed.',
            ], 422);
        }

        $filename = sprintf('%s_%s.%s', time(), Str::random(16), $extension);
        $path = $file->storeAs('uploads', $filename, 'public');

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
        ]);
    }
}
