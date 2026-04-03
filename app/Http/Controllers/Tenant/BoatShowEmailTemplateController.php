<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatShowEvent\Support\BoatShowFollowUpMerger;
use App\Domain\EmailTemplate\Models\EmailTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BoatShowEmailTemplateController extends Controller
{
    public function index(): Response
    {
        $template = EmailTemplate::ensureBoatShowFollowUpSingleton();

        return Inertia::render('Tenant/BoatShow/EmailTemplates/Index', [
            'template' => $template,
            'availableVariables' => EmailTemplate::getAvailableVariables(),
        ]);
    }

    public function update(Request $request, EmailTemplate $email_template): RedirectResponse
    {
        if ($email_template->type !== EmailTemplate::TYPE_BOAT_SHOW_FOLLOWUP) {
            throw new NotFoundHttpException;
        }

        $singleton = EmailTemplate::ensureBoatShowFollowUpSingleton();
        if ($email_template->id !== $singleton->id) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'email_subject' => ['required', 'string', 'max:255'],
            'email_message' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $email_template->update([
            'email_subject' => $validated['email_subject'],
            'email_message' => $validated['email_message'],
            'is_active' => $validated['is_active'] ?? $email_template->is_active,
        ]);

        return redirect()->route('boat-show-email-templates.index')->with('success', 'Template updated.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['required', 'string'],
            'message' => ['required', 'string'],
        ]);

        $sample = BoatShowFollowUpMerger::sampleData();
        $subject = BoatShowFollowUpMerger::merge($validated['subject'], $sample);
        $body = BoatShowFollowUpMerger::merge($validated['message'], $sample);

        try {
            Mail::html($body, function ($mail) use ($validated, $subject) {
                $mail->to($validated['email'])->subject('[TEST] '.$subject);
            });

            return back()->with('success', 'Test email sent to '.$validated['email'].'.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
