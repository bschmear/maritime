<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\InventoryImage\Actions\CreateInventoryImage as CreateAction;
use App\Domain\InventoryImage\Actions\DeleteInventoryImage as DeleteAction;
use App\Domain\InventoryImage\Actions\UpdateInventoryImage as UpdateAction;
use App\Domain\InventoryImage\Models\InventoryImage as RecordModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryImageController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryimages',
            'InventoryImage',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'InventoryImage'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recordsForAttachmentLinkParent(string $attachableType, int $attachableId): array
    {
        $links = AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->with(['inventoryImage'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($links as $link) {
            $img = $link->inventoryImage;
            if (! $img) {
                continue;
            }
            $row = $img->toArray();
            $row['attachment_link_id'] = $link->id;
            $row['sort_order'] = (int) $link->sort_order;
            $row['is_primary'] = (bool) $link->is_primary;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Override index to ensure all fields needed for image gallery are loaded
     */
    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $linkParentType = (string) $request->get('link_parent_type', '');
        $linkParentId = (int) $request->get('link_parent_id', 0);

        if ($linkParentType !== '' && $linkParentId > 0 && AttachmentLink::usesLinksForMorphClass($linkParentType)) {
            $records = $this->recordsForAttachmentLinkParent($linkParentType, $linkParentId);
            $perPage = max(1, (int) $request->get('per_page', 100));
            $page = max(1, (int) $request->get('page', 1));
            $total = count($records);
            $offset = ($page - 1) * $perPage;
            $pageItems = array_slice($records, $offset, $perPage);

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'records' => $pageItems,
                    'schema' => $schema,
                    'fieldsSchema' => $fieldsSchema,
                    'meta' => [
                        'current_page' => $page,
                        'last_page' => (int) max(1, (int) ceil($total / $perPage)),
                        'per_page' => $perPage,
                        'total' => $total,
                    ],
                ]);
            }

            return inertia('Tenant/'.$this->domainName.'/Index', [
                'records' => $pageItems,
                'recordType' => $this->recordType,
                'recordTitle' => $this->recordTitle,
                'pluralTitle' => \Str::plural($this->recordTitle),
                'schema' => $schema,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
            ]);
        }

        $query = $this->recordModel->select([
            'id',
            'imageable_type',
            'imageable_id',
            'display_name',
            'description',
            'file',
            'file_extension',
            'file_size',
            'sort_order',
            'role',
            'is_primary',
            'created_at',
            'updated_at',
            'created_by_id',
            'updated_by_id',
        ]);

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode((string) $filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // ignore invalid filters
            }
        }

        $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 100);
        $records = $query->paginate($perPage);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia('Tenant/'.$this->domainName.'/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => \Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function store(Request $request, \App\Actions\PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();

            $result = ($this->createAction)($data);

            if (! is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'record' => $result['record'],
                        'message' => ucfirst($this->recordTitle).' created successfully.',
                    ]);
                }

                return redirect()
                    ->route("{$this->recordType}.show", $result['record']->id)
                    ->with('success', ucfirst($this->recordTitle).' created successfully.');
            }
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create record.',
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create record.');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'An error occurred: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id, \App\Actions\PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();

            $result = ($this->updateAction)($id, $data);

            if (! is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'record' => $result['record'],
                        'message' => ucfirst($this->recordTitle).' updated successfully.',
                    ]);
                }

                return redirect()
                    ->route("{$this->recordType}.show", $id)
                    ->with('success', ucfirst($this->recordTitle).' updated successfully.');
            }
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to update record.',
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to update record.');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'An error occurred: '.$e->getMessage());
        }
    }

    public function destroy($id): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $request = request();
        $attachableType = (string) $request->get('attachable_type', '');
        $attachableId = (int) $request->get('attachable_id', 0);

        $imageId = (int) $id;

        if ($attachableType !== '' && $attachableId > 0 && AttachmentLink::usesLinksForMorphClass($attachableType)) {
            app(InventoryImageAttachmentService::class)->unlinkFromAttachable($imageId, $attachableType, $attachableId);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Image removed.',
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'Image removed.');
        }

        $result = ($this->deleteAction)($id);

        if ($result['success']) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => ucfirst($this->recordTitle).' deleted successfully.',
                ]);
            }

            return redirect()
                ->route($this->recordType.'.index')
                ->with('success', $this->domainName.' deleted successfully');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to delete '.$this->recordTitle,
            ], 422);
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
    }
}
