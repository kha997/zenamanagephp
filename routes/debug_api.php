<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Debug API Routes
|--------------------------------------------------------------------------
|
| These routes are only loaded in local/testing environments when app.debug
| is enabled to avoid exposing demo credentials or debug helpers in prod.
|
*/

if (! (app()->environment(['local', 'testing']) && config('app.debug'))) {
    return;
}

Route::post('/login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');

    $demoUsers = [
        'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
        'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
        'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
        'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
        'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
        'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
        'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
    ];

    if ($password === 'zena1234' && isset($demoUsers[$email])) {
        $userData = $demoUsers[$email];

        $user = new \stdClass();
        $user->id = rand(1000, 9999);
        $user->name = $userData['name'];
        $user->email = $email;
        $user->role = $userData['role'];

        session(['user' => $user]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'redirect' => '/dashboard',
            'user' => $user
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Email hoặc mật khẩu không đúng'
    ], 401);
});

Route::get('/documents-simple', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Documents endpoint working',
        'data' => []
    ]);
});

Route::post('/v1/upload-document', function (Request $request) {
    try {
        \Log::info('Upload request data:', [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'project_id' => $request->input('project_id'),
            'document_type' => $request->input('document_type'),
            'version' => $request->input('version'),
            'has_file' => $request->hasFile('file'),
            'file_info' => $request->file('file') ? [
                'name' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
                'mime' => $request->file('file')->getMimeType(),
                'is_valid' => $request->file('file')->isValid(),
                'error' => $request->file('file')->getError()
            ] : null
        ]);

        $title = $request->input('title', 'Untitled Document');
        $description = $request->input('description', '');
        $projectId = $request->input('project_id', null);
        $documentType = $request->input('document_type', 'other');
        $version = $request->input('version', '1.0');
        $file = $request->file('file');

        if (!$request->hasFile('file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded - hasFile() returned false'
            ], 400);
        }

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded - file() returned null'
            ], 400);
        }

        if (!$file->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'File upload failed - isValid() returned false. Error: ' . $file->getError()
            ], 400);
        }

        $fileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $fileMimeType = $file->getMimeType();

        if (empty($fileName)) {
            $fileName = $file->getFilename();
            if (empty($fileName)) {
                $fileName = 'uploaded_file_' . time();
            }
        }

        if (empty($fileName) || $fileName === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'File name is empty or invalid'
            ], 400);
        }

        $storedPath = $file->store('documents', 'public');

        return response()->json([
            'status' => 'success',
            'message' => 'Document uploaded successfully',
            'data' => [
                'id' => rand(1000, 9999),
                'title' => $title,
                'description' => $description,
                'project_id' => $projectId,
                'document_type' => $documentType,
                'version' => $version,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_mime_type' => $fileMimeType,
                'stored_path' => $storedPath,
                'uploaded_at' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Upload failed: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('test-simple', function () {
    return response()->json(['status' => 'success', 'message' => 'Simple test working']);
});

Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    Route::get('test', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'API is working',
            'timestamp' => now()
        ]);
    });

    Route::get('documents-simple', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Documents endpoint working',
            'data' => []
        ]);
    });

    Route::get('documents', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Documents endpoint working',
            'data' => []
        ]);
    });

    Route::post('documents', function (Request $request) {
        try {
            $title = $request->input('title');
            $description = $request->input('description');
            $projectId = $request->input('project_id');
            $documentType = $request->input('document_type');
            $version = $request->input('version');
            $file = $request->file('file');

            if (!$title) {
                return response()->json(['status' => 'error', 'message' => 'Title is required'], 400);
            }

            if (!$documentType) {
                return response()->json(['status' => 'error', 'message' => 'Document type is required'], 400);
            }

            if (!$file) {
                return response()->json(['status' => 'error', 'message' => 'File is required'], 400);
            }

            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileMimeType = $file->getMimeType();

            $storedPath = $file->store('documents', 'public');

            return response()->json([
                'status' => 'success',
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => rand(1000, 9999),
                    'title' => $title,
                    'description' => $description,
                    'project_id' => $projectId,
                    'document_type' => $documentType,
                    'version' => $version ?: '1.0',
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_mime_type' => $fileMimeType,
                    'stored_path' => $storedPath,
                    'uploaded_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    });

    Route::get('test-error', function () {
        return response()->json(['error' => 'Test error'], 400);
    });
});
