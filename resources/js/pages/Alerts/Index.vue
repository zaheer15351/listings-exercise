<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate, formatPrice } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});

// The digest skips listings that left the market, so an unsent alert for one
// will never be emailed.
function delivery(alert) {
    if (alert.sent_at) {
        return `Emailed ${formatDate(alert.sent_at)}`;
    }

    return alert.listing_is_live ? 'In your next daily email' : 'Not emailed';
}
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that matched your saved searches.">
        <ul v-if="alerts.data.length" class="space-y-3">
            <li
                v-for="alert in alerts.data"
                :key="alert.id"
                class="rounded-xl border border-slate-200 bg-white p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link
                            v-if="alert.listing_is_live"
                            :href="`/listings/${alert.listing.id}`"
                            class="text-lg font-bold underline-offset-2 hover:underline"
                        >
                            {{ formatPrice(alert.listing.price) }}
                        </Link>
                        <span v-else class="text-lg font-bold text-slate-400">
                            {{ formatPrice(alert.listing.price) }}
                        </span>

                        <p class="text-slate-900">
                            {{ alert.listing.address_line_1 }}, {{ alert.listing.city }}
                        </p>
                        <p class="text-sm text-slate-600">
                            {{ alert.listing.bedrooms }} bed &middot; {{ alert.listing.property_type_label }}
                            &middot; {{ alert.listing.branch.region }}
                        </p>
                    </div>

                    <div class="text-right text-xs text-slate-500">
                        <p>{{ delivery(alert) }}</p>
                        <p v-if="!alert.listing_is_live" class="mt-1 font-medium text-amber-700">
                            No longer available ({{ alert.listing_status_label }})
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-400">Matched: {{ alert.saved_search }}</p>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. We add one here when a new listing matches one of your
            <Link href="/saved-searches" class="underline underline-offset-2">saved searches</Link>.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
