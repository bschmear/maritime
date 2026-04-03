<?php

declare(strict_types=1);

namespace App\Domain\Communication\Support;

use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;

final class CommunicableTypeResolver
{
    /**
     * @return list<string>
     */
    public static function allowedShortNames(): array
    {
        return ['Lead', 'Customer', 'Vendor'];
    }

    /**
     * @return class-string<Lead|Customer|Vendor>|null
     */
    public static function toClass(string $type): ?string
    {
        return match ($type) {
            'Lead', Lead::class => Lead::class,
            'Customer', Customer::class => Customer::class,
            'Vendor', Vendor::class => Vendor::class,
            default => null,
        };
    }

    /**
     * @param  class-string<Model>  $class
     */
    public static function findCommunicable(string $class, int $id): Model
    {
        return $class::query()->findOrFail($id);
    }
}
