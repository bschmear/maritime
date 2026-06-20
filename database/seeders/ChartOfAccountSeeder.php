<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\ChartOfAccount\DefaultChartOfAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        if (DB::table('chart_of_accounts')->exists()) {
            return;
        }

        $now = now();
        $idByFqn = [];

        foreach (DefaultChartOfAccounts::definitionsByDepth() as $definition) {
            $fqn = $definition['fully_qualified_name'];
            $parentFqn = DefaultChartOfAccounts::parentFullyQualifiedName($fqn);

            $id = DB::table('chart_of_accounts')->insertGetId([
                'name' => $definition['name'],
                'quickbooks_account_id' => null,
                'account_type' => $definition['account_type'],
                'detail_type' => $definition['detail_type'],
                'fully_qualified_name' => $fqn,
                'active' => true,
                'parent_id' => $parentFqn !== null ? ($idByFqn[$parentFqn] ?? null) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $idByFqn[$fqn] = $id;
        }
    }
}
