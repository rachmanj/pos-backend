@echo off
echo.
echo ================================
echo  POS-ATK Phase 3A Test Suite
echo ================================
echo.

echo Running Inventory Management API Tests...
echo.

REM Run all feature tests
echo [1/6] Running Category API Tests...
php artisan test tests/Feature/CategoryApiTest.php --stop-on-failure

echo.
echo [2/6] Running Product API Tests...
php artisan test tests/Feature/ProductApiTest.php --stop-on-failure

echo.
echo [3/6] Running Unit API Tests...
php artisan test tests/Feature/UnitApiTest.php --stop-on-failure

echo.
echo [4/6] Running Supplier API Tests...
php artisan test tests/Feature/SupplierApiTest.php --stop-on-failure

echo.
echo [5/6] Running Stock Movement API Tests...
php artisan test tests/Feature/StockMovementApiTest.php --stop-on-failure

echo.
echo [6/6] Running Inventory Permissions Tests...
php artisan test tests/Feature/InventoryPermissionsTest.php --stop-on-failure

echo.
echo ================================
echo  Test Suite Complete!
echo ================================
echo.

REM Run all tests together for summary
echo Running full test suite for summary...
php artisan test tests/Feature/ --verbose

pause 