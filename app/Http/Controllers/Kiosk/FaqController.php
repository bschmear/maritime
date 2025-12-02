<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        $faqs = Faq::latest()->paginate(15);

        return Inertia::render('Kiosk/Faqs/Index', [
            'faqs' => $faqs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Faqs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'featured' => 'boolean',
        ]);

        Faq::create($validated);

        return redirect()->route('kiosk.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function show(Faq $faq): Response
    {
        return Inertia::render('Kiosk/Faqs/Show', [
            'faq' => $faq,
        ]);
    }

    public function edit(Faq $faq): Response
    {
        return Inertia::render('Kiosk/Faqs/Edit', [
            'faq' => $faq,
        ]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'featured' => 'boolean',
        ]);

        $faq->update($validated);

        return redirect()->route('kiosk.faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('kiosk.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }
}
