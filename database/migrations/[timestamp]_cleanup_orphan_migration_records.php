<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration để xóa các bản ghi migration mồ côi
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Xóa bản ghi migration mồ côi '2025_01_17_000001_add_performance_indexes'
        // File này đã bị xóa nhưng bản ghi vẫn còn trong bảng migrations
        DB::table('migrations')
            ->where('migration', '2025_01_17_000001_add_performance_indexes')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không thể khôi phục bản ghi đã xóa vì không biết batch number
        // Nếu cần, có thể thêm lại thủ công:
        // DB::table('migrations')->insert([
        //     'migration' => '2025_01_17_000001_add_performance_indexes',
        //     'batch' => 1 // hoặc batch number phù hợp
        // ]);
    }
};