@extends('crm.layouts.app')

@section('title', 'Survey Response - ' . $survey->title)
@section('content-class', 'flex flex-col')


@section ('content')

<breadcrumbs :items="{{ $breadcrumbs }}"></breadcrumbs>

{{-- Header --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 p-6 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-1">
                {{ $survey->title }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Response Details
            </p>
        </div>
        <a href="{{ route('surveysShow', ['id' => $survey->uuid]) }}"
           class="inline-flex items-center text-sm px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <i class="fas fa-arrow-left mr-2"></i> Back to Survey
        </a>
    </div>
</div>


@if(!$response)
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 text-center">
        <p class="text-gray-500 dark:text-gray-400">Response not found.</p>
    </div>
@else
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6" v-cloak>
    {{-- Left column: response meta --}}
    <div class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-user text-blue-600 dark:text-blue-500 mr-2"></i>
                Respondent Info
            </h3>
            <ul class="text-sm space-y-3 text-gray-700 dark:text-gray-300">
                <li><strong>Name:</strong> {{ collect([$response->first_name, $response->last_name])->filter()->implode(' ') ?: 'Anonymous' }}</li>
                <li><strong>Email:</strong> {{ $response->email ?? 'Anonymous' }}</li>
                <li><strong>Submitted:</strong> {{ $response->submitted_at ? $response->submitted_at->timezone($user->timezone ?? 'America/Chicago')->format('M d, Y g:i A') : ($response->created_at ? $response->created_at->timezone($user->timezone ?? 'America/Chicago')->format('M d, Y g:i A') : 'N/A') }}</li>
                <li><strong>IP Address:</strong> {{ $response->ip_address ?? 'N/A' }}</li>
                <li><strong>User Agent:</strong> <span class="text-gray-500">{{ $response->user_agent ?? 'N/A' }}</span></li>

                @if($response->owner_type && $response->owner_id)
                    <li class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-600 space-x-2">
                        <strong>Linked to:</strong>
                        @php
                            $ownerClass = class_basename($response->owner_type);
                            $ownerRoute = null;
                            $ownerIcon = 'fa-link';

                            switch($ownerClass) {
                                case 'Contact':
                                    $ownerRoute = route('dashShowContact', ['id' => $response->owner_id]);
                                    $ownerIcon = 'fa-user';
                                    break;
                                case 'Lead':
                                    $ownerRoute = route('dashShowLead', ['id' => $response->owner_id]);
                                    $ownerIcon = 'fa-user-plus';
                                    break;
                                case 'Vendor':
                                    $ownerRoute = route('dashShowVendor', ['id' => $response->owner_id]);
                                    $ownerIcon = 'fa-briefcase';
                                    break;
                            }
                        @endphp

                        @if($ownerRoute)
                            <a href="{{ $ownerRoute }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" target="_blank">
                                <i class="fas {{ $ownerIcon }} mr-1.5"></i>
                                {{ $ownerClass }}
                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                        @endif
                    </li>
                @endif

                @if($response->deal_id)
                    @php
                        $deal = \App\Models\Deal::find($response->deal_id);
                    @endphp
                    @if($deal)
                        <li class="space-x-2">
                            <strong>Transaction:</strong>
                            <a href="{{ route('dashShowDeal', ['id' => $deal->id]) }}"
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                               target="_blank">
                                <i class="fas fa-home mr-1.5"></i>
                                {{ $deal->title }}
                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                        </li>
                    @endif
                @endif
            </ul>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-clipboard-check text-green-600 dark:text-green-500 mr-2"></i>
                Summary
            </h3>
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-3">
                <li><strong>Survey Type:</strong> {{ ucfirst($survey->type) }}</li>
                <li><strong>Survey Status:</strong>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ml-2
                        {{ $survey->status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                        <i class="fas {{ $survey->status ? 'fa-check-circle' : 'fa-clock' }} mr-1.5"></i>
                        {{ $survey->status ? 'Active' : 'Draft' }}
                    </span>
                </li>
            </ul>
        </div>

        {{-- Reassign Response --}}
        @php
            $canReassign = $isAdmin || ($response->assigned_to == $user->id);
        @endphp
        @if($canReassign && count($TeamUsers) > 1)
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-user-edit text-purple-600 dark:text-purple-500 mr-2"></i>
                Assigned To
            </h3>
            <div class="space-y-3">
                <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    @if($response->assignedTo)
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold mr-3">
                            {{ substr($response->assignedTo->name, 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $response->assignedTo->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $response->assignedTo->email }}</p>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center mr-3">
                            <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unassigned</p>
                        </div>
                    @endif
                </div>

                <div>
                    <label for="reassign-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Reassign response to:
                    </label>
                    <select
                        id="reassign-select"
                        @change="reassignResponse({{ $response->id }}, $event.target.value)"
                        class="input-style"
                    >
                        <option value="">-- Select Team Member --</option>
                        @foreach($TeamUsers as $member)
                            @if($member->id != ($response->assigned_to ?? 0))
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @endif

        {{-- AI Analysis Section --}}
        <div class="bg-gradient-to-br from-purple-50 to-blue-50 dark:from-gray-800 dark:to-gray-900 border border-purple-200 dark:border-purple-900 rounded-lg shadow-sm p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                AI Analysis
            </h3>


            <aianalysisbutton
                :surveyresponseid="{{ $response->id }}"
                :teamid="{{ $team->id }}"
                :hasanalysis="{{ $response->latestAiAnalysis ? 'true' : 'false' }}"
                :ontrial="{{ $onTrial ? 'true' : 'false' }}"
                :subscriptionlevel="{{ $subscriptionLevel }}"
                :upgradeurl="'{{ config('app.url') }}/settings/subscriptions'"
                @analysiscomplete="onAnalysisComplete"
                @showanalysis="showAiAnalysis = true"
            />
        </div>

        {{-- Convert to Lead Button --}}
        @if($survey->type === 'lead' && !$response->converted && $response->email)
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-user-plus text-blue-600 dark:text-blue-500 mr-2"></i>
                Actions
            </h3>
            <button
                @click.prevent="convertToLead({{ $response->id }}, '{{ route('surveyResponseConvertToLead') }}')"
                class="inline-flex items-center justify-center btn btn-blue w-full"
            >
                <i class="fas fa-user-plus mr-2"></i>
                Convert to Lead
            </button>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Create a new lead from this survey response
            </p>
        </div>
        @elseif($survey->type === 'lead' && $response->converted)
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
            <div class="flex items-center justify-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
                <span class="text-sm font-medium text-green-700 dark:text-green-300">
                    Already Converted to Lead
                </span>
            </div>
        </div>
        @endif

        {{-- Schedule Follow-up Card Component --}}
        <surveyfollowupcard
            :survey-response-id="{{ $response->id }}"
            :team-id="{{ $team->id }}"
            :scheduled-followup="{{ json_encode($response->scheduledFollowupEmail) }}"
        ></surveyfollowupcard>
    </div>

    {{-- Right column: answers and AI results --}}
    <div class="lg:col-span-2 space-y-6">
        <generalvariables
            :variables="{
                @isset($response->latestAiAnalysis)
                    aiAnalysis: {{ $response->latestAiAnalysis }},
                    showAiAnalysis: true,
                @endisset
            }"
        ></generalvariables>
        <aianalysisresults
            v-if="showAiAnalysis && aiAnalysis"
            :analysis="aiAnalysis"
            :teamid="{{ $team->id }}"
            currentusername="{{ $user->name }}"
            :initially-collapsed="{{ $response->latestAiAnalysis ? 'false' : 'true' }}"
            :response="{{ $response }}"
            @close="showAiAnalysis = false"
            @suggestionsapplied="onSuggestionsApplied"
        ></aianalysisresults>


        {{-- Survey Answers --}}
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-list-alt text-purple-600 dark:text-purple-500 mr-2"></i>
                    Answers
                </h2>
            </div>
            <div class="p-6 space-y-4">
                @forelse($survey->questions as $question)
                    @php
                        $answer = $response->answers->firstWhere('survey_question_id', $question->id);
                    @endphp
                    <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-1">
                            {{ $loop->iteration }}. {{ $question->label }}
                            @if($question->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </h4>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            @if($answer)
                                @if(is_array($answer->answer))
                                    {{ implode(', ', $answer->answer) }}
                                @else
                                    {{ $answer->answer }}
                                @endif
                            @else
                                <span class="italic text-gray-400">No response</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-6">No questions found for this survey.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif



@endsection
