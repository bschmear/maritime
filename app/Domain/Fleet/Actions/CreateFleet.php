<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Models\Fleet as RecordModel;
use App\Domain\Fleet\Validation\FleetInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateFleet
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, FleetInputRules::create())->validate();

        try {
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateFleet', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateFleet', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
