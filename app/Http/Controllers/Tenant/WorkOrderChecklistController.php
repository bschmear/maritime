<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Checklist\Actions\SyncWorkOrderChecklist;
use App\Domain\Checklist\Models\Checklist;
use App\Domain\Checklist\Models\ChecklistItem;
use App\Domain\ChecklistTemplate\Actions\CreateChecklistTemplate;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\WorkOrderApprovalState;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkOrderChecklistController extends Controller
{
    public function update(Request $request, WorkOrder $workorder): JsonResponse
    {
        $result = (new SyncWorkOrderChecklist)($workorder, $request->only(['name', 'checklist_template_id', 'items']));

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function submitForApproval(Request $request, WorkOrder $workorder): JsonResponse
    {
        if (! $workorder->requires_manager_approval) {
            throw ValidationException::withMessages([
                'approval' => ['Manager approval is not required for this work order.'],
            ]);
        }

        if ($workorder->manager_signed_off_at) {
            throw ValidationException::withMessages([
                'approval' => ['This work order has already been signed off by a manager.'],
            ]);
        }

        if ($workorder->technician_submitted_at) {
            throw ValidationException::withMessages([
                'approval' => ['This work order is already pending manager approval.'],
            ]);
        }

        $userId = current_tenant_user_id();
        if (! $this->canActAsTechnician($workorder, $userId)) {
            throw ValidationException::withMessages([
                'approval' => ['Only the assigned technician can submit for manager approval.'],
            ]);
        }

        $checklist = $this->loadChecklist($workorder);
        $this->assertRequiredItemsAnswered($checklist);

        if (! $workorder->manager_user_id) {
            throw ValidationException::withMessages([
                'manager_user_id' => ['A manager must be assigned before submitting for approval.'],
            ]);
        }

        $workorder->update([
            'technician_submitted_at' => now(),
            'technician_submitted_by' => $userId,
        ]);

        app(NotificationService::class)->notifyWorkOrderPendingManagerApproval($workorder->fresh());

        return response()->json([
            'success' => true,
            'approval_state' => WorkOrderApprovalState::resolve($workorder->fresh()),
            'record' => $this->formatWorkOrderApproval($workorder->fresh()),
        ]);
    }

    public function managerApproveLine(Request $request, WorkOrder $workorder): JsonResponse
    {
        if (WorkOrderApprovalState::resolve($workorder) !== WorkOrderApprovalState::PENDING_MANAGER) {
            throw ValidationException::withMessages([
                'approval' => ['Work order is not pending manager approval.'],
            ]);
        }

        $userId = current_tenant_user_id();
        if (! $this->canActAsManager($workorder, $userId)) {
            throw ValidationException::withMessages([
                'approval' => ['Only the assigned manager can approve checklist lines.'],
            ]);
        }

        $validated = $request->validate([
            'item_id' => 'required|integer|min:1',
        ]);

        $checklist = $this->loadChecklist($workorder);
        $item = $checklist->items()->whereKey($validated['item_id'])->firstOrFail();

        if (! $item->response) {
            throw ValidationException::withMessages([
                'item_id' => ['Line must have a True, False, or N/A response before manager approval.'],
            ]);
        }

        $item->update([
            'manager_approved' => true,
            'manager_approved_at' => now(),
            'manager_approved_by' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'checklist' => SyncWorkOrderChecklist::formatForFrontend($checklist->fresh()->load(['items' => fn ($q) => $q->orderBy('position')])),
        ]);
    }

    public function managerSignoff(Request $request, WorkOrder $workorder): JsonResponse
    {
        if (WorkOrderApprovalState::resolve($workorder) !== WorkOrderApprovalState::PENDING_MANAGER) {
            throw ValidationException::withMessages([
                'approval' => ['Work order is not pending manager approval.'],
            ]);
        }

        $userId = current_tenant_user_id();
        if (! $this->canActAsManager($workorder, $userId)) {
            throw ValidationException::withMessages([
                'approval' => ['Only the assigned manager can complete final sign-off.'],
            ]);
        }

        $checklist = $this->loadChecklist($workorder);
        $this->assertAllRequiredManagerApproved($checklist);

        $workorder->update([
            'manager_signed_off_at' => now(),
            'manager_signed_off_by' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'approval_state' => WorkOrderApprovalState::resolve($workorder->fresh()),
            'record' => $this->formatWorkOrderApproval($workorder->fresh()),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        if (! SyncWorkOrderChecklist::canManageChecklistStructure()) {
            throw ValidationException::withMessages([
                'checklist' => ['Only administrators and managers can save checklist templates.'],
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.label' => 'required|string|max:500',
            'items.*.required' => 'sometimes|boolean',
        ]);

        $validated['context'] = 'work_order';

        $result = (new CreateChecklistTemplate)($validated);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $result['record']->id,
                'name' => $result['record']->name,
                'items' => $result['record']->items->map(fn ($i) => [
                    'label' => $i->label,
                    'required' => $i->required,
                ])->values()->all(),
            ],
        ]);
    }

    private function loadChecklist(WorkOrder $workorder): Checklist
    {
        $checklist = Checklist::query()
            ->where('checklistable_type', WorkOrder::class)
            ->where('checklistable_id', $workorder->id)
            ->with(['items' => fn ($q) => $q->orderBy('position')])
            ->first();

        if (! $checklist || $checklist->items->isEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => ['Approval checklist must have at least one line item.'],
            ]);
        }

        return $checklist;
    }

    private function assertRequiredItemsAnswered(Checklist $checklist): void
    {
        $missing = $checklist->items
            ->filter(fn (ChecklistItem $item) => ! $item->response)
            ->count();

        if ($missing > 0) {
            throw ValidationException::withMessages([
                'checklist' => ['All checklist lines must have a True, False, or N/A response before submitting for approval.'],
            ]);
        }
    }

    private function assertAllRequiredManagerApproved(Checklist $checklist): void
    {
        $pending = $checklist->items
            ->filter(fn (ChecklistItem $item) => $item->required && ! $item->manager_approved)
            ->count();

        if ($pending > 0) {
            throw ValidationException::withMessages([
                'checklist' => ['All required checklist lines must be manager-approved before final sign-off.'],
            ]);
        }
    }

    private function canActAsTechnician(WorkOrder $workorder, ?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return (int) $workorder->assigned_user_id === $userId;
    }

    private function canActAsManager(WorkOrder $workorder, ?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return (int) $workorder->manager_user_id === $userId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatWorkOrderApproval(WorkOrder $workorder): array
    {
        return [
            'requires_manager_approval' => (bool) $workorder->requires_manager_approval,
            'manager_user_id' => $workorder->manager_user_id,
            'technician_submitted_at' => $workorder->technician_submitted_at?->toISOString(),
            'technician_submitted_by' => $workorder->technician_submitted_by,
            'manager_signed_off_at' => $workorder->manager_signed_off_at?->toISOString(),
            'manager_signed_off_by' => $workorder->manager_signed_off_by,
            'approval_state' => WorkOrderApprovalState::resolve($workorder),
        ];
    }
}
