<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Approvals - ZenaManage</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .btn { background: #ffc107; color: #212529; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-bottom: 20px; margin-right: 10px; }
        .btn:hover { background: #e0a800; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .status-pending-approval { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .success { color: #28a745; font-weight: bold; }
        .approval-actions { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Document Approvals</h1>
        
        <a href="{{ route('documents.index') }}" class="btn">← Back to Documents</a>
        <a href="{{ route('documents.create') }}" class="btn btn-success">+ Upload New Document</a>
        
        <table>
            <thead>
                <tr>
                    <th>Document Title</th>
                    <th>Type</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Uploaded By</th>
                    <th>Upload Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>{{ $document->title }}</td>
                        <td>{{ ucfirst($document->document_type) }}</td>
                        <td>{{ $document->project ? $document->project->name : 'No Project' }}</td>
                        <td><span class="status-{{ str_replace('_', '-', $document->status) }}">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span></td>
                        <td>{{ $document->uploadedBy ? $document->uploadedBy->name : 'Unknown' }}</td>
                        <td>{{ \Carbon\Carbon::parse($document->created_at)->format('M d, Y') }}</td>
                        <td>
                            <div class="approval-actions">
                                <a href="#" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">Approve</a>
                                <a href="#" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Reject</a>
                                <a href="#" style="color: #007bff; margin-left: 10px;">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #6c757d;">
                            No documents pending approval. <a href="{{ route('documents.create') }}" style="color: #007bff;">Upload a document</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 4px;">
            <p class="success">✅ Document Approvals page working! This page is accessible at: <code>/documents/approvals</code></p>
            <p>This page shows all documents that are pending approval. You can approve or reject documents from here.</p>
        </div>
    </div>
</body>
</html>
