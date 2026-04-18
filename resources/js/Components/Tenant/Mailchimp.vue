<template>
    <div
        v-if="showSyncModal"
        id="syncMailchimp"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mailchimp-sync-title"
    >
        <div
            class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70"
            aria-hidden="true"
            @click="closeSyncModal"
        />
        <div class="relative z-10 w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-lg shadow-xl">
            <div class="relative bg-white p-4 dark:bg-gray-800 sm:p-5">
                <div class="mb-4 flex items-center justify-between rounded-t border-b pb-4 dark:border-gray-600 sm:mb-5">
                    <div class="flex items-center">
                        <h3 id="mailchimp-sync-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                            Mailchimp
                        </h3>
                        <a href="/help#Mailchimp" title="view help" class="ml-2 font-semibold text-gray-900 dark:text-white" target="_blank"><span class="sr-only">View Help</span><i class="fas fa-question-circle me-2"></i></a>
                    </div>
                    <button type="button" class="ml-auto inline-flex items-center rounded-lg p-1.5 text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white" @click="closeSyncModal">
                        <i class="fas fa-times"></i>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                  <form @submit.prevent="handleSubmit">
                    <div class="mb-4 space-y-4">
                      <div>
                        <label class="input-label dark:text-white">Action</label>
                        <div class="flex space-x-4">
                          <label class="flex items-center space-x-1 dark:text-white">
                            <input type="radio" value="pull" v-model="actionType" />
                            <span>Import from Mailchimp</span>
                          </label>
                          <label class="flex items-center space-x-1 dark:text-white">
                            <input type="radio" value="push" v-model="actionType" />
                            <span>Export to Mailchimp</span>
                          </label>
                        </div>
                      </div>
    
                      <div v-if="!loadingLists && lists.length === 0" class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">No Mailchimp Audiences Found</h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mb-2">
                          You need to create at least one audience in Mailchimp before you can sync contacts.
                        </p>
                        <ol class="text-sm text-yellow-700 dark:text-yellow-300 list-decimal list-inside space-y-1">
                          <li>Go to <a href="https://mailchimp.com" target="_blank" class="underline font-semibold">Mailchimp.com</a></li>
                          <li>Navigate to Audience → All contacts</li>
                          <li>Click "Manage Audience" → "Create Audience"</li>
                          <li>Fill in the required information</li>
                          <li>Return here and refresh to see your audience</li>
                        </ol>
                        <button
                          type="button"
                          class="mt-3 text-sm bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded"
                          @click="fetchLists"
                        >
                          Refresh Audiences
                        </button>
                      </div>
    
                      <div v-if="actionType === 'push' && lists.length > 0" class="mt-2">
                        <label class="input-label dark:text-white">Export to</label>
                        <div class="flex space-x-4">
                          <label class="flex items-center space-x-1 dark:text-white">
                            <input type="radio" value="list" v-model="destinationType" />
                            <span>Entire Audience</span>
                          </label>
                          <label class="flex items-center space-x-1 dark:text-white">
                            <input type="radio" value="segment" v-model="destinationType" />
                            <span>With Tag/Segment</span>
                          </label>
                        </div>
                      </div>
    
                      <div v-if="actionType === 'push' && destinationType === 'list' && lists.length > 0" class="mt-2">
                        <label class="input-label dark:text-white">Select Audience</label>
                        <select class="input-style w-full" v-model="selectedListId" required>
                          <option value="">-- Select an Audience --</option>
                          <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                          Contacts will be added to this audience (duplicates will be updated)
                        </p>
                      </div>
    
                      <div v-if="actionType === 'push' && destinationType === 'segment' && lists.length > 0" class="mt-2">
                        <label class="input-label dark:text-white">Select Audience</label>
                        <select class="input-style w-full mb-3" v-model="selectedListId" @change="onListChange">
                          <option value="">-- Select an Audience --</option>
                          <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                        </select>
    
                        <div v-if="selectedListId" class="space-y-2">
                          <label class="input-label dark:text-white">Select Tag or Segment</label>
                          <select class="input-style w-full" v-model="selectedSegmentId" :disabled="loadingSegments">
                            <option value="">-- Select a Tag or Segment --</option>
                            <option v-for="segment in segments" :key="segment.id" :value="segment.id">{{ segment.name }}</option>
                          </select>
    
                          <p v-if="loadingSegments" class="text-xs text-gray-500 dark:text-gray-400">
                            Loading tags and segments...
                          </p>
    
                          <p v-if="!loadingSegments && segments.length === 0" class="text-xs text-gray-500 dark:text-gray-400">
                            No tags or segments found. Create a tag below to organize your contacts.
                          </p>
    
                          <div class="mt-2">
                            <button
                              type="button"
                              class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 underline"
                              @click="showCreateSegment = !showCreateSegment"
                            >
                              {{ showCreateSegment ? '✕ Cancel' : '+ Create New Tag' }}
                            </button>
                          </div>
    
                          <div v-if="showCreateSegment" class="mt-2 p-4 border-2 border-blue-200 dark:border-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800">
                            <h4 class="font-semibold mb-3 dark:text-white text-lg">Create New Tag</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                              Tags help you organize contacts within an audience (e.g., "Active_Buyers", "VIP_Clients", "CRM_Export_2025")
                            </p>
                            <input
                              type="text"
                              v-model="newSegmentName"
                              placeholder="Tag name (e.g., 'CRM_Export_Q1_2025')"
                              class="input-style w-full mb-3"
                            />
                            <button
                              type="button"
                              class="btn btn-primary w-full"
                              :disabled="creatingSegment || !newSegmentName.trim()"
                              @click="createSegment"
                            >
                              {{ creatingSegment ? 'Creating...' : 'Create Tag' }}
                            </button>
                          </div>
                        </div>
                      </div>
    
                      <div v-if="actionType === 'pull' && lists.length > 0" class="mt-2">
                        <label class="input-label dark:text-white">Select Audience</label>
                        <select class="input-style w-full" v-model="selectedListId" @change="onListChange" :disabled="loadingLists">
                          <option value="">-- Select an Audience --</option>
                          <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                        </select>
    
                        <div v-if="selectedListId" class="mt-3">
                          <label class="input-label dark:text-white">Import from</label>
                          <div class="flex space-x-4 mb-2">
                            <label class="flex items-center space-x-1 dark:text-white">
                              <input type="radio" value="entire_list" v-model="importSource" />
                              <span>Entire Audience</span>
                            </label>
                            <label class="flex items-center space-x-1 dark:text-white">
                              <input type="radio" value="segment" v-model="importSource" />
                              <span>Specific Tag/Segment</span>
                            </label>
                          </div>
    
                          <select
                            v-if="importSource === 'segment'"
                            class="input-style w-full"
                            v-model="selectedSegmentId"
                            :disabled="loadingSegments"
                          >
                            <option value="">-- Select a Tag or Segment --</option>
                            <option v-for="segment in segments" :key="segment.id" :value="segment.id">{{ segment.name }}</option>
                          </select>
    
                          <p v-if="importSource === 'segment' && !loadingSegments && segments.length === 0" class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                            No tags or segments found in this audience. Import from entire audience instead.
                          </p>
                        </div>
                      </div>
    
                    <div v-if="actionType === 'pull' && lists.length > 0" class="mt-3">
                      <label class="input-label dark:text-white">
                        Assign a {{ type === 'lead' ? 'Lead' : 'Contact' }} Type
                      </label>
                      <select
                        class="input-style w-full"
                        v-model="selectedRecordTypeId"
                      >
                        <option value="">-- Select Type --</option>
                        <option v-for="(record, key) in recordTypeList" :key="record.item_id ?? record.id" :value="record.item_id ?? record.id">
                          {{ record.name }}
                        </option>
                      </select>
                    </div>
    
                    <div v-if="actionType === 'push' && lists.length > 0" class="mt-4 space-y-3">
                      <label class="input-label">
                        Which {{ type === 'lead' ? 'leads' : 'contacts' }} to export:
                      </label>
    
                      <div class="flex flex-wrap gap-4">
                        <label class="flex items-center space-x-1 dark:text-white">
                          <input type="radio" value="all" v-model="applyScope" />
                          <span>All {{ type === 'lead' ? 'Leads' : 'Contacts' }}</span>
                        </label>
    
                        <label class="flex items-center space-x-1 dark:text-white">
                          <input type="radio" value="selected" v-model="applyScope" />
                          <span>Selected Only ({{ tableSelectedIds.length }})</span>
                        </label>
    
                        <label class="flex items-center space-x-1 dark:text-white">
                          <input type="radio" value="filtered" v-model="applyScope" />
                          <span>Filtered by Type, Status, etc.</span>
                        </label>
                      </div>
    
                      <!-- Validation message for selected -->
                      <p v-if="applyScope === 'selected' && tableSelectedIds.length === 0"
                         class="text-sm text-red-600 dark:text-red-400">
                        No contacts selected. Please select contacts from the table first.
                      </p>
    
                      <!-- Filters section (only when filtered is chosen) -->
                    <div
                      v-if="applyScope === 'filtered'"
                      class="mt-3 space-y-3 border rounded-lg p-3 bg-gray-50 dark:bg-gray-800"
                    >
                      <div v-if="recordTypeList">
                        <label class="input-label dark:text-white">Type</label>
                        <select
                          class="input-style w-full"
                          v-model="filters.types"
                          multiple
                        >
                          <option
                            v-for="record in recordTypeList"
                            :key="record.item_id ?? record.id"
                            :value="record.item_id ?? record.id"
                          >
                            {{ record.name }}
                          </option>
                        </select>
                      </div>
    
                      <div v-if="statuses">
                        <label class="input-label dark:text-white">Status</label>
                        <select
                          class="input-style w-full"
                          v-model="filters.statuses"
                          multiple
                        >
                          <option
                            v-for="status in statuses"
                            :key="status.item_id ?? status.id"
                            :value="status.item_id ?? status.id"
                          >
                            {{ status.name }}
                          </option>
                        </select>
                      </div>
    
                      <div v-if="sources">
                        <label class="input-label dark:text-white">Source</label>
                        <select
                          class="input-style w-full"
                          v-model="filters.sources"
                          multiple
                        >
                          <option
                            v-for="source in sources"
                            :key="source.item_id ?? source.id"
                            :value="source.item_id ?? source.id"
                          >
                            {{ source.name }}
                          </option>
                        </select>
                      </div>
    
                      <div v-if="priorities">
                        <label class="input-label dark:text-white">Priority</label>
                        <select
                          class="input-style w-full"
                          v-model="filters.priorities"
                          multiple
                        >
                          <option
                            v-for="priority in priorities"
                            :key="priority.item_id ?? priority.id"
                            :value="priority.item_id ?? priority.id"
                          >
                            {{ priority.name }}
                          </option>
                        </select>
                      </div>
                    </div>
    
                    </div>
    
    
                    </div>
    
                    <!-- Buttons -->
                    <div class="flex justify-end space-x-4 mt-4">
                      <button type="button" class="btn btn-outline" @click="closeSyncModal">Cancel</button>
                      <button
                        type="submit"
                        class="btn btn-primary  disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!canSubmit"
                      >
                        {{ actionType === 'pull' ? `Import ${type === 'lead' ? 'Leads' : 'Contacts'}` : `Export ${type === 'lead' ? 'Leads' : 'Contacts'}` }}
    
                      </button>
                    </div>
    
                  </form>
            </div>
        </div>
    </div>
    
    
    <div v-if="showSuccess" class="fixed inset-0 z-[60] bg-gray-900/50 dark:bg-gray-900/80" aria-hidden="true" />
    <div v-if="showSuccess" tabindex="-1" aria-hidden="true"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="relative w-full max-w-md">
            <div class="relative p-4 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
                <button type="button" class="text-gray-400 absolute top-2.5 right-2.5 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        @click.prevent="closeSuccess">
                    <i class="fas fa-check text-green-500 dark:text-green-400"></i>
                    <span class="sr-only">Close modal</span>
                </button>
    
                <!-- Success content -->
                <div class="flex items-center space-x-1.5 mb-2">
                    <div class="flex justify-center items-center w-6 h-6 bg-green-100 rounded-full dark:bg-green-900">
                        <i class="fas fa-check text-green-500 dark:text-green-400"></i>
                        <span class="sr-only">Success icon</span>
                    </div>
                    <h3 class="text-lg font-medium text-green-500 dark:text-green-400">
                        {{ successType == 'import' ? 'Mailchimp Import Started!' : 'Mailchimp Export Started!' }}
                    </h3>
                </div>
    
                <p class="mb-4 font-light text-gray-500 dark:text-gray-400">
                    {{ successType == 'import'
                        ? 'Your selected records are being imported from Mailchimp. It may take a few minutes for them to appear in your list.'
                        : 'Your selected records are being exported from your system to Mailchimp. It may take a few minutes for them to appear in your list.' }}
                </p>
                <button @click.prevent="closeSuccess" type="button"
                        class="py-2 px-3 text-sm font-medium text-center text-white rounded-lg bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 dark:focus:ring-primary-900">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    
    </template>
    
    <script>
    export default {
      props: {
        type: { type: String, default: 'lead'},
        tableSelectedIds: { type: Array, default: () => [] },
        statuses: { type: Object, default: () => {} },
        sources: { type: Object, default: () => {} },
        priorities: { type: Object, default: () => {} },
        recordtype: { type: Object, default: () => {} },
      },
      data() {
        return {
            showSyncModal: false,
            actionType: 'push',
            destinationType: 'list',
            importSource: 'entire_list',
            applyScope: 'all',
            selectedRecordTypeId: '',
            recordTypeList: this.recordtype,
            lists: [],
            segments: [],
            selectedListId: '',
            selectedSegmentId: null,
            loadingLists: false,
            loadingSegments: false,
            showCreateSegment: false,
            newSegmentName: '',
            creatingSegment: false,
            showSuccess: false,
            successType: 'import',
            filters: {
              types: [],
              statuses: [],
              sources: [],
              priorities: []
            }
        };
      },
      mounted() {
        document.addEventListener('keydown', this.onDocumentEscape);
      },
      beforeUnmount() {
        document.removeEventListener('keydown', this.onDocumentEscape);
      },
      computed: {
          canSubmit() {
            if (this.lists.length === 0) return false;
            if (this.actionType === 'push' && !this.selectedListId) return false;
            if (this.actionType === 'push' && this.destinationType === 'segment' && !this.selectedSegmentId) {
              return false;
            }
            if (this.actionType === 'pull' && this.importSource === 'segment' && !this.selectedSegmentId) {
              return false;
            }
            if (this.actionType === 'push' && this.applyScope === 'selected' && this.tableSelectedIds.length === 0) {
              return false;
            }
    
            return true;
          }
      },
      watch: {
        actionType() {
          this.selectedListId = '';
          this.selectedSegmentId = null;
          this.segments = [];
          this.showCreateSegment = false;
        },
        destinationType() {
          // Reset when switching export destination type
          this.selectedListId = '';
          this.selectedSegmentId = null;
          this.segments = [];
          this.showCreateSegment = false;
        },
        selectedListId() {
          this.selectedSegmentId = null;
        },
        recordtype: {
          handler(val) {
            this.recordTypeList = val;
          },
          deep: true,
        },
      },
      methods: {
        onDocumentEscape(e) {
          if (e.key !== 'Escape') {
            return;
          }
          if (this.showSuccess) {
            this.closeSuccess();
            return;
          }
          if (this.showSyncModal) {
            this.closeSyncModal();
          }
        },
        openSyncModal() {
          this.showSyncModal = true;
          this.onModalOpen();
        },
        closeSyncModal() {
          this.showSyncModal = false;
        },
        toggleSyncModal() {
          if (this.showSyncModal) {
            this.closeSyncModal();
          } else {
            this.openSyncModal();
          }
        },
        closeSuccess() {
            this.showSuccess = false;
        },
        onModalOpen() {
          if (!this.loadingLists && this.lists.length === 0) {
            this.fetchLists();
          }
        },
    
        fetchLists() {
          this.loadingLists = true;
    
          axios.get('/integrations/mailchimp/lists')
            .then(res => {
              this.lists = res.data.lists || res.data;
    
              if (this.lists.length === 0) {
                console.warn('No lists found in Mailchimp account');
              }
            })
            .catch(err => {
              console.error('Failed to fetch lists:', err);
              console.error('Error response:', err.response);
              const errorMessage = err.response?.data?.error || 'Failed to load Mailchimp audiences. Please check your connection.';
              alert(errorMessage);
            })
            .finally(() => {
              this.loadingLists = false;
            });
        },
    
        onListChange() {
          this.selectedSegmentId = null;
          this.segments = [];
          if (this.selectedListId) {
            this.fetchSegments();
          }
        },
    
        fetchSegments() {
          if (!this.selectedListId) return;
    
          this.loadingSegments = true;
    
          axios.get(`/integrations/mailchimp/lists/${this.selectedListId}/segments`)
            .then(res => {
              this.segments = res.data.segments || res.data;
            })
            .catch(err => {
              // console.error('Failed to fetch segments:', err);
              const errorMessage = err.response?.data?.error || 'Failed to load tags and segments';
              // Don't alert for segments - just log it
              console.warn(errorMessage);
            })
            .finally(() => {
              this.loadingSegments = false;
            });
        },
    
        createSegment() {
          if (!this.newSegmentName.trim()) {
            alert('Please enter a tag name');
            return;
          }
    
          if (!this.selectedListId) {
            alert('Please select an audience first');
            return;
          }
    
          this.creatingSegment = true;
    
          axios.post(`/integrations/mailchimp/lists/${this.selectedListId}/segments`, {
            name: this.newSegmentName
          })
          .then(res => {
            this.segments.push(res.data);
            this.selectedSegmentId = res.data.id;
            this.newSegmentName = '';
            this.showCreateSegment = false;
            alert('Tag created successfully! It will appear in Mailchimp once contacts are added with this tag.');
          })
          .catch(err => {
            console.error('Failed to create tag:', err);
            const errorMessage = err.response?.data?.error || 'Failed to create tag';
            alert(errorMessage);
          })
          .finally(() => {
            this.creatingSegment = false;
          });
        },
    
        handleSubmit() {
          if (!this.canSubmit) return;
    
          let endpoint, payload;
          if (this.actionType === 'pull') {
            endpoint = `/integrations/mailchimp/lists/pull`;
            payload = {};
    
            payload.list = this.selectedListId;
            payload.type = this.type;
            payload.type_id = this.selectedRecordTypeId;
    
            if (this.importSource === 'segment' && this.selectedSegmentId) {
              payload.segment_id = this.selectedSegmentId;
            }
    
            axios.get(endpoint, { params: payload })
              .then(res => {
                const message = res.data.message || `Successfully imported ${res.data.imported || 0} contacts from Mailchimp`;
                // alert(message);
                this.successType = 'import';
                this.showSyncModal = false;
                this.showSuccess = true;

                // window.location.reload();
                // this.$emit('success', res.data);
                // this.$emit('close');
              })
              .catch(err => {
                console.error('Import failed:', err);
                const errorMessage = err.response?.data?.error || 'Failed to import contacts';
                alert(errorMessage);
              });
    
          } else {
              payload = { apply_scope: this.applyScope };
              payload.type = this.type;
              if (this.applyScope === 'selected') {
                payload.selected_ids = this.tableSelectedIds;
              } else if (this.applyScope === 'filtered') {
                payload.filters = this.filters;
              }
    
              if (this.destinationType === 'segment' && this.selectedSegmentId) {
                endpoint = `/integrations/mailchimp/lists/${this.selectedListId}/segments/${this.selectedSegmentId}/push`;
              } else {
                endpoint = `/integrations/mailchimp/lists/${this.selectedListId}/push`;
              }
    
              axios.post(endpoint, payload)
                .then(res => {
                    const message = res.data.message || `Successfully exported ${res.data.exported || 0} contacts to Mailchimp`;
                    // alert(message);
                    this.successType = 'export';
                    this.showSyncModal = false;
                    this.showSuccess = true;
                })
                .catch(err => {
                  console.error('Export failed:', err);
                  const errorMessage = err.response?.data?.error || 'Failed to export contacts';
                  alert(errorMessage);
                });
          }
        }
      },
      // Parent can call via template ref: ref="mailchimp" then mailchimp.openSyncModal()
      expose: ['openSyncModal', 'closeSyncModal', 'toggleSyncModal'],
    };
    </script>
    