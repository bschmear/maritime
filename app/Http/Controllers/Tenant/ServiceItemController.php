<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\ServiceItem\Actions\CreateServiceItem as CreateAction;
use App\Domain\ServiceItem\Actions\DeleteServiceItem as DeleteAction;
use App\Domain\ServiceItem\Actions\UpdateServiceItem as UpdateAction;
use App\Domain\ServiceItem\Models\ServiceItem as RecordModel;
use App\Enums\RecordType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceItemController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::ServiceItem;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $recordType->domainName()
        );
    }

    /**
     * Update billing type, taxable, rate, or cost on multiple service items.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:service_items,id',
            'fields' => 'required|array|min:1',
            'fields.billing_type' => 'sometimes|integer|in:1,2,3',
            'fields.taxable' => 'sometimes|boolean',
            'fields.default_rate' => 'sometimes|numeric|min:0',
            'fields.default_cost' => 'sometimes|numeric|min:0',
        ]);

        $allowedFields = ['billing_type', 'taxable', 'default_rate', 'default_cost'];
        $updates = array_intersect_key($validated['fields'], array_flip($allowedFields));

        if ($updates === []) {
            return back()->with('error', 'Choose at least one field to update.');
        }

        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($validated, $updates, &$updated, &$errors): void {
            foreach ($validated['ids'] as $id) {
                $result = ($this->updateAction)((int) $id, $updates);
                if ($result['success'] ?? false) {
                    $updated++;
                } else {
                    $errors[] = $id.': '.($result['message'] ?? 'failed');
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'No service items were updated.'.(count($errors) ? ' '.implode(' ', $errors) : ''));
        }

        $message = $updated === 1
            ? '1 service item updated.'
            : "{$updated} service items updated.";

        if (count($errors)) {
            Log::warning('Service item bulk update partial failures', ['errors' => $errors]);
            $message .= ' Some rows could not be updated.';
        }

        return redirect()
            ->route($this->recordType.'.index', $request->only(['search', 'filters', 'per_page', 'sort', 'direction', 'page']))
            ->with('success', $message);
    }

    /**
     * Delete multiple service items in one request (used by table bulk actions).
     * Local records only — linked QuickBooks items are not modified.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:service_items,id',
        ]);

        $deleted = 0;
        $errors = [];

        DB::transaction(function () use ($validated, &$deleted, &$errors): void {
            foreach ($validated['ids'] as $id) {
                $result = ($this->deleteAction)((int) $id);
                if ($result['success'] ?? false) {
                    $deleted++;
                } else {
                    $errors[] = $id.': '.($result['message'] ?? 'failed');
                }
            }
        });

        if ($deleted === 0) {
            return back()->with('error', 'No service items were deleted.'.(count($errors) ? ' '.implode(' ', $errors) : ''));
        }

        $message = $deleted === 1
            ? '1 service item deleted.'
            : "{$deleted} service items deleted.";

        if (count($errors)) {
            Log::warning('Service item bulk delete partial failures', ['errors' => $errors]);
            $message .= ' Some rows could not be removed.';
        }

        return redirect()
            ->route($this->recordType.'.index', $request->only(['search', 'filters', 'per_page', 'sort', 'direction', 'page']))
            ->with('success', $message);
    }
}
