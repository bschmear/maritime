<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\RecordController;
use App\Domain\User\Models\User as RecordModel;
use App\Domain\User\Actions\CreateUser as CreateAction;
use App\Domain\User\Actions\UpdateUser as UpdateAction;
use App\Domain\User\Actions\DeleteUser as DeleteAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends RecordController
{
    protected $recordType = 'User';
    protected $table = null;

    public function __construct(Request $request)
    {

        parent::__construct(
            $request,
            'users',
            'User',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            'User' // Domain name for schema lookup
        );
    }

    /**
     * Display a listing of users with relationships loaded.
     */
    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        if (!in_array('id', $columns)) {
            $columns[] = 'id';
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

        $records = $query->paginate(15);

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

    /**
     * Show a specific user with role relationship loaded.
     */
    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getFieldsSchema();
        $record = $this->recordModel->with($this->getRelationshipsToLoad($fieldsSchema))->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        // If it's an AJAX request, return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
            ]);
        }

        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }
}