<?php declare(strict_types=1);

namespace Src\Foundation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Src\Foundation\Services\FileStorageService;
use Exception;

/**
 * Controller xử lý upload, download và quản lý files
 */
class FileController extends Controller
{
    private FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Upload file lên server
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'disk' => 'sometimes|string|in:documents,uploads,public',
                'directory' => 'sometimes|string|max:255',
                'filename' => 'sometimes|string|max:255'
            ]);

            $file = $request->file('file');
            $disk = $request->input('disk', 'documents');
            $directory = $request->input('directory');
            $filename = $request->input('filename');

            $fileInfo = $this->fileStorageService->uploadFile(
                $file,
                $disk,
                $directory,
                $filename
            );

            return response()->json([
                'status' => 'success',
                'data' => $fileInfo,
                'message' => 'File uploaded successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download file từ server
     *
     * @param Request $request
     * @param string $disk
     * @param string $encodedPath
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function download(Request $request, string $disk, string $encodedPath)
    {
        try {
            $path = base64_decode($encodedPath);
            
            return $this->fileStorageService->downloadFile($path, $disk);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Xóa file khỏi server
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'disk' => 'required|string|in:documents,uploads,public'
            ]);

            $path = $request->input('path');
            $disk = $request->input('disk');

            $deleted = $this->fileStorageService->deleteFile($path, $disk);

            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'File deleted successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete file'
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Lấy thông tin chi tiết của file
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function info(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'disk' => 'required|string|in:documents,uploads,public'
            ]);

            $path = $request->input('path');
            $disk = $request->input('disk');

            $fileInfo = $this->fileStorageService->getFileInfo($path, $disk);

            if ($fileInfo) {
                return response()->json([
                    'status' => 'success',
                    'data' => $fileInfo
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found'
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}