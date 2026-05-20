@extends ('crm.layouts.app')
@section('title', "Support")
@section('body-class', 'table-view')
@section('content-class', 'flex flex-col')
@section ('content')

<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>
{{-- <script>
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    alert(tz);
</script> --}}
@if(!$activeTicket && !$otherItems)
<div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden grow flex flex-col shadow-md"  v-cloak>
    <div class="overflow-x-auto grow flex flex-col">
        <div  class="text-gray-700  dark:text-gray-400 flex items-center justify-center  grow">
            <div class="relative text-center flex flex-col justify-center items-center">
                <div class="max-w-sm">
                    <i class="fas fa-ticket text-gray-300 md:text-4xl dark:text-white"></i>
                    <h2 class="mb-4 text-xl font-bold text-gray-900 md:text-3xl  dark:text-white">You haven't submitted any tickets yet.</h2>
                    <div class="text-center flex  justify-center items-center">
                        <button type="button" data-modal-toggle="createModal" class="btn btn-primary flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Create Ticket</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif


@if($activeTicket)
    <div class="bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden mb-4 md:mb-6 shadow-md">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 md:p-6 border-b">
            <div class="flex  items-center flex-1">
                <h1 class="dark:text-white font-semibold">
                    <span class="text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">Active Ticket - {{ $activeTicket->ticket_number }}</span>
                </h1>
            </div>
            <div class="flex sm:justify-end items-center space-x-2">
                <a href="{{ route('showTicket', ['id' => $activeTicket->uid]) }}" class="btn btn-blue sm">View Ticket</a>
            </div>
        </div>
        <div class="p-4 md:p-6 text-gray-900 bg-white dark:text-white dark:bg-gray-800">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                <div class="lg:col-span-3">
                    <label class="input-label">Subject</label>
                    <div>{{ $activeTicket->subject }}</div>
                </div>
                <div class="col-span-1">
                    <label class="input-label">Created</label>
                    @php
                        $created = $activeTicket->created_at->timezone($user->timezone);
                    @endphp
                    <p class="me-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ $created->format('D, M j, Y \a\t g:i A') }}
                    </p>
                </div>
                <div class="col-span-1">
                    <label class="input-label">Category</label>
                    <span class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium
                        bg-gray-100
                        text-gray-800
                        dark:bg-gray-900
                        dark:text-gray-300
                    ">
                        {{ $activeTicket->category_label }}
                    </span>
                </div>
                <div class="col-span-1">
                    <label class="input-label">Status</label>
                    <span class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium
                        bg-{{ $activeTicket->status_color }}-100
                        text-{{ $activeTicket->status_color }}-800
                        dark:bg-{{ $activeTicket->status_color }}-900
                        dark:text-{{ $activeTicket->status_color }}-300
                    ">
                        <svg class="me-1.5 h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                             fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                  d="M5.5 3a1 1 0 0 0 0 2H7v2.3c0 .7.2 1.3.6 1.8L9 11.9l.1.1v.1L7.5 15a3 3 0 0 0-.6 1.8V19H5.5a1 1 0 1 0 0 2h13a1 1 0 1 0 0-2H17v-2.3a3 3 0 0 0-.6-1.8l-1.6-2.8v-.2l1.6-2.8a3 3 0 0 0 .6-1.8V5h1.5a1 1 0 1 0 0-2h-13Z" />
                        </svg>
                        {{ $activeTicket->status_label }}
                    </span>
                </div>
            </div>
        </div>
        <div class="p-4 md:p-6 border-t">
            <p class="text-sm text-gray-600 dark:text-gray-400 italic">You can only have <span class="font-bold">one</span> open ticket at a time.</p>
        </div>
    </div>
@endif

@if($otherItems)
<div class="bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden shadow-md" v-cloak>
    <div class="flex items-center justify-between pt-4 md:pb-4 px-4">
        <div class="flex items-center flex-1">
            <h1 class="dark:text-white font-semibold">
                <span class="pt-0 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">Inactive Tickets</span>
            </h1>
        </div>
        @if(!$activeTicket)
        <div class="flex items-center space-x-2">
            <button type="button" data-modal-toggle="createModal" class="btn btn-primary sm flex items-center space-x-2">
                <i class="far fa-plus"></i>
                <span>New ticket</span>
            </button>
        </div>
        @else
        <div class="flex sm:justify-end items-center space-x-2">
            <a href="{{ route('showTicket', ['id' => $activeTicket->uid]) }}" class="btn btn-blue sm">View Open Ticket</a>
        </div>
        @endif
    </div>

    <div class="relative "  v-cloak>
        <div class="grow flex flex-col">
            <div class="overflow-x-auto grow flex flex-col">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" v-if="tableitems.length > 0">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3  whitespace-nowrap pointer" @click="tableColSort('ticket_number')">
                                ID
                                <svg class="h-4 w-4 ml-1 inline-block" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" />
                                </svg>
                            </th>
                            <th scope="col" class="px-4 py-3  whitespace-nowrap pointer" @click="tableColSort('subject')">
                                Subject
                                <svg class="h-4 w-4 ml-1 inline-block" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" />
                                </svg>
                            </th>
                            <th scope="col" class="px-4 py-3 whitespace-nowrap" @click="tableColSort('status')">
                                Status
                                <svg class="h-4 w-4 ml-1 inline-block" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" />
                                </svg>
                            </th>
                            <th scope="col" class="px-4 py-3 whitespace-nowrap" @click="tableColSort('created_at')">
                                Created
                                <svg class="h-4 w-4 ml-1 inline-block" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" />
                                </svg>
                            </th>
                        </tr>
                    </thead>
                    <tbody >
                        <tr class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" v-for="(item, index) in tableitems" :class="tableLoading ? 'pointer-events-none opacity-25' : ''">
                            <td class="table-td " >
                                <a :href="`{{ route('showTicket') }}?id=${item.uid}`" class="hover:underline underline-offset-2" v-text="item.ticket_number"></a>
                            </td>
                            <td class="table-td" v-text="item.subject"></td>
                            <td class="table-td">
                              <span
                                :class="[
                                  'text-xs font-medium px-2.5 py-0.5 rounded-sm border',
                                  item.status_color === 'blue' && 'bg-blue-100 text-blue-800 border-blue-400 dark:bg-gray-700 dark:text-blue-400',
                                  item.status_color === 'yellow' && 'bg-yellow-100 text-yellow-800 border-yellow-400 dark:bg-gray-700 dark:text-yellow-400',
                                  item.status_color === 'green' && 'bg-green-100 text-green-800 border-green-400 dark:bg-gray-700 dark:text-green-400',
                                  item.status_color === 'red' && 'bg-red-100 text-red-800 border-red-400 dark:bg-gray-700 dark:text-red-400',
                                ]"
                                v-text="item.status_label"
                              >
                              </span>
                            </td>
                            <td class="table-td" v-text="formatDate(item.created_at, false)"></td>
                        </tr>

                    </tbody>
                </table>
            </div>
            <pagination :items="{{ $otherItems }}"></pagination>
        </div>
    </div>

</div>
@endif
@endsection

@section ('modals')
<div id="createModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-calc100-1rem ">
    <div class="relative p-4 w-full max-w-2xl  h-full md:h-auto max-h-95">
        <!-- Modal content -->
        <div class="relative  bg-white rounded-lg shadow dark:bg-gray-800 ">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Support Ticket</h3>
                <button type="button" class="close-modal" data-modal-toggle="createModal">
                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <!-- Modal body -->
            <form method="POST" action="{{ route('storeTicket') }}" class="" autocomplete="off">
                @csrf
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="subject" class="input-label">Subject</label>
                        <input type="text" name="subject" id="support_subject" class="input-style" required autocomplete="auto-support_subject" >
                    </div>

                    <div>
                        <label for="category" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Category</label>
                        <select id="category" name="category" class="input-style" required>
                            <option disabled >Category</option>
                            @foreach ($ticketCategory as $c)
                                <option value="{{ $c['id'] }}">
                                    {{ $c['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="message" class="input-label">Message</label>
                        <textarea rows="8" name="message" class="input-style" required>{{ old('message') }}</textarea>
                    </div>
               </div>
                <div class="modal-footer">
                    <button type="submit" class="input-submit">Submit Ticket</button>
                    <button data-modal-toggle="createModal" type="button" class="btn-outline ml-2">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
