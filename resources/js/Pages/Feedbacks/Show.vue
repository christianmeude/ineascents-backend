<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    feedback: Object,
});

const renderStars = (count) => '★'.repeat(count || 0) + '☆'.repeat(5 - (count || 0));

const formatDate = (dateString) => {
    if (!dateString) return '—';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
};
</script>

<template>
    <Head title="Feedback Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between border-b border-brand-primary/10 dark:border-brand-dark-border pb-4 mb-4">
                <div>
                    <h2 class="text-3xl font-semibold text-brand-primary dark:text-brand-cream">
                        {{ renderStars(feedback.stars) }} {{ feedback.stars }}/5
                    </h2>
                    <p class="text-brand-muted dark:text-brand-cream/70 text-sm mt-1">
                        {{ feedback.user?.name || feedback.user?.email || 'Unknown user' }} · {{ formatDate(feedback.created_at) }}
                    </p>
                </div>
                <BackLink :href="route('admin.feedbacks.index')" label="Feedback" />
            </div>
        </template>

        <div class="py-2">
            <div class="mx-auto max-w-3xl space-y-6">
                <div class="bg-white dark:bg-brand-dark-surface rounded-3xl p-6 sm:p-8 shadow-ambient dark:shadow-none border border-brand-primary/10 dark:border-brand-dark-border">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Stars</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">
                                <span class="text-amber-500 tracking-widest">{{ renderStars(feedback.stars) }}</span>
                                <span class="ml-2">{{ feedback.stars }} out of 5</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Date</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">{{ formatDate(feedback.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">User</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">{{ feedback.user?.name || '—' }} {{ feedback.user?.email ? `· ${feedback.user.email}` : '' }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Booking</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">
                                {{ feedback.booking?.booking_reference || 'No linked booking' }}
                                <span v-if="feedback.booking?.status" class="text-brand-muted dark:text-brand-cream/70">· Status: {{ feedback.booking.status }}</span>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Feedback text</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream whitespace-pre-wrap">{{ feedback.text || 'No written feedback.' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
