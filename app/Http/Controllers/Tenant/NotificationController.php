<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Notification\Models\Notification as RecordModel;
use App\Domain\Notification\Actions\CreateNotification as CreateAction;
use App\Domain\Notification\Actions\UpdateNotification as UpdateAction;
use App\Domain\Notification\Actions\DeleteNotification as DeleteAction;
use App\Enums\RecordType;
use Illuminate\Http\Request;

class NotificationController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::Notification;

        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->domainName(),
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $recordType->domainName()
        );
    }

    /**
     * Display a listing of notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        return RecordModel::where('assigned_to_user_id', $request->user()->id)
            ->latest()
            ->paginate(20);
    }

    /**
     * Redirect to notification route and mark as read.
     */
    public function redirect(Request $request, $id)
    {
        $notification = RecordModel::findOrFail($id);
        $this->authorizeNotification($request, $notification);

        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now()
            ]);
        }

        $routeParams = $notification->getRouteParameters();

        \Log::info('Notification redirect', [
            'notification_id' => $id,
            'route' => $notification->route,
            'route_params_raw' => $notification->route_params,
            'route_params_processed' => $routeParams,
        ]);

        return redirect()->route(
            $notification->route,
            $routeParams
        );
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = RecordModel::findOrFail($id);
        $this->authorizeNotification($request, $notification);

        $notification->update([
            'read_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request)
    {
        RecordModel::where('assigned_to_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $notification = RecordModel::findOrFail($id);
        $this->authorizeNotification(request(), $notification);

        $notification->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Authorize that the notification belongs to the authenticated user.
     */
    protected function authorizeNotification(Request $request, RecordModel $notification)
    {
        if ($notification->assigned_to_user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
