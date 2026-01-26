<?php

use Doctrine\DBAL\Types\Type;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Type::hasType('enum')) {
            Type::addType('enum', \Doctrine\DBAL\Types\StringType::class);
        }

        $platform = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');

        Schema::table('rfis', function (Blueprint $table) {
            $table->enum('status', ['open', 'pending', 'in_progress', 'answered', 'escalated', 'closed'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Type::hasType('enum')) {
            Type::addType('enum', \Doctrine\DBAL\Types\StringType::class);
        }

        $platform = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');

        Schema::table('rfis', function (Blueprint $table) {
            $table->enum('status', ['open', 'answered', 'closed'])
                ->default('open')
                ->change();
        });
    }
};
