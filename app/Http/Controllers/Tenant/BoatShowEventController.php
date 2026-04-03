<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Actions\CreateBoatShowEvent as CreateAction;
use App\Domain\BoatShowEvent\Actions\DeleteBoatShowEvent as DeleteAction;
use App\Domain\BoatShowEvent\Actions\UpdateBoatShowEvent as UpdateAction;
use App\Domain\BoatShowEvent\Models\BoatShowEvent as RecordModel;
use App\Domain\Checklist\Actions\SyncChecklist;
use App\Domain\ChecklistTemplate\Models\ChecklistTemplate;
use App\Domain\Document\Models\Document;
use App\Domain\User\Models\User;
use App\Enums\Tasks\Priority;
use App\Enums\Tasks\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class BoatShowEventController extends RecordController
{
    private const NESTED_SCOPE = 'boat_show_event_nested_boat_show';

    /**
     * @return array<string, mixed>
     */
    private function taskBoardInertiaProps(): array
    {
        $fieldsPath = app_path('Domain/Task/Schema/fields.json');
        $formPath = app_path('Domain/Task/Schema/form.json');

        $fieldsRaw = is_file($fieldsPath)
            ? json_decode((string) file_get_contents($fieldsPath), true) ?? []
            : [];
        $taskFieldsSchema = $fieldsRaw['fields'] ?? $fieldsRaw;

        $taskFormSchema = is_file($formPath)
            ? json_decode((string) file_get_contents($formPath), true) ?? []
            : [];

        return [
            'taskBoardFormSchema' => $taskFormSchema,
            'taskBoardFieldsSchema' => $taskFieldsSchema,
            'taskBoardEnumOptions' => [
                'App\\Enums\\Tasks\\Status' => Status::options(),
                'App\\Enums\\Tasks\\Priority' => Priority::options(),
            ],
            'taskStatusOptions' => Status::options(),
        ];
    }

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boat-show-events',
            'Boat Show Event',
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

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    protected function recipientUserOptions(): array
    {
        return User::query()
            ->orderBy('display_name')
            ->orderBy('first_name')
            ->get(['id', 'display_name', 'first_name', 'last_name', 'email'])
            ->map(function (User $u) {
                $name = $u->display_name;
                if ($name === null || $name === '') {
                    $name = trim(implode(' ', array_filter([$u->first_name, $u->last_name])));
                }
                if ($name === '') {
                    $name = (string) $u->email;
                }

                return [
                    'id' => $u->id,
                    'name' => $name,
                    'email' => (string) $u->email,
                ];
            })
            ->values()
            ->all();
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
            'recipientUserOptions' => $this->recipientUserOptions(),
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
            $event = RecordModel::query()
                ->with([
                    'checklist.items' => fn ($q) => $q->orderBy('position'),
                    'tasks' => fn ($q) => $q->with([
                        'assigned' => fn ($a) => $a->select(['id', 'display_name', 'first_name']),
                    ])
                        ->orderByRaw('case when due_date is null then 1 else 0 end')
                        ->orderBy('due_date'),
                ])
                ->findOrFail($eventId);

            $checklist = $event->checklist
                ? SyncChecklist::formatForFrontend($event->checklist)
                : [
                    'id' => null,
                    'name' => 'Event Checklist',
                    'checklist_template_id' => null,
                    'items' => [],
                ];

            $checklistTemplates = ChecklistTemplate::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('context', 'boat_show_event')->orWhereNull('context');
                })
                ->with(['items' => fn ($q) => $q->orderBy('position')])
                ->orderBy('name')
                ->get()
                ->map(fn (ChecklistTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'items' => $t->items->map(fn ($i) => [
                        'label' => $i->label,
                        'required' => (bool) $i->required,
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            $layout = $event->layouts()->orderBy('id')->first();

            $followUpSettings = [
                'auto_followup' => (bool) $event->auto_followup,
                'delay_amount' => (int) ($event->delay_amount ?? 1),
                'delay_unit' => $event->delay_unit ?? 'days',
                'recipient_user_count' => count($event->recipients['user_ids'] ?? []),
            ];

            return $response
                ->with('extraRouteParams', $this->eventExtraRouteParams($request))
                ->with('checklist', $checklist)
                ->with('checklistTemplates', $checklistTemplates)
                ->with('tasks', $event->tasks)
                ->with('assets', $event->assetsGroupedForInertia())
                ->with('layoutSpace', [
                    'width_ft' => $layout ? (int) $layout->width_ft : 60,
                    'height_ft' => $layout ? (int) $layout->height_ft : 40,
                ])
                ->with('followUpSettings', $followUpSettings)
                ->with($this->taskBoardInertiaProps());
        }

        return $response;
    }

    public function edit($first, $second = null)
    {
        $this->recordType = $this->currentEventRoutePrefix();
        $eventId = request()->route('event') ?? $first;
        $response = parent::edit($eventId);

        if ($response instanceof InertiaResponse) {
            return $response
                ->with('extraRouteParams', $this->eventExtraRouteParams(request()))
                ->with('recipientUserOptions', $this->recipientUserOptions());
        }

        return $response;
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        $event = RecordModel::query()->findOrFail($id);
        $params = ['event' => $event->getRouteKey()];
        if ($request->route('boatShow') !== null) {
            $params['boatShow'] = $this->resolveBoatShow($request->route('boatShow'))->getRouteKey();
        }

        return redirect()
            ->route($this->recordType.'.show', $params)
            ->with('success', $this->domainName.' updated successfully');
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
