<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    scents: {
        type: Array,
        required: true,
    },
});

const toggleScent = (scent) => {
    router.post(route('admin.scents.toggle', scent.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Scents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between border-b border-brand-primary/10 dark:border-brand-dark-border pb-4 mb-4">
                <div>
                    <h2 class="text-3xl font-semibold text-brand-primary dark:text-brand-cream">
                        Scents
                    </h2>
                    <p class="text-brand-muted dark:text-brand-cream/70 text-sm mt-1">
                        Toggle availability of the promo scents.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-2">
            <div class="mx-auto max-w-7xl">
                <div v-if="$page.props.flash?.success" class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                  <span class="block sm:inline text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <div class="bg-white dark:bg-brand-dark-surface rounded-3xl p-6 sm:p-8 shadow-ambient dark:shadow-none border border-brand-primary/10 dark:border-brand-dark-border">
                    <h3 class="text-2xl font-semibold text-brand-primary dark:text-brand-cream mb-6">Promo Scents</h3>

                    <div v-if="scents.length === 0" class="text-center py-12 text-brand-muted dark:text-brand-cream/70">
                        <p>No scents found.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="scent in scents" :key="scent.id" class="border border-brand-primary/10 dark:border-brand-dark-border rounded-2xl overflow-hidden flex flex-col relative">

                            <div class="h-40 bg-brand-cream dark:bg-brand-dark-base flex items-center justify-center relative overflow-hidden">
                                <img v-if="scent.image_url" :src="scent.image_url.startsWith('http') ? scent.image_url : '/storage/' + scent.image_url" :alt="scent.name" class="w-full h-full object-cover" />
                                <span v-else class="text-brand-primary dark:text-brand-cream/40 font-medium italic">Image Placeholder</span>
                                <span
                                    class="absolute top-3 left-3 text-xs font-semibold px-3 py-1 rounded-full"
                                    :class="scent.is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ scent.is_available ? 'Available' : 'Unavailable' }}
                                </span>
                            </div>

                            <div class="p-5 flex flex-col flex-1 bg-white dark:bg-brand-dark-surface">
                                <p class="text-xs font-medium uppercase tracking-wide text-brand-muted dark:text-brand-cream/70">{{ scent.category }}</p>
                                <h4 class="font-semibold text-brand-primary dark:text-brand-cream text-base mt-1">{{ scent.name }}</h4>

                                <div class="mt-auto pt-4 flex items-center justify-end">
                                    <button
                                        @click="toggleScent(scent)"
                                        class="text-xs font-medium px-6 py-2 rounded-full transition-opacity hover:opacity-90"
                                        :class="scent.is_available ? 'bg-red-500 text-white' : 'bg-brand-primary text-white'"
                                    >
                                        {{ scent.is_available ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
