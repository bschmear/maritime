<?php

declare(strict_types=1);

namespace App\Domain\Document\Support;

use App\Support\ContactDocumentLinker;
use Illuminate\Database\Eloquent\Model;

/**
 * Allowlisted documentable models for attach/detach operations.
 */
final class DocumentableTypes
{
    /**
     * @var list<string>
     */
    private const DOMAINS = [
        'Asset',
        'AssetUnit',
        'Contact',
        'Customer',
        'InventoryItem',
        'InventoryUnit',
        'Invoice',
        'Lead',
        'Location',
        'Proposal',
        'Quote',
        'ServiceTicket',
        'Subsidiary',
        'Task',
        'Transaction',
        'User',
        'Vendor',
        'WarrantyClaim',
    ];

    /**
     * @return list<class-string<Model>>
     */
    public static function allowedClasses(): array
    {
        return array_values(array_filter(
            array_map(fn (string $domain) => self::classForDomain($domain), self::DOMAINS),
            fn (?string $class) => $class !== null && class_exists($class),
        ));
    }

    /**
     * @return class-string<Model>|null
     */
    public static function resolveClass(string $type): ?string
    {
        if (class_exists($type) && in_array($type, self::allowedClasses(), true)) {
            return $type;
        }

        if (preg_match('#^App\\\\Domain\\\\([A-Za-z0-9_]+)\\\\Models\\\\\1$#', $type, $matches)) {
            return self::classForDomain($matches[1]);
        }

        if (preg_match('#^[A-Za-z][A-Za-z0-9_]*$#', $type)) {
            return self::classForDomain($type);
        }

        return null;
    }

    public static function resolveModel(string $type, int $id): ?Model
    {
        $class = self::resolveClass($type);
        if ($class === null) {
            return null;
        }

        $model = $class::query()->find($id);
        if (! $model || ! method_exists($model, 'attachDocument')) {
            return null;
        }

        return $model;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function classForDomain(string $domain): ?string
    {
        if (! in_array($domain, self::DOMAINS, true)) {
            return null;
        }

        $class = ContactDocumentLinker::modelClassForDomain($domain);

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class;
    }
}
