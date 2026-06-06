<?php

namespace App\Domain\Contact\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Communication\Models\Communication;
use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contract\Models\Contract;
use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Lead\Models\Lead;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\PortalAccess\Models\PortalAccess;
use App\Domain\Qualification\Models\Qualification;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContactDeletionGuard
{
    public const MESSAGE = 'This record cannot be deleted because it is referred to by other records.';

    public function messageFor(Contact $contact): ?string
    {
        return $this->hasBlockingReferences($contact) ? self::MESSAGE : null;
    }

    public function isBlocked(Contact $contact): bool
    {
        return $this->messageFor($contact) !== null;
    }

    private function hasBlockingReferences(Contact $contact): bool
    {
        $contactId = (int) $contact->getKey();
        $morphType = $contact->getMorphClass();

        if ($this->modelExists(Estimate::class, fn ($query) => $query->where('contact_id', $contactId))) {
            return true;
        }

        if ($this->modelExists(Invoice::class, fn ($query) => $query->where('contact_id', $contactId))) {
            return true;
        }

        if ($this->modelExists(Communication::class, fn ($query) => $query
            ->where('communicable_type', $morphType)
            ->where('communicable_id', $contactId))) {
            return true;
        }

        if ($this->tableExists('documentables', fn ($query) => $query
            ->where('documentable_type', $morphType)
            ->where('documentable_id', $contactId))) {
            return true;
        }

        if ($this->modelExists(ConsignmentAgreement::class, fn ($query) => $query->where('owner_contact_id', $contactId))) {
            return true;
        }

        if ($this->modelExists(WarrantyClaim::class, fn ($query) => $query
            ->where(function ($inner) use ($contactId): void {
                $inner->where('vendor_approved_by_contact_id', $contactId)
                    ->orWhere('vendor_rejected_by_contact_id', $contactId);
            }))) {
            return true;
        }

        if ($this->modelExists(DocumentRequest::class, fn ($query) => $query->where('contact_id', $contactId))) {
            return true;
        }

        if (Schema::hasTable((new Lead)->getTable())) {
            $leadIds = Lead::query()->where('contact_id', $contactId)->pluck('id');
            if ($leadIds->isNotEmpty()
                && $this->modelExists(Qualification::class, fn ($query) => $query->whereIn('lead_id', $leadIds))) {
                return true;
            }
        }

        if (! Schema::hasTable((new Customer)->getTable())) {
            return false;
        }

        $customerId = Customer::query()->where('contact_id', $contactId)->value('id');
        if ($customerId === null) {
            return false;
        }

        $customerId = (int) $customerId;

        return $this->modelExists(Transaction::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(Opportunity::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(Contract::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(ServiceTicket::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(Estimate::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(Delivery::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(PortalAccess::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(WorkOrder::class, fn ($query) => $query->where('customer_id', $customerId))
            || $this->modelExists(AssetUnit::class, fn ($query) => $query->where('customer_id', $customerId));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(Builder): mixed  $constraint
     */
    private function modelExists(string $modelClass, callable $constraint): bool
    {
        /** @var Model $model */
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return false;
        }

        $query = $modelClass::query();
        $constraint($query);

        return $query->exists();
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): mixed  $constraint
     */
    private function tableExists(string $table, callable $constraint): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $query = DB::table($table);
        $constraint($query);

        return $query->exists();
    }
}
