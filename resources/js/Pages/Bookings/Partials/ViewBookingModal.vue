<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: Boolean,
    booking: Object
});

const emit = defineEmits(['close']);

// Keyboard accessibility: close on Escape
const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const formattedDate = computed(() => {
    if (!props.booking?.event_date) return '';
    const options = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
    return new Date(props.booking.event_date).toLocaleDateString('en-US', options);
});

const formattedPrice = computed(() => {
    if (props.booking?.total_price == null) return '₱0.00';
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP' 
    }).format(props.booking.total_price);
});
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="2xl">
        <div class="bg-brand-cream dark:bg-brand-dark-surface flex flex-col max-h-full">
            <!-- Dossier Header -->
            <div class="px-8 pt-8 pb-6 bg-white dark:bg-brand-dark-base border-b border-brand-primary/10 dark:border-brand-dark-border flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div>
                    <h2 class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Booking Reference</h2>
                    <div class="text-3xl font-black text-brand-primary dark:text-brand-cream tracking-tight">{{ booking?.booking_reference }}</div>
                </div>
                <div class="flex items-center gap-3 self-start">
                    <span :class="{
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/10 dark:text-yellow-400 dark:border-yellow-500/20': booking?.status?.toLowerCase() === 'pending',
                        'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400 dark:border-green-500/20': booking?.status?.toLowerCase() === 'confirmed',
                        'bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-gray-300 dark:border-white/20': booking?.status?.toLowerCase() === 'completed',
                        'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20': booking?.status?.toLowerCase() === 'cancelled'
                    }" class="px-4 py-1.5 border border-transparent rounded-full text-xs font-black uppercase tracking-widest">{{ booking?.status }}</span>
                    
                    <button @click="emit('close')" class="p-2 rounded-full bg-brand-primary/5 hover:bg-brand-primary/10 dark:bg-white/5 dark:hover:bg-white/10 text-brand-primary dark:text-brand-cream transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Dossier Body -->
            <div class="p-8 overflow-y-auto" v-if="booking">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-10">
                    
                    <!-- Customer Info Chunk -->
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-8 p-6 rounded-2xl bg-white dark:bg-brand-dark-base shadow-sm border border-brand-primary/5 dark:border-brand-dark-border">
                        <div>
                            <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-2">Customer</dt>
                            <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ booking.customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-2">Pax (Guests)</dt>
                            <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ booking.pax || 'N/A' }}</dd>
                        </div>
                    </div>

                    <!-- Event Details Chunk -->
                    <div class="flex flex-col gap-2">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Event Date</dt>
                        <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ formattedDate }}</dd>
                    </div>
                    <div class="flex flex-col gap-2">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Venue / Address</dt>
                        <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream leading-snug">{{ booking.venue_address || 'N/A' }}</dd>
                    </div>

                    <!-- Divider -->
                    <div class="sm:col-span-2 h-px bg-brand-primary/10 dark:bg-brand-dark-border my-2"></div>

                    <!-- Financials Chunk -->
                    <div class="flex flex-col gap-2">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Package Selected</dt>
                        <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ booking.package?.name || 'N/A' }}</dd>
                    </div>
                    <div class="flex flex-col gap-2 sm:text-right">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Total Amount Paid</dt>
                        <dd class="text-3xl font-black text-brand-primary dark:text-brand-cream tracking-tight">{{ formattedPrice }}</dd>
                    </div>

                    <!-- A18: provable privacy consent -->
                    <div class="sm:col-span-2 h-px bg-brand-primary/10 dark:bg-brand-dark-border my-2"></div>
                    <div class="flex flex-col gap-2">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Privacy consent</dt>
                        <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ booking.consent_privacy_version ?? 'Not recorded (pre-policy)' }}</dd>
                    </div>
                    <div class="flex flex-col gap-2 sm:text-right" v-if="booking.consented_at">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Consented at</dt>
                        <dd class="text-lg font-medium text-brand-primary dark:text-brand-cream">{{ booking.consented_at }}</dd>
                    </div>

                    <!-- Linked customer feedback (read-only) -->
                    <div v-if="booking.feedbacks?.length" class="sm:col-span-2 h-px bg-brand-primary/10 dark:bg-brand-dark-border my-2"></div>
                    <div v-if="booking.feedbacks?.length" class="sm:col-span-2 flex flex-col gap-2">
                        <dt class="text-xs font-bold tracking-widest uppercase text-brand-muted dark:text-brand-cream/50 mb-1">Customer feedback</dt>
                        <dd class="flex flex-col gap-3">
                            <div v-for="fb in booking.feedbacks" :key="fb.id" class="rounded-xl bg-white dark:bg-brand-dark-base border border-brand-primary/5 dark:border-brand-dark-border p-4">
                                <span class="text-amber-500 tracking-widest text-sm">{{ '★'.repeat(fb.stars) }}{{ '☆'.repeat(5 - fb.stars) }}</span>
                                <span class="ml-2 text-sm font-medium text-brand-primary dark:text-brand-cream">{{ fb.stars }}/5</span>
                                <p v-if="fb.text" class="mt-1 text-sm text-brand-primary dark:text-brand-cream whitespace-pre-wrap">{{ fb.text }}</p>
                            </div>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </Modal>
</template>
