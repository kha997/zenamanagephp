// Thay vì:
$user = Auth::user();
$userId = Auth::id();

// Sử dụng:
$user = Auth::user();
$userId = Auth::id();

// Hoặc chỉ định guard cụ thể:
$user = Auth::guard('api')->user();