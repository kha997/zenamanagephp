<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ZenaManage')</title>
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
           <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
           <style>
               /* Fixed Header Styles */
               .zena-main-nav {
                   position: fixed !important;
                   top: 0 !important;
                   left: 0 !important;
                   right: 0 !important;
                   z-index: 1000 !important;
                   background: white !important;
                   border-bottom: 1px solid #e5e7eb !important;
                   box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
               }
               
               /* Ensure ZenaManage is always visible */
               .zena-nav-brand-text {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: inline-block !important;
                   color: #2563eb !important;
                   font-weight: 700 !important;
               }
               
               .zena-nav-logo {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: flex !important;
               }
               
               .zena-nav-logo div {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: flex !important;
               }
               
               .zena-nav-logo span {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: inline-block !important;
                   color: #2563eb !important;
                   font-weight: 700 !important;
               }
               
               /* Ensure body has proper padding for fixed header */
               body {
                   padding-top: 80px !important;
               }
               
               /* Breadcrumb styles */
               .zena-breadcrumb {
                   background: #f9fafb;
                   border-bottom: 1px solid #e5e7eb;
                   padding: 1rem 0;
               }
               
               .zena-breadcrumb nav {
                   max-width: 1280px;
                   margin: 0 auto;
                   padding: 0 1rem;
               }
               
               .zena-breadcrumb a:hover {
                   color: #1d4ed8;
               }
               
               /* Adjust main content padding to account for breadcrumb */
               .admin-content {
                   padding-top: 0;
               }
           </style>
</head>
<body class="bg-gray-50">
    @yield('content')
</body>
</html>
