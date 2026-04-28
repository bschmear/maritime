<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Remove runtime global scopes registered with a string identifier.
 *
 * Laravel 12 no longer exposes {@see Model::clearGlobalScopes()} on the model; use the registry API instead.
 */
final class DynamicGlobalScope
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function remove(string $modelClass, string $identifier): void
    {
        $all = $modelClass::getAllGlobalScopes();

        if (! isset($all[$modelClass][$identifier])) {
            return;
        }

        unset($all[$modelClass][$identifier]);

        if (isset($all[$modelClass]) && $all[$modelClass] === []) {
            unset($all[$modelClass]);
        }

        $modelClass::setAllGlobalScopes($all);
    }
}
