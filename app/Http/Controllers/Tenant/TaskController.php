<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Task\Models\Task as RecordModel;
use App\Domain\Task\Actions\CreateTask as CreateAction;
use App\Domain\Task\Actions\UpdateTask as UpdateAction;
use App\Domain\Task\Actions\DeleteTask as DeleteAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends RecordController
{
    protected $recordType = 'Task';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'tasks',
            'Task',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        if (!in_array('id', $columns)) {
            $columns[] = 'id';
            $columns[] = 'notes';
            $columns[] = 'start_date';
            $columns[] = 'due_date';
            $columns[] = 'has_due_time';
            $columns[] = 'due_time';
            $columns[] = 'completed';
        }

        $query = $this->recordModel->select($columns)->with($this->getRelationshipsToLoad($fieldsSchema));

        // Apply search query (fuzzy search on display_name, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
        }

        // Apply filters from query parameters
        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // Invalid filters, ignore
            }
        }

        // $records = $query->where('completed', false)->paginate(15);
        $records = $query->where('completed', false)->get();
// dd($records);
        // For now, always return Inertia response for navigation
        // AJAX requests are handled by the parent RecordController

        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'recordType' => $this->recordType,
            'pluralTitle' => $this->recordTitle,
            'recordTitle' => Str::singular($this->recordTitle),
        ]);
    }

}
