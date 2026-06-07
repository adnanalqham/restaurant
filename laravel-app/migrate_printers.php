<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    // Add windows_name column if not exists
    if (!Schema::hasColumn('printers', 'windows_name')) {
        Schema::table('printers', function (Blueprint $table) {
            $table->string('windows_name', 200)->nullable()->after('name');
        });
        echo "✅ Added 'windows_name' column to printers table\n";
    } else {
        echo "ℹ️ Column 'windows_name' already exists\n";
    }

    // Add port column if not exists
    if (!Schema::hasColumn('printers', 'port')) {
        Schema::table('printers', function (Blueprint $table) {
            $table->integer('port')->default(9100)->nullable()->after('ip');
        });
        echo "✅ Added 'port' column to printers table\n";
    } else {
        echo "ℹ️ Column 'port' already exists\n";
    }

    // Check current structure
    $columns = Schema::getColumnListing('printers');
    echo "📋 Current columns: " . implode(', ', $columns) . "\n";

    // Show current printers
    $printers = DB::table('printers')->get();
    echo "🖨️ Found " . count($printers) . " printer(s) in DB\n";
    foreach ($printers as $p) {
        echo "  - [{$p->id}] {$p->name} | Type: {$p->type}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
