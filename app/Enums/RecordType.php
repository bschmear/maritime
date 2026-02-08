<?php

namespace App\Enums;

enum RecordType: string
{
    case InventoryUnit   = 'inventoryunit';
    case InventoryItem   = 'inventoryitem';
    case InventoryImage  = 'inventoryimage';
    case BoatMake        = 'boatmake';
    case Subsidiary      = 'subsidiary';
    case Lead            = 'lead';
    case Customer        = 'customer';
    case Vendor          = 'vendor';
    case Transaction     = 'transaction';
    case Invoice         = 'invoice';
    case Task            = 'task';
    case Document        = 'document';
    case User            = 'user';
    case Role            = 'role';
    case Location        = 'location';
    case WorkOrder       = 'workorder';
    case ServiceItem     = 'serviceitem';

    /**
     * Returns the human-readable domain name (usually matches controller group)
     */
    public function domainName(): string
    {
        return match($this) {
            self::InventoryUnit   => 'InventoryUnit',
            self::InventoryItem   => 'InventoryItem',
            self::InventoryImage  => 'InventoryImage',
            self::BoatMake        => 'BoatMake',
            self::Subsidiary      => 'Subsidiary',
            self::Lead            => 'Lead',
            self::Customer        => 'Customer',
            self::Vendor          => 'Vendor',
            self::Transaction     => 'Transaction',
            self::Invoice         => 'Invoice',
            self::Task            => 'Task',
            self::Document        => 'Document',
            self::User            => 'User',
            self::Role            => 'Role',
            self::Location        => 'Location',
            self::WorkOrder       => 'WorkOrder',
            self::ServiceItem     => 'ServiceItem',
        };
    }

    /**
     * Returns the fully-qualified model class path
     */
    public function domainPath(): string
    {
        return match($this) {
            self::InventoryUnit   => 'App\\Domain\\InventoryUnit',
            self::InventoryItem   => 'App\\Domain\\InventoryItem',
            self::InventoryImage  => 'App\\Domain\\InventoryImage',
            self::BoatMake        => 'App\\Domain\\BoatMake',
            self::Subsidiary      => 'App\\Domain\\Subsidiary',
            self::Lead            => 'App\\Domain\\Lead',
            self::Customer        => 'App\\Domain\\Customer',
            self::Vendor          => 'App\\Domain\\Vendor',
            self::Transaction     => 'App\\Domain\\Transaction',
            self::Invoice         => 'App\\Domain\\Invoice',
            self::Task            => 'App\\Domain\\Task',
            self::Document        => 'App\\Domain\\Document',
            self::User            => 'App\\Domain\\User',
            self::Role            => 'App\\Domain\\Role',
            self::Location        => 'App\\Domain\\Location',
            self::WorkOrder       => 'App\\Domain\\WorkOrder',
            self::ServiceItem     => 'App\\Domain\\ServiceItem',
        };
    }

    /**
     * Returns a human-friendly title
     */
    public function title(): string
    {
        return match($this) {
            self::InventoryUnit   => 'Item',
            self::InventoryItem   => 'Inventory Item',
            self::InventoryImage  => 'Image',
            self::BoatMake        => 'Boat Make',
            self::Subsidiary      => 'Subsidiary',
            self::Lead            => 'Lead',
            self::Customer        => 'Customer',
            self::Vendor          => 'Vendor',
            self::Transaction     => 'Transaction',
            self::Invoice         => 'Invoice',
            self::Task            => 'Task',
            self::Document        => 'Document',
            self::User            => 'User',
            self::Role            => 'Role',
            self::Location        => 'Location',
            self::WorkOrder       => 'Work Order',
            self::ServiceItem     => 'Service Item',
        };
    }

    /**
     * Returns the plural form of the record type
     */
    public function plural(): string
    {
        return match($this) {
            self::InventoryUnit   => 'inventoryunits',
            self::InventoryItem   => 'inventoryitems',
            self::InventoryImage  => 'inventoryimages',
            self::BoatMake        => 'boatmakes',
            self::Subsidiary      => 'subsidiaries',
            self::Lead            => 'leads',
            self::Customer        => 'customers',
            self::Vendor          => 'vendors',
            self::Transaction     => 'transactions',
            self::Invoice         => 'invoices',
            self::Task            => 'tasks',
            self::Document        => 'documents',
            self::User            => 'users',
            self::Role            => 'roles',
            self::Location        => 'locations',
            self::WorkOrder       => 'workorders',
            self::ServiceItem     => 'serviceitems',
        };
    }

    /**
     * Returns the enum key
     */
    public function key(): string
    {
        return $this->value;
    }

    /**
     * Returns an array suitable for dropdowns / selects
     */
    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'key' => $case->key(),
            'domain_name' => $case->domainName(),
            'domain_path' => $case->domainPath(),
            'title' => $case->title(),
        ], self::cases());
    }
}
