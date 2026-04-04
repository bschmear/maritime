<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// use App\Models\User;

// use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{

    public function __construct(Request $request)
    {
        $this->route = Route::currentRouteName();
       
    }

    // public function about()
    // {
    //     $user = auth()->user();
    //     return view('about',compact(['user']));
    // }

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

    // public function faq()
    // {
    //     $user = auth()->user();

    //     $faqs = Cache::remember('faqs.all', now()->addHours(12), function () {
    //         return Faq::all();
    //     });

    //     return view('faq', compact('user', 'faqs'));
    // }

    // public function support()
    // {
    //     $user = auth()->user();
    //     return view('support',compact(['user']));
    // }

    public function terms()
    {
        $user = auth()->user();

        return view('page.terms',compact(['user']));
    }

    public function privacy()
    {
        $user = auth()->user();
        return view('page.privacy',compact(['user']));
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
