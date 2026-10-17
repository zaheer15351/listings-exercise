<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    // The filters the current results were run with, not the unsubmitted form,
    // so what gets saved is exactly what the buyer is looking at.
    filters: { type: Object, required: true },
    frequencies: { type: Array, required: true },
});

const criteria = ['property_type', 'region', 'min_bedrooms', 'min_price', 'max_price'];

const applied = computed(() =>
    Object.fromEntries(criteria.filter((key) => props.filters[key]).map((key) => [key, props.filters[key]])),
);

const hasCriteria = computed(() => Object.keys(applied.value).length > 0);

const form = useForm({ frequency: 'daily' });

function save() {
    form.transform((data) => ({ ...applied.value, ...data })).post('/saved-searches', {
        preserveScroll: true,
    });
}
</script>

<template>
    <form
        class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4"
        @submit.prevent="save"
    >
        <div class="flex flex-col gap-1">
            <label for="frequency" class="text-xs font-medium text-slate-600">Email me</label>
            <select
                id="frequency"
                v-model="form.frequency"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
            >
                <option v-for="option in frequencies" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <button
            type="submit"
            :disabled="!hasCriteria || form.processing"
            class="rounded-lg border border-slate-900 px-5 py-2 text-sm font-medium text-slate-900 transition hover:bg-slate-900 hover:text-white disabled:opacity-40"
        >
            Save this search
        </button>

        <p v-if="!hasCriteria" class="text-sm text-slate-500">
            Search with at least one filter to save it and get alerts.
        </p>

        <ul v-if="Object.keys(form.errors).length" class="w-full text-sm text-red-600">
            <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
        </ul>
    </form>
</template>
