<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';

defineProps({
    // A17: legal pages need a wider prose column; auth card stays narrow.
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <OverlayScrollbarsComponent defer :options="{ scrollbars: { theme: 'os-theme-custom', autoHide: 'scroll' } }" class="min-h-screen bg-brand-cream dark:bg-brand-dark-base transition-colors duration-500">
        <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden">
            
            <div class="absolute top-6 right-6 z-50">
                <ThemeToggle />
            </div>

            <!-- Mesh gradient decorative blobs based exactly on Figma CSS -->
            
            <!-- Mesh gradient decorative blobs based exactly on user's hand-drawn structural paths -->
            
            <!-- Top Left Peach (Boomerang shape built with two intersecting ellipses) -->
            <div class="absolute -top-[10%] -left-[5%] h-[600px] w-[300px] rotate-[30deg] rounded-[50%] bg-[#DABDAC] dark:bg-burgundy-900/40 blur-[60px] filter pointer-events-none"></div>
            <div class="absolute top-[10%] left-[5%] h-[300px] w-[600px] rotate-[15deg] rounded-[50%] bg-[#DABDAC] dark:bg-burgundy-900/40 blur-[60px] filter pointer-events-none"></div>
            
            <!-- Middle Left Mauve (Long sweeping sausage) -->
            <div class="absolute top-[30%] -left-[10%] h-[250px] w-[800px] rotate-[10deg] rounded-[50%] bg-[#C08D9E] dark:bg-brand-primary/50 blur-[60px] filter pointer-events-none"></div>
            
            <!-- Bottom Left Gray-Purple (Rounded Triangle) -->
            <div class="absolute top-[65%] -left-[5%] h-[400px] w-[500px] rounded-[40%_60%_70%_30%] bg-[#988088] dark:bg-[#4A2D3C]/60 blur-[60px] filter pointer-events-none"></div>
            
            <!-- Top Right Pale Rose (Massive organic blob covering the top right) -->
            <div class="absolute -top-[15%] right-[5%] h-[600px] w-[800px] rounded-[30%_70%_50%_50%] bg-[#C4A5A8] dark:bg-burgundy-950/70 blur-[60px] filter pointer-events-none"></div>
            <div class="absolute top-[20%] right-[20%] h-[400px] w-[500px] rounded-[50%] bg-[#C4A5A8] dark:bg-burgundy-950/70 blur-[60px] filter pointer-events-none"></div>
            
            <!-- Bottom Right Dark Plum (Reverse L built with two intersecting ellipses) -->
            <div class="absolute top-[50%] right-[-5%] h-[500px] w-[300px] rounded-[50%] bg-[#6E3C53] dark:bg-brand-primary/60 blur-[60px] filter pointer-events-none"></div>
            <div class="absolute top-[75%] right-[5%] h-[250px] w-[600px] rounded-[50%] bg-[#6E3C53] dark:bg-brand-primary/60 blur-[60px] filter pointer-events-none"></div>

            <div class="relative z-10 w-full px-6 py-12" :class="wide ? 'max-w-2xl' : 'max-w-sm'">
                <slot />
                <!-- A17: legal links reachable from every guest surface incl. admin login. -->
                <div class="mt-6 flex items-center justify-center gap-4 text-xs text-brand-muted dark:text-brand-cream/60">
                    <Link :href="route('privacy')" class="underline hover:text-brand-primary dark:hover:text-brand-cream">Privacy</Link>
                    <span aria-hidden="true">·</span>
                    <Link :href="route('terms')" class="underline hover:text-brand-primary dark:hover:text-brand-cream">Terms</Link>
                </div>
            </div>
        </div>
    </OverlayScrollbarsComponent>
</template>
