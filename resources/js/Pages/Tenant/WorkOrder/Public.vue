<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true
    },
    recordType: {
        type: String,
        default: 'WorkOrder'
    },
    fieldsSchema: {
        type: Object,
        default: () => ({})
    },
    formSchema: {
        type: Object,
        default: null
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const pluralTitle = computed(() => {
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: pluralTitle.value, href: route('workorders.index') },
        { label: props.record.work_order_number || 'Print View', href: route('workorders.show', props.record.id) },
        { label: 'Print View' },
    ];
});

// Helper functions
const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const formatDateTime = (value) => {
    if (!value) return '—';

    try {
        let date;
        if (typeof value === 'string') {
            date = new Date(value);
        } else if (value instanceof Date) {
            date = value;
        } else {
            return '—';
        }

        if (isNaN(date.getTime())) return '—';

        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }).format(date);
    } catch (error) {
        console.warn('Date formatting error:', error, value);
        return '—';
    }
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '$0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value);
};

// Sample data - replace with actual props.record data in production
const workOrder = computed(() => ({
    number: props.record.work_order_number || 'WO-2024-1234',
    status: props.record.status || 'In Progress',
    type: props.record.type || 'Preventive Maintenance',
    priority: props.record.priority || 'High',
    date: formatDateTime(props.record.created_at) || 'February 11, 2026',
    scheduled_start: formatDateTime(props.record.scheduled_start_at) || 'Feb 11, 2026 at 9:00 AM',
    due_date: formatDateTime(props.record.due_at) || 'Feb 11, 2026 at 5:00 PM',
    po_number: props.record.po_number || 'PO-2024-456',
}));

const company = computed(() => ({
    name: props.account?.name || 'ACME Service Company',
    address: props.account?.address_line_1 || '123 Industrial Parkway, Suite 100',
    city: props.account?.city ? `${props.account.city}, ${props.account.state} ${props.account.zip}` : 'Milwaukee, WI 53202',
    phone: props.account?.phone || '(414) 555-0123',
    email: props.account?.email || 'service@acmeservice.com',
    license: props.account?.license_number || 'HVAC-12345-WI',
}));

const customer = computed(() => ({
    name: props.record.customer?.display_name || 'Johnson Manufacturing Inc.',
    contact: props.record.customer?.primary_contact || 'Sarah Johnson',
    address: props.record.customer?.address_line_1 || '456 Commerce Drive',
    city: props.record.customer?.city ? `${props.record.customer.city}, ${props.record.customer.state} ${props.record.customer.zip}` : 'Milwaukee, WI 53211',
    phone: props.record.customer?.phone || '(414) 555-7890',
    email: props.record.customer?.email || 'sjohnson@johnsonmfg.com',
}));

const serviceLocation = computed(() => ({
    name: props.record.location?.display_name || props.record.asset_unit?.display_name || 'Plant 2 - East Building',
    address: props.record.location?.address_line_1 || '789 Manufacturing Blvd',
    city: props.record.location?.city ? `${props.record.location.city}, ${props.record.location.state} ${props.record.location.zip}` : 'Milwaukee, WI 53218',
    asset: props.record.asset_unit?.display_name || 'Rooftop HVAC Unit #4',
    model: props.record.asset_unit?.model || 'Carrier 50TCA12',
    serial: props.record.asset_unit?.serial_number || '1234567890',
}));

// Calculation functions
const calculateSubtotal = () => {
    if (!props.record.line_items || props.record.line_items.length === 0) {
        return 0;
    }
    return props.record.line_items.reduce((sum, item) => {
        return sum + ((item.quantity || 0) * (item.unit_price || 0));
    }, 0);
};

const calculateTotal = () => {
    const subtotal = calculateSubtotal();
    const tax = props.record.tax_amount || 0;
    return subtotal + tax;
};

</script>


<template>
    <Head :title="`${pluralTitle} - ${record.work_order_number || record.display_name}`" />

    <TenantLayout>
        <!-- Print Styles -->
  <!--  -->

        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ pluralTitle }} - Print View
                    </h2>

                    <div class="flex items-center space-x-2">
                        <Link :href="route('workorders.index')">
                            <button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back to List
                            </button>
                        </Link>
                        <Link :href="route('workorders.show', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">visibility</span>
                                View Details
                            </button>
                        </Link>
                        <Link :href="route('workorders.edit', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button
                            @click="window.print()"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                        >
                            <span class="material-icons text-sm">print</span>
                            Print
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Work Order Document -->
        <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden p-8">
            <!-- Company Header -->
            <div class="border-b-4 border-blue-600 pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <!-- Company Logo & Info -->
                    <div class="flex items-start gap-4">
                        <!-- Logo Placeholder -->
                        <div class="w-20 h-20 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-2xl font-bold">LOGO</span>
                        </div>

                        <!-- Company Details -->
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ company.name }}</h1>
                            <div class="text-sm text-gray-600 space-y-0.5">
                                <p>{{ company.address }}</p>
                                <p>{{ company.city }}</p>
                                <p>Phone: {{ company.phone }}</p>
                                <p>Email: {{ company.email }}</p>
                                <p v-if="company.license">License #: {{ company.license }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Work Order Number & Date -->
                    <div class="text-right">
                        <div class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg mb-3">
                            <div class="text-xs font-medium uppercase tracking-wide">Work Order</div>
                            <div class="text-2xl font-bold">#{{ workOrder.number }}</div>
                        </div>
                        <div class="text-sm text-gray-600">
                            <p><strong>Date:</strong> {{ workOrder.date }}</p>
                            <p><strong>Status:</strong> <span class="text-green-600 font-semibold">{{ getEnumLabel('status', record.status) || workOrder.status }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer & Service Location -->
            <div class="grid grid-cols-2 gap-6 mb-6">
                <!-- Bill To -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 border-b border-gray-200 pb-2">
                        Bill To
                    </h3>
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-gray-900">{{ customer.name }}</p>
                        <p v-if="customer.contact" class="text-gray-600">Attn: {{ customer.contact }}</p>
                        <p class="text-gray-600">{{ customer.address }}</p>
                        <p class="text-gray-600">{{ customer.city }}</p>
                        <p v-if="customer.phone" class="text-gray-600">Phone: {{ customer.phone }}</p>
                        <p v-if="customer.email" class="text-gray-600">Email: {{ customer.email }}</p>
                    </div>
                </div>

                <!-- Service Location -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 border-b border-gray-200 pb-2">
                        Service Location
                    </h3>
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-gray-900">{{ serviceLocation.name }}</p>
                        <p class="text-gray-600">{{ serviceLocation.address }}</p>
                        <p class="text-gray-600">{{ serviceLocation.city }}</p>
                        <p v-if="serviceLocation.asset" class="text-gray-600 mt-3"><strong>Asset:</strong> {{ serviceLocation.asset }}</p>
                        <p v-if="serviceLocation.model" class="text-gray-600"><strong>Model:</strong> {{ serviceLocation.model }}</p>
                        <p v-if="serviceLocation.serial" class="text-gray-600"><strong>Serial:</strong> {{ serviceLocation.serial }}</p>
                    </div>
                </div>
            </div>

            <!-- Work Order Details -->
            <div class="mb-6">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                        Work Order Information
                    </h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Type:</span>
                            <span class="ml-2 font-semibold text-gray-900">{{ getEnumLabel('type', record.type) || workOrder.type }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Priority:</span>
                            <span class="ml-2 font-semibold text-orange-600">{{ getEnumLabel('priority', record.priority) || workOrder.priority }}</span>
                        </div>
                        <div v-if="record.assigned_user">
                            <span class="text-gray-600">Technician:</span>
                            <span class="ml-2 font-semibold text-gray-900">{{ record.assigned_user.display_name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Scheduled:</span>
                            <span class="ml-2 font-semibold text-gray-900">{{ workOrder.scheduled_start }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Due Date:</span>
                            <span class="ml-2 font-semibold text-gray-900">{{ workOrder.due_date }}</span>
                        </div>
                        <div v-if="workOrder.po_number">
                            <span class="text-gray-600">PO Number:</span>
                            <span class="ml-2 font-semibold text-gray-900">{{ workOrder.po_number }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description of Work -->
            <div class="mb-6">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 border-b border-gray-200 pb-2">
                    Description of Work Required
                </h3>
                <p class="text-sm text-gray-700 leading-relaxed">
                    {{ record.description || 'No description provided.' }}
                </p>
            </div>

            <!-- Service Items / Line Items -->
            <div class="mb-6">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                    Services & Materials
                </h3>
                <table class="w-full border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                Description
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                Qty
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                Rate
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="!record.line_items || record.line_items.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                No service items added
                            </td>
                        </tr>
                        <tr v-else v-for="(item, index) in record.line_items" :key="index">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium">{{ item.display_name || item.description }}</div>
                                <div v-if="item.description && item.display_name !== item.description" class="text-xs text-gray-500 mt-0.5">
                                    {{ item.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-900">{{ item.quantity || 0 }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">
                                {{ formatCurrency((item.quantity || 0) * (item.unit_price || 0)) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                Subtotal:
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">
                                {{ formatCurrency(calculateSubtotal()) }}
                            </td>
                        </tr>
                        <tr v-if="record.tax_amount">
                            <td colspan="3" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                Tax:
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">
                                {{ formatCurrency(record.tax_amount) }}
                            </td>
                        </tr>
                        <tr class="border-t-2 border-gray-300">
                            <td colspan="3" class="px-4 py-4 text-right text-base font-bold text-gray-900">
                                Total Amount Due:
                            </td>
                            <td class="px-4 py-4 text-right text-lg font-bold text-blue-600">
                                {{ formatCurrency(calculateTotal()) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Work Performed / Technician Notes -->
            <div class="mb-6" v-if="record.internal_notes">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 border-b border-gray-200 pb-2">
                    Work Performed / Technician Notes
                </h3>
                <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                    {{ record.internal_notes }}
                </div>
            </div>

            <!-- Time Summary -->
            <div class="mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="grid grid-cols-4 gap-4 text-sm">
                        <div>
                            <div class="text-xs text-gray-600 mb-1">Estimated Hours</div>
                            <div class="text-lg font-bold text-gray-900">{{ record.estimated_hours || '0' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600 mb-1">Actual Hours</div>
                            <div class="text-lg font-bold text-gray-900">{{ record.actual_hours || '0' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600 mb-1">Labor Cost</div>
                            <div class="text-lg font-bold text-gray-900">{{ formatCurrency(record.labor_cost) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600 mb-1">Parts Cost</div>
                            <div class="text-lg font-bold text-gray-900">{{ formatCurrency(record.parts_cost) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-2 gap-8 mb-6">
                <div class="border-t-2 border-gray-300 pt-4">
                    <div class="mb-12"></div>
                    <div class="text-sm">
                        <p class="font-semibold text-gray-900">Technician Signature</p>
                        <p class="text-gray-600 mt-1">Mike Rodriguez - Feb 11, 2026 at 2:30 PM</p>
                    </div>
                </div>
                <div class="border-t-2 border-gray-300 pt-4">
                    <div class="mb-12"></div>
                    <div class="text-sm">
                        <p class="font-semibold text-gray-900">Customer Signature</p>
                        <p class="text-gray-600 mt-1">Date: ___________________</p>
                    </div>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="border-t-2 border-gray-200 pt-4">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                    Terms & Conditions
                </h3>
                <div class="text-xs text-gray-600 space-y-1 leading-relaxed">
                    <p>• Payment is due upon completion of work unless otherwise agreed in writing.</p>
                    <p>• All work is guaranteed for 90 days from completion date for parts and labor.</p>
                    <p>• Customer is responsible for providing clear access to work areas.</p>
                    <p>• Additional charges may apply for work performed outside of normal business hours.</p>
                    <p>• A 1.5% monthly finance charge will be applied to accounts over 30 days past due.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    Thank you for your business! For questions about this work order, please contact us at (414) 555-0123
                </p>
            </div>
        </div>
    </TenantLayout>
</template>


<style scoped>
/* Additional print-specific styles */
@page {
    margin: 0.5in;
}

@media print {
    .bg-white {
        background-color: white !important;
    }
}


@media print {
    body {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    .no-print {
        display: none !important;
    }
    .page-break {
        page-break-after: always;
    }
}

</style>
