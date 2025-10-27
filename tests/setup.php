<?php

echo "Running database setup script...\n";

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

Artisan::call('migrate:fresh');
echo "migrate:fresh command executed.\n";
Artisan::call('db:seed');
echo "db:seed command executed.\n";

echo "Database migrated and seeded successfully!\n";