<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import BackLink from '@/Components/BackLink.vue';

const props = defineProps({
    inquiry: Object,
    packages: {
        type: Array,
        default: () => [],
    },
});

const statusOrder = { new: 0, contacted: 1, booked: 2, closed: 3 };
const nextStatuses = ['contacted', 'closed'].filter(
    (s) => statusOrder[s] > statusOrder[props.inquiry.status]
);

const form = useForm({
    status: props.inquiry.status,
    archived: !!props.inquiry.archived,
});

const submit = () => {
    form.put(route('admin.inquiries.update', props.inquiry.id));
};

const promoteForm = useForm({
    customer_name: props.inquiry.name ?? '',
    customer_email: props.inquiry.email ?? '',
    customer_phone: props.inquiry.phone ?? '',
    package_id: '',
    pax: 50,
    event_date: props.inquiry.event_date || '',
    event_time: '',
    venue_address: '',
    payment_method: 'cash',
    notes: '',
});

// One-product world: preselect the sole package; >1 keeps the placeholder.
if (props.packages?.length === 1) {
    promoteForm.package_id = props.packages[0].id;
}

const promote = () => {
    promoteForm.post(route('admin.inquiries.promote', props.inquiry.id));
};

const formatDate = (dateString) => {
    if (!dateString) return 'No date given';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
};
</script>

<template>
    <Head title="Inquiry Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between border-b border-brand-primary/10 dark:border-brand-dark-border pb-4 mb-4">
                <div>
                    <h2 class="text-3xl font-semibold text-brand-primary dark:text-brand-cream">
                        {{ inquiry.name }}
                    </h2>
                    <p class="text-brand-muted dark:text-brand-cream/70 text-sm mt-1">
                        {{ inquiry.email }} · {{ inquiry.phone }}
                    </p>
                </div>
                <BackLink :href="route('admin.inquiries.index')" label="Inquiries" />
            </div>
        </template>

        <div class="py-2">
            <div class="mx-auto max-w-3xl space-y-6">
                <div class="bg-white dark:bg-brand-dark-surface rounded-3xl p-6 sm:p-8 shadow-ambient dark:shadow-none border border-brand-primary/10 dark:border-brand-dark-border">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm mb-8">
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Event Date</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">{{ formatDate(inquiry.event_date) }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Status</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream capitalize">{{ inquiry.status }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Details</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream whitespace-pre-wrap">{{ inquiry.message }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Privacy consent</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">{{ inquiry.consent_privacy_version ?? 'Not recorded (pre-policy)' }}</dd>
                        </div>
                        <div v-if="inquiry.consented_at">
                            <dt class="font-bold text-brand-primary dark:text-brand-cream text-xs uppercase">Consented at</dt>
                            <dd class="mt-1 text-brand-primary dark:text-brand-cream">{{ inquiry.consented_at }}</dd>
                        </div>
                    </dl>

                    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-4">
                        <div v-if="nextStatuses.length">
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Move status</label>
                            <select
                                v-model="form.status"
                                class="pl-4 pr-10 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary"
                            >
                                <option :value="inquiry.status">{{ inquiry.status }} (current)</option>
                                <option v-for="s in nextStatuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <div v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
                        </div>
                        <p v-else-if="!nextStatuses.length" class="text-sm text-brand-muted dark:text-brand-cream/70 pb-2">
                            Terminal state — status locked, archive toggle still available.
                        </p>
                        <label class="inline-flex items-center gap-2 text-sm text-brand-primary dark:text-brand-cream pb-2">
                            <input type="checkbox" v-model="form.archived" class="rounded">
                            Archived
                        </label>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-full bg-brand-primary px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-primary/90 disabled:opacity-50 transition-all"
                        >
                            Save
                        </button>
                    </form>
                </div>

                <div v-if="inquiry.status === 'contacted'" class="bg-white dark:bg-brand-dark-surface rounded-3xl p-6 sm:p-8 shadow-ambient dark:shadow-none border border-brand-primary/10 dark:border-brand-dark-border">
                    <h3 class="text-xl font-semibold text-brand-primary dark:text-brand-cream">Promote to Booking</h3>
                    <p v-if="!inquiry.event_date" class="text-sm text-brand-muted dark:text-brand-cream/70 mt-1">No date on this Inquiry — set an Available date to promote.</p>

                    <form @submit.prevent="promote" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Customer name</label>
                            <input v-model="promoteForm.customer_name" type="text" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.customer_name" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.customer_name }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Customer email</label>
                            <input v-model="promoteForm.customer_email" type="email" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.customer_email" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.customer_email }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Customer phone</label>
                            <input v-model="promoteForm.customer_phone" type="text" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.customer_phone" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.customer_phone }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Package</label>
                            <select v-model="promoteForm.package_id" class="w-full pl-4 pr-10 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary">
                                <option value="" disabled>Select package</option>
                                <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <div v-if="promoteForm.errors.package_id" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.package_id }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Pax</label>
                            <select v-model="promoteForm.pax" class="w-full pl-4 pr-10 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary">
                                <option value="" disabled>Select pax</option>
                                <option :value="50">50</option>
                                <option :value="70">70</option>
                                <option :value="100">100</option>
                                <option :value="150">150</option>
                            </select>
                            <div v-if="promoteForm.errors.pax" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.pax }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Event date</label>
                            <input v-model="promoteForm.event_date" type="date" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.event_date" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.event_date }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Event time</label>
                            <input v-model="promoteForm.event_time" type="time" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.event_time" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.event_time }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Venue address</label>
                            <input v-model="promoteForm.venue_address" type="text" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary" />
                            <div v-if="promoteForm.errors.venue_address" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.venue_address }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Payment method</label>
                            <div class="flex items-center gap-4 text-sm text-brand-primary dark:text-brand-cream pt-2">
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="promoteForm.payment_method" type="radio" value="cash" class="rounded" />
                                    Cash
                                </label>
                            </div>
                            <div v-if="promoteForm.errors.payment_method" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.payment_method }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase text-brand-primary dark:text-brand-cream mb-1">Notes</label>
                            <textarea v-model="promoteForm.notes" rows="3" class="w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-sm bg-white dark:bg-brand-dark-surface text-brand-primary dark:text-brand-cream focus:outline-none focus:border-brand-primary"></textarea>
                            <div v-if="promoteForm.errors.notes" class="text-red-500 text-xs mt-1">{{ promoteForm.errors.notes }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="promoteForm.processing"
                                class="rounded-full bg-brand-primary px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-primary/90 disabled:opacity-50 transition-all"
                            >
                                Promote to Booking
                            </button>
                        </div>
                    </form>
                </div>
                <p v-else-if="inquiry.status === 'booked'" class="text-sm text-brand-muted dark:text-brand-cream/70">Promoted — status pinned.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
