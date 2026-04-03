<template>
    <div class="btn-bar inline-flex w-full" v-if="$root.dataRecords[dataKey] && $root.dataRecords[dataKey].length && !isDisabled" role="group" >
        <button class="btn primary-btn sm items-center" @click.prevent="createRecord()">Log Activity</button>
    </div>
    <div class="col-block" style="overflow-x: auto;">
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" v-if="$root.dataRecords[dataKey] && $root.dataRecords[dataKey].length">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 whitespace-nowrap">Type</th>
                    <th class="px-2 py-3 whitespace-nowrap">Direction</th>
                    <th class="px-2 py-3 whitespace-nowrap">Date Contacted</th>
                    <th class="px-2 py-3 whitespace-nowrap">Subject</th>
                    <th class="px-2 py-3 whitespace-nowrap">Status</th>
                    <th class="px-2 py-3 whitespace-nowrap">Priority</th>
                    <th class="px-2 py-3 whitespace-nowrap">Action</th>
                    <th class="px-2 py-3 whitespace-nowrap">Next Action Date</th>
                    <th class="px-2 py-3 whitespace-nowrap">Is Private</th>
                    <th class="px-2 py-3 whitespace-nowrap" v-if="Object.keys($root.listData.team_users || {}).length > 1 ">Created By</th>
                    <th class="px-2 py-3 whitespace-nowrap" v-if="Object.keys($root.listData.team_users || {}).length > 1 ">Assigned To</th>
                    <th class="px-2 py-3 whitespace-nowrap">Outcome</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer"  v-for="record in $root.dataRecords[dataKey]" :key="record.id" @click.prevent="editRecord(record.id)">
                    <td class="px-4 py-4 flex items-center capitalize">
                        {{ enums.communicationTypes.find(t => t.id === record.communication_type_id)?.name || 'None' }}
                    </td>
                    <td class="px-2 py-2 capitalize">{{ record.direction }}</td>
                    <td class="px-2 py-2" v-text="$root.formatDate(record.date_contacted, true)"></td>
                    <td class="px-2 py-2">
                        <span>{{ record.subject }}</span>
                        <a v-if="record.survey_response_id" 
                           :href="`/surveys/responses/${record.survey_response_id}`"
                           class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                           title="View survey response"
                           target="_blank">
                            <i class="fas fa-external-link-alt text-xs"></i>
                        </a>
                    </td>
                    <td class="px-2 py-2 capitalize">
                        <EnumBadge
                            :value="record.status_id"
                            :options="enums.statusTypes"
                            fallback-text="None"
                            fallback-class="bg-gray-200"
                        />
                    </td>
                    <td class="px-2 py-2 capitalize">
                        <EnumBadge
                            :value="record.priority_id"
                            :options="enums.priorityLevels"
                            fallback-text="None"
                            fallback-class="bg-gray-200"
                        />
                    </td>
                    <td class="px-2 py-2 capitalize">
                        <EnumBadge
                            :value="record.next_action_type_id"
                            :options="enums.nextActionTypes"
                            fallback-text="None"
                            fallback-class="bg-gray-200"
                        />
                    </td>
                    <td class="px-2 py-2" v-text="$root.formatDate(record.next_action_at, true)"></td>
                    <td class="px-2 py-2 text-center">
                      <i
                        v-if="record.is_private"
                        class="fas fa-check-square text-primary-700 dark:text-primary-600 text-lg"
                        title="Private"
                      ></i>
                      <i
                        v-else
                        class="far fa-square text-gray-400 text-lg"
                        title="Not Private"
                      ></i>
                    </td>
                    <th class="px-2 py-2" v-if="Object.keys($root.listData.team_users || {}).length > 1 ">
                        <div class="avatar-wrap small rounded-full">
                            <avatar :name="$root.getUserName(record.user_id)" v-cloak></avatar>
                        </div>
                    </th>
                    <th class="px-2 py-2" v-if="Object.keys($root.listData.team_users || {}).length > 1 ">
                        <div class="avatar-wrap small rounded-full">
                            <avatar :name="$root.getUserName(record.assigned_to)" v-cloak></avatar>
                        </div>
                    </th>
                    <td class="px-2 py-2 capitalize">
                        <EnumBadge
                            :value="record.outcome_id"
                            :options="enums.outcomeActions"
                            fallback-text="None"
                            fallback-class="bg-gray-200"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="table-empty text-gray-700 dark:text-gray-400 flex items-center justify-center h-full" v-if="!$root.dataRecords[dataKey] || !$root.dataRecords[dataKey].length">
        <div class="relative text-center p-4 md:p-5">
            <i class="fas fa-comments-alt text-gray-800 dark:text-gray-200 mx-auto mb-4 text-2xl md:text-4xl"></i>
            <h3 class="mb-4 text-lg font-bold text-gray-700 dark:text-gray-200 md:text-xl ">
                No communication records have been logged yet.
            </h3>
            <button class="btn blue-btn sm" @click.prevent="createRecord()">Log Activity</button>
        </div>
    </div>
    <!-- Modal -->
    <div v-if="showCreate" id="communicationModal" tabindex="-1" class="flex overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-modal md:h-full p-4">
        <div class="!fixed top-0 left-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0  !w-full !h-full !max-h-none" @click="closeModal()"></div>
        <div class="relative  w-full max-w-5xl  h-full md:h-auto bg-white rounded-lg shadow dark:bg-gray-800 ">
            <div class="modal-header sticky top-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Log Activity
                </h3>
                <button type="button" class="close-modal" @click="closeModal()">
                <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <form @submit.prevent="isEditing ? submitUpdate() : submitCreate()" class="space-y-4">
                <div class="p-4 md:p-5 grid gap-4 lg:gap-6 mb-4 lg:grid-cols-2 sm:mb-6">
                    <div class="space-y-4">
                        <!-- Subject -->
                        <div class="required">
                            <label class="input-label">Subject <span>*</span></label>
                            <input type="text" v-model="createForm.subject"
                                class="input-style" required />
                        </div>
                        <div class="">
                            <label class="input-label">Type <span>*</span></label>
                            <EnumSelect
                                required
                                v-model="createForm.communication_type_id"
                                :options="enums.communicationTypes"
                                placeholder="Select Type"
                                class="input-style"
                            />
                        </div>
                        <!-- Direction -->
                        <div class="">
                            <label class="input-label">Direction  <span>*</span></label>
                            <select v-model="createForm.direction"
                                class="input-style" required>
                                <option value="" disabled>Select Direction</option>
                                <option value="inbound">Inbound</option>
                                <option value="outbound">Outbound</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4 lg:gap-6">
                            <!-- Status -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="input-label">Status</label>
                                <EnumSelect
                                    required
                                    v-model="createForm.status_id"
                                    :options="enums.statusTypes"
                                    placeholder="Select Status"
                                    class="input-style"
                                />
                            </div>
                            <!-- Priority -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="input-label">Priority</label>
                                <EnumSelect
                                    v-model="createForm.priority_id"
                                    :options="enums.priorityLevels"
                                    placeholder="Select Priority"
                                    class="input-style"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 lg:gap-6">
                            <!-- Outcome -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="input-label">Outcome</label>
                                <EnumSelect
                                    v-model="createForm.outcome_id"
                                    :options="enums.outcomeActions"
                                    placeholder="Select Outcome"
                                    class="input-style"
                                />
                            </div>
                            <!-- Channel -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="input-label">Channel</label>
                                <EnumSelect
                                    v-model="createForm.channel_id"
                                    :options="enums.channelTypes"
                                    placeholder="Select Channel"
                                    class="input-style"
                                />
                            </div>

                        </div>
                        <!-- Assigned To -->
                        <div v-show="(Object.keys($root.listData.team_users || {}).length > 1 || createForm.assigned_to != $currentuser)">

                            <label for="assigned_to" class="input-label">Assigned To</label>
                            <select name="assigned_to" id="assigned_to" class="input-style" v-model="createForm.assigned_to">
                                <option v-for="(name, id) in $root.listData.team_users" :key="id" :value="id">{{ name }}</option>
                            </select>
                        </div>


                        <!-- Is Private -->
                        <div  class="">
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="isPrivate" v-model="createForm.is_private" class="checkbox-input" />
                                <label for="isPrivate" class="checkbox-label">Is Private</label>
                                <helppopup
                                  header="Private Records"
                                  text="Private communication records can only be viewed by the creator, the assigned user, and team admins."
                                  id="CommunicationHelper"
                                  :modal="true"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <!-- Notes -->
                        <div class="">
                            <label class="input-label">Notes</label>
                            <textarea v-model="createForm.notes" rows="5" class="input-style"></textarea>
                        </div>
                        <div>
                            <label class="input-label">Next Action</label>
                            <EnumSelect
                                v-model="createForm.next_action_type_id"
                                :options="enums.nextActionTypes"
                                placeholder="Select Next Action"
                                class="input-style "
                            />
                        </div>
                        <!-- Action Date - Updated to native HTML datetime-local -->
                        <div class="">
                            <label class="input-label">Action Date</label>
                            <input
                                type="datetime-local"
                                v-model="createForm.next_action_at"
                                class="input-style"
                                placeholder="Select a date"
                            />
                        </div>
                        <!-- Date Contacted - Updated to native HTML datetime-local -->
                        <div class="">
                            <label class="input-label">Date Contacted</label>
                            <input
                                type="datetime-local"
                                v-model="createForm.date_contacted"
                                class="input-style"
                                placeholder="Select a date"
                            />
                        </div>
                        <div v-if="showCreatedBy">
                            <label class="input-label">Created by</label>
                            <span class="input-style">
                                {{ $root.getUserName(createForm.user_id) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" :class="isEditing ? 'justify-between' : 'justify-end'">
                    <button
                        v-if="isEditing"
                        @click="deleteRecord()"
                        type="button"
                        class="btn btn-warning sm"
                        :disabled="saving"
                    >
                        <span v-if="deleting">Deleting...</span>
                        <span v-else>
                            <i class="fas fa-trash mr-2"></i>Delete
                        </span>
                    </button>

                    <div class="flex space-x-2">
                        <button @click="closeModal()" type="button" class="btn btn-outline sm">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary sm" :disabled="saving">
                            <span v-if="saving">Saving...</span>
                            <span v-else>Save Communication</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
<script>
import { EnumManager } from './EnumManager.js'

// Reusable EnumSelect Component
const EnumSelect = {
  props: {
    modelValue: [String, Number],
    options: {
      type: Array,
      default: () => []
    },
    placeholder: {
      type: String,
      default: 'Select an option'
    },
    disabled: {
      type: Boolean,
      default: false
    },
    required: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue'],
  template: `
    <select
      :value="modelValue"
      @input="$emit('update:modelValue', parseInt($event.target.value) || $event.target.value)"
      :disabled="disabled"
      :required="required"
      v-bind="$attrs"
    >
      <option value="" disabled>{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="option.id"
        :value="option.id"
      >
        {{ option.name }}
      </option>
    </select>
  `
}

// Reusable EnumBadge Component
const EnumBadge = {
  props: {
    value: [String, Number],
    options: {
      type: Array,
      default: () => []
    },
    fallbackText: {
      type: String,
      default: 'None'
    },
    fallbackClass: {
      type: String,
      default: 'bg-gray-200'
    }
  },
  computed: {
    option() {
      // Convert value to number for comparison if it's a string number
      const numValue = typeof this.value === 'string' ? parseInt(this.value) : this.value;
      return this.options.find(opt => opt.id === numValue);
    }
  },
  template: `
    <span
      v-if="option"
      :class="[
        'text-xs font-medium px-2.5 py-0.5 cursor-pointer rounded text-gray-800',
        option.bgClass || 'bg-white'
      ]"
    >
      {{ option.name }}
    </span>
    <span
      v-else
      :class="[
        'text-xs font-medium px-2.5 py-0.5 cursor-pointer rounded text-gray-800',
        fallbackClass
      ]"
    >
      {{ fallbackText }}
    </span>
  `
}

export default {
  // Mix in the EnumManager
  mixins: [EnumManager],

  props: ['recid', 'type', 'dateformat', 'timezone', 'disabled'],
  components: {
    EnumSelect,
    EnumBadge
  },
  data() {
    return {
      dataKey: 'activity',
      isEditing: false,
      isDisabled: false,
      debounceTimer: null,
      currentRecord: null,
      urlBase: window.location.origin,
      showEdit: false,
      showCreate: false,
      saveSuccessful: false,
      saveFailed: false,
      saving: false,
      deleting: false,
      saved: false,
      loading: true,
      loaded: false,
      toastTimer: 2500,
      currentPage: 1,
      lastPage: 1,
      nextPageUrl: null,
      prevPageUrl: null,
      createForm: {
        communication_type_id: null,
        direction: '',
        subject: '',
        notes: '',
        is_private: true,
        status_id: 1, // Default to 'open' (ID 1)
        channel_id: null,
        priority_id: 2, // Default to 'medium' (ID 2)
        tagsString: '',
        outcome_id: null,
        next_action_type_id: null,
        next_action_at: null,
        assigned_to: this.$currentuser ?? null,
        user_id: null,
        date_contacted: this.formatDateTimeForInput(new Date()),
      }
    }
  },
  computed: {
    showCreatedBy() {
        const currentUser = parseInt(this.$currentuser, 10);
        const assignedId = this.createForm.assigned_id !== null ? this.createForm.assigned_id : currentUser;
        return assignedId !== this.createForm.user_id && this.createForm.user_id !== currentUser;
    }
  },
    mounted() {
        if(this.disabled) {
            this.isDisabled = true;
        }
        this.$root.getTeamUsers();
    },
  methods: {
    isValidDate(date) {
      return date && !isNaN(new Date(date).getTime());
    },

    // Helper method to format date for datetime-local input
    formatDateTimeForInput(date) {
      if (!date) return '';
      const d = new Date(date);
      if (isNaN(d.getTime())) return '';

      // Format as YYYY-MM-DDTHH:MM (required format for datetime-local)
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');

      return `${year}-${month}-${day}T${hours}:${minutes}`;
    },

    // Helper method to convert datetime-local input back to ISO string
    formatInputForStorage(dateTimeLocal) {
      if (!dateTimeLocal) return null;
      return new Date(dateTimeLocal).toISOString();
    },

    tabChanged(tab) {
      if(tab == 'activity' && !this.loaded) {
        this.fetchRecords();
        this.loaded = true;
        this.loading = false;
      }
    },

    handleItemAdded(newItem) {
      this.$root.dataRecords[this.dataKey].push(newItem);
    },

    fetchRecords() {
      axios.get(this.urlBase + '/communications/recorditems', {
        params: {
          type: this.type,
          id: this.recid
        }
      })
      .then(response => {
        const res = response.data;
        if (res.communications && Array.isArray(res.communications.data)) {
          this.$root.dataRecords[this.dataKey] = res.communications.data;
          this.currentPage = res.communications.current_page;
          this.lastPage = res.communications.last_page;
          this.nextPageUrl = res.communications.next_page_url;
          this.prevPageUrl = res.communications.prev_page_url;

          // Use the EnumManager's setEnumData method
          this.setEnumData({
            nextActionTypes: res.next_action_types || [],
            outcomeActions: res.outcome || [],
            channelTypes: res.channel || [],
            priorityLevels: res.priority || [],
            statusTypes: res.status || [],
            communicationTypes: res.communication_types || []
          });
          // console.log(res);
        } else {
          alert(res.message || 'Unexpected response');
        }
      })
      .catch(error => {
        console.error(error);
        alert('Failed to fetch communications');
      });
    },

    editRecord(id) {
      const record = this.$root.dataRecords[this.dataKey].find(r => r.id === id);
      if (!record) return;

      this.currentRecord = record;
      this.isEditing = true;
      this.createForm = {
        communication_type_id: record.communication_type_id || null,
        direction: record.direction || 'outbound',
        subject: record.subject || '',
        notes: record.notes || '',
        is_private: Boolean(record.is_private),
        status_id: record.status_id || 1,
        channel_id: record.channel_id || null,
        priority_id: record.priority_id || 2,
        tagsString: record.tags?.join(', ') || '',
        outcome_id: record.outcome_id || null,
        next_action_type_id: record.next_action_type_id || null,
        next_action_at: this.formatDateTimeForInput(record.next_action_at),
        date_contacted: this.formatDateTimeForInput(record.date_contacted),
        assigned_to: record.assigned_to || this.$currentuser,
        user_id: record.user_id || null,
      };
      this.showCreate = true;
    },

    createRecord() {
      this.createForm = {
        communication_type_id: null,
        direction: 'outbound',
        subject: '',
        notes: '',
        is_private: true,
        status_id: 1, // Default to 'open' (ID 1)
        channel_id: null,
        priority_id: 2, // Default to 'medium' (ID 2)
        tagsString: '',
        outcome_id: null,
        next_action_type_id: null,
        next_action_at: null,
        assigned_to: this.$currentuser,
        user_id: this.$currentuser,
        date_contacted: this.formatDateTimeForInput(new Date()),
      };
      this.showCreate = true;
    },

    openModal(modal) {
      this.$root.toggleBackdrop(true);
      if(modal == 'edit') {
        this.showSlideout = true;
      }
    },
    async deleteRecord() {
        if (!this.currentRecord?.id) {
            this.$root.createToast('danger', 'No record selected for deletion.');
            return;
        }

        // Confirm deletion
        if (!confirm('Are you sure you want to delete this communication record? This action cannot be undone.')) {
            return;
        }

        this.deleting = true;
        try {
            const response = await axios.delete(`${this.urlBase}/communications/destroy`, {
                params: {
                    id: this.currentRecord.id
                },
                headers: { 'X-App-Ajax': 'true' }
            });

            this.$root.createToast('success', response.data.message || 'Communication deleted successfully.');

            // Remove the record from the local array
            const index = this.$root.dataRecords[this.dataKey].findIndex(r => r.id === this.currentRecord.id);
            if (index !== -1) {
                this.$root.dataRecords[this.dataKey].splice(index, 1);
            }

            this.closeModal();
            this.isEditing = false;
            this.currentRecord = null;
        } catch (error) {
            const message = error.response?.data?.message || 'Error deleting communication.';
            this.$root.createToast('danger', message);
        } finally {
            this.deleting = false;
        }
    },

    closeModal() {
        this.$root.toggleBackdrop(false);
        this.showEdit = false;
        this.showCreate = false;
        this.isEditing = false;
        this.currentRecord = null;
    },

    async submitCreate() {
      this.saving = true;
      try {
        const tags = this.createForm.tagsString
          .split(',')
          .map(tag => tag.trim())
          .filter(tag => tag.length > 0);

        const payload = {
          communicable_type: this.type,
          communicable_id: this.recid,
          communication_type_id: this.createForm.communication_type_id,
          direction: this.createForm.direction,
          subject: this.createForm.subject,
          notes: this.createForm.notes,
          is_private: this.createForm.is_private,
          status_id: this.createForm.status_id,
          channel_id: this.createForm.channel_id,
          priority_id: this.createForm.priority_id,
          next_action_type_id: this.createForm.next_action_type_id,
          tags: tags,
          outcome_id: this.createForm.outcome_id,
          next_action_at: this.formatInputForStorage(this.createForm.next_action_at),
          date_contacted: this.formatInputForStorage(this.createForm.date_contacted),
          assigned_to: this.createForm.assigned_to,
        };

        const response = await axios.post(`${window.location.origin}/communications/store`, payload, {
          headers: { 'X-App-Ajax': 'true' }
        });

        this.$root.createToast('success', response.data.message || 'Communication logged.');
        this.$root.dataRecords[this.dataKey].push(response.data.record);
        this.showCreate = false;
      } catch (error) {
        const message = error.response?.data?.message || 'Error saving communication.';
        this.$root.createToast('danger', message);
      } finally {
        this.saving = false;
      }
    },

    async submitUpdate() {
      this.saving = true;
      try {
        const tags = this.createForm.tagsString
          .split(',')
          .map(tag => tag.trim())
          .filter(tag => tag.length > 0);

        const payload = {
          id: this.currentRecord.id,
          communicable_type: this.type,
          communicable_id: this.recid,
          communication_type_id: this.createForm.communication_type_id,
          direction: this.createForm.direction,
          subject: this.createForm.subject,
          notes: this.createForm.notes,
          is_private: this.createForm.is_private,
          status_id: this.createForm.status_id,
          channel_id: this.createForm.channel_id,
          priority_id: this.createForm.priority_id,
          next_action_type_id: this.createForm.next_action_type_id,
          tags: tags,
          outcome_id: this.createForm.outcome_id,
          next_action_at: this.formatInputForStorage(this.createForm.next_action_at),
          date_contacted: this.formatInputForStorage(this.createForm.date_contacted),
          assigned_to: this.createForm.assigned_to,
        };

        const response = await axios.put(`${window.location.origin}/communications/update`, payload, {
          headers: { 'X-App-Ajax': 'true' }
        });

        this.$root.createToast('success', response.data.message || 'Communication updated.');
        const idx = this.$root.dataRecords[this.dataKey].findIndex(r => r.id === response.data.record.id);
        if (idx !== -1) {
          this.$root.dataRecords[this.dataKey].splice(idx, 1, response.data.record);
        }
        this.showCreate = false;
      } catch (error) {
        const message = error.response?.data?.message || 'Error updating communication.';
        this.$root.createToast('danger', message);
      } finally {
        this.saving = false;
      }
    }
  }
}
</script>
