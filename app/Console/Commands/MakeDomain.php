<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDomain extends Command
{
    protected $signature = 'make:Domain';

    protected $description = 'Create a new model and move it to the Domain directory';

    public function handle()
    {
        // Prompt for the model name
        $name = $this->ask('Enter the domain name');
        $modelName = ucfirst(Str::singular($name));
        $domainName = $modelName; // Use capitalized model name for directory structure

        // Create the directory structure if it doesn't exist
        $domainDirectory = app_path("Domain/{$domainName}/Models");
        if (!File::exists($domainDirectory)) {
            File::makeDirectory($domainDirectory, 0755, true, true);
        }

        // Create the model
        Artisan::call('make:model', ['name' => "Temp\\{$modelName}"]);

        // Move the model to the correct directory
        $sourcePath = app_path("Models/Temp/{$modelName}.php");
        $destinationPath = "{$domainDirectory}/{$modelName}.php";
        File::move($sourcePath, $destinationPath);

        $actionsDirectory = app_path("/Domain/{$domainName}/Actions");
        if (!File::exists($actionsDirectory)) {
            File::makeDirectory($actionsDirectory, 0755, true, true);
        }

        $dataDirectory = app_path("/Domain/{$domainName}/Schema");
        if (!File::exists($dataDirectory)) {
            File::makeDirectory($dataDirectory, 0755, true, true);
        }

        $this->createCreateActionClass($domainName, 'Create'. $modelName, $modelName);
        $this->createUpdateActionClass($domainName, 'Update'. $modelName, $modelName);
        $this->createDeleteActionClass($domainName, 'Delete'. $modelName, $modelName);
        $this->createFormClass($domainName, 'form');
        $this->createFieldsClass($domainName, 'fields');
        $this->createTableClass($domainName, 'table');
        $this->updateNamespace($destinationPath, "App\\Domain\\{$domainName}\\Models");

        // Ask user if they want to create a controller
        if ($this->confirm("Do you want to create a controller for {$modelName}?")) {
            $this->createControllerClass($domainName, $modelName);
            $this->info("Controller created for {$modelName}.");
        }

        // Ask user if they want to create a migration
        if ($this->confirm("Do you want to create a migration for {$modelName}?")) {
            Artisan::call('make:migration', [
                'name' => "create_" . strtolower(Str::plural($modelName)) . "_table",
                '--path' => 'database/migrations/tenant'
            ]);
            $this->info("Migration created for {$modelName} in database/migrations/tenant.");
        }

        // Create Vue pages
        $this->createVueIndexPage($domainName, $modelName);
        $this->createVueShowPage($domainName, $modelName);
        $this->info("Vue pages created for {$modelName} in resources/js/Pages/Tenant/{$domainName}/.");

        $this->info("Model {$modelName} and create action created in Domain/{$domainName}/Models and Domain/{$domainName}/Actions directories.");
    }

    protected function createFormClass($domainName, $fileName)
    {
        $dataDirectory = app_path("Domain/{$domainName}/Schema");
        $filePath = "{$dataDirectory}/{$fileName}.json";

        if (!File::exists($filePath)) {
            File::put($filePath,
'{
  "primary": {
    "label": "Primary",
    "fields": [

    ]
  },
  "secondary": {
    "label": "Secondary",
    "fields": [

    ]
  }
}'
            );
        }
    }

    protected function createFieldsClass($domainName, $fileName)
    {
        $dataDirectory = app_path("Domain/{$domainName}/Schema");
        $filePath = "{$dataDirectory}/{$fileName}.json";

        if (!File::exists($filePath)) {
            File::put($filePath,
'{

}'
            );
        }
    }

    protected function createTableClass($domainName, $fileName)
    {
        $dataDirectory = app_path("Domain/{$domainName}/Schema");
        $filePath = "{$dataDirectory}/{$fileName}.json";

        if (!File::exists($filePath)) {
            File::put($filePath,
'{
    "columns": [

    ]
}'
            );
        }
    }

protected function createCreateActionClass($domainName, $className, $modelName)
{
    $actionsDirectory = app_path("Domain/{$domainName}/Actions");
    $filePath = "{$actionsDirectory}/{$className}.php";

    if (!File::exists($filePath)) {
        File::put($filePath, "<?php
namespace App\\Domain\\{$domainName}\\Actions;

use App\\Domain\\{$domainName}\\Models\\{$modelName} as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class {$className}
{
    public function __invoke(array \$data): array
    {
        \$validated = Validator::make(\$data, [
            // Add validation rules here
        ])->validate();

        try {
            \$record = RecordModel::create(\$validated);

            return [
                'success' => true,
                'record' => \$record,
            ];
        } catch (QueryException \$e) {
            Log::error('Database query error in {$className}', [
                'error' => \$e->getMessage(),
                'data' => \$data
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable \$e) {
            Log::error('Unexpected error in {$className}', [
                'error' => \$e->getMessage(),
                'data' => \$data
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
                'record' => null,
            ];
        }
    }
}"
        );
    }
}

protected function createUpdateActionClass($domainName, $className, $modelName)
{
    $actionsDirectory = app_path("Domain/{$domainName}/Actions");
    $filePath = "{$actionsDirectory}/{$className}.php";

    if (!File::exists($filePath)) {
        File::put($filePath, "<?php
namespace App\\Domain\\{$domainName}\\Actions;

use App\\Domain\\{$domainName}\\Models\\{$modelName} as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class {$className}
{
    public function __invoke(int \$id, array \$data): array
    {
        \$validated = Validator::make(\$data, [
            // Add validation rules here
        ])->validate();

        try {
            \$record = RecordModel::findOrFail(\$id);
            \$record->update(\$validated);

            return [
                'success' => true,
                'record' => \$record,
            ];
        } catch (QueryException \$e) {
            Log::error('Database query error in {$className}', [
                'error' => \$e->getMessage(),
                'id' => \$id,
                'data' => \$data
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable \$e) {
            Log::error('Unexpected error in {$className}', [
                'error' => \$e->getMessage(),
                'id' => \$id,
                'data' => \$data
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
                'record' => null,
            ];
        }
    }
}"
        );
    }
}

protected function createDeleteActionClass($domainName, $className, $modelName)
{
    $actionsDirectory = app_path("Domain/{$domainName}/Actions");
    $filePath = "{$actionsDirectory}/{$className}.php";

    if (!File::exists($filePath)) {
        File::put($filePath, "<?php
namespace App\\Domain\\{$domainName}\\Actions;

use App\\Domain\\{$domainName}\\Models\\{$modelName} as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class {$className}
{
    public function __invoke(int \$id): array
    {
        try {
            \$record = RecordModel::findOrFail(\$id);
            \$record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException \$e) {
            Log::error('Database query error in {$className}', [
                'error' => \$e->getMessage(),
                'id' => \$id
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
            ];
        } catch (Throwable \$e) {
            Log::error('Unexpected error in {$className}', [
                'error' => \$e->getMessage(),
                'id' => \$id
            ]);
            return [
                'success' => false,
                'message' => \$e->getMessage(),
            ];
        }
    }
}"
        );
    }
}


    protected function updateNamespace($filePath, $newNamespace)
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('~namespace App\\\\Models\\\\Temp;~', "namespace $newNamespace;", $content);
        file_put_contents($filePath, $content);
    }

    protected function createControllerClass($domainName, $modelName)
    {
        $controllerName = "{$modelName}Controller";
        $controllerPath = app_path("Http/Controllers/Tenant/{$controllerName}.php");

        if (!File::exists($controllerPath)) {
            File::put($controllerPath, "<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\\Domain\\{$domainName}\\Models\\{$modelName} as RecordModel;
use App\\Domain\\{$domainName}\\Actions\\Create{$modelName} as CreateAction;
use App\\Domain\\{$domainName}\\Actions\\Update{$modelName} as UpdateAction;
use App\\Domain\\{$domainName}\\Actions\\Delete{$modelName} as DeleteAction;
use Illuminate\Http\Request;

class {$controllerName} extends RecordController
{
    protected \$recordType = '{$modelName}';
    protected \$table = null;

    public function __construct(Request \$request)
    {
        parent::__construct(
            \$request,
            '".strtolower(Str::plural($modelName))."',
            '{$modelName}',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            \$this->recordType // Domain name for schema lookup
        );
    }
}"
            );
        }
    }

    protected function createVueIndexPage($domainName, $modelName)
    {
        $pagesDirectory = resource_path("js/Pages/Tenant/{$domainName}");
        if (!File::exists($pagesDirectory)) {
            File::makeDirectory($pagesDirectory, 0755, true, true);
        }

        $filePath = "{$pagesDirectory}/Index.vue";
        $title = Str::plural($modelName);

        if (!File::exists($filePath)) {
            File::put($filePath, "<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title=\"{$title}\" />

    <TenantLayout>
        <template #header>
            <h2 class=\"text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200\">
                {$title}
            </h2>
        </template>

        <div class=\"py-12\">
            <div class=\"mx-auto max-w-7xl sm:px-6 lg:px-8\">
                <div class=\"bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg\">
                    <div class=\"p-6 text-gray-900 dark:text-gray-100\">
                        <p>{$title} management coming soon...</p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
"
            );
        }
    }

    protected function createVueShowPage($domainName, $modelName)
    {
        $pagesDirectory = resource_path("js/Pages/Tenant/{$domainName}");
        if (!File::exists($pagesDirectory)) {
            File::makeDirectory($pagesDirectory, 0755, true, true);
        }

        $filePath = "{$pagesDirectory}/Show.vue";
        $title = $modelName;

        if (!File::exists($filePath)) {
            File::put($filePath, "<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title=\"{$title}\" />

    <TenantLayout>
        <template #header>
            <h2 class=\"text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200\">
                {$title}
            </h2>
        </template>

        <div class=\"py-12\">
            <div class=\"mx-auto max-w-7xl sm:px-6 lg:px-8\">
                <div class=\"bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg\">
                    <div class=\"p-6 text-gray-900 dark:text-gray-100\">
                        <p>{$title} details coming soon...</p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
"
            );
        }
    }

}
