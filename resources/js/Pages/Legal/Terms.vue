<script setup>
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    version: String,
    effectiveDate: String,
    requestedVersion: { type: String, default: null },
    contactEmail: String,
    appName: String,
});
</script>

<template>
    <GuestLayout wide>
        <Head title="Terms of Service" />

        <div class="bg-white dark:bg-brand-dark-surface rounded-2xl p-6 sm:p-8 shadow-ambient dark:shadow-none">
            <p class="text-sm text-brand-muted dark:text-brand-cream/70">Terms of Service</p>
            <h1 class="text-2xl font-medium text-brand-primary dark:text-brand-cream mt-1">Booking with {{ appName }}</h1>
            <p class="text-sm text-brand-muted dark:text-brand-cream/70 mt-2">
                Version {{ version }} · Effective {{ effectiveDate }}
            </p>

            <p v-if="requestedVersion && requestedVersion !== version"
               class="mt-4 rounded-xl bg-brand-primary/10 text-brand-primary dark:text-brand-cream text-sm p-3">
                You agreed to version {{ requestedVersion }}. This page always shows the current version ({{ version }}).
            </p>

            <div class="mt-6 space-y-5 text-[15px] leading-relaxed text-brand-primary/90 dark:text-brand-cream/90">
                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">1. The service</h2>
                    <p>We set up a perfume bar at your event location on your booked date. Scent choices, inclusions, and complimentary items follow the offering and headcount choice shown at booking time.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">2. One booking per day</h2>
                    <p>Our physical bar serves one event per calendar day. A date is yours once your booking is confirmed; unavailable dates cannot be booked.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">3. Payment</h2>
                    <p>Pay online through the PayMongo checkout link sent after booking, or choose cash, which our team confirms directly. A booking stays pending until payment or confirmation is complete.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">4. Changes and cancellation</h2>
                    <p>Contact us at {{ contactEmail }} to move or cancel a booking. Paid bookings cancelled by us are refunded through the original payment channel.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">5. Your details</h2>
                    <p>Give accurate contact details so we can reach you about your event. How we store and protect them is described in our <Link :href="route('privacy', { v: version })" class="underline">Privacy Policy</Link>.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">6. Fair use</h2>
                    <p>Do not misuse the booking system (for example, holding dates you do not intend to use). We may cancel abusive bookings.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">7. Contact</h2>
                    <p>Questions about these terms: {{ contactEmail }}.</p>
                </section>
            </div>

            <div class="mt-8 flex gap-4 text-sm">
                <Link :href="route('privacy', { v: version })" class="text-brand-primary dark:text-brand-cream underline">Privacy Policy</Link>
                <Link :href="route('login')" class="text-brand-primary dark:text-brand-cream underline">Admin login</Link>
            </div>
        </div>
    </GuestLayout>
</template>
