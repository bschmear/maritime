@extends ('crm.layouts.app')
@section('title', "Support Ticket")
@section('body-class', 'table-view')
@section('content-class', 'flex flex-col')
@section ('content')

<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>

@php
    $lastResponse = $ticket->responses->last();
    $canEditLastResponse = $lastResponse &&
    $lastResponse->user_id === $user->id &&
    $lastResponse->created_at->gt(now()->subMinutes(15));
@endphp

<div class="grid grid-cols-12 gap-4 lg:gap-6">
    <div class="col-span-full lg:col-span-8 space-y-4">
        <div class="bg-white border-gray-200 border dark:border-gray-700 dark:bg-gray-800 rounded-lg overflow-hidden ">
          <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center sm:mb-0 sm:space-x-4">
              <h1 class="ms:ms-0 ms-2 font-semibold text-gray-900 dark:text-white sm:text-xl">{{ $ticket->ticket_number }}</h1>
                <span class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium
                    bg-{{ $ticket->status_color }}-100
                    text-{{ $ticket->status_color }}-800
                    dark:bg-{{ $ticket->status_color }}-900
                    dark:text-{{ $ticket->status_color }}-300
                ">
                    <svg class="me-1.5 h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                         fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M5.5 3a1 1 0 0 0 0 2H7v2.3c0 .7.2 1.3.6 1.8L9 11.9l.1.1v.1L7.5 15a3 3 0 0 0-.6 1.8V19H5.5a1 1 0 1 0 0 2h13a1 1 0 1 0 0-2H17v-2.3a3 3 0 0 0-.6-1.8l-1.6-2.8v-.2l1.6-2.8a3 3 0 0 0 .6-1.8V5h1.5a1 1 0 1 0 0-2h-13Z" />
                    </svg>
                    {{ $ticket->status_label }}
                </span>
            </div>
            <a href="#last-response" class="btn-link">Scroll to bottom</a>
          </div>

          <div class="col-span-full p-4 xl:p-8 dark:bg-gray-900">
            <div class="mb-4 items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 sm:flex">
              <div>
                <h2 class="mb-1.5 text-xl font-medium leading-none text-gray-900 dark:text-white">{{ $ticket->subject }}</h2>
                <span class="text-gray-500 dark:text-gray-400">{{ $ticket->category_label }}</span>
              </div>
              <div class="mt-4 flex items-center border-t border-gray-200 pt-4 text-gray-500 dark:border-gray-800 dark:text-gray-400 sm:mt-0 sm:border-0 sm:pt-0 ">
                @php
                    use Illuminate\Support\Carbon;
                    $created = $ticket->created_at->timezone($user->timezone);
                @endphp

                <p class="me-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ $created->format('l, M j, Y') }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="avatar-wrap h-10 w-10 rounded-full">
                      <avatar color="{{ "#".substr(md5($ticket->user->name), 0, 6) }}" name="{{ $ticket->user->name[0] }}" v-cloak></avatar>
                </div>
              <div class="font-semibold dark:text-white">
                <div>{{ $ticket->user->name }}</div>
                <div
                  class="inline-flex items-center text-sm font-medium text-gray-500 0 dark:text-gray-400 "
                >
                  to {{ config('app.name') ?? '' }} Support
                </div>
              </div>
            </div>
            <div class="py-8  text-gray-500  dark:text-gray-400 ">
                {!! nl2br(e($ticket->message)) !!}
            </div>
          </div>

        @foreach($ticket->responses as $response)
            @php
                $created = $response->created_at->timezone($user->timezone);
                $isLast = $response->id === optional($lastResponse)->id;
            @endphp

            <div class="col-span-full dark:bg-gray-900 p-4 xl:p-8 !pt-0 !pb-0" @if($isLast) id="last-response" @endif>

                <div class=" border-t border-gray-200  dark:border-gray-800 pt-4 xl:pt-8">
                    <div class="flex items-center gap-4 ">
                        <div class="avatar-wrap h-10 w-10 rounded-full w-10 border">
                            @if($response->internal)
                            {!! internalIcon() !!}
                            @else
                            <avatar color="{{ "#".substr(md5($response->user->name), 0, 6) }}" name="{{ $response->user->name[0] }}" v-cloak></avatar>
                            @endif
                        </div>
                        <div class="font-semibold dark:text-white">
                            @if($response->internal)
                            <div>{{ config('app.name') ?? '' }} Support</div>
                            @else
                            <div>{{ $response->user->name }}</div>
                            @endif

                            <div class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" >
                                {{ $created->format('l, M j, Y') }} ({{ $created->diffForHumans() }})
                            </div>
                        </div>
                    </div>
                    <div class="py-8  text-gray-500  dark:text-gray-400 ">
                        {!! nl2br(e($response->response)) !!}
                    </div>
                    @php
                        $createdAt = $response->created_at->timezone($user->timezone);
                        $now = now()->timezone($user->timezone);
                        $secondsSinceResponse = $createdAt->diffInSeconds($now, false);
                        $secondsLeft = 900 - $secondsSinceResponse; // 900 seconds = 15 minutes
                        $secondsLeft = max(0, min(900, $secondsLeft));
                        $minutesLeft = (int) ceil($secondsLeft / 60);
                    @endphp

                    @if (
                        !$response->internal &&
                        $response->id === optional($lastResponse)->id &&
                        $canEditLastResponse &&
                        $minutesLeft > 0 &&
                        !in_array($ticket->status, [
                            $ticketStatusClass::Solved,
                            $ticketStatusClass::Closed
                        ])
                    )
                        <div class="mt-2 space-y-1 pb-4">
                            <button
                                type="button"
                                class="btn btn-blue sm mb-2"
                                data-modal-target="edit-response-modal"
                                data-modal-toggle="edit-response-modal"
                            >
                                Edit Response
                            </button>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                You have {{ $minutesLeft }} minute{{ $minutesLeft !== 1 ? 's' : '' }} left to update your response.
                            </p>
                        </div>
                    @endif

                  </div>
              </div>
          @endforeach
        </div>

        @php
            $userWasLast = $lastResponse && ($lastResponse->user_id === $user->id && !$lastResponse->internal);
        @endphp
        {{-- @if ($ticket->is_replyable && !$userWasLast && $ticket->responses->isNotEmpty()) --}}
        @if ($ticket->is_replyable )
        <div class="bg-white border-gray-200 border dark:border-gray-700 dark:bg-gray-80 rounded-lg overflow-hidden ">
          <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center sm:mb-0 sm:space-x-4">
              <h1 class="ms:ms-0 ms-2 font-semibold text-gray-900 dark:text-white sm:text-xl">Reply</h1>
            </div>
          </div>
          <div class="col-span-full dark:bg-gray-900">
            <form method="POST" action="{{ route('ticketReply', ['id' => $ticket->id]) }}" class="p-4 text-gray-500 dark:text-gray-400">
                @csrf
                <input type="hidden" name="service_ticket_id" value="{{ $ticket->id }}">
                <textarea rows="8" name="response" class="input-style mb-4"></textarea>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
          </div>
        </div>
        @endif
    </div>

    <div class="col-span-full lg:col-span-4 -order-1 lg:order-2">
      <div class="bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-800 rounded-lg overflow-hidden sticky top-[85px]">
        <div class="p-4 " >

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Ticket Settings</h2>
            <div class="space-y-4">
                @php
                    $created = $ticket->created_at->timezone($user->timezone);
                @endphp
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Date Created</label>
                    <p class="text-gray-500 dark:text-gray-400">
                         {{ $created->format('l, M j, Y \a\t g:i A') }} ({{ $created->diffForHumans() }})
                    </p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Category</label>
                    <span class="bg-gray-100 text-gray-800 text-sm font-medium me-2 px-2.5 py-0.5 rounded-sm dark:bg-gray-900 dark:text-gray-300">
                        {{ $ticket->category->label() }}
                    </span>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Status</label>
                    <span class="bg-{{ $ticket->status->color() }}-100 text-{{ $ticket->status->color() }}-800 text-sm font-medium me-2 px-2.5 py-0.5 rounded-sm dark:bg-{{ $ticket->status->color() }}-900 dark:text-{{ $ticket->status->color() }}-300">
                        {{ $ticket->status->label() }}
                    </span>
                </div>

                @if ($ticket->is_solved)
                    <form method="POST" action="{{ route('reopenTicket', ['id' => $ticket->id]) }}">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-blue">Re-open Ticket</button>
                    </form>
                @endif

                @if ($ticket->is_closed)
                    <p class="italic text-sm text-gray-600 dark:text-gray-400">
                        Closed tickets cannot be reopened. Only tickets marked as resolved can be reopened. If the issue persists, please open a new ticket.
                    </p>
                @endif
            </div>
        </div>
        </div>
    </div>
</div>

@endsection

@section ('modals')
    @if($lastResponse)
    <div id="edit-response-modal" tabindex="-1" aria-hidden="true"
        class="hidden fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50">
        <div class="relative w-full max-w-2xl p-4">
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-800">
                <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Your Response</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-900 dark:hover:text-white" data-modal-hide="edit-response-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('ticketResponseUpdate', ['id' => $ticket->id]) }}" class="p-4">
                    @csrf
                    @method('PUT')
                    <textarea name="response" rows="6" class="input-style">{{ old('response', $lastResponse->response) }}</textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="btn btn-outline" data-modal-hide="edit-response-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endsection
