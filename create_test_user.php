<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔐 Creating Test Users for POS-ATK System\n";
echo "=========================================\n\n";

// Check if users already exist
$existingUsers = DB::table('users')->count();
echo "Existing users: $existingUsers\n";

if ($existingUsers == 0) {
    echo "Creating test users...\n";

    // Create Super Admin
    $superAdmin = DB::table('users')->insertGetId([
        'name' => 'Super Admin',
        'email' => 'admin@sarange-erp.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create Manager
    $manager = DB::table('users')->insertGetId([
        'name' => 'Store Manager',
        'email' => 'manager@sarange-erp.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create Cashier
    $cashier = DB::table('users')->insertGetId([
        'name' => 'Cashier User',
        'email' => 'cashier@sarange-erp.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "✅ Test users created successfully!\n\n";
} else {
    echo "✅ Users already exist in database\n\n";
}

// Display login credentials
echo "🔑 TEST LOGIN CREDENTIALS\n";
echo "========================\n";
echo "Super Admin:\n";
echo "  Email: admin@sarange-erp.com\n";
echo "  Password: password123\n\n";
echo "Manager:\n";
echo "  Email: manager@sarange-erp.com\n";
echo "  Password: password123\n\n";
echo "Cashier:\n";
echo "  Email: cashier@sarange-erp.com\n";
echo "  Password: password123\n\n";

echo "🌐 SYSTEM ACCESS\n";
echo "================\n";
echo "Frontend: http://localhost:3000\n";
echo "Backend API: http://localhost:8000\n\n";

echo "🧪 TESTING INSTRUCTIONS\n";
echo "=======================\n";
echo "1. Open http://localhost:3000 in your browser\n";
echo "2. Login with any of the credentials above\n";
echo "3. Test each module with the sample data\n";
echo "4. Verify CRUD operations work correctly\n";
echo "5. Test business workflows end-to-end\n\n";

echo "🎯 READY FOR TESTING! 🚀\n";
