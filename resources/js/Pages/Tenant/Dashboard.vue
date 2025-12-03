<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

defineProps({
    account: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Tenant Dashboard" />

    <TenantLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Tenant Dashboard
            </h2>
        </template>

<div class="p-4 w-full">
  <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:p-6">
    <div class="items-start justify-between sm:flex">
      <div class="mb-4 sm:mb-0">
        <h2 class="mb-1 text-2xl font-bold leading-none text-gray-900 dark:text-white">$401,857</h2>
        <p class="text-gray-500 dark:text-gray-400">Total revenue for flowbite.com</p>
      </div>
      <div>
        <div date-rangepicker datepicker-autohide class="flex items-center">
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
              <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M5 5c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1a2 2 0 0 1 2 2v1c0 .6-.4 1-1 1H4a1 1 0 0 1-1-1V7c0-1.1.9-2 2-2ZM3 19v-7c0-.6.4-1 1-1h16c.6 0 1 .4 1 1v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm6-6c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1ZM7 17a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <input
              name="start"
              type="text"
              class="block w-32 rounded-lg border border-gray-300 bg-gray-50 p-2 ps-9 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500  dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
              placeholder="Start date"
            />
          </div>
          <span class="mx-2 text-gray-500 dark:text-gray-400">to</span>
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
              <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M5 5c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1a2 2 0 0 1 2 2v1c0 .6-.4 1-1 1H4a1 1 0 0 1-1-1V7c0-1.1.9-2 2-2ZM3 19v-7c0-.6.4-1 1-1h16c.6 0 1 .4 1 1v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm6-6c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1ZM7 17a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <input
              name="end"
              type="text"
              class="block w-32 rounded-lg border border-gray-300 bg-gray-50 p-2 ps-9 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500  dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
              placeholder="End date"
            />
          </div>
        </div>
      </div>
    </div>
    <div id="total-sales-chart"></div>
  </div>
  <div class="my-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <path
          fill-rule="evenodd"
          d="M9 15a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm3.8-1.9c.3-.5.7-1 1.2-1.2a1 1 0 0 1 2 0c.5.1.8.4 1.1.7a1 1 0 1 1-1.4 1.4l-.4-.2h-.1a.4.4 0 0 0-.4 0l.4.3a3 3 0 0 1 1.5.9 2 2 0 0 1 .5 1.9c-.3.5-.7 1-1.2 1.2a1 1 0 0 1-2 0c-.4 0-.8-.3-1.2-.7a1 1 0 1 1 1.6-1.3l.3.2h.1a.4.4 0 0 0 .4 0 1 1 0 0 0-.4-.3 3 3 0 0 1-1.5-.9 2 2 0 0 1-.5-2Zm2 .6Zm.5 2.6ZM4 14c.6 0 1 .4 1 1v4a1 1 0 1 1-2 0v-4c0-.6.4-1 1-1Zm3-2c.6 0 1 .4 1 1v6a1 1 0 1 1-2 0v-6c0-.6.4-1 1-1Zm6.5-8c0-.6.4-1 1-1H18c.6 0 1 .4 1 1v3a1 1 0 1 1-2 0v-.8l-2.3 2a1 1 0 0 1-1.3.1l-2.9-2-3.9 3a1 1 0 1 1-1.2-1.6l4.5-3.5a1 1 0 0 1 1.2 0l2.8 2L15.3 5h-.8a1 1 0 0 1-1-1Z"
          clip-rule="evenodd"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Income</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$163.4k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 ">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400 sm:text-base">
          <svg class="h-5 w-5 text-green-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          7%
        </span>
        vs last month
      </p>
    </div>
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4.5V19c0 .6.4 1 1 1h15M7 10l4 4 4-4 5 5m0 0h-3.2m3.2 0v-3.2" />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Outcome</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$82.1k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400 sm:text-base">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          8.8%
        </span>
        vs last month
      </p>
    </div>
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path
          stroke="currentColor"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M8 17.3a5 5 0 0 0 2.6 1.7c2.2.6 4.5-.5 5-2.3.4-2-1.3-4-3.6-4.5-2.3-.6-4-2.7-3.5-4.5.5-1.9 2.7-3 5-2.3 1 .2 1.8.8 2.5 1.6m-3.9 12v2m0-18v2.2"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Profit</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$54.3k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-red-600 dark:text-red-500">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V5m0 14-4-4m4 4 4-4" />
          </svg>
          2.5%
        </span>
        vs last month
      </p>
    </div>
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <path
          fill-rule="evenodd"
          d="M12 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm-1.5 8a4 4 0 0 0-4 4c0 1.1.9 2 2 2h7a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-3Zm6.8-3.1a5.5 5.5 0 0 0-2.8-6.3c.6-.4 1.3-.6 2-.6a3.5 3.5 0 0 1 .8 6.9Zm2.2 7.1h.5a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-1.1l-.5.8c1.9 1 3.1 3 3.1 5.2ZM4 7.5a3.5 3.5 0 0 1 5.5-2.9A5.5 5.5 0 0 0 6.7 11 3.5 3.5 0 0 1 4 7.5ZM7.1 12H6a4 4 0 0 0-4 4c0 1.1.9 2 2 2h.5a6 6 0 0 1 3-5.2l-.4-.8Z"
          clip-rule="evenodd"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">New Customers</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">68</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          5.6%
        </span>
        vs last month
      </p>
    </div>
  </div>
  <!-- Columns-->
  <div class="my-4 grid grid-cols-1 gap-4 2xl:grid-cols-3">
    <div class="space-y-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:space-y-6 md:p-6 2xl:col-span-2">

    </div>
    <!-- Right Content -->
    <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-1">
      <!-- Widget -->
      <div class="space-y-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:space-y-6 md:p-6">

      </div>
      <!-- Widget -->
      <div class="space-y-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800 md:space-y-6 md:p-6">

      </div>
    </div>
  </div>
  <!-- Widget -->
  <div class="mb-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800 sm:p-6">
    <!-- Card Title -->
    <div class="mb-4 items-start justify-between flex md:mb-6">
      <div>
        <h3 class="mb-2 pe-1 text-xl font-bold leading-none text-gray-900 dark:text-white">Transactions</h3>
        <p class="text-gray-500 dark:text-gray-400">Latest transactions</p>
      </div>
      <button
        id="actionsDropdownButton"
        data-dropdown-toggle="actionsDropdown"
        class="flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700 sm:w-auto"
        type="button"
      >
         Actions
        <svg class="-me-0.5 ms-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
        </svg>
      </button>
      <div id="actionsDropdown" class="z-10 hidden w-40 rounded-lg bg-white shadow dark:bg-gray-700">
        <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="actionsDropdownButton">
          <li>
            <button
              id="archiveAllTransactionsModalButton"
              data-modal-target="archiveAllTransactionsModal"
              data-modal-toggle="archiveAllTransactionsModal"
              type="button"
              href="#archiveModal"
              class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
            >
              <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M4 4a2 2 0 1 0 0 4h16a2 2 0 1 0 0-4H4Zm0 6h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Zm10.707 5.707a1 1 0 0 0-1.414-1.414l-.293.293V12a1 1 0 1 0-2 0v2.586l-.293-.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l2-2Z"
                  clip-rule="evenodd"
                />
              </svg>

              Archive all
            </button>
          </li>
          <li>
            <button
              id="deleteAllTransactionsModalButton"
              data-modal-target="deleteAllTransactionsModal"
              data-modal-toggle="deleteAllTransactionsModal"
              type="button"
              class="inline-flex w-full items-center rounded-md px-3 py-2 text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
            >
              <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                  clip-rule="evenodd"
                />
              </svg>
              Delete all
            </button>
          </li>
        </ul>
      </div>
    </div>
    <div class="relative mb-4 overflow-x-auto md:mb-6">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
          <tr>
            <th scope="col" class="p-4">
              <div class="flex items-center">
                <input
                  id="checkbox-all"
                  type="checkbox"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-all" class="sr-only">checkbox</label>
              </div>
            </th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">Order ID</th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">
              Customer
              <svg class="ml-1 inline-block h-3 w-3" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                />
              </svg>
            </th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">Email</th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">
              Total
              <svg class="ml-1 inline-block h-3 w-3" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                />
              </svg>
            </th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">
              Due Date
              <svg class="ml-1 inline-block h-3 w-3" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                />
              </svg>
            </th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">
              Status
              <svg class="ml-1 inline-block h-3 w-3" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                />
              </svg>
            </th>
            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">
              Delivery Type
              <svg class="ml-1 inline-block h-3 w-3" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                />
              </svg>
            </th>
            <th scope="col" class="px-4 py-3 font-semibold">
              <span class="sr-only">Actions</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846325</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Flowbite LLC</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">flowbite@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$466</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">09 Mar 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">Completed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-1-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-1-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-1-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-1-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846326</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Jese Leos</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$2000</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">07 Mar 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="me-2 rounded-sm bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">Failed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Scheduled (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-2-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-2-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-2-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-2-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846327</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Bonnie Green</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@company.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$245</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">12 Mar 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">Completed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-3-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-3-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-3-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-3-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846328</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Themesberg LLC</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">company@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$90</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">18 Apr 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">Refunded</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Scheduled (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-4-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-4-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-4-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-4-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846329</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Micheal Gough</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$3040</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">21 Apr 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">Pending</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Shipment (Packaging)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-5-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-5-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-5-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-5-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846330</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Lana Byrd</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$2999</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">24 Apr 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">Completed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-6-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-6-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-6-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-6-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846331</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Netflix LLC</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">company@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$1870</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">05 May 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">Completed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Shipment (Packaging)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-7-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-7-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-7-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-7-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846332</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Leslie Livingston</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$5067</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">08 May 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">Refunded</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-8-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-8-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-8-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-8-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="border-b hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 border-gray-200">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846333</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Bergside LLC</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">company@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$60</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">02 May 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">Pending</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-9-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-9-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-9-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-9-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          <tr class="hover:bg-gray-100 dark:hover:bg-gray-800">
            <td class="w-4 px-4 py-3">
              <div class="flex items-center">
                <input
                  id="checkbox-table-search-1"
                  type="checkbox"
                  onclick="event.stopPropagation()"
                  class="h-4 w-4 rounded-sm border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                />
                <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
              </div>
            </td>
            <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white"><a href="#" class="hover:underline">#1846334</a></th>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Robert Brown</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium">name@example.com</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">$499</td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">20 Jun 2025</td>
            <td class="whitespace-nowrap px-4 py-3">
              <span class="mr-2 rounded-sm bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">Completed</span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">Instant (Digital)</td>
            <td class="px-4 py-3">
              <button
                id="transaction-10-dropdown-button"
                type="button"
                data-dropdown-toggle="transaction-10-dropdown"
                class="inline-flex items-center rounded-lg p-1 text-center text-sm font-medium text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="4" d="M6 12h0m6 0h0m6 0h0" />
                </svg>
              </button>
              <div id="transaction-10-dropdown" class="z-10 hidden w-40 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700">
                <ul class="p-2 text-sm font-medium text-gray-500 dark:text-gray-400" aria-labelledby="transaction-10-dropdown-button">
                  <li>
                    <a href="#" class="inline-flex w-full items-center rounded-md px-3 py-2 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white">
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M5 7.8C6.7 6.3 9.2 5 12 5s5.3 1.3 7 2.8a12.7 12.7 0 0 1 2.7 3.2c.2.2.3.6.3 1s-.1.8-.3 1a2 2 0 0 1-.6 1 12.7 12.7 0 0 1-9.1 5c-2.8 0-5.3-1.3-7-2.8A12.7 12.7 0 0 1 2.3 13c-.2-.2-.3-.6-.3-1s.1-.8.3-1c.1-.4.3-.7.6-1 .5-.7 1.2-1.5 2.1-2.2Zm7 7.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Details
                    </a>
                  </li>
                  <li>
                    <button
                      type="button"
                      id="deleteInvoiceButton"
                      data-modal-target="deleteInvoiceModal"
                      data-modal-toggle="deleteInvoiceModal"
                      class="inline-flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-gray-100 dark:text-red-500 dark:hover:bg-gray-600"
                    >
                      <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path
                          fill-rule="evenodd"
                          d="M8.6 2.6A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4c0-.5.2-1 .6-1.4ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      Delete
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <button
      type="button"
      class="flex w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700 sm:w-auto"
    >
      View all transactions
      <svg class="-me-0.5 ms-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4" />
      </svg>
    </button>
  </div>
</div>


<!--                     <Link
                        href="/contacts"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Contacts</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Manage your contacts</p>
                    </Link>
 -->
    </TenantLayout>
</template>

