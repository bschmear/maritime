@extends ('crm.layouts.app')
@section('title', $survey ? "Responses: {$survey->title}" : "All Survey Responses")
@section('body-class', 'table-view')
@section('content-class', 'flex flex-col')
@section ('content')

<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>

{{-- Stats Overview Cards (only show when viewing specific survey) --}}
@if($survey)
<div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
    {{-- Total Responses --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300">
                <i class="fas fa-comments text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($responses->total()) }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Total Responses</p>
        </div>
    </div>

    {{-- Survey Type --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300">
                <i class="fas fa-tag text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                @if($survey->type === 'lead')Lead
                @elseif($survey->type === 'feedback')Feedback
                @elseif($survey->type === 'followup')Follow Up
                @else Custom
                @endif
            </h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Survey Type</p>
        </div>
    </div>

    {{-- Survey Status --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 rounded-lg
                @if($survey->status) text-green-600 bg-green-100 dark:bg-green-900 dark:text-green-300
                @else text-gray-600 bg-gray-100 dark:bg-gray-900 dark:text-gray-300
                @endif">
                <i class="fas fa-circle-check text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                @if($survey->status) Active @else Draft @endif
            </h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Status</p>
        </div>
    </div>

    {{-- Created By --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="avatar-wrap">
                <avatar :name="'{{ $survey->user->name ?? 'Unknown' }}'" v-cloak></avatar>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $survey->user->name ?? 'Unknown' }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Created By</p>
        </div>
    </div>
</div>
@endif

{{-- Main Table Container --}}
<div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden grow flex flex-col shadow-md" v-cloak>
    <div class="px-4">
        <div class="border-b dark:border-gray-700 space-y-4">
            {{-- Top Bar with Title and Buttons --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b dark:border-gray-700">
                {{-- Title --}}
                <div class="flex items-center flex-1">
                    <h5 class="dark:text-white font-semibold">
                        <span class="pt-0 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">
                            @if($survey)
                                Responses: {{ $survey->title }}
                            @else
                                All Survey Responses
                            @endif
                        </span>
                    </h5>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 gap-2 mt-3 sm:mt-0 w-full sm:w-auto">
                    @if($survey)
                        {{-- View Survey Button --}}
                        <a href="{{ route('surveysShow', ['id' => $survey->uuid]) }}" 
                            class="btn btn-outline sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                            <i class="fas fa-poll-h"></i>
                            <span>View Survey</span>
                        </a>

                        {{-- View All Responses Button --}}
                        <a href="{{ route('surveyResponses') }}" 
                            class="btn btn-outline sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                            <i class="fas fa-list"></i>
                            <span>All Responses</span>
                        </a>
                    @else
                        {{-- Back to Surveys Button --}}
                        <a href="{{ route('surveysIndex') }}" 
                            class="btn btn-outline sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Surveys</span>
                        </a>
                    @endif

                    {{-- Export Button (future feature) --}}
                    {{-- <button type="button"
                        class="btn btn-primary sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                    </button> --}}
                </div>
            </div>

            {{-- Filter Bar --}}
            @if($isAdmin)
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4">
                <div class="flex items-center gap-3">
                    <label for="team-member-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        Filter by Team Member:
                    </label>
                    <select 
                        id="team-member-filter" 
                        onchange="window.location.href = updateTeamMemberFilter(this.value)"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full sm:w-64 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        <option value="all" {{ $filterUser === 'all' ? 'selected' : '' }}>All Team Members</option>
                        @foreach($TeamUsers as $teamUser)
                            <option value="{{ $teamUser['id'] }}" {{ $filterUser == $teamUser['id'] ? 'selected' : '' }}>
                                {{ $teamUser['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Table Content --}}
    <div class="overflow-x-auto grow flex flex-col" v-cloak>
        {{-- Empty State --}}
        @if($responses->isEmpty())
        <div class="text-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-gray-400 flex items-center justify-center grow">
            <div class="relative text-center flex flex-col justify-center items-center p-8">
                <i class="fas fa-inbox text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                <h3 class="mb-4 text-lg font-bold text-gray-900 md:text-xl dark:text-white">No responses yet</h3>
                <p class="mb-4 text-gray-500 dark:text-gray-400">
                    @if($survey)
                        This survey hasn't received any responses yet.
                    @else
                        No survey responses have been submitted yet.
                    @endif
                </p>
                @if($survey)
                <a href="{{ route('surveysShow', ['id' => $survey->uuid]) }}" class="btn btn-primary inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i>
                    View Survey
                </a>
                @endif
            </div>
        </div>
        @else
        {{-- Responses Table --}}
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Submitted
                    </th>
                    @if(!$survey)
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Survey
                    </th>
                    @endif
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Type
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Respondent
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Email
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Type
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Assigned To
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($responses as $response)
                <tr class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    {{-- Submitted Date --}}
                    <td class="px-4 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ $response->submitted_at ? $response->submitted_at->timezone($user->timezone ?? 'America/Chicago')->format('M d, Y') : 'N/A' }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $response->submitted_at ? $response->submitted_at->timezone($user->timezone ?? 'America/Chicago')->format('g:i A') : '' }}
                            </span>
                        </div>
                    </td>

                    @if(!$survey)
                    <td class="px-4 py-3">
                        <a href="{{ route('surveyResponsesByUuid', ['id' => $response->survey->uuid]) }}"
                            class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline underline-offset-2">
                            {{ $response->survey->title }}
                        </a>
                    </td>
                    @endif

                    <td class="px-4 py-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            @if($response->survey->type === 'lead')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    Lead
                                </span>
                            @elseif($response->survey->type === 'feedback')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    Feedback
                                </span>
                            @elseif($response->survey->type === 'followup')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                    Follow Up
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                    Custom
                                </span>
                            @endif
                        </div>
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            @if($response->first_name || $response->last_name)
                                <div class="avatar-wrap small mr-2">
                                    <avatar :name="'{{ trim(($response->first_name ?? '') . ' ' . ($response->last_name ?? '')) }}'" v-cloak></avatar>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ trim(($response->first_name ?? '') . ' ' . ($response->last_name ?? '')) }}
                                </span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400 italic">Anonymous</span>
                            @endif
                        </div>
                    </td>

                    {{-- Email --}}
                    <td class="px-4 py-3">
                        @if($response->email)
                            <a href="mailto:{{ $response->email }}" 
                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                {{ $response->email }}
                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">No email</span>
                        @endif
                    </td>

                    {{-- Owner Type --}}
                    <td class="px-4 py-3">
                        @if($response->owner_type && $response->owner_id)
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
                                        $ownerIcon = 'fa-building';
                                        break;
                                }
                            @endphp
                            
                            @if($ownerRoute)
                                <a href="{{ $ownerRoute }}" 
                                    class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                    target="_blank">
                                    <i class="fas {{ $ownerIcon }} mr-1.5"></i>
                                    {{ $ownerClass }}
                                </a>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">{{ $ownerClass }}</span>
                            @endif
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">Public</span>
                        @endif
                    </td>

                    {{-- Assigned To --}}
                    <td class="px-4 py-3">
                        @if($response->assignedTo)
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-semibold mr-2">
                                    {{ substr($response->assignedTo->name, 0, 1) }}
                                </div>
                                <span class="text-sm text-gray-900 dark:text-white">{{ $response->assignedTo->name }}</span>
                            </div>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic text-sm">Unassigned</span>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('surveyResponseShow', ['sid' => $response->survey->uuid, 'rid' => $response->id]) }}"
                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" 
                                title="View Response">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            @if($response->survey->type === 'lead' && !$response->converted && $response->email)
                                <button 
                                    @click="convertToLead({{ $response->id }}, '{{ route('surveyResponseConvertToLead') }}')"
                                    class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300" 
                                    title="Convert to Lead">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                            @elseif($response->converted)
                                <span class="text-green-600 dark:text-green-400" title="Already Converted">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Pagination --}}
    @if(!$responses->isEmpty())
    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-gray-400 mb-3 sm:mb-0">
                Showing <span class="font-semibold text-gray-900 dark:text-white">{{ $responses->firstItem() }}</span> to 
                <span class="font-semibold text-gray-900 dark:text-white">{{ $responses->lastItem() }}</span> of 
                <span class="font-semibold text-gray-900 dark:text-white">{{ $responses->total() }}</span> responses
            </div>
            
            <div class="flex items-center space-x-2">
                {{ $responses->appends(['filteruser' => request('filteruser'), 'id' => request('id')])->links() }}
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@push('footer.scripts')
<script>
function updateTeamMemberFilter(userId) {
    const url = new URL(window.location.href);
    
    if (userId === 'all') {
        url.searchParams.delete('filteruser');
    } else {
        url.searchParams.set('filteruser', userId);
    }
    
    return url.toString();
}
</script>
@endpush

