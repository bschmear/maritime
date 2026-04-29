<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    assetId: { type: Number, required: true },
});

const emit = defineEmits(['close', 'sent']);

const loadingOptions = ref(false);
const sending = ref(false);
const errorMessage = ref('');
const hasVariants = ref(false);
const variants = ref([]);
const customerQuery = ref('');
const customerResults = ref([]);
const searchingCustomers = ref(false);
const selectedCustomer = ref(null);
const selectedVariantIds = ref(new Set());
const includeAssetLevel = ref(false);

let searchTimer = null;

const resetForm = () => {
    errorMessage.value = '';
    customerQuery.value = '';
    customerResults.value = [];
    selectedCustomer.value = null;
    selectedVariantIds.value = new Set();
    includeAssetLevel.value = false;
};

const loadOptions = async () => {
    loadingOptions.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get(route('assets.spec-sheets.send-options', props.assetId));
        hasVariants.value = !!data?.has_variants;
        variants.value = data?.variants ?? [];
        if (!hasVariants.value) {
            includeAssetLevel.value = false;
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not load send options.';
    } finally {
        loadingOptions.value = false;
    }
};

watch(
    () => props.show,
    (open) => {
        if (open) {
            resetForm();
            loadOptions();
        }
    },
);

watch(customerQuery, (q) => {
    clearTimeout(searchTimer);
    const term = typeof q === 'string' ? q.trim() : '';
    if (term.length < 2) {
        customerResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        searchingCustomers.value = true;
        try {
            const { data } = await axios.get(route('records.lookup'), {
                params: { type: 'customer', search: term, per_page: 15 },
            });
            customerResults.value = data?.records ?? [];
        } catch {
            customerResults.value = [];
        } finally {
            searchingCustomers.value = false;
        }
    }, 250);
});

const pickCustomer = (row) => {
    selectedCustomer.value = row;
    customerQuery.value = row.display_name || `#${row.id}`;
    customerResults.value = [];
};

const toggleVariant = (id) => {
    const next = new Set(selectedVariantIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedVariantIds.value = next;
};

const canSubmit = computed(() => {
    if (!selectedCustomer.value?.id) return false;
    if (!hasVariants.value) return true;
    return includeAssetLevel.value || selectedVariantIds.value.size > 0;
});

const submit = async () => {
    if (!canSubmit.value || sending.value) return;
    errorMessage.value = '';
    sending.value = true;
    try {
        await axios.post(route('assets.spec-sheets.send', props.assetId), {
            customer_profile_id: selectedCustomer.value.id,
            variant_ids: Array.from(selectedVariantIds.value),
            include_asset_level: hasVariants.value ? includeAssetLevel.value : false,
        });
        emit('sent');
        emit('close');
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not send specification sheets.';
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">Send specification sheets</h3>
            <p class="mt-1 text-sm text-gray-500">
                Email portal links to a customer. Activity is logged on the contact record.
            </p>

            <div v-if="loadingOptions" class="mt-6 text-sm text-gray-500">Loading…</div>

            <div v-else class="mt-6 space-y-5">
                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <input
                        v-model="customerQuery"
                        type="search"
                        autocomplete="off"
                        placeholder="Search by name, email, or ID…"
                        class="mt-0 block w-full rounded-lg border-gray-300 text-base sm:text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 min-h-[2.5rem]"
                        @keydown.enter.prevent
                    />
                    <div
                        v-if="customerResults.length"
                        class="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                    >
                        <button
                            v-for="row in customerResults"
                            :key="row.id"
                            type="button"
                            class="flex w-full px-3 py-2 text-left text-base sm:text-sm text-gray-800 hover:bg-gray-50"
                            @click="pickCustomer(row)"
                        >
                            {{ row.display_name || `Customer #${row.id}` }}
                        </button>
                    </div>
                    <p v-if="searchingCustomers" class="mt-1 text-xs text-gray-400">Searching…</p>
                    <p v-if="selectedCustomer" class="mt-2 text-xs text-gray-600">
                        Selected customer profile ID {{ selectedCustomer.id }}
                    </p>
                </div>

                <div v-if="hasVariants && variants.length">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Specifications to include</label>
                    <label class="flex items-start gap-2 mb-3 text-base sm:text-sm">
                        <input v-model="includeAssetLevel" type="checkbox" class="rounded border-gray-300 mt-0.5" />
                        <span>Include base asset specifications (no variant)</span>
                    </label>
                    <div class="space-y-2 max-h-40 overflow-y-auto rounded-lg border border-gray-100 p-3">
                        <label
                            v-for="v in variants"
                            :key="v.id"
                            class="flex items-start gap-2 text-base sm:text-sm cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 mt-0.5"
                                :checked="selectedVariantIds.has(v.id)"
                                @change="toggleVariant(v.id)"
                            />
                            <span>{{ v.label }}</span>
                        </label>
                    </div>
                </div>

                <div v-else-if="hasVariants && !variants.length" class="text-sm text-amber-700">
                    This asset has variants enabled but no active variants. Add variants before sending.
                </div>

                <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
            </div>

            <div class="mt-8 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                    :disabled="!canSubmit || sending || loadingOptions"
                    @click="submit"
                >
                    {{ sending ? 'Sending…' : 'Send email' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
