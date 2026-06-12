php artisan inventory:seed-asset-catalog


Useful variants
Dry run first (recommended):

php artisan inventory:seed-asset-catalog --dry-run
One brand only (e.g. AB Inflatables or Walker Bay):

php artisan inventory:seed-asset-catalog --brand=ab-inflatables
php artisan inventory:seed-asset-catalog --brand=walker-bay
Keep old inventory variants that were removed from meta.json:

php artisan inventory:seed-asset-catalog --keep-orphan-variants
Prerequisites
Inventory migrations must be applied on the inventory connection (your INVENTORY_DATABASE in .env):

php artisan migrate --database=inventory --path=database/migrations/inventory
Optional — refresh make rows from the manufacturer list:

php artisan inventory:seed-makes