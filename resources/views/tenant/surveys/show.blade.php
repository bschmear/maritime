@extends ('crm.layouts.app')
@section('title', $survey->title)
@section('content-class', 'flex flex-col')

@push('head.styles')
<style>
[v-cloak] {
    display: none !important;
}
</style>
@endpush

@section ('content')

<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>

{{-- Survey Header --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h1 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">{{ $survey->title }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $survey->status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                        <i class="fas {{ $survey->status ? 'fa-check-circle' : 'fa-clock' }} mr-1.5"></i>
                        {{ $survey->status ? 'Active' : 'Draft' }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($survey->type === 'feedback') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                        @elseif($survey->type === 'lead') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                        @elseif($survey->type === 'followup') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300
                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                        @endif">
                        {{ ucfirst($survey->type) }}
                    </span>
                </div>
                @if($survey->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $survey->description }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center">
                        <i class="fas fa-user w-4 mr-1.5"></i>
                        {{ $survey->user->name ?? 'Unknown' }}
                    </span>
                    <span class="inline-flex items-center">
                        <i class="fas fa-calendar w-4 mr-1.5"></i>
                        {{ $survey->created_at->format('M d, Y') }}
                    </span>
                    <span class="inline-flex items-center">
                        <i class="fas fa-comments w-4 mr-1.5"></i>
                        {{ $survey->responses->count() }} responses
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2" v-cloak>
                <a href="{{ route('surveysEdit', ['id' => $survey->uuid]) }}" class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700">
                    <i class="fas fa-edit mr-2"></i>
                    Edit
                </a>
                
                <surveystatustoggle
                    :status="{{ $survey->status ? 'true' : 'false' }}"
                    updateroute="{{ route('surveysUpdate', ['id' => $survey->uuid]) }}">
                </surveystatustoggle>
                
                <surveyactions
                    :status="{{ $survey->status ? 1 : 0 }}"
                    deleteroute="{{ route('surveysDestroy', ['id' => $survey->uuid]) }}"
                    surveysindex="{{ route('surveysIndex') }}"
                    surveysupdate="{{ route('surveysUpdate', ['id' => $survey->uuid]) }}"
                    surveysclone="{{ route('surveysClone', ['id' => $survey->uuid]) }}"
                    uuid="{{ $survey->uuid }}">
                </surveyactions>
            </div>
        </div>
    </div>
</div>

{{-- Stats Cards --}}
<div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300">
                <i class="fas fa-comments text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($survey->responses->count()) }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Total Responses</p>
        </div>
    </div>
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-green-600 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-300">
                <i class="fas fa-calendar-week text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-lg md:text-xl lg:text-2xl  font-bold text-gray-900 dark:text-white">{{ $weeklyResponses }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">This Week</p>
        </div>
    </div>
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300">
                <i class="fas fa-clipboard-check text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-lg md:text-xl lg:text-2xl  font-bold text-gray-900 dark:text-white">{{ $completionRate }}%</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Completion Rate</p>
        </div>
    </div>
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-yellow-600 bg-yellow-100 rounded-lg dark:bg-yellow-900 dark:text-yellow-300">
                <i class="fas fa-star text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-lg md:text-xl lg:text-2xl  font-bold text-gray-900 dark:text-white">{{ $avgRating ?? 'N/A' }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Avg. Rating</p>
        </div>
    </div>
</div>

{{-- Main Content Grid --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Column: Questions --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Questions Section --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="sm:flex space-y-2 sm:space-y-0 items-center justify-between">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-question-circle text-blue-600 dark:text-blue-500 mr-2"></i>
                        Questions ({{ $survey->questions->count() }})
                    </h2>
                    <div class="flex space-x-2 divide-x divide-gray-300 dark:divide-gray-600">
                        <a href="{{ route('surveysEdit', ['id' => $survey->uuid]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">
                            Edit Questions
                        </a>
                        <button @click="toggleBooleanObject('hideQuestions')" class="pl-2 text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-500 dark:hover:text-white">
                            <i :class="booleanObject.hideQuestions ? 'fas fa-eye-slash' : 'fas fa-eye'" class="mr-1"></i>
                            <span v-if="booleanObject.hideQuestions">Show Questions</span>
                            <span v-else >Hide Questions</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6" v-show="!booleanObject.hideQuestions">
                @if($survey->questions->count() > 0)
                    <div class="space-y-4">
                        @foreach($survey->questions->sortBy('order') as $index => $question)
                        <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300">
                                            {{ $index + 1 }}
                                        </span>
                                        <h3 class="font-medium text-gray-900 dark:text-white">{{ $question->label }}</h3>
                                        @if($question->required)
                                        <span class="text-red-500 text-sm">*</span>
                                        @endif
                                    </div>

                                    <div class="ml-8">
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                                            <i class="fas
                                                @if($question->type === 'text') fa-font
                                                @elseif($question->type === 'multiple_choice') fa-list-ul
                                                @elseif($question->type === 'rating') fa-star
                                                @elseif($question->type === 'dropdown') fa-caret-square-down
                                                @elseif($question->type === 'nps') fa-chart-line
                                                @else fa-question
                                                @endif mr-1"></i>
                                            {{ ucfirst(str_replace('_', ' ', $question->type)) }}
                                        </span>

                                        @if($question->options && count($question->options) > 0)
                                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">Options:</span>
                                            {{ implode(', ', array_slice($question->options, 0, 3)) }}
                                            @if(count($question->options) > 3)
                                                <span class="text-gray-400">+{{ count($question->options) - 3 }} more</span>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-question-circle text-5xl mb-3"></i>
                        <p class="text-sm">No questions added yet</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Responses Section --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-chart-bar text-green-600 dark:text-green-500 mr-2"></i>
                        Recent Responses
                    </h2>
                    @if($survey->responses->count() > 0)
                    <a href="{{ route('surveyResponsesByUuid', ['id' => $survey->uuid]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">
                        View All
                    </a>
                    @endif
                </div>
            </div>

            <div class="p-6">
                @if($survey->responses->count() > 0)
                    <div class="space-y-3">
                        @foreach($survey->responses->take(5) as $response)
                        <a href="{{ route('surveyResponseShow', ['sid' => $survey->uuid, 'rid' => $response->id]) }}" class="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:hover:bg-gray-600 dark:hover:border-gray-600 dark:focus:ring-gray-700">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-10 h-10 bg-gray-200 rounded-full dark:bg-gray-600">
                                        <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate dark:text-white">
                                        {{ $response->email ?? 'Anonymous' }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                        {{ $response->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="inline-flex items-center p-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg  dark:bg-gray-800 dark:text-white dark:border-gray-600 ">
                                <i class="fas fa-eye"></i>
                            </div>
                        </a>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-inbox text-5xl mb-3"></i>
                        <p class="text-sm">No responses yet</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Settings --}}
    <div class="space-y-6">
        {{-- Survey Link --}}
        @if($survey->status)
        <survey-links
            base-url="{{ $survey->getPublicUrl() }}"
            :team-users="{{ json_encode(array_values($TeamUsers)) }}"
            :current-user-id="{{ $user->id }}"
            current-user-name="{{ $user->name }}"
            visibility="{{ $survey->visibility ?? 'public' }}"
        ></survey-links>
        @endif

        {{-- Delivery Settings --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-paper-plane text-green-600 dark:text-green-500 mr-2"></i>
                Delivery & Automation
            </h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Delivery Method</span>
                    <span class="inline-flex items-center font-medium text-gray-900 dark:text-white">
                        <i class="fas
                            @if($survey->delivery_method === 'email') fa-envelope
                            @elseif($survey->delivery_method === 'sms') fa-sms
                            @else fa-code
                            @endif mr-1.5"></i>
                        {{ ucfirst($survey->delivery_method ?? 'Email') }}
                    </span>
                </div>

                <div class="flex items-center justify-between py-2 text-sm border-t border-gray-200 dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">Automation</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ ucfirst(str_replace('_', ' ', $survey->automation_trigger ?? 'manual')) }}
                    </span>
                </div>

                @if($survey->automation_config && isset($survey->automation_config['days']) && $survey->automation_trigger != 'manual')
                <div class="flex items-center justify-between py-2 text-sm border-t border-gray-200 dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">Send After</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ $survey->automation_config['days'] }} days
                    </span>
                </div>
                @endif
            </div>
        </div>

        {{-- Privacy Settings --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-shield-alt text-purple-600 dark:text-purple-500 mr-2"></i>
                Privacy Settings
            </h3>
            @php
                $privacy = $survey->privacy_settings ?? [];
            @endphp
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Anonymous Responses</span>
                    <i class="fas {{ ($privacy['anonymous'] ?? false) ? 'fa-check-circle text-green-500 dark:text-green-400' : 'fa-times-circle text-gray-300 dark:text-gray-600' }}"></i>
                </li>

                <li class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Require Email</span>
                    <i class="fas {{ ($privacy['require_email'] ?? false) ? 'fa-check-circle text-green-500 dark:text-green-400' : 'fa-times-circle text-gray-300 dark:text-gray-600' }}"></i>
                </li>

                <li class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">One Response Per User</span>
                    <i class="fas {{ ($privacy['one_response_per_user'] ?? false) ? 'fa-check-circle text-green-500 dark:text-green-400' : 'fa-times-circle text-gray-300 dark:text-gray-600' }}"></i>
                </li>

                <li class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Show Results</span>
                    <i class="fas {{ ($privacy['show_results'] ?? false) ? 'fa-check-circle text-green-500 dark:text-green-400' : 'fa-times-circle text-gray-300 dark:text-gray-600' }}"></i>
                </li>
            </ul>
        </div>

        {{-- Completion Settings --}}
        @if($survey->thank_you_message || $survey->redirect_url)
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-heart text-red-600 dark:text-red-500 mr-2"></i>
                Completion Settings
            </h3>
            <div class="space-y-4">
                @if($survey->thank_you_message)
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Thank You Message</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $survey->thank_you_message }}</p>
                </div>
                @endif

                @if($survey->redirect_url)
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Redirect URL</label>
                    <a href="{{ $survey->redirect_url }}" target="_blank" class="inline-flex items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-500 break-all">
                        {{ $survey->redirect_url }}
                        <i class="fas fa-external-link-alt ml-1.5 text-xs"></i>
                    </a>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>


</div>

@endsection
