<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Actions\CreateBoatShowEvent as CreateAction;
use App\Domain\BoatShowEvent\Actions\DeleteBoatShowEvent as DeleteAction;
use App\Domain\BoatShowEvent\Actions\UpdateBoatShowEvent as UpdateAction;
use App\Domain\BoatShowEvent\Models\BoatShowEvent as RecordModel;
use App\Domain\Document\Models\Document;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class BoatShowEventController extends RecordController
{
    private const NESTED_SCOPE = 'boat_show_event_nested_boat_show';

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boat-show-events',
            'BoatShowEvent',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'BoatShowEvent'
        );
    }

    protected function currentEventRoutePrefix(): string
    {
        return request()->routeIs('boat-shows.events.*') ? 'boat-shows.events' : 'boat-show-events';
    }

    protected function resolveBoatShow(mixed $boatShow): BoatShow
    {
        if ($boatShow instanceof BoatShow) {
            return $boatShow;
        }

        return BoatShow::where('id', $boatShow)->orWhere('slug', $boatShow)->firstOrFail();
    }

    protected function eventExtraRouteParams(Request $request): array
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
        $this->recordType = $this->currentEventRoutePrefix();

        $boatShow = $request->route('boatShow');
        if ($boatShow !== null) {
            $show = $this->resolveBoatShow($boatShow);
            RecordModel::addGlobalScope(self::NESTED_SCOPE, function ($query) use ($show) {
                $query->where('boat_show_id', $show->id);
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
                $initialCreateData['boat_show_id'] = $this->resolveBoatShow($boatShow)->id;
            }

            return $response
                ->with('extraRouteParams', $this->eventExtraRouteParams($request))
                ->with('initialCreateData', $initialCreateData);
        }

        return $response;
    }

    public function create()
    {
        $this->recordType = $this->currentEventRoutePrefix();

        $initialData = [];
        $parentBoatShow = null;
        $boatShow = request()->route('boatShow');
        if ($boatShow !== null) {
            $show = $this->resolveBoatShow($boatShow);
            $initialData['boat_show_id'] = $show->id;
            $parentBoatShow = [
                'id' => $show->id,
                'name' => $show->display_name,
                'routeKey' => $show->getRouteKey(),
            ];
        }

        $boatShowOptions = $boatShow === null
            ? BoatShow::query()
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->display_name,
                ])
                ->values()
                ->all()
            : [];

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'initialData' => $initialData,
            'extraRouteParams' => $this->eventExtraRouteParams(request()),
            'parentBoatShow' => $parentBoatShow,
            'boatShowOptions' => $boatShowOptions,
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $this->recordType = $this->currentEventRoutePrefix();

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

            if ($request->route('boatShow') !== null) {
                $data['boat_show_id'] = $this->resolveBoatShow($request->route('boatShow'))->id;
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

                return $this->redirectAfterEventStore($request, $result['record']);
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

    protected function redirectAfterEventStore(Request $request, $record)
    {
        $params = ['event' => $record->getRouteKey()];
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
        $this->recordType = $this->currentEventRoutePrefix();
        $eventId = $request->route('event') ?? $first;
        $response = parent::show($request, $eventId);

        if ($response instanceof InertiaResponse) {
            return $response->with('extraRouteParams', $this->eventExtraRouteParams($request));
        }

        return $response;
    }

    public function edit($first, $second = null)
    {
        $this->recordType = $this->currentEventRoutePrefix();
        $eventId = request()->route('event') ?? $first;
        $response = parent::edit($eventId);

        if ($response instanceof InertiaResponse) {
            return $response->with('extraRouteParams', $this->eventExtraRouteParams(request()));
        }

        return $response;
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        $this->recordType = $this->currentEventRoutePrefix();
        $eventId = $request->route('event') ?? $id;

        return parent::update($request, $eventId, $publicStorage);
    }

    public function destroy($first, $second = null)
    {
        $this->recordType = $this->currentEventRoutePrefix();
        $eventId = request()->route('event') ?? $first;
        $result = ($this->deleteAction)($eventId);

        if (! $result['success']) {
            return back()
                ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
        }

        if (request()->route('boatShow') !== null) {
            $show = $this->resolveBoatShow(request()->route('boatShow'));

            return redirect()
                ->route('boat-shows.events.index', ['boatShow' => $show->getRouteKey()])
                ->with('success', $this->domainName.' deleted successfully');
        }

        return redirect()
            ->route('boat-show-events.index')
            ->with('success', $this->domainName.' deleted successfully');
    }
}
