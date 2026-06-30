<?php

namespace App\Enums;

enum RecordType: string
{
    case InventoryUnit = 'inventoryunit';
    case InventoryItem = 'inventoryitem';
    case InventoryImage = 'inventoryimage';
    case Asset = 'asset';
    case AssetUnit = 'assetunit';
    case BoatMake = 'boatmake';
    case Subsidiary = 'subsidiary';
    case Lead = 'lead';
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Transaction = 'transaction';
    case Invoice = 'invoice';
    case Task = 'task';
    case Document = 'document';
    case User = 'user';
    case Role = 'role';
    case Location = 'location';
    case WorkOrder = 'workorder';
    case ServiceItem = 'serviceitem';
    case ServiceTicket = 'serviceticket';
    case Notification = 'notification';
    case Survey = 'survey';
    case Payment = 'payment';
    case Estimate = 'estimate';
    case Contract = 'contract';
    case Delivery = 'delivery';
    case WarrantyClaim = 'warrantyclaim';
    case MsoRecord = 'msorecord';
    case Financing = 'financing';
    case Bill = 'bill';
    case BillPayment = 'billpayment';
    case NavigationMenu = 'navigationmenu';

    /**
     * Returns the human-readable domain name (usually matches controller group)
     */
    public function domainName(): string
    {
        return match ($this) {
            self::InventoryUnit => 'InventoryUnit',
            self::InventoryItem => 'InventoryItem',
            self::InventoryImage => 'InventoryImage',
            self::Asset => 'Asset',
            self::AssetUnit => 'AssetUnit',
            self::BoatMake => 'BoatMake',
            self::Subsidiary => 'Subsidiary',
            self::Lead => 'Lead',
            self::Customer => 'Customer',
            self::Vendor => 'Vendor',
            self::Transaction => 'Transaction',
            self::Invoice => 'Invoice',
            self::Task => 'Task',
            self::Document => 'Document',
            self::User => 'User',
            self::Role => 'Role',
            self::Location => 'Location',
            self::WorkOrder => 'WorkOrder',
            self::ServiceItem => 'ServiceItem',
            self::ServiceTicket => 'ServiceTicket',
            self::Notification => 'Notification',
            self::Survey => 'Survey',
            self::Payment => 'Payment',
            self::Estimate => 'Estimate',
            self::Contract => 'Contract',
            self::Delivery => 'Delivery',
            self::WarrantyClaim => 'WarrantyClaim',
            self::MsoRecord => 'MsoRecord',
            self::Financing => 'Financing',
            self::Bill => 'Bill',
            self::BillPayment => 'BillPayment',
            self::NavigationMenu => 'NavigationMenu',
        };
    }

    /**
     * Returns the fully-qualified model class path
     */
    public function domainPath(): string
    {
        return match ($this) {
            self::InventoryUnit => 'App\\Domain\\InventoryUnit',
            self::InventoryItem => 'App\\Domain\\InventoryItem',
            self::InventoryImage => 'App\\Domain\\InventoryImage',
            self::Asset => 'App\\Domain\\Asset',
            self::AssetUnit => 'App\\Domain\\AssetUnit',
            self::BoatMake => 'App\\Domain\\BoatMake',
            self::Subsidiary => 'App\\Domain\\Subsidiary',
            self::Lead => 'App\\Domain\\Lead',
            self::Customer => 'App\\Domain\\Customer',
            self::Vendor => 'App\\Domain\\Vendor',
            self::Transaction => 'App\\Domain\\Transaction',
            self::Invoice => 'App\\Domain\\Invoice',
            self::Task => 'App\\Domain\\Task',
            self::Document => 'App\\Domain\\Document',
            self::User => 'App\\Domain\\User',
            self::Role => 'App\\Domain\\Role',
            self::Location => 'App\\Domain\\Location',
            self::WorkOrder => 'App\\Domain\\WorkOrder',
            self::ServiceItem => 'App\\Domain\\ServiceItem',
            self::ServiceTicket => 'App\\Domain\\ServiceTicket',
            self::Notification => 'App\\Domain\\Notification',
            self::Survey => 'App\\Domain\\Survey',
            self::Payment => 'App\\Domain\\Payment',
            self::Estimate => 'App\\Domain\\Estimate',
            self::Contract => 'App\\Domain\\Contract',
            self::Delivery => 'App\\Domain\\Delivery',
            self::WarrantyClaim => 'App\\Domain\\WarrantyClaim',
            self::MsoRecord => 'App\\Domain\\MsoRecord',
            self::Financing => 'App\\Domain\\Financing',
            self::Bill => 'App\\Domain\\Bill',
            self::BillPayment => 'App\\Domain\\BillPayment',
            self::NavigationMenu => 'App\\Domain\\NavigationMenu',
        };
    }

    /**
     * Returns a human-friendly title
     */
    public function title(): string
    {
        return match ($this) {
            self::InventoryUnit => 'Item',
            self::InventoryItem => 'Parts & Accessories',
            self::InventoryImage => 'Image',
            self::Asset => 'Asset',
            self::AssetUnit => 'Asset Unit',
            self::BoatMake => 'Boat Make',
            self::Subsidiary => 'Subsidiary',
            self::Lead => 'Lead',
            self::Customer => 'Customer',
            self::Vendor => 'Vendor',
            self::Transaction => 'Transaction',
            self::Invoice => 'Invoice',
            self::Task => 'Task',
            self::Document => 'Document',
            self::User => 'User',
            self::Role => 'Role',
            self::Location => 'Location',
            self::WorkOrder => 'Work Order',
            self::ServiceItem => 'Service Item',
            self::ServiceTicket => 'Service Ticket',
            self::Notification => 'Notification',
            self::Survey => 'Survey',
            self::Payment => 'Payment',
            self::Estimate => 'Estimate',
            self::Contract => 'Contract',
            self::Delivery => 'Delivery',
            self::WarrantyClaim => 'Warranty Claim',
            self::MsoRecord => 'MSO',
            self::Financing => 'Financing',
            self::Bill => 'Bill',
            self::BillPayment => 'Bill Payment',
            self::NavigationMenu => 'Navigation Menu',
        };
    }

    /**
     * Returns the plural form of the record type
     */
    public function plural(): string
    {
        return match ($this) {
            self::InventoryUnit => 'inventoryunits',
            self::InventoryItem => 'inventoryitems',
            self::InventoryImage => 'inventoryimages',
            self::Asset => 'assets',
            self::AssetUnit => 'assetunits',
            self::BoatMake => 'boatmakes',
            self::Subsidiary => 'subsidiaries',
            self::Lead => 'leads',
            self::Customer => 'customers',
            self::Vendor => 'vendors',
            self::Transaction => 'transactions',
            self::Invoice => 'invoices',
            self::Task => 'tasks',
            self::Document => 'documents',
            self::User => 'users',
            self::Role => 'roles',
            self::Location => 'locations',
            self::WorkOrder => 'workorders',
            self::ServiceItem => 'serviceitems',
            self::ServiceTicket => 'servicetickets',
            self::Notification => 'notifications',
            self::Survey => 'surveys',
            self::Payment => 'payments',
            self::Estimate => 'estimates',
            self::Contract => 'contracts',
            self::Delivery => 'deliveries',
            self::WarrantyClaim => 'warrantyclaims',
            self::MsoRecord => 'msorecords',
            self::Financing => 'financings',
            self::Bill => 'bills',
            self::BillPayment => 'bill-payments',
            self::NavigationMenu => 'navigation-menus',
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
     * Whether the authenticated user's tenant role may access this record type
     * (see config/record_type_access.php).
     */
    public function tenantUserCanAccess(): bool
    {
        return tenant_can_access_record_type($this);
    }

    public static function fromDomainName(string $domainName): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->domainName() === $domainName) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Returns an array suitable for dropdowns / selects
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'key' => $case->key(),
            'domain_name' => $case->domainName(),
            'domain_path' => $case->domainPath(),
            'title' => $case->title(),
        ], self::cases());
    }
}
