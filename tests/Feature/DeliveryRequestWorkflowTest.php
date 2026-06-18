<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Delivery\Actions\CreateDeliveryRequest;
use App\Domain\Delivery\Actions\ResubmitDeliveryRequest;
use App\Domain\Delivery\Actions\ReviewDeliveryRequest;
use App\Domain\Delivery\Actions\UpdatePendingDeliveryRequest;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Enums\Deliveries\Status;
use App\Http\Controllers\Tenant\AccountDeliveryManagementController;
use App\Http\Controllers\Tenant\DeliveryRequestController;
use App\Mail\DeliveryRequestReviewedMail;
use App\Mail\DeliveryRequestSubmittedMail;
use App\Services\NotificationService;
use ReflectionClass;
use Tests\TestCase;

class DeliveryRequestWorkflowTest extends TestCase
{
    public function test_status_enum_has_requested_and_not_confirmed(): void
    {
        $values = array_map(fn (Status $s) => $s->value, Status::cases());

        $this->assertContains('requested', $values);
        $this->assertNotContains('confirmed', $values);
    }

    public function test_operational_status_values_exclude_requested(): void
    {
        $operational = Status::operationalValues();

        $this->assertNotContains('requested', $operational);
        $this->assertContains('scheduled', $operational);
    }

    public function test_delivery_request_controller_exists_with_expected_actions(): void
    {
        $ref = new ReflectionClass(DeliveryRequestController::class);

        foreach (['index', 'create', 'store', 'edit', 'update', 'approve', 'deny', 'proposeReschedule', 'resubmit', 'cancel'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function test_delivery_request_actions_exist(): void
    {
        $this->assertTrue(class_exists(CreateDeliveryRequest::class));
        $this->assertTrue(class_exists(ReviewDeliveryRequest::class));
        $this->assertTrue(class_exists(UpdatePendingDeliveryRequest::class));
    }

    public function test_account_delivery_management_controller_exists(): void
    {
        $ref = new ReflectionClass(AccountDeliveryManagementController::class);
        $this->assertTrue($ref->hasMethod('index'));
        $this->assertTrue($ref->hasMethod('update'));
    }

    public function test_delivery_approver_resolver_prefers_dedicated_approver(): void
    {
        $location = new \App\Domain\Location\Models\Location;
        $location->manager_user_id = 10;
        $location->delivery_approver_user_id = 20;

        $dedicated = new \App\Domain\User\Models\User;
        $dedicated->id = 20;
        $dedicated->display_name = 'Dedicated';
        $location->setRelation('deliveryApprover', $dedicated);

        $approver = DeliveryApproverResolver::forLocation($location);

        $this->assertNotNull($approver);
        $this->assertSame(20, $approver->id);
    }

    public function test_delivery_approver_resolver_falls_back_to_manager(): void
    {
        $location = new \App\Domain\Location\Models\Location;
        $location->manager_user_id = 10;
        $location->delivery_approver_user_id = null;

        $manager = new \App\Domain\User\Models\User;
        $manager->id = 10;
        $manager->display_name = 'Manager';
        $location->setRelation('managerUser', $manager);

        $approver = DeliveryApproverResolver::forLocation($location);

        $this->assertNotNull($approver);
        $this->assertSame(10, $approver->id);
    }

    public function test_notification_service_has_delivery_request_methods(): void
    {
        $ref = new ReflectionClass(NotificationService::class);
        $this->assertTrue($ref->hasMethod('notifyDeliveryRequestSubmitted'));
        $this->assertTrue($ref->hasMethod('notifyDeliveryRequestReviewed'));
    }

    public function test_delivery_request_mailables_exist(): void
    {
        $this->assertTrue(class_exists(DeliveryRequestSubmittedMail::class));
        $this->assertTrue(class_exists(DeliveryRequestReviewedMail::class));
    }

    public function test_review_decision_constants(): void
    {
        $this->assertSame('approved', ReviewDeliveryRequest::DECISION_APPROVED);
        $this->assertSame('denied', ReviewDeliveryRequest::DECISION_DENIED);
        $this->assertSame('reschedule_requested', ReviewDeliveryRequest::DECISION_RESCHEDULE_REQUESTED);
    }
}
