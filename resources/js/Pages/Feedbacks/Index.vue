<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    feedbacks: Object,
    filters: Object,
});

const stars = ref(props.filters?.stars || '');

watch(stars, (value) => {
    router.get(
        route('admin.feedbacks.index'),
        { stars: value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
});

const formatDate = (dateString) => {
    if (!dateString) return '—';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

const renderStars = (count) => '★'.repeat(count) + '☆'.repeat(5 - count);

const columns = [
    { key: 'stars', label: 'Stars' },
    { key: 'text', label: 'Feedback' },
    { key: 'user', label: 'User', hideBelow: 'hidden md:table-cell' },
    { key: 'booking', label: 'Booking' },
    { key: 'date', label: 'Date' },
    { key: 'action', label: 'Action' },
];
</script>

<template>
    <Head title="Feedback" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between border-b border-brand-primary/10 dark:border-brand-dark-border pb-4 mb-4">
                <div>
                    <h2 class="text-3xl font-semibold text-brand-primary dark:text-brand-cream">
                        Feedback
                    </h2>
                    <p class="text-brand-muted dark:text-brand-cream/70 text-sm mt-1">
                        Read-only inbox of customer ratings linked to bookings.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-2">
            <div class="mx-auto max-w-7xl">
                <div class="bg-white dark:bg-brand-dark-surface rounded-3xl p-6 sm:p-8 border border-brand-primary/10 dark:border-brand-dark-border">
                    <DataTable
                        :columns="columns"
                        :items="feedbacks.data"
                        :links="feedbacks.links"
                        empty-text="No feedback found."
                    >
                        <template #filters>
                            <select
                                v-model="stars"
                                class="pl-4 pr-10 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm text-brand-primary dark:text-brand-cream bg-white dark:bg-brand-dark-surface focus:outline-none focus:border-brand-primary"
                            >
                                <option value="">All stars</option>
                                <option value="5">5 stars</option>
                                <option value="4">4 stars</option>
                                <option value="3">3 stars</option>
                                <option value="2">2 stars</option>
                                <option value="1">1 star</option>
                            </select>
                        </template>

                        <template #row="{ item: feedback }">
                            <td class="py-4 px-4 font-medium text-brand-primary dark:text-brand-cream whitespace-nowrap" :title="`${feedback.stars} out of 5 stars`">
                                <span class="text-amber-500 tracking-widest">{{ renderStars(feedback.stars) }}</span>
                                <span class="ml-2 text-xs text-brand-muted dark:text-brand-cream/60">{{ feedback.stars }}/5</span>
                            </td>
                            <td class="py-4 px-4 font-medium text-brand-primary dark:text-brand-cream max-w-xs truncate">
                                {{ feedback.text || '—' }}
                            </td>
                            <td class="py-4 px-4 font-medium text-brand-primary dark:text-brand-cream hidden md:table-cell">
                                {{ feedback.user?.name || feedback.user?.email || '—' }}
                            </td>
                            <td class="py-4 px-4 font-medium text-brand-primary dark:text-brand-cream whitespace-nowrap">
                                {{ feedback.booking?.booking_reference || '—' }}
                                <span v-if="feedback.booking?.status" class="ml-1 text-xs text-brand-muted dark:text-brand-cream/60">· {{ feedback.booking.status }}</span>
                            </td>
                            <td class="py-4 px-4 font-medium text-brand-primary dark:text-brand-cream whitespace-nowrap">
                                {{ formatDate(feedback.created_at) }}
                            </td>
                            <td class="py-4 px-4">
                                <Link :href="route('admin.feedbacks.show', feedback.id)" class="text-cyan-500 font-medium hover:text-cyan-600 transition-colors whitespace-nowrap">
                                    View Details
                                </Link>
                            </td>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
