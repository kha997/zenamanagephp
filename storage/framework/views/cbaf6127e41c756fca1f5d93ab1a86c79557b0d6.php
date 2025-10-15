


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Documents Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
</head>
<body class="bg-gray-50">
    
    
    <?php
        $mockDocuments = collect([
            (object) [
                'id' => 'doc_001',
                'name' => 'Project Requirements Document',
                'original_name' => 'Project Requirements Document.pdf',
                'description' => 'Detailed requirements for the website project',
                'file_type' => 'pdf',
                'type' => 'pdf',
                'file_size' => '2.5 MB',
                'size' => '2.5 MB',
                'status' => 'active',
                'category' => 'requirements',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'uploader' => (object) ['id' => '1', 'name' => 'John Doe'],
                'uploaded_by' => 'John Doe',
                'version' => '1.2',
                'download_count' => 15,
                'uploaded_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1)
            ],
            (object) [
                'id' => 'doc_002',
                'name' => 'Design Mockup',
                'original_name' => 'Design Mockup.png',
                'description' => 'UI/UX mockups for the homepage',
                'file_type' => 'png',
                'type' => 'png',
                'file_size' => '1.2 MB',
                'size' => '1.2 MB',
                'status' => 'active',
                'category' => 'design',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'uploader' => (object) ['id' => '2', 'name' => 'Alice Johnson'],
                'uploaded_by' => 'Alice Johnson',
                'version' => '2.0',
                'download_count' => 8,
                'uploaded_at' => now()->subDays(5),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(3)
            ],
            (object) [
                'id' => 'doc_003',
                'name' => 'Contract Template',
                'original_name' => 'Contract Template.docx',
                'description' => 'Standard contract template for clients',
                'file_type' => 'docx',
                'type' => 'docx',
                'file_size' => '850 KB',
                'size' => '850 KB',
                'status' => 'draft',
                'category' => 'contracts',
                'project' => (object) ['id' => '2', 'name' => 'Mobile App Development'],
                'uploader' => (object) => ['id' => '3', 'name' => 'Bob Smith'],
                'uploaded_by' => 'Bob Smith',
                'version' => '1.0',
                'download_count' => 3,
                'uploaded_at' => now()->subDays(7),
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6)
            ],
            (object) [
                'id' => 'doc_004',
                'name' => 'Meeting Notes',
                'original_name' => 'Meeting Notes.pdf',
                'description' => 'Weekly team meeting notes',
                'file_type' => 'pdf',
                'type' => 'pdf',
                'file_size' => '1.8 MB',
                'size' => '1.8 MB',
                'status' => 'active',
                'category' => 'reports',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'uploader' => (object) ['id' => '4', 'name' => 'Carol Davis'],
                'uploaded_by' => 'Carol Davis',
                'version' => '1.1',
                'download_count' => 12,
                'uploaded_at' => now()->subDays(10),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(8)
            ],
            (object) [
                'id' => 'doc_005',
                'name' => 'Budget Spreadsheet',
                'original_name' => 'Budget Spreadsheet.xlsx',
                'description' => 'Project budget and cost tracking',
                'file_type' => 'xlsx',
                'type' => 'xlsx',
                'file_size' => '650 KB',
                'size' => '650 KB',
                'status' => 'pending_approval',
                'category' => 'reports',
                'project' => (object) ['id' => '2', 'name' => 'Mobile App Development'],
                'uploader' => (object) ['id' => '5', 'name' => 'David Wilson'],
                'uploaded_by' => 'David Wilson',
                'version' => '1.0',
                'download_count' => 5,
                'uploaded_at' => now()->subDays(12),
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(10)
            ]
        ]);
        
        $mockProjects = collect([
            (object) ['id' => '1', 'name' => 'Website Redesign'],
            (object) ['id' => '2', 'name' => 'Mobile App Development'],
            (object) ['id' => '3', 'name' => 'API Integration']
        ]);
        
        $mockUser = (object) [
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'role' => 'project_manager'
        ];
        
        $mockTenant = (object) [
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Acme Corp',
            'slug' => 'acme-corp'
        ];
    ?>
    
    
    <script>
        window.mockAuth = {
            user: <?php echo json_encode($mockUser, 15, 512) ?>,
            tenant: <?php echo json_encode($mockTenant, 15, 512) ?>
        };
    </script>
    
    
    <div class="min-h-screen">
        <h1 class="text-3xl font-bold text-center py-8 bg-white shadow-sm">Documents Demo - Phase 2</h1>
        
        
        <div class="documents-demo">
            <?php echo $__env->make('app.documents.index-new', [
                'documents' => $mockDocuments,
                'projects' => $mockProjects,
                'user' => $mockUser,
                'tenant' => $mockTenant
            ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
    
    
    <div class="fixed bottom-4 left-4 z-50">
        <button onclick="toggleTheme()" 
                class="btn bg-gray-800 text-white px-4 py-2 rounded-full shadow-lg hover:bg-gray-700">
            <i class="fas fa-moon mr-2"></i>
            Toggle Theme
        </button>
    </div>
    
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
        }
        
        // Mock functions for demo
        function refreshDocuments() {
            alert('Documents refresh triggered!');
        }
        
        function exportDocuments() {
            alert('Export documents functionality would be implemented here!');
        }
        
        function uploadDocument() {
            alert('Upload document modal would open here!');
        }
        
        function downloadDocument(documentId) {
            alert('Download document: ' + documentId);
        }
        
        function viewDocument(documentId) {
            alert('View document: ' + documentId);
        }
        
        function editDocument(documentId) {
            alert('Edit document: ' + documentId);
        }
        
        function showVersionHistory(documentId) {
            alert('Show version history: ' + documentId);
        }
        
        function deleteDocument(documentId) {
            alert('Delete document: ' + documentId);
        }
        
        function bulkChangeStatus() {
            alert('Bulk change status functionality would be implemented here!');
        }
        
        function bulkArchive() {
            alert('Bulk archive functionality would be implemented here!');
        }
        
        function bulkExport() {
            alert('Bulk export functionality would be implemented here!');
        }
        
        function bulkDelete() {
            alert('Bulk delete functionality would be implemented here!');
        }
        
        function openModal(modalId) {
            alert('Open modal: ' + modalId);
        }
        
        function closeModal(modalId) {
            alert('Close modal: ' + modalId);
        }
        
        function handleFileSelect(event) {
            alert('File selected: ' + event.target.files[0].name);
        }
        
        function downloadVersion(versionId) {
            alert('Download version: ' + versionId);
        }
        
        function revertToVersion(versionId) {
            alert('Revert to version: ' + versionId);
        }
        
        // Mock auth token
        function getAuthToken() {
            return 'mock-token';
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_demos/documents-demo.blade.php ENDPATH**/ ?>