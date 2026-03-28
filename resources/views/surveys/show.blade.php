@extends('layouts.private')
@section('title', $agent ? ($agent->name . "'s Survey") : 'Survey')

@push('meta')
@php
    $pageTitle = $agent ? ($agent->name . "'s Survey") : 'Survey';
    $pageDescription = $agent
        ? "Complete " . $agent->name . "'s client survey to help us understand your needs and provide personalized real estate guidance. " . ($team->team_name ? "Powered by " . $team->team_name . "." : "")
        : "Client survey to collect information for personalized real estate guidance and recommendations.";
    $pageUrl = $survey->getPublicUrl();
    $ogImage = 'https://closing-cloud-prod.s3.us-east-1.amazonaws.com/public/thumbnails/survey_page.jpg';
@endphp

<meta name="description" content="{{ $pageDescription }}">

<!-- Open Graph -->
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:url" content="{{ $pageUrl }}">
<meta property="og:type" content="website">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<!-- Schema.org structured data -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "{{ $pageTitle }}",
    "url": "{{ $pageUrl }}",
    @if($ogImage)
    "image": "{{ $ogImage }}",
    @endif
    "description": "{{ $pageDescription }}",
    "applicationCategory": "SurveyApplication",
    "operatingSystem": "Web",
    "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
    },
    @if($agent)
    "author": {
        "@type": "Person",
        "name": "{{ $agent->name }}",
        @if($agent->email)
        "email": "{{ $agent->email }}",
        @endif
        @if($agent->phone)
        "telephone": "{{ $agent->phone }}",
        @endif
        @if(isset($agent->avatar))
        "image": "{{ $agent->getUserImgPath() }}"
        @endif
    },
    @endif
    "featureList": [
        "Personalized Recommendations",
        "Client Preferences Collection",
        "Confidential Responses",
        "Quick and Easy Completion",
        "Pre-filled Information for Convenience"
    ]
}
</script>
@endpush

@push('header.styles')


<style>
:root {
    --survey-color: {{ $surveyColor }};
}
.survey-accent-bg {
    background-color: var(--survey-color) !important;
}
.survey-accent-text {
    color: var(--survey-color) !important;
}
.survey-accent-border {
    border-color: var(--survey-color) !important;
}
.survey-accent-gradient {
    background: linear-gradient(to bottom right, var(--survey-color), color-mix(in srgb, var(--survey-color) 70%, black)) !important;
}
</style>
@endpush
@section('content')

<!-- Admin Edit Modal (Top Right) -->
@if($canEdit)
<admin-survey-controls
    survey-id="{{ $survey->uuid }}"
    edit-url="{{ $survey->surveysEdit() }}"
    analytics-url="{{ $survey->surveysShow() }}"
    :can-customize-colors="{{ $subscription && $subscription->level >= 2 ? 'true' : 'false' }}"
    initial-color-scheme="{{ $survey->color_scheme ?? 'default' }}"
    initial-custom-color="{{ $survey->custom_color ?? config('app.app_brand', '#0d9488') }}"
    default-color="{{ config('app.app_brand', '#0d9488') }}"
    team-color="{{ $team->team_color ?? '' }}"
    current-color="{{ $surveyColor }}"
    update-route="{{ route('surveysPublicEdit') }}"
></admin-survey-controls>
@endif

<!-- Hero Header with Gradient -->
<div class="relative survey-accent-gradient dark:from-gray-900 dark:via-gray-800 dark:to-gray-900" >
    <!-- Navigation -->
    <header class="relative z-10">
        <nav class="border-b border-white/10">
            <div class="flex flex-wrap justify-between items-center mx-auto max-w-screen-xl px-4 md:px-6 py-4">
                @if($team->logo && !config('global.demoMode'))

                <div class="flex items-center">
                    <div class="bg-white rounded-lg p-2 shadow-lg  timeline-logo">
                        {!! $team->getPhotoUrlAttribute() !!}
                    </div>
                </div>
                @else
                <a href="{{ config('app.url') }}" title="Closing Cloud" class="flex items-center">
                    <img src="{{ asset('img/closingcloud-200b.svg') }}" alt="logo" class="h-8 brightness-0 invert">
                </a>
                @endif
                <!-- Contact Phone (Upper Right) -->
                @if($agent && $agent->phone)
                <div class="hidden sm:block text-right">
                    <a href="tel:{{ $agent->phone }}" class="text-white hover:opacity-80 transition-all duration-200">
                        <div class="text-sm opacity-90">Contact Me</div>
                        <div class="text-lg font-semibold">{{ $agent->phone }}</div>
                    </a>
                </div>
                @endif

            </div>
        </nav>
    </header>

    <!-- Hero Content -->
    <div class="max-w-4xl mx-auto px-4 py-12 md:py-16 text-center">
        <div class="mb-6">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-white border border-white/20 backdrop-blur-sm">
                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                </svg>
                Survey
            </span>
        </div>

        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 leading-tight">
            {{ $survey->title }}
        </h1>

        @if($survey->public_description)
        <p class="text-lg md:text-xl text-blue-100 max-w-2xl mx-auto leading-relaxed">
            {{ $survey->public_description }}
        </p>
        @endif

        <div class="mt-6 flex items-center justify-center space-x-4 text-sm text-blue-100">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
                Takes ~{{ $survey->estimated_time ?? '5' }} minutes
            </div>
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Your responses are confidential
            </div>
        </div>
    </div>

    <!-- Decorative Wave -->
    <div class="relative">
        <svg class="w-full h-12 md:h-16" viewBox="0 0 1440 48" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M0 48H1440V0C1440 0 1140 48 720 48C300 48 0 0 0 0V48Z" class="fill-gray-50 dark:fill-gray-900"/>
        </svg>
    </div>
</div>

<!-- Main Content -->
<div class="bg-gray-50 dark:bg-gray-900 min-h-screen -mt-1" id="PublicShowSurvey">
    <div class="max-w-7xl mx-auto px-4 py-8 md:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-6">

            <!-- Left Sidebar - Agent Info (Hidden on mobile until after submission) -->
            <div class="lg:col-span-1 order-2 lg:order-1">
                <div class="sticky top-8 space-y-6">

                    <!-- Agent Card -->
                    @if(isset($agent))
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <!-- Agent Header with Photo -->
                        <div class="relative survey-accent-gradient dark:from-gray-700 dark:to-gray-800 p-6 pb-16">
                            <div class="relative text-center">
                                <h3 class="text-white font-semibold text-lg mb-1">Your Real Estate Expert</h3>
                                <p class="text-gray-100 text-sm">Available to help you</p>
                            </div>
                        </div>

                        <!-- Agent Photo -->
                        <div class="relative -mt-12 mb-4 flex justify-center">
                            <div class="relative">

                                @if(isset($agent->avatar))
                                <img src="{{ $agent->getUserImgPath() }}" alt="{{ $agent->name ?? 'Agent' }}" class="w-24 h-24 rounded-full border-4 border-white dark:border-gray-800 shadow-xl object-cover">
                                @else
                                <div class="w-24 h-24 rounded-full border-4 border-white dark:border-gray-800 shadow-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                    <span class="text-3xl font-bold text-white">{{ substr($agent->name ?? 'A', 0, 1) }}</span>
                                </div>
                                @endif
                                {{-- <div class="absolute bottom-0 right-0 w-6 h-6 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></div> --}}
                            </div>
                        </div>

                        <!-- Agent Details -->
                        <div class="px-6 pb-6">
                            <div class="text-center mb-4">
                                <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                                    {{ $agent->name ?? 'Real Estate Agent' }}
                                </h4>
                                @if(isset($agent->title))
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $agent->title }}</p>
                                @endif
                                @if(isset($agent->company))
                                <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">{{ $agent->company }}</p>
                                @endif
                            </div>

                            <!-- Contact Info -->
                            <div class="space-y-3">
                                @if(isset($agent->phone))
                                <a href="tel:{{ $agent->phone }}" class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Phone</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white break-all">{{ $agent->phone }}</p>
                                    </div>
                                </a>
                                @endif

                                @if(isset($agent->email))
                                <a href="mailto:{{ $agent->email }}" class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Email</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white break-all">{{ $agent->email }}</p>
                                    </div>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Help Card -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-1">Need Help?</h4>
                                <p class="text-sm text-blue-800 dark:text-blue-400">
                                    If you have any questions about this survey, feel free to reach out to us using the contact information above.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Indicator (Shows during survey) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border border-gray-200 dark:border-gray-700 hidden lg:block" id="progress-card">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Survey Progress
                        </h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Completion</span>
                                <span class="font-medium text-gray-900 dark:text-white" id="progress-text">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%" id="progress-bar"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Main Survey Content -->
            <div class="lg:col-span-2 order-1 lg:order-2">
                @if($recipientData && isset($recipientData['name']) )
                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        <i class="fas fa-user-circle mr-2"></i>
                        Hello, <strong>{{ $recipientData['name'] }}</strong>! We've pre-filled your information to save you time.
                    </p>
                </div>
                @endif
                <survey
                    :survey='@json($survey)'
                    survey-color="{{ $surveyColor }}"
                    submitroute="{{ route('surveysPublicSubmit') }}"
                    :recipientdata='@json($recipientData)'
                ></survey>
            </div>

        </div>
    </div>
</div>



@endsection

