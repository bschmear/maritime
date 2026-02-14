<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
      <!-- Header -->
      <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <span class="material-icons text-white">business</span>
              </div>
              <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">Client Portal</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Welcome back, John</p>
              </div>
            </div>
  
            <!-- User Menu -->
            <div class="flex items-center gap-4">
              <button class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons">notifications</span>
              </button>
              <button class="flex items-center gap-2 p-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-semibold">JD</span>
                </div>
                <span class="material-icons text-sm">expand_more</span>
              </button>
            </div>
          </div>
        </div>
      </header>
  
      <!-- Main Content -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <!-- Stat Card 1 -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
              <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                <span class="material-icons text-blue-600 dark:text-blue-400">receipt_long</span>
              </div>
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">This Month</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">$2,450</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Invoiced</p>
          </div>
  
          <!-- Stat Card 2 -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
              <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
              </div>
              <span class="text-xs font-medium text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-1 rounded">Active</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">3</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Open Tickets</p>
          </div>
  
          <!-- Stat Card 3 -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
              <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                <span class="material-icons text-purple-600 dark:text-purple-400">image</span>
              </div>
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Total</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">124</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Media Files</p>
          </div>
  
          <!-- Stat Card 4 -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
              <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                <span class="material-icons text-orange-600 dark:text-orange-400">schedule</span>
              </div>
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Pending</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">2</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Awaiting Payment</p>
          </div>
        </div>
  
        <!-- Main Navigation Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <!-- Invoices Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow group cursor-pointer">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                  <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-icons text-2xl text-blue-600 dark:text-blue-400">receipt_long</span>
                  </div>
                  <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">Invoices</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">View and manage invoices</p>
                  </div>
                </div>
                <span class="material-icons text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">arrow_forward</span>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Outstanding Balance</span>
                  <span class="font-semibold text-gray-900 dark:text-white">$1,200.00</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Paid This Year</span>
                  <span class="font-semibold text-gray-900 dark:text-white">$18,450.00</span>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
              <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Last invoice: Jan 15, 2026</span>
            </div>
          </div>
  
          <!-- Service Tickets Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow group cursor-pointer">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                  <div class="w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-icons text-2xl text-green-600 dark:text-green-400">confirmation_number</span>
                  </div>
                  <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">Service Tickets</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Track your requests</p>
                  </div>
                </div>
                <span class="material-icons text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors">arrow_forward</span>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Open Tickets</span>
                  <span class="font-semibold text-green-600 dark:text-green-400">3</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Resolved This Month</span>
                  <span class="font-semibold text-gray-900 dark:text-white">12</span>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
              <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Latest update: 2 hours ago</span>
            </div>
          </div>
  
          <!-- Customer Service Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow group cursor-pointer">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                  <div class="w-14 h-14 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-icons text-2xl text-purple-600 dark:text-purple-400">support_agent</span>
                  </div>
                  <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">Customer Service</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Get help and support</p>
                  </div>
                </div>
                <span class="material-icons text-gray-400 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">arrow_forward</span>
              </div>
              <div class="space-y-3">
                <button class="w-full flex items-center gap-2 px-4 py-2 bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 rounded-lg text-purple-700 dark:text-purple-300 text-sm font-medium transition-colors">
                  <span class="material-icons text-base">chat</span>
                  Start Live Chat
                </button>
                <button class="w-full flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-gray-700 dark:text-gray-300 text-sm font-medium transition-colors">
                  <span class="material-icons text-base">email</span>
                  Send Email
                </button>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
              <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Support hours: Mon-Fri, 9AM-5PM</span>
            </div>
          </div>
  
          <!-- Media Library Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow group cursor-pointer">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                  <div class="w-14 h-14 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <span class="material-icons text-2xl text-orange-600 dark:text-orange-400">perm_media</span>
                  </div>
                  <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">Images & Videos</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Access your media files</p>
                  </div>
                </div>
                <span class="material-icons text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">arrow_forward</span>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Total Files</span>
                  <span class="font-semibold text-gray-900 dark:text-white">124</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Recently Added</span>
                  <span class="font-semibold text-gray-900 dark:text-white">8 this week</span>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
              <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Last upload: Feb 12, 2026</span>
            </div>
          </div>
        </div>
  
        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recent Activity</h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Activity Item 1 -->
            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                  <span class="material-icons text-blue-600 dark:text-blue-400">receipt</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">New invoice generated</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Invoice #INV-2026-045 for $650.00</p>
                  <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">2 hours ago</p>
                </div>
                <button class="px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                  View
                </button>
              </div>
            </div>
  
            <!-- Activity Item 2 -->
            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                  <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">Ticket resolved</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Service ticket #ST-1234 has been completed</p>
                  <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">5 hours ago</p>
                </div>
                <button class="px-3 py-1.5 text-sm font-medium text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors">
                  View
                </button>
              </div>
            </div>
  
            <!-- Activity Item 3 -->
            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                  <span class="material-icons text-orange-600 dark:text-orange-400">cloud_upload</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">Media files uploaded</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">5 new photos added to your gallery</p>
                  <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Yesterday</p>
                </div>
                <button class="px-3 py-1.5 text-sm font-medium text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-lg transition-colors">
                  View
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </template>
  
  <script>
  export default {
    name: 'ClientHub',
    // Add your component logic here
  }
  </script>