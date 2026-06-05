<script setup>
import SalesFlowConnector from '@/Components/Tenant/Sales/SalesFlowConnector.vue';
import SalesFlowStepCard from '@/Components/Tenant/Sales/SalesFlowStepCard.vue';
import {
    getSalesFlowStep,
    salesFlowCustomerPath,
    salesFlowLeadPath,
    salesFlowMainSpine,
    salesFlowSteps,
} from '@/data/salesFlowSteps';
import { computed, ref } from 'vue';

const expandedIds = ref(new Set());

const allStepIds = computed(() => Object.keys(salesFlowSteps));

function isExpanded(id) {
    return expandedIds.value.has(id);
}

function toggleStep(id) {
    const next = new Set(expandedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedIds.value = next;
}

function expandAll() {
    expandedIds.value = new Set(allStepIds.value);
}

function collapseAll() {
    expandedIds.value = new Set();
}

function stepsFromIds(ids) {
    return ids.map((id) => getSalesFlowStep(id)).filter(Boolean);
}

const leadPathSteps = stepsFromIds(salesFlowLeadPath);
const customerPathSteps = stepsFromIds(salesFlowCustomerPath);
const mainSpineSteps = stepsFromIds(salesFlowMainSpine);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Intake paths on the left feed into the quote-to-close pipeline on the right. Click a step to expand notes.
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="expandAll"
                >
                    Expand all
                </button>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="collapseAll"
                >
                    Collapse all
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-8 lg:items-start">
            <!-- Left: intake & qualification -->
            <section class="min-w-0 sm:p-6 sticky  top-[140px]" >
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50 ">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Intake &amp; qualification
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Both paths merge at Opportunity on the right. Dashed arrows mark optional steps.
                </p>

                <div class="mt-6 space-y-8 overflow-x-auto pb-2 lg:overflow-x-visible">
                    <div class="inline-flex min-w-max items-center lg:flex lg:min-w-0 lg:flex-wrap lg:gap-y-4">
                        <template v-for="(step, index) in leadPathSteps" :key="step.id">
                            <SalesFlowStepCard
                                :step="step"
                                :expanded="isExpanded(step.id)"
                                @toggle="toggleStep"
                            />
                            <SalesFlowConnector
                                v-if="index < leadPathSteps.length - 1"
                                :dashed="step.optional"
                            />
                        </template>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span class="h-px min-w-[2rem] flex-1 bg-gray-300 dark:bg-gray-600" />
                        <span class="shrink-0 font-medium uppercase tracking-wide">and / or</span>
                        <span class="h-px min-w-[2rem] flex-1 bg-gray-300 dark:bg-gray-600" />
                    </div>

                    <div class="inline-flex min-w-max items-center lg:flex lg:min-w-0 lg:flex-wrap lg:gap-y-4">
                        <template v-for="(step, index) in customerPathSteps" :key="`cust-${step.id}`">
                            <SalesFlowStepCard
                                :step="step"
                                :expanded="isExpanded(step.id)"
                                @toggle="toggleStep"
                            />
                            <SalesFlowConnector v-if="index < customerPathSteps.length - 1" />
                        </template>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lead may convert directly to Opportunity (skip Qualification). Contact can link straight to Opportunity when already qualified.
                    </p>
                </div>


                <p
                    class="mt-6 hidden items-center justify-end gap-2 text-sm font-medium text-primary-600 dark:text-primary-400 lg:flex"
                    aria-hidden="true"
                >
                    Merges into Opportunity
                    <span class="material-icons text-base">arrow_forward</span>
                </p>
            </div>

                
                <div class="flex flex-wrap gap-6 text-sm text-gray-500 dark:text-gray-400 mt-5">
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center">
                    <span class="h-0.5 w-6 bg-gray-400 dark:bg-gray-500" />
                    <span class="material-icons text-base">arrow_forward</span>
                </span>
                Intake path
            </span>
            <span class="flex items-center gap-2">
                <span class="inline-flex flex-col items-center">
                    <span class="w-0.5 h-4 bg-gray-400 dark:bg-gray-500" />
                    <span class="material-icons text-base">arrow_downward</span>
                </span>
                Quote to close
            </span>
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center">
                    <span class="w-6 border-t-2 border-dashed border-gray-400 dark:border-gray-500" />
                    <span class="material-icons text-base opacity-60">arrow_forward</span>
                </span>
                Optional step
            </span>
        </div>
            </section>

            <!-- Right: quote to close (vertical stack) -->
            <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6 sticky top-[140px]">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Quote to close
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    From opportunity through deal closed.
                </p>

                <div class="mx-auto mt-6 flex w-full max-w-md flex-col items-center">
                    <template v-for="(step, index) in mainSpineSteps" :key="step.id">
                        <SalesFlowStepCard
                            :step="step"
                            fluid
                            :expanded="isExpanded(step.id)"
                            @toggle="toggleStep"
                        />
                        <SalesFlowConnector
                            v-if="index < mainSpineSteps.length - 1"
                            vertical
                        />
                    </template>
                </div>
            </section>
        </div>


    </div>
</template>
