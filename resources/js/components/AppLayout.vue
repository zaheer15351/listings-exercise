<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});

const page = usePage();

const nav = [
    { href: '/', label: 'Listings', component: 'Listings/' },
    { href: '/saved-searches', label: 'Saved searches', component: 'SavedSearches/' },
];
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    Street Listings
                </Link>

                <nav class="flex gap-4 text-sm">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="transition hover:text-slate-900"
                        :class="page.component.startsWith(item.component) ? 'font-medium text-slate-900' : 'text-slate-500'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-slate-500">{{ subheading }}</p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
