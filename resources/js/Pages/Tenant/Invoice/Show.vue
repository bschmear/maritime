<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Invoice" />

    <TenantLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Invoice
            </h2>
        </template>

        <div class="w-full">
        <div class="grid grid-cols-12 gap-4 p-4">
          <div class="col-span-12 2xl:col-span-9">
            <div class="space-y-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:space-y-8 md:p-6">
              <a href="https://flowbite.com" class="flex items-center">
                <ApplicationLogo
                    class="block mr-3 h-6 sm:h-8 w-auto fill-current text-gray-800 dark:text-white-100"
                />
              </a>
              <div class="flex items-center justify-between border-b border-t border-gray-100 py-4 dark:border-gray-700">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Invoice #1846325</h1>
                <time datetime="2025-07-05" class="text-lg text-gray-500 dark:text-gray-400">Date: 05/07/2025</time>
              </div>
              <!-- Payment Details -->
              <div class="flex items-start justify-between gap-8">
                <div class="md:w-64">
                  <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Pay to:</h2>
                  <address class="text-sm text-gray-500 dark:text-gray-400 md:text-base">
                    <span class="font-medium text-gray-900 dark:text-white">Flowbite LLC,</span> LOUISVILLE, Selby 3864 Johnson Street, United States of America, <br />
                    VAT Code: AA-1234567890
                  </address>
                </div>
                <div class="md:w-64">
                  <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Invoice to:</h2>
                  <address class="text-sm text-gray-500 dark:text-gray-400 md:text-base">
                    <span class="font-medium text-gray-900 dark:text-white">Bonnie Green,</span> Carolina, Selby 3864 Johnson Street, United States of America, <br />
                    <span class="font-medium text-gray-900 dark:text-white">name@company.com</span>
                  </address>
                </div>
              </div>
              <!-- Table -->
              <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm font-medium text-gray-900 dark:text-white rtl:text-right">
                  <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                      <th scope="col" class="px-6 py-3 font-semibold">Product name</th>
                      <th scope="col" class="px-6 py-3 font-semibold">Qty</th>
                      <th scope="col" class="px-6 py-3 font-semibold">Price</th>
                      <th scope="col" class="px-6 py-3 font-semibold">Discount</th>
                      <th scope="col" class="text-nowrap px-6  py-3 font-semibold">Total price</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                      <th scope="row" class="h-16 space-y-2 whitespace-nowrap px-6 font-medium text-gray-900 dark:text-white">
                        <div class="text-base">Flowbite Developer Edition</div>
                        <div class="font-normal leading-none text-gray-500 dark:text-gray-400">HTML, Figma, JS</div>
                      </th>
                      <td class="h-16 px-6">$269</td>
                      <td class="h-16 px-6">2</td>
                      <td class="h-16 px-6">50%</td>
                      <td class="h-16 px-6">$269</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                      <th scope="row" class="h-16 space-y-2 whitespace-nowrap px-6 font-medium text-gray-900 dark:text-white">
                        <div class="text-base">Flowbite Designer Edition</div>
                        <div class="font-normal leading-none text-gray-500 dark:text-gray-400">Figma Design System</div>
                      </th>
                      <td class="h-16 px-6">$149</td>
                      <td class="h-16 px-6">3</td>
                      <td class="h-16 px-6">0%</td>
                      <td class="h-16 px-6">$447</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                      <th scope="row" class="h-16 space-y-2 whitespace-nowrap px-6 font-medium">
                        <div class="text-base">Flowbite Open Source</div>
                        <div class="font-normal leading-none text-gray-500 dark:text-gray-400">Open source components</div>
                      </th>
                      <td class="h-16 px-6">$0</td>
                      <td class="h-16 px-6">1</td>
                      <td class="h-16 px-6">0%</td>
                      <td class="h-16 px-6">$0</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                      <th scope="row" class="h-16 space-y-2 whitespace-nowrap px-6 font-medium text-gray-900 dark:text-white">
                        <div class="text-base">2 Years Support</div>
                        <div class="font-normal leading-none text-gray-500 dark:text-gray-400">Premium support</div>
                      </th>
                      <td class="h-16 px-6">$199</td>
                      <td class="h-16 px-6">1</td>
                      <td class="h-16 px-6">0%</td>
                      <td class="h-16 px-6">$199</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                      <th scope="row" class="h-16 space-y-2 whitespace-nowrap px-6 font-medium text-gray-900 dark:text-white">
                        <div class="text-base">Flowbite Developer (Team License)</div>
                        <div class="font-normal leading-none text-gray-500 dark:text-gray-400">HTML, Figma, JS</div>
                      </th>
                      <td class="h-16 px-6">$799</td>
                      <td class="h-16 px-6">2</td>
                      <td class="h-16 px-6">0%</td>
                      <td class="h-16 px-6">$1598</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="ms-auto mt-4 max-w-xs">
                <h3 class="font-semibold text-gray-900 dark:text-white">Order summary</h3>
                <ul class="mt-4 max-w-md">
                  <li class="mb-2 flex items-center justify-between rtl:space-x-reverse">
                    <p class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">Subtotal</p>
                    <div class="font-medium text-gray-900 dark:text-white">$320</div>
                  </li>
                  <li class="mb-2 flex items-center justify-between rtl:space-x-reverse">
                    <p class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">Tax</p>
                    <div class="font-medium text-gray-900 dark:text-white">$477</div>
                  </li>
                  <li class="mb-4 flex items-center justify-between rtl:space-x-reverse">
                    <p class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">Shipping estimate</p>
                    <div class="font-medium text-gray-900 dark:text-white">$0</div>
                  </li>
                  <li class="flex items-center justify-between text-lg font-bold text-gray-900 dark:text-white rtl:space-x-reverse">
                    <p class="min-w-0 flex-1 truncate">Order total</p>
                    <div>$2990</div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-span-12 2xl:col-span-3">
            <div class="relative h-full space-y-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:space-y-6 md:p-6">
              <!-- Alert -->
              <div class="mb-4 flex items-center rounded-lg bg-green-50 p-4 text-base font-medium text-green-800 dark:bg-gray-700 dark:text-green-300" role="alert">
                <svg class="me-2 inline h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z" />
                </svg>
                <span class="sr-only">Info</span>
                <div>Invoice paid</div>
              </div>
              <h2 class="text-lg text-gray-500 dark:text-gray-400">Details:</h2>
              <!-- Price & Input -->
              <div class="flex items-center justify-between border-b border-t border-gray-100 py-4 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">$2,999</h3>
                <label for="payment-option" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Select an option</label>
                <select
                  id="payment-option"
                  class="block w-40 rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                >
                  <option selected>Paid</option>
                  <option value="PA">Unpaid</option>
                  <option value="UN">Pending</option>
                  <option value="OV">Overdue</option>
                </select>
              </div>
              <!-- List -->
              <ul class="max-w-md list-inside space-y-4 border-b border-gray-100 pb-4 text-gray-500 dark:border-gray-700 dark:text-gray-400 sm:pb-6">
                <li class="flex items-center">
                  <svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                    <path
                      fill-rule="evenodd"
                      d="M5 8a4 4 0 1 1 7.8 1.3l-2.5 2.5A4 4 0 0 1 5 8Zm4 5H7a4 4 0 0 0-4 4v1c0 1.1.9 2 2 2h2.2a3 3 0 0 1-.1-1.6l.6-3.4a3 3 0 0 1 .9-1.5L9 13Zm9-5a3 3 0 0 0-2 .9l-6 6a1 1 0 0 0-.3.5L9 18.8a1 1 0 0 0 1.2 1.2l3.4-.7c.2 0 .3-.1.5-.3l6-6a3 3 0 0 0-2-5Z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  <span class="me-2 font-medium text-gray-900 dark:text-white">Created by:</span> Jese Leos
                </li>
                <li class="flex items-center">
                  <svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                    <path
                      fill-rule="evenodd"
                      d="M6 5V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H3V7c0-1.1.9-2 2-2h1ZM3 19v-8h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm6-7H7v2h2v-2Zm2 0h2v2h-2v-2Zm6 0h-2v2h2v-2ZM7 16h2v2H7v-2Zm6 0h-2v2h2v-2Zm2 0h2v2h-2v-2Z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  <span class="me-2 font-medium text-gray-900 dark:text-white">Due date:</span> 08 July 2025
                </li>
                <li class="flex items-center">
                  <svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M4 5a2 2 0 0 0-2 2v10c0 1.1.9 2 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H4Zm0 6h16v6H4v-6Z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M5 14c0-.6.4-1 1-1h2a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm5 0c0-.6.4-1 1-1h5a1 1 0 1 1 0 2h-5a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                  </svg>
                  Pay by Bank Transfer
                </li>
                <li class="flex items-center">
                  <svg class="me-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M7 6c0-1.1.9-2 2-2h11a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-2v-4a3 3 0 0 0-3-3H7V6Z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M2 11c0-1.1.9-2 2-2h11a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-7Zm7.5 1a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" clip-rule="evenodd" />
                    <path d="M10.5 14.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                  </svg>
                  <span class="me-2 font-medium text-gray-900 dark:text-white">Currency:</span> American Dollar
                </li>
              </ul>
              <!-- Timeline -->
              <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700">
                <li class="mb-4 ms-6">
                  <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                      <path fill-rule="evenodd" d="M2 12a10 10 0 1 1 20 0 10 10 0 0 1-20 0Zm13.7-1.3a1 1 0 0 0-1.4-1.4L11 12.6l-1.8-1.8a1 1 0 0 0-1.4 1.4l2.5 2.5c.4.4 1 .4 1.4 0l4-4Z" clip-rule="evenodd" />
                    </svg>
                  </span>
                  <div class="flex items-center justify-between">
                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white">Invoice created:</h4>
                    <time datetime="2025-07-05" class="text-gray-500 dark:text-gray-400">05/07/2025</time>
                  </div>
                </li>
                <li class="mb-4 ms-6">
                  <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                      <path fill-rule="evenodd" d="M2 12a10 10 0 1 1 20 0 10 10 0 0 1-20 0Zm13.7-1.3a1 1 0 0 0-1.4-1.4L11 12.6l-1.8-1.8a1 1 0 0 0-1.4 1.4l2.5 2.5c.4.4 1 .4 1.4 0l4-4Z" clip-rule="evenodd" />
                    </svg>
                  </span>
                  <div class="flex items-center justify-between">
                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white">Invoice sent:</h4>
                    <time datetime="2025-07-05" class="text-gray-500 dark:text-gray-400">06/07/2025</time>
                  </div>
                </li>
                <li class="ms-6">
                  <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                      <path fill-rule="evenodd" d="M2 12a10 10 0 1 1 20 0 10 10 0 0 1-20 0Zm13.7-1.3a1 1 0 0 0-1.4-1.4L11 12.6l-1.8-1.8a1 1 0 0 0-1.4 1.4l2.5 2.5c.4.4 1 .4 1.4 0l4-4Z" clip-rule="evenodd" />
                    </svg>
                  </span>
                  <div class="flex items-center justify-between">
                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white">Invoice paid:</h4>
                    <time datetime="2025-07-05" class="text-gray-500 dark:text-gray-400">08/07/2025</time>
                  </div>
                </li>
              </ol>
            </div>
          </div>
        </div>
        </div>
    </TenantLayout>
</template>
