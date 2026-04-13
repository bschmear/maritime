<?php

namespace App\Http\Controllers;

use App\Mail\ContactDemoRequest;
use App\Models\Faq;
use App\Support\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// use App\Models\User;

use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{

    public function __construct(Request $request)
    {
        $this->route = Route::currentRouteName();
       
    }

    public function about()
    {
        $user = auth()->user();
        return Inertia::render('About', [
            
        ]);
    }

    // public function pricing()
    // {
    //     $user = auth()->user();

    //     $plans = Cache::remember('plans.active', now()->addHours(12), function () {
    //         return Plan::where('active', 1)
    //             ->where('promotional', 0)
    //             ->with(['items' => fn($query) => $query->where('active', true)])
    //             ->get();
    //     });

    //     return view('pricing', compact('user', 'plans'));
    // }

    public function faq()
    {
        $user = auth()->user();

        $faqs = Cache::remember(PublicPageCache::FAQS_ALL, now()->addHours(12), function () {
            return Faq::query()
                ->orderByDesc('featured')
                ->orderBy('created_at')
                ->get()
                ->map(function ($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                        'featured' => $faq->featured,
                    ];
                })
                ->values()
                ->all();
        });

        return Inertia::render('Faq', [
            'faqs' => $faqs,
        ]);

    }

    public function contact()
    {
        $user = auth()->user();

        session(['contact_form_loaded_at' => time()]);

        return Inertia::render('Contact', [
            'legalEmail' => config('app.legal_email'),
        ]);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        if ($this->contactSubmissionLooksAutomated($request)) {
            Log::warning('Contact form rejected (anti-bot heuristics)', [
                'ip' => $request->ip(),
            ]);

            return redirect()->route('contact')
                ->with('error', 'We couldn’t verify your submission. Please wait a few seconds after the page loads, then try again.')
                ->withInput($request->except('_company_website'));
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'dealership_name' => ['required', 'string', 'max:255'],
            'locations' => ['required', 'string', Rule::in(['1', '2-5', '6-10', '10+'])],
            'message' => ['nullable', 'string', 'max:5000'],
            '_company_website' => ['nullable', 'string', 'max:0'],
        ]);

        $mailFields = Arr::except($validated, ['_company_website']);

        $locationsLabel = match ($mailFields['locations']) {
            '1' => '1 location',
            '2-5' => '2–5 locations',
            '6-10' => '6–10 locations',
            '10+' => '10+ locations',
            default => $mailFields['locations'],
        };

        $to = $this->outboundContactMailAddress();

        Mail::to($to)->send(new ContactDemoRequest($mailFields, $locationsLabel));

        return redirect()->route('contact')
        ->with('success', 'Thanks! We received your request and will be in touch soon.');
    }

    /**
     * Honeypot + minimum time since GET /contact (no captcha).
     */
    protected function contactSubmissionLooksAutomated(Request $request): bool
    {
        if (filled($request->input('_company_website'))) {
            return true;
        }

        $loadedAt = session('contact_form_loaded_at');

        if (! is_int($loadedAt) && ! is_numeric($loadedAt)) {
            return true;
        }

        $elapsed = time() - (int) $loadedAt;

        return $elapsed < 2;
    }

    protected function outboundContactMailAddress(): string
    {
        foreach ([config('app.contact_email'), config('app.legal_email')] as $address) {
            if (is_string($address) && trim($address) !== '') {
                return trim($address);
            }
        }

        return 'contact@helmful.com';
    }

    // public function support()
    // {
    //     $user = auth()->user();
    //     return view('support',compact(['user']));
    // }

    public function terms()
    {
        $user = auth()->user();

        // return view('page.terms',compact(['user']));

        return Inertia::render('Terms', [
            'legalEmail' => config('app.legal_email'),
        ]);


    }

    public function privacy()
    {
        $user = auth()->user();
        return Inertia::render('Privacy', [
            'legalEmail' => config('app.legal_email'),
        ]);
    }


    // public function googlecal()
    // {
    //     $user = auth()->user();
    //     return view('landingpages.integrations.google-calendar',compact(['user']));
    // }

    // public function outlookcal()
    // {
    //     $user = auth()->user();
    //     return view('landingpages.integrations.outlook-calendar',compact(['user']));
    // }

    // public function mailchimp()
    // {
    //     $user = auth()->user();
    //     return view('landingpages.integrations.mailchimp',compact(['user']));
    // }


}
