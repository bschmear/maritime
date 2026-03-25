<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowLayout\Actions\CreateBoatShowLayout as CreateAction;
use App\Domain\BoatShowLayout\Actions\DeleteBoatShowLayout as DeleteAction;
use App\Domain\BoatShowLayout\Actions\UpdateBoatShowLayout as UpdateAction;
use App\Domain\BoatShowLayout\Models\BoatShowLayout as RecordModel;
use App\Domain\Document\Models\Document;
use App\Enums\Timezone;
use App\Services\BoatShowLayoutService;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class BoatShowLayoutController extends RecordController
{
    private const NESTED_SCOPE = 'boat_show_layout_nested_show';

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boat-show-layouts',
            'BoatShowLayout',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'BoatShowLayout'
        );
    }

    protected function currentLayoutRoutePrefix(): string
    {
        return request()->routeIs('boat-shows.layouts.*') ? 'boat-shows.layouts' : 'boat-show-layouts';
    }

    protected function resolveBoatShow(mixed $boatShow): BoatShow
    {
        if ($boatShow instanceof BoatShow) {
            return $boatShow;
        }

        return BoatShow::where('id', $boatShow)->orWhere('slug', $boatShow)->firstOrFail();
    }

    protected function layoutExtraRouteParams(Request $request): array
    {
        $boatShow = $request->route('boatShow');
        if ($boatShow === null) {
            return [];
        }

        $show = $this->resolveBoatShow($boatShow);

        return ['boatShow' => $show->getRouteKey()];
    }

    public function index(Request $request)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();

        $boatShow = $request->route('boatShow');
        if ($boatShow !== null) {
            $show = $this->resolveBoatShow($boatShow);
            $eventIds = BoatShowEvent::query()->where('boat_show_id', $show->id)->pluck('id');
            RecordModel::addGlobalScope(self::NESTED_SCOPE, function ($query) use ($eventIds) {
                $query->whereIn('boat_show_event_id', $eventIds);
            });
            try {
                return $this->withIndexExtraResponse(parent::index($request), $request);
            } finally {
                RecordModel::clearGlobalScope(self::NESTED_SCOPE);
            }
        }

        return $this->withIndexExtraResponse(parent::index($request), $request);
    }

    protected function withIndexExtraResponse(mixed $response, Request $request): mixed
    {
        if ($response instanceof InertiaResponse) {
            $initialCreateData = [];
            $boatShow = $request->route('boatShow');
            if ($boatShow !== null) {
                $show = $this->resolveBoatShow($boatShow);
                $firstEvent = BoatShowEvent::query()->where('boat_show_id', $show->id)->orderBy('year', 'desc')->first();
                if ($firstEvent) {
                    $initialCreateData['boat_show_event_id'] = $firstEvent->id;
                }
            }

            return $response
                ->with('extraRouteParams', $this->layoutExtraRouteParams($request))
                ->with('initialCreateData', $initialCreateData);
        }

        return $response;
    }

    public function create()
    {
        $this->recordType = $this->currentLayoutRoutePrefix();

        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];
        $boatShow = request()->route('boatShow');
        if ($boatShow !== null) {
            $show = $this->resolveBoatShow($boatShow);
            $firstEvent = BoatShowEvent::query()->where('boat_show_id', $show->id)->orderBy('year', 'desc')->first();
            if ($firstEvent) {
                $initialData['boat_show_event_id'] = $firstEvent->id;
            }
        }

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
            'extraRouteParams' => $this->layoutExtraRouteParams(request()),
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();

        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image' && $request->hasFile($fieldKey)) {
                    $file = $request->file($fieldKey);
                    $meta = $fieldDef['meta'] ?? [];
                    $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                    $isPrivate = $meta['private'] ?? false;
                    $resizeWidth = $meta['max_width'] ?? null;
                    $crop = $meta['crop'] ?? false;

                    $result = $publicStorage->store(
                        file: $file,
                        directory: $directory,
                        resizeWidth: $resizeWidth,
                        existingFile: null,
                        crop: $crop,
                        deleteOld: false,
                        isPrivate: $isPrivate
                    );

                    $document = Document::create([
                        'display_name' => $result['display_name'],
                        'file' => $result['key'],
                        'file_extension' => $result['file_extension'],
                        'file_size' => $result['file_size'],
                        'created_by_id' => auth()->id(),
                        'updated_by_id' => auth()->id(),
                    ]);

                    $data[$fieldKey] = $document->id;
                }
            }

            if ($request->route('boatShow') !== null && isset($data['boat_show_event_id'])) {
                $show = $this->resolveBoatShow($request->route('boatShow'));
                $event = BoatShowEvent::query()->findOrFail((int) $data['boat_show_event_id']);
                if ((int) $event->boat_show_id !== (int) $show->id) {
                    return back()->withInput()->withErrors([
                        'boat_show_event_id' => 'This event does not belong to the selected boat show.',
                    ]);
                }
            }

            $result = ($this->createAction)($data);

            if (! is_array($result)) {
                $result = ['success' => true, 'record' => $result];
            }

            if ($result['success']) {
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    $record = $this->recordModel->find($result['record']->id);

                    return response()->json([
                        'success' => true,
                        'recordId' => $result['record']->id,
                        'record' => $record,
                        'message' => $this->domainName.' created successfully',
                    ]);
                }

                return $this->redirectAfterLayoutStore($request, $result['record']);
            }

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to create '.$this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create '.$this->recordTitle);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()->withInput()->withErrors($e->errors());
        }
    }

    protected function redirectAfterLayoutStore(Request $request, $record)
    {
        $params = ['layout' => $record->getRouteKey()];
        if ($request->route('boatShow') !== null) {
            $params['boatShow'] = $this->resolveBoatShow($request->route('boatShow'))->getRouteKey();
        }

        return redirect()
            ->route($this->recordType.'.show', $params)
            ->with('success', $this->domainName.' created successfully')
            ->with('recordId', $record->id);
    }

    public function show(Request $request, $first, $second = null)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();
        $layoutId = $request->route('layout') ?? $first;
        $response = parent::show($request, $layoutId);

        if ($response instanceof InertiaResponse) {
            return $response->with('extraRouteParams', $this->layoutExtraRouteParams($request));
        }

        return $response;
    }

    public function edit($first, $second = null)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();
        $layoutId = request()->route('layout') ?? $first;
        $response = parent::edit($layoutId);

        if ($response instanceof InertiaResponse) {
            return $response->with('extraRouteParams', $this->layoutExtraRouteParams(request()));
        }

        return $response;
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();
        $layoutId = $request->route('layout') ?? $id;

        return parent::update($request, $layoutId, $publicStorage);
    }

    public function destroy($first, $second = null)
    {
        $this->recordType = $this->currentLayoutRoutePrefix();
        $layoutId = request()->route('layout') ?? $first;
        $result = ($this->deleteAction)($layoutId);

        if (! $result['success']) {
            return back()
                ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
        }

        if (request()->route('boatShow') !== null) {
            $show = $this->resolveBoatShow(request()->route('boatShow'));

            return redirect()
                ->route('boat-shows.layouts.index', ['boatShow' => $show->getRouteKey()])
                ->with('success', $this->domainName.' deleted successfully');
        }

        return redirect()
            ->route('boat-show-layouts.index')
            ->with('success', $this->domainName.' deleted successfully');
    }

    public function sync(Request $request, BoatShowLayoutService $service)
    {
        $layoutId = $request->route('layout');
        $layout = RecordModel::query()->findOrFail($layoutId);

        $data = $request->validate([
            'width_ft' => ['required', 'integer', 'min:1'],
            'height_ft' => ['required', 'integer', 'min:1'],
            'items' => ['array'],

            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string'],
            'items.*.length_ft' => ['required', 'numeric', 'min:0'],
            'items.*.width_ft' => ['required', 'numeric', 'min:0'],
            'items.*.x' => ['required', 'numeric'],
            'items.*.y' => ['required', 'numeric'],
            'items.*.rotation' => ['required', 'in:0,90,180,270'],
            'items.*.color' => ['nullable', 'string'],

            'items.*.asset_unit_id' => ['nullable', 'integer'],
            'items.*.inventory_unit_id' => ['nullable', 'integer'],
        ]);

        $layout = $service->sync($layout, $data);

        return response()->json($layout->load('items'));
    }
}
