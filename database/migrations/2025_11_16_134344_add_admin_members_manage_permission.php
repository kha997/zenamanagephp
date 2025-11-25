<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['code' => 'admin.members.manage'],
            [
                'code' => 'admin.members.manage',
                'module' => 'admin',
                'action' => 'members.manage',
                'description' => 'Manage tenant members (invite, kick, change role)'
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('code', 'admin.members.manage')->delete();
    }
};
