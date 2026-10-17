<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import { formatDate } from '../../format';

defineProps({
    savedSearches: { type: Array, required: true },
});

// Templates can't reach browser globals, so the link is built here.
function matchesUrl(savedSearch) {
    return `/?${new URLSearchParams(savedSearch.filters)}`;
}

function destroy(savedSearch) {
    if (confirm(`Stop alerts for "${savedSearch.summary}"?`)) {
        router.delete(`/saved-searches/${savedSearch.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="We email you when a new listing matches one of these.">
        <ul v-if="savedSearches.length" class="space-y-3">
            <li
                v-for="savedSearch in savedSearches"
                :key="savedSearch.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4"
            >
                <div>
                    <p class="font-medium">{{ savedSearch.summary }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ savedSearch.frequency_label }} &middot; saved {{ formatDate(savedSearch.created_at) }}
                    </p>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <Link
                        :href="matchesUrl(savedSearch)"
                        class="text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline"
                    >
                        View current matches
                    </Link>
                    <button
                        type="button"
                        class="text-red-600 underline-offset-2 hover:underline"
                        @click="destroy(savedSearch)"
                    >
                        Delete
                    </button>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Filter the
            <Link href="/" class="underline underline-offset-2">listings</Link>
            and choose "Save this search".
        </p>
    </AppLayout>
</template>
