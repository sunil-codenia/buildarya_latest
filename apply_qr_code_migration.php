<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

try {
    // 1. Get all databases
    $databases = DB::select("SHOW DATABASES");
    
    foreach ($databases as $dbObj) {
        $dbName = $dbObj->Database;
        
        // Match only rsgeotech and company_ databases
        if ($dbName === 'rsgeotech' || strpos($dbName, 'company_') === 0) {
            echo "Checking database: {$dbName}\n";
            
            // Check & Add to bills_party
            try {
                $tables = DB::select("SHOW TABLES FROM `{$dbName}` LIKE 'bills_party'");
                if (!empty($tables)) {
                    $columns = DB::select("SHOW COLUMNS FROM `{$dbName}`.`bills_party` LIKE 'qr_code'");
                    if (empty($columns)) {
                        DB::statement("ALTER TABLE `{$dbName}`.`bills_party` ADD COLUMN `qr_code` VARCHAR(2000) NULL DEFAULT NULL AFTER `cost_category_id`");
                        echo "  - Added 'qr_code' column to 'bills_party' table.\n";
                    } else {
                        echo "  - 'qr_code' column already exists in 'bills_party' table.\n";
                    }
                }
            } catch (\Exception $ex) {
                echo "  - Error checking bills_party: " . $ex->getMessage() . "\n";
            }
            
            // Check & Add to material_supplier
            try {
                $tables = DB::select("SHOW TABLES FROM `{$dbName}` LIKE 'material_supplier'");
                if (!empty($tables)) {
                    $columns = DB::select("SHOW COLUMNS FROM `{$dbName}`.`material_supplier` LIKE 'qr_code'");
                    if (empty($columns)) {
                        DB::statement("ALTER TABLE `{$dbName}`.`material_supplier` ADD COLUMN `qr_code` VARCHAR(2000) NULL DEFAULT NULL AFTER `cost_category_id`");
                        echo "  - Added 'qr_code' column to 'material_supplier' table.\n";
                    } else {
                        echo "  - 'qr_code' column already exists in 'material_supplier' table.\n";
                    }
                }
            } catch (\Exception $ex) {
                echo "  - Error checking material_supplier: " . $ex->getMessage() . "\n";
            }

            // Check & Add to payment_vouchers
            try {
                $tables = DB::select("SHOW TABLES FROM `{$dbName}` LIKE 'payment_vouchers'");
                if (!empty($tables)) {
                    $columns = DB::select("SHOW COLUMNS FROM `{$dbName}`.`payment_vouchers` LIKE 'qr_code'");
                    if (empty($columns)) {
                        DB::statement("ALTER TABLE `{$dbName}`.`payment_vouchers` ADD COLUMN `qr_code` VARCHAR(2000) NULL DEFAULT NULL AFTER `payment_image`");
                        echo "  - Added 'qr_code' column to 'payment_vouchers' table.\n";
                    } else {
                        echo "  - 'qr_code' column already exists in 'payment_vouchers' table.\n";
                    }
                }
            } catch (\Exception $ex) {
                echo "  - Error checking payment_vouchers: " . $ex->getMessage() . "\n";
            }

            // Check & Add to other_parties
            try {
                $tables = DB::select("SHOW TABLES FROM `{$dbName}` LIKE 'other_parties'");
                if (!empty($tables)) {
                    $columns = DB::select("SHOW COLUMNS FROM `{$dbName}`.`other_parties` LIKE 'qr_code'");
                    if (empty($columns)) {
                        DB::statement("ALTER TABLE `{$dbName}`.`other_parties` ADD COLUMN `qr_code` VARCHAR(2000) NULL DEFAULT NULL AFTER `cost_category_id`");
                        echo "  - Added 'qr_code' column to 'other_parties' table.\n";
                    } else {
                        echo "  - 'qr_code' column already exists in 'other_parties' table.\n";
                    }
                }
            } catch (\Exception $ex) {
                echo "  - Error checking other_parties: " . $ex->getMessage() . "\n";
            }
        }
    }
    
    echo "Migration completed successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
