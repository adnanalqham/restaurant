<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

try {
    if (!Schema::hasTable('inv_units')) {
        Schema::create('inv_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        echo "Table inv_units created successfully.\n";
        
        // Seed some common units
        DB::table('inv_units')->insert([
            ['name' => 'كيلو', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'حبة', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'لتر', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'جرام', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'كيس', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'كرتون', 'created_at' => now(), 'updated_at' => now()],
        ]);
        echo "Default units seeded.\n";
    } else {
        echo "Table inv_units already exists.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
