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
        <Head title="Privacy Policy" />

        <div class="bg-white dark:bg-brand-dark-surface rounded-2xl p-6 sm:p-8 shadow-ambient dark:shadow-none">
            <p class="text-sm text-brand-muted dark:text-brand-cream/70">Privacy Policy</p>
            <h1 class="text-2xl font-medium text-brand-primary dark:text-brand-cream mt-1">How we handle your information</h1>
            <p class="text-sm text-brand-muted dark:text-brand-cream/70 mt-2">
                Version {{ version }} · Effective {{ effectiveDate }}
            </p>

            <p v-if="requestedVersion && requestedVersion !== version"
               class="mt-4 rounded-xl bg-brand-primary/10 text-brand-primary dark:text-brand-cream text-sm p-3">
                You agreed to version {{ requestedVersion }}. This page always shows the current version ({{ version }}).
            </p>

            <div class="mt-6 space-y-5 text-[15px] leading-relaxed text-brand-primary/90 dark:text-brand-cream/90">
                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">1. Who we are</h2>
                    <p>{{ appName }} provides a perfume bar service reservation system. For questions about this policy or your information, contact us at {{ contactEmail }}.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">2. What we collect</h2>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Booking contact details: name, email, phone, event date, time slot, headcount.</li>
                        <li>Inquiry details: name, email, phone, event date, message.</li>
                        <li>Account details: name and email when you register in the mobile app.</li>
                        <li>Payment references (booking reference and status). We never see or store card numbers — online payments are processed by PayMongo.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">3. Why we use it</h2>
                    <p>To take and confirm reservations, coordinate your event, process payments, respond to inquiries, and keep you informed about your booking. We do not sell your information and never share it except with the payment processors below.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">4. Payment processors</h2>
                    <p>Online payments go through PayMongo (cards, e-wallets such as GCash). Cash payments are confirmed by our team directly. Raw payment data stays with the processor.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">5. How long we keep it</h2>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Inquiries with no activity: 24 months, then anonymized.</li>
                        <li>Bookings: 36 months, then anonymized.</li>
                        <li>Payment webhook records: 12 months.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">6. Your rights</h2>
                    <p>You may ask for a copy of your information, correct it, or have it deleted. Email {{ contactEmail }} and we will act on verified requests. Deleting your account anonymizes your bookings and inquiries while keeping anonymous counts for business records.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">7. Cookies and tracking</h2>
                    <p>Our sites store only a theme preference on your device. We run no advertising trackers. If that ever changes, we will ask for your consent first.</p>
                </section>

                <section>
                    <h2 class="font-medium text-brand-primary dark:text-brand-cream">8. Breaches</h2>
                    <p>If your information is ever exposed, we will notify you and the National Privacy Commission as required by law.</p>
                </section>
            </div>

            <div class="mt-8 flex gap-4 text-sm">
                <Link :href="route('terms', { v: version })" class="text-brand-primary dark:text-brand-cream underline">Terms of Service</Link>
                <Link :href="route('login')" class="text-brand-primary dark:text-brand-cream underline">Admin login</Link>
            </div>
        </div>
    </GuestLayout>
</template>
