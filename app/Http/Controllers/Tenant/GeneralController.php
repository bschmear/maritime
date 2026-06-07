<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Location\Models\Location;
use App\Enums\Entity\IntendedUse;
use App\Enums\Entity\OwnershipType;
use App\Enums\Entity\PurchaseTimeline;
use App\Enums\Entity\Source;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use App\Enums\Leads\Status;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Services\TaxRateService;
use App\Support\ContactPartyResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class GeneralController extends BaseController
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    public function lookup(Request $request)
    {
        // Simple lookup - just return id and display_name
        $type = $request->get('type');

        // Map lowercase types to correct domain class names (e.g. workorder -> WorkOrder)
        $typeToDomain = [
            'workorder' => 'WorkOrder',
            'serviceticket' => 'ServiceTicket',
            'serviceitem' => 'ServiceItem',
            'assetunit' => 'AssetUnit',
            'assetvariant' => 'AssetVariant',
            'inventoryunit' => 'InventoryUnit',
            // Str::studly('addon') is "Addon" but the domain is AddOn / AddOn model
            'addon' => 'AddOn',
            // Str::studly('boatmake') is "Boatmake" but the domain / model is BoatMake
            'boatmake' => 'BoatMake',
            'assetoption' => 'AssetOption',
            'delivery_location' => 'DeliveryLocation',
            'deliverylocation' => 'DeliveryLocation',
            'maintenancetype' => 'MaintenanceType',
        ];
        $typeKey = $type !== null && $type !== '' ? strtolower((string) $type) : '';
        $domainName = $typeToDomain[$typeKey] ?? Str::studly($type);

        $recordModel = 'App\Domain\\'.$domainName.'\Models\\'.$domainName;
        $recordModel = new $recordModel;

        // Models whose display_name is a virtual accessor (not a real DB column).
        // Schema::hasColumn() can return a false-positive for these (e.g. when the
        // system DB has a homonymous table), so we force-exclude them here.
        // "customer" / "lead" are profile rows: labels come from the linked contact (and primary address), not profile tables.
        $virtualDisplayNameTypes = ['transaction', 'estimate', 'qualification', 'contract', 'delivery_location', 'deliverylocation', 'customer', 'lead', 'workorder'];

        // Check if display_name column exists, otherwise just select id
        $tableName = $recordModel->getTable();
        $hasDisplayNameColumn = ! in_array($typeKey, $virtualDisplayNameTypes, true)
            && \Schema::connection($recordModel->getConnectionName())
                ->hasColumn($tableName, 'display_name');

        $columns = ['id'];
        if ($hasDisplayNameColumn) {
            $columns[] = 'display_name';
        } else {
            // For models without display_name column, add foreign keys needed for relationships
            if ($type === 'assetunit') {
                $columns[] = 'asset_id';
                $columns[] = 'serial_number';
                $columns[] = 'hin';
                $columns[] = 'sku';
            } elseif ($type === 'inventoryunit') {
                $columns[] = 'inventory_item_id';
                $columns[] = 'serial_number';
                $columns[] = 'hin';
                $columns[] = 'sku';
            } elseif (in_array($typeKey, ['transaction', 'estimate', 'contract'], true)) {
                // display_name is computed from `sequence` (e.g. "DL-1001", "CTR-10003")
                $columns[] = 'sequence';
            } elseif ($typeKey === 'workorder') {
                // display_name accessor is WO-{work_order_number}; not a DB column
                $columns[] = 'work_order_number';
            } elseif (in_array($typeKey, ['delivery_location', 'deliverylocation'], true)) {
                // display_name accessor falls back to the real `name` column.
                $columns[] = 'name';
                $columns[] = 'address_line_1';
                $columns[] = 'address_line_2';
                $columns[] = 'city';
                $columns[] = 'state';
                $columns[] = 'postal_code';
                $columns[] = 'country';
                $columns[] = 'latitude';
                $columns[] = 'longitude';
                $columns[] = 'active';
            } elseif (in_array($typeKey, ['customer', 'lead'], true)) {
                // display_name and address* accessors need contact + primary address; never select legacy
                // address columns on profile tables. Customer/Lead::$with loads contact.primaryAddress.
                $columns[] = 'contact_id';
            } elseif ($typeKey === 'assetoption') {
                $columns[] = 'name';
                $columns[] = 'input_type';
                $columns[] = 'active';
            }
        }

        // Contacts: lookup search and list UI use name / email fields, not only display_name
        if (strtolower($type) === 'contact') {
            $columns = array_merge($columns, [
                'first_name',
                'last_name',
                'email',
                'company',
                'phone',
                'mobile',
            ]);
        }

        // Include pricing and display fields for line item types
        if (strtolower($type) === 'inventoryitem') {
            $columns[] = 'sku';
            $columns[] = 'default_price';
            $columns[] = 'default_cost';
        } elseif (strtolower($type) === 'asset') {
            $columns[] = 'year';
            $columns[] = 'make_id';
            $columns[] = 'default_price';
            $columns[] = 'default_cost';
            $columns[] = 'has_variants';
            $columns[] = 'description';
        } elseif ($typeKey === 'assetvariant') {
            $columns[] = 'asset_id';
            $columns[] = 'name';
        } elseif (strtolower($type) === 'addon') {
            $columns[] = 'name';
            $columns[] = 'default_price';
            $columns[] = 'description';
            $columns[] = 'type';
        } elseif ($typeKey === 'maintenancetype') {
            $columns[] = 'category';
            $columns[] = 'applies_to';
            $columns[] = 'sort_order';
        }

        $query = $recordModel->select(array_unique($columns));

        if ($typeKey === 'assetoption') {
            $query->where($recordModel->getTable().'.active', true);
        }

        if ($typeKey === 'contact') {
            $query->with([
                'customer:id,contact_id',
                'leads:id,contact_id',
            ]);
        }

        // Load relationships for models that need them for display names
        if ($type === 'assetunit') {
            $query->with('asset:id,display_name');
        } elseif ($type === 'inventoryunit') {
            $query->with('inventoryItem:id,display_name');
        } elseif (strtolower($type) === 'asset') {
            $query->with('make:id,display_name');
        } elseif ($typeKey === 'assetvariant') {
            $query->with('asset:id,display_name');
        }

        if ($typeKey === 'maintenancetype') {
            $fa = strtolower((string) $request->get('fleet_applies', ''));
            if ($fa === 'truck' || $fa === 'trailer') {
                $t = $recordModel->getTable();
                $query->where(function ($q) use ($fa, $t) {
                    $q->where($t.'.applies_to', 'all')
                        ->orWhere($t.'.applies_to', $fa);
                });
            }
        }

        if ($typeKey === 'fleet') {
            $loc = $request->get('fleet_location_id');
            if ($loc !== null && $loc !== '') {
                $query->where($recordModel->getTable().'.location_id', (int) $loc);
            }
            $ftype = strtolower((string) $request->get('fleet_type', ''));
            if ($ftype === 'truck' || $ftype === 'trailer') {
                $query->where($recordModel->getTable().'.type', $ftype);
            }
        }

        // Apply filters if provided
        $filters = $request->get('filters');
        if ($filters) {
            $filtersArray = is_string($filters) ? json_decode($filters, true) : $filters;
            if (is_array($filtersArray) && ! empty($filtersArray)) {
                // Set domain name for HasSchemaSupport trait
                $this->domainName = $domainName;
                $this->recordModel = $recordModel;

                // Load fields schema for the domain
                $fieldsSchemaRaw = $this->getFieldsSchema();
                $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

                // Apply filters using the trait method
                $query = $this->applyFilters($query, $filtersArray, $fieldsSchema);
            }
        }

        // Apply search query (fuzzy search on display_name or related fields, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            if (strtolower($type) === 'asset') {
                // Search assets by display_name, year, or make name (via join)
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(CAST(year AS TEXT)) LIKE ?', [$searchTerm])
                        ->orWhereHas('make', fn ($q2) => $q2->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm]));
                });
            } elseif (strtolower($type) === 'addon') {
                // Search add-ons by name
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
            } elseif (strtolower($type) === 'contact') {
                // Contacts often have empty display_name; match how RecordSelect shows labels
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $trim = trim((string) $searchQuery);
                $contactTable = $recordModel->getTable();
                $query->where(function ($q) use ($searchTerm, $trim, $contactTable) {
                    $q->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(secondary_email, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(mobile, \'\')) LIKE ?', [$searchTerm]);
                    if ($trim !== '' && ctype_digit($trim)) {
                        $q->orWhere($contactTable.'.id', '=', (int) $trim);
                    }
                });
            } elseif ($typeKey === 'workorder') {
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $trim = trim((string) $searchQuery);
                $woTable = $recordModel->getTable();
                $query->where(function ($q) use ($searchTerm, $trim, $woTable) {
                    $q->whereRaw('LOWER(CAST('.$woTable.'.work_order_number AS TEXT)) LIKE ?', [$searchTerm])
                        ->orWhereRaw('CAST('.$woTable.'.id AS TEXT) LIKE ?', [$searchTerm]);
                    if ($trim !== '' && ctype_digit($trim)) {
                        $q->orWhere($woTable.'.id', '=', (int) $trim)
                            ->orWhere($woTable.'.work_order_number', '=', (int) $trim);
                    }
                });
            } elseif (in_array($typeKey, ['transaction', 'estimate', 'contract'], true)) {
                // display_name is virtual ("DL-…", "CTR-…"); search by sequence (and contract_number for contracts)
                $searchTerm = trim($searchQuery);
                $like = '%'.$searchTerm.'%';
                $query->where(function ($q) use ($searchTerm, $like, $typeKey) {
                    $q->whereRaw('CAST(sequence AS TEXT) LIKE ?', [$like]);
                    if (ctype_digit($searchTerm)) {
                        $q->orWhere('sequence', '=', (int) $searchTerm);
                    }
                    if ($typeKey === 'contract' && $searchTerm !== '') {
                        $q->orWhereRaw('LOWER(COALESCE(contract_number, \'\')) LIKE ?', ['%'.strtolower($searchTerm).'%']);
                    }
                });
            } elseif (in_array($typeKey, ['delivery_location', 'deliverylocation'], true)) {
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(state, \'\')) LIKE ?', [$searchTerm]);
                });
            } elseif (in_array($typeKey, ['customer', 'lead'], true)) {
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $trim = trim((string) $searchQuery);
                $profileTable = $recordModel->getTable();
                $query->where(function ($q) use ($searchTerm, $trim, $profileTable) {
                    $q->whereHas('contact', function ($q2) use ($searchTerm) {
                        $q2->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(company, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(COALESCE(mobile, \'\')) LIKE ?', [$searchTerm]);
                    });
                    if ($trim !== '' && ctype_digit($trim)) {
                        $q->orWhere($profileTable.'.id', '=', (int) $trim);
                    } elseif ($trim !== '') {
                        $q->orWhereRaw('CAST('.$profileTable.'.id AS TEXT) LIKE ?', ['%'.strtolower($trim).'%']);
                    }
                });
            } elseif ($typeKey === 'maintenancetype') {
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$searchTerm]);
                });
            } elseif ($typeKey === 'assetoption') {
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
            } elseif ($hasDisplayNameColumn) {
                $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower(trim($searchQuery)).'%']);
            } else {
                // For models without display_name column, search in typical display name fields
                $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                if ($type === 'assetunit') {
                    // For AssetUnit, also search in the joined assets table
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(asset_units.serial_number) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(asset_units.hin) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(asset_units.sku) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(assets.display_name) LIKE ?', [$searchTerm])
                            ->orWhereRaw('CAST(asset_units.id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                } elseif ($type === 'inventoryunit') {
                    // For InventoryUnit, also search in the joined inventory_items table
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(inventory_units.serial_number) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(inventory_units.hin) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(inventory_units.sku) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(inventory_items.display_name) LIKE ?', [$searchTerm])
                            ->orWhereRaw('CAST(inventory_units.id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                } else {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(serial_number) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(hin) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                            ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$searchTerm]);
                    });
                }
            }
        }

        // Custom ordering support
        $orderBy = $request->get('order_by', 'display_name');
        $orderDirection = $request->get('order_direction', 'asc');

        // Handle ordering - if display_name column doesn't exist, use alternative ordering
        if ($orderBy === 'display_name' && ! $hasDisplayNameColumn) {
            // For AssetUnit and InventoryUnit models, order by parent item then unit identifier
            if ($type === 'assetunit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function ($col) {
                    // Prefix all columns that belong to the asset_units table
                    $tableColumns = ['id', 'asset_id', 'serial_number', 'hin', 'sku', 'condition', 'status', 'inactive', 'is_customer_owned', 'is_consignment', 'engine_hours', 'last_service_at', 'warranty_expires_at', 'cost', 'asking_price', 'sold_price', 'price_history', 'vendor_id', 'customer_id', 'location_id', 'subsidiary_id', 'in_service_at', 'out_of_service_at', 'sold_at', 'attributes', 'notes', 'created_at', 'updated_at'];

                    return in_array($col, $tableColumns) ? 'asset_units.'.$col : $col;
                }, $columns);
                $query->select($prefixedColumns)
                    ->join('assets', 'asset_units.asset_id', '=', 'assets.id')
                    ->orderBy('assets.display_name')
                    ->orderByRaw("COALESCE(NULLIF(asset_units.serial_number, ''), NULLIF(asset_units.hin, ''), NULLIF(asset_units.sku, ''), CAST(asset_units.id AS TEXT))");
            } elseif ($type === 'inventoryunit') {
                // Override select to use table prefixes to avoid ambiguous column errors
                $prefixedColumns = array_map(function ($col) {
                    // Prefix all columns that belong to the inventory_units table
                    $tableColumns = ['id', 'inventory_item_id', 'serial_number', 'hin', 'sku', 'condition', 'status', 'inactive', 'is_customer_owned', 'is_consignment', 'engine_hours', 'last_service_at', 'warranty_expires_at', 'cost', 'asking_price', 'sold_price', 'price_history', 'vendor_id', 'customer_id', 'location_id', 'subsidiary_id', 'in_service_at', 'out_of_service_at', 'sold_at', 'attributes', 'notes', 'created_at', 'updated_at'];

                    return in_array($col, $tableColumns) ? 'inventory_units.'.$col : $col;
                }, $columns);
                $query->select($prefixedColumns)
                    ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id')
                    ->orderBy('inventory_items.display_name')
                    ->orderByRaw("COALESCE(NULLIF(inventory_units.serial_number, ''), NULLIF(inventory_units.hin, ''), NULLIF(inventory_units.sku, ''), CAST(inventory_units.id AS TEXT))");
            } elseif ($typeKey === 'addon') {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('name', $dir);
            } elseif ($typeKey === 'assetoption') {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('name', $dir);
            } elseif (in_array($typeKey, ['transaction', 'estimate', 'contract'], true)) {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('sequence', $dir);
            } elseif ($typeKey === 'workorder') {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('work_order_number', $dir);
            } elseif (in_array($typeKey, ['delivery_location', 'deliverylocation'], true)) {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('name', $dir);
            } elseif (in_array($typeKey, ['customer', 'lead'], true)) {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $t = $recordModel->getTable();
                $query->leftJoin('contacts', 'contacts.id', '=', $t.'.contact_id')
                    ->orderBy('contacts.display_name', $dir)
                    ->select([$t.'.id', $t.'.contact_id']);
            } else {
                // Default ordering for other models without display_name column
                $query->orderBy('created_at', 'desc');
            }
        } elseif ($typeKey === 'maintenancetype' && $orderBy === 'display_name') {
            $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
            // RecordSelect / pickers: alphabetical by label; do not prioritize seed sort_order.
            $query->orderBy('display_name', $dir)->orderBy('category', $dir);
        } elseif (in_array($orderBy, ['id', 'created_at', 'updated_at']) || ($orderBy === 'display_name' && $hasDisplayNameColumn)) {
            $query->orderBy($orderBy, $orderDirection);
        } else {
            // Default ordering
            $query->orderBy('created_at', 'desc');
        }

        // Get per_page from request, default to 15
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Ensure display_name is included in the response for all records
        $items = $records->items();
        if (! $hasDisplayNameColumn) {
            // For models without display_name column, add it from the accessor
            $items = array_map(function ($item) {
                $itemArray = $item->toArray();
                $itemArray['display_name'] = $item->display_name;

                return $itemArray;
            }, $items);
        }

        if ($typeKey === 'contact') {
            $items = array_map(function ($item) {
                $itemArray = $item instanceof Contact ? $item->toArray() : (array) $item;
                if ($item instanceof Contact) {
                    $itemArray['party_labels'] = ContactPartyResolver::partyLabelsForContact($item);
                }

                return $itemArray;
            }, $items);
        }

        return response()->json([
            'records' => $items,
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function selectForm(Request $request)
    {
        $type = $request->get('type');
        $typeKey = $type !== null && $type !== '' ? strtolower((string) $type) : '';

        $typeToDomain = [
            'workorder' => 'WorkOrder',
            'serviceticket' => 'ServiceTicket',
            'serviceitem' => 'ServiceItem',
            'assetunit' => 'AssetUnit',
            'assetvariant' => 'AssetVariant',
            'inventoryunit' => 'InventoryUnit',
            'addon' => 'AddOn',
            'boatmake' => 'BoatMake',
            'assetoption' => 'AssetOption',
            'deliverylocation' => 'DeliveryLocation',
            'delivery_location' => 'DeliveryLocation',
            'maintenancetype' => 'MaintenanceType',
            'contactaddress' => 'ContactAddress',
            'invoice' => 'Invoice',
            'payment' => 'Payment',
            'estimate' => 'Estimate',
        ];

        $domainName = $typeToDomain[$typeKey] ?? Str::studly((string) $type);

        // Temporarily set the domain name for schema methods
        $this->domainName = $domainName;

        // Get form schema and fields schema
        $formSchema = $this->getFormSchema();
        $fieldsSchemaRaw = $this->getFieldsSchema();

        // Handle fields schema structure - some schemas have a "fields" wrapper
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        $enumOptions = $this->getEnumOptions();

        // Debug: ensure enum options are loaded
        if (empty($enumOptions)) {
            // Fallback: manually load the enum options for InventoryUnit
            if ($domainName === 'InventoryUnit') {
                $enumOptions = [
                    'App\Enums\Inventory\UnitCondition' => UnitCondition::options(),
                    'App\Enums\Inventory\UnitStatus' => UnitStatus::options(),
                ];
            }
            // Fallback: manually load the enum options for Qualification
            elseif ($domainName === 'Qualification') {
                $enumOptions = [
                    'App\Enums\Leads\Status' => Status::options(),
                    'App\Enums\Entity\IntendedUse' => IntendedUse::options(),
                    'App\Enums\Entity\OwnershipType' => OwnershipType::options(),
                    'App\Enums\Entity\PurchaseTimeline' => PurchaseTimeline::options(),
                    'App\Enums\Entity\Source' => Source::options(),
                ];
            }
        }

        // If it's an AJAX request, return JSON
        if ($request->wantsJson() || $request->ajax()) {
            $recordType = match ($domainName) {
                'ContactAddress' => 'contactaddresses',
                'MaintenanceType' => 'maintenance-types',
                'DeliveryLocation' => 'delivery-locations',
                'BoatShow' => 'boat-shows',
                'BoatShowEvent' => 'boat-show-events',
                default => strtolower($domainName).'s',
            };

            return response()->json([
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'recordType' => $recordType,
                'recordTitle' => $domainName,
            ]);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * Unified tax-rate lookup used by service tickets, work orders, and estimates.
     *
     * Accepts either:
     *   ?location_id=<id>                  — looks up a saved Location model (most accurate)
     *   ?state=FL&city=Miami&postal_code=33101  — falls back to address fields
     */
    public function getTaxRate(Request $request): JsonResponse
    {
        $service = app(TaxRateService::class);

        // ── Location-based lookup (service ticket / work order) ────────────────
        $locationId = $request->get('location_id');
        if ($locationId) {
            $location = Location::find($locationId);

            if (! $location) {
                return response()->json([
                    'tax_rate' => null,
                    'jurisdiction_code' => null,
                    'jurisdiction_label' => null,
                ]);
            }

            $lookup = $service->lookupByLocation($location);

            return response()->json([
                'tax_rate' => $lookup['tax_rate'],
                'jurisdiction_code' => $lookup['jurisdiction_code'],
                'jurisdiction_label' => $lookup['jurisdiction_label'],
            ]);
        }

        // ── Address-based lookup (estimates / any record with a billing address) ─
        $state = trim($request->get('state', ''));
        if (! $state) {
            return response()->json([
                'tax_rate' => null,
                'jurisdiction_code' => null,
                'jurisdiction_label' => null,
            ]);
        }

        $lookup = $service->lookupByAddress([
            'state' => $state,
            'city' => $request->get('city') ?: '',
            'postal_code' => $request->get('postal_code') ?: '',
            'country' => $request->get('country') ?: 'US',
            'line1' => $request->get('line1') ?: '',
        ]);

        return response()->json([
            'tax_rate' => $lookup['tax_rate'],
            'jurisdiction_code' => $lookup['jurisdiction_code'],
            'jurisdiction_label' => $lookup['jurisdiction_label'],
        ]);
    }
}
