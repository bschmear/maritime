@extends ('crm.layouts.app')
@section('title', "Surveys")
@section('body-class', 'table-view')
@section('content-class', 'flex flex-col')
@section ('content')

<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>

{{-- Stats Overview Cards --}}
<div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
    {{-- Total Responses This Month --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300">
                <i class="fas fa-comments text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalResponsesThisMonth) }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Total Responses This Month</p>
        </div>
    </div>

    {{-- Avg. Satisfaction Score --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-green-600 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-300">
                <i class="fas fa-star text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($avgSatisfaction, 1) }}/5.0</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Avg. Satisfaction Score</p>
        </div>
    </div>

    {{-- Top user by surveys created --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300">
                <i class="fas fa-user-tie text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ data_get($topUsers ?? null, 'name', '—') }}</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Most surveys created</p>
        </div>
    </div>

    {{-- Conversion Rate --}}
    <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-12 h-12 text-yellow-600 bg-yellow-100 rounded-lg dark:bg-yellow-900 dark:text-yellow-300">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
        </div>
        <div class="flex-1 ms-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $conversionRate }}%</h3>
            <p class="text-sm font-normal text-gray-500 dark:text-gray-400">Conversion Rate</p>
        </div>
    </div>
</div>

{{-- Main Table Container --}}
<div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden grow flex flex-col" :class="tableitems.length > 0 ? 'shadow-md' : ''" v-cloak>
    <div class="px-4">
        <div class="border-b dark:border-gray-700 space-y-4">
            {{-- Top Bar with Title and Buttons --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b dark:border-gray-700">
                {{-- Title --}}
                <div class="flex items-center flex-1">
                    <h5 class="dark:text-white font-semibold">
                        <span class="pt-0 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">
                            Surveys
                        </span>
                    </h5>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 gap-2 mt-3 sm:mt-0 w-full sm:w-auto">
                    {{-- Delete Selected Button --}}
                    <button type="button"
                        class="red-button sm flex items-center justify-center space-x-2 w-full sm:w-auto"
                        @click="toggleDeleteModal(true)" v-show="tableSelectedIds.length > 0">
                        <i class="far fa-trash-alt"></i>
                        <span>Delete selected</span>
                    </button>

                    {{-- View All Responses Button --}}
                    <a href="{{ route('surveyResponses') }}" 
                        class="btn btn-outline sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                        <i class="fas fa-comments"></i>
                        <span>View All Responses</span>
                    </a>

                    {{-- Create New Survey Button --}}
                    <a href="{{ route('surveysCreate') }}" 
                        class="btn btn-primary sm flex items-center justify-center space-x-2 w-full sm:w-auto">
                        <i class="far fa-plus"></i>
                        <span>Create New Survey</span>
                    </a>

                    {{-- Actions Dropdown --}}
{{--                     <button id="actionsDropdownButton" data-dropdown-toggle="actionsDropdown"
                        class="icon-btn w-full sm:w-auto justify-center sm" type="button">
                        <i class="fas fa-cog text-md"></i>
                    </button>

                    <div id="actionsDropdown"
                        class="z-10 hidden w-50 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                        <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400"
                            aria-labelledby="actionsDropdownButton">
                            <li>
                                <button type="button" class="dropdown-btn">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>View Analytics</span>
                                </button>
                            </li>
                        </ul>
                    </div> --}}
                </div>
            </div>

            {{-- Filters and Search Bar --}}
            <div class="flex flex-col pb-4 space-y-3 space-x-2">
                <div class="w-full flex flex-col space-y-3 lg:space-y-0 lg:flex-row lg:items-center">
                    {{-- Search Bar --}}
                    <searchfilter placeholder="Search surveys..." text="{{ $filterName }}" param="n"></searchfilter>
                    
                    {{-- Filter Buttons --}}
                    <div class="grid grid-cols-2 gap-4 md:flex md:flex-wrap md:items-center md:space-x-2 md:gap-0">

                        {{-- <radiofilter label="Stage" @if($filterType) :value="{{ $filterType  }}" @endif param="type" :options="{{$filterType}}" :button="true"></radiofilter> --}}

                        <radiofilter label="Type"
                                     @if($filterType) :value="{{ $filterType }}" @endif
                                     param="type"
                                     :options="{{$surveyTypes}}"
                                     :button="true"
                        ></radiofilter>

                        @if(count($TeamUsers) > 1)
                            <teamusers
                                :value="0"
                                :options='@json($TeamUsers)'
                            ></teamusers>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col items-stretch justify-between space-y-3 md:flex-row md:items-center md:space-y-0 mx-4 ">
                    <radiofilter  label="Status" value="{{ $filterStatus }}"  param="status" :options="{{$surveyStatuses}}" :button="false" all="false"></radiofilter>
                </div>
            </div>
        </div>
    </div>

    {{-- Clear Filters --}}
    <div class="flex items-center justify-between px-4 py-3" v-if="hasFilter">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Filters active
        </div>
        <button class="link warning text-sm" @click="clearFilters">Clear filters</button>
    </div>

    {{-- Table Content --}}
    <div class="overflow-x-auto grow flex flex-col" v-cloak>
        {{-- Empty State --}}
        <div class="text-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-gray-400 flex items-center justify-center grow" v-if="tableitems.length == 0 && !tableLoading">
            <div class="relative text-center flex flex-col justify-center items-center p-8">
                <div v-if="hasFilter">
                    <svg class="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <h3 class="mb-4 text-lg font-bold text-gray-900 md:text-xl dark:text-white">No surveys match your filters</h3>
                    <button @click="clearFilters" type="button" class="btn-link">Clear Filters</button>
                </div>
                <div v-else>
                    <i class="fas fa-poll-h text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                    <h3 class="mb-4 text-lg font-bold text-gray-900 md:text-xl dark:text-white">No surveys yet</h3>
                    <p class="mb-4 text-gray-500 dark:text-gray-400">Create your first survey to start collecting feedback and data.</p>
                    <a href="{{ route('surveysCreate') }}" class="btn btn-primary inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Create Your First Survey
                    </a>
                </div>
            </div>
        </div>

        {{-- Surveys Table --}}
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" v-if="tableitems.length > 0">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap pointer" @click="tableColSort('title')">
                        Survey Name <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap" @click="tableColSort('type')">
                        Type <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap" @click="tableColSort('user_id')">
                        Created By <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        # of Responses
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap" @click="tableColSort('status')">
                        Status <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" v-for="(item, index) in tableitems" :class="tableLoading ? 'pointer-events-none opacity-25' : ''">
                    <td class="table-td " >
                        <a :href="`{{ route('surveysShow', '') }}?id=${item.uuid}`"
                            class="hover:underline underline-offset-2"
                            v-text="item.title"></a>
                    </td>
                    <td class="table-td " >
                        <span class="font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            <span v-if="item.type === 'lead' ">Lead</span>
                            <span v-else-if="item.type === 'feedback'">Feedback</span>
                            <span v-else-if="item.type === 'followup'">Follow Up</span>
                            <span v-else>Custom</span>
                        </span>
                    </td>
                    <td class="table-td">
                        <div class="avatar-wrap small ">
                            <avatar :name="$root.getUserName(item.user_id)" v-cloak></avatar>
                        </div>
                    </td>

                    {{-- # of Responses --}}
                    <td class="table-td " >
                        <span class="font-semibold"
                            v-text="item.responses_count || 0"></span>
                    </td>

                    {{-- Status --}}
                    <td class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                          <div class="flex items-center">
                              <div class="w-3 h-3 mr-2  rounded-full"
                                :class="{
                                    'bg-green-500': item.status === 'active' || item.status === true || item.status === 1,
                                    'bg-blue-500': item.status === 'draft',
                                    'bg-red-500': item.status === 'archived' || item.status === false || item.status === 0
                                }"
                              ></div>
                                <span v-if="item.status === 'active' || item.status === true || item.status === 1">Active</span>
                                <span v-else-if="item.status === 'draft'">Draft</span>
                                <span v-else>Inactive</span>
                          </div>
                      </td>

                    {{-- Actions --}}
                    <td class="table-td">
                        <div class="flex items-center gap-3">
                            <a :href="`{{ route('surveyResponsesByUuid', '') }}?id=${item.uuid}`"
                                class="inline-flex items-center text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                title="View Responses">
                                <i class="fas fa-comments mr-1.5"></i>
                                <span v-text="item.responses_count || 0"></span>
                            </a>

                            <surveystatustoggle
                                :small="true"
                                :status="item.status === 'active' || item.status === true || item.status === 1"
                                :updateroute="`{{ route('surveysUpdate', '') }}?id=${item.uuid}`">
                            </surveystatustoggle>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <pagination :items='@json($surveys)' :users='@json($TeamUsers)'></pagination>
</div>

{{-- Delete Confirmation Modal --}}
<div class="bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40" v-if="successAlert || confirmDelete"></div>
<div id="delete-modal" v-if="confirmDelete" tabindex="-1" class="overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-calc100-1rem max-h-full flex" v-cloak>
    <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <button type="button" class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" @click="toggleDeleteModal(false)">
                <i class="fas fa-times"></i>
                <span class="sr-only">Close modal</span>
            </button>
            <div class="p-4 md:p-5 text-center">
                <svg class="mx-auto mb-4 text-gray-400 w-12 h-12 dark:text-gray-200" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <h3 class="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">Are you sure you want to delete the selected surveys?</h3>
                <button @click="deleteSelectedIds('{{route('surveysIndex')}}')" type="button" class="text-white bg-red-600 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 dark:focus:ring-red-800 font-medium rounded-lg text-sm inline-flex items-center px-5 py-2.5 text-center me-2">
                    Yes, I'm sure
                </button>
                <button @click="toggleDeleteModal(false)" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">No, cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- Success Modal --}}
<div id="successDelete" tabindex="-1" aria-hidden="true" class="overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-modal md:h-full flex" v-if="successAlert" v-cloak>
    <div class="relative p-4 w-full max-w-md h-full md:h-auto">
        <div class="relative p-4 text-center bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
            <button type="button" class="text-gray-400 absolute top-2.5 right-2.5 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white" @click="toggleSuccessModal(false)">
                <i class="fas fa-times"></i>
                <span class="sr-only">Close modal</span>
            </button>
            <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 p-2 flex items-center justify-center mx-auto mb-3.5">
                <svg aria-hidden="true" class="w-8 h-8 text-green-500 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                <span class="sr-only">Success</span>
            </div>
            <p class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Successfully deleted survey(s).</p>
            <button @click="toggleSuccessModal(false)" type="button" class="py-2 px-3 text-sm font-medium text-center text-white rounded-lg bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 dark:focus:ring-primary-900">
                Continue
            </button>
        </div>
    </div>
</div>

@endsection

@push('footer.scripts')

@endpush

