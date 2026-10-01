<script setup>
defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <!-- By wrapping in flex and using negative margin on Scents, the browser accurately calculates the combined bounding box and centers it naturally -->
    <div class="inline-flex items-baseline justify-center">
        <span
            :class="compact ? 'text-2xl' : 'text-6xl sm:text-7xl'"
            class="font-logo-sans font-bold tracking-[0.15em] text-brand-primary dark:text-brand-cream [-webkit-text-stroke:2px_#fdf4f5] dark:[-webkit-text-stroke:2px_#151012] [paint-order:stroke_fill]"
            >INEA</span
        >

        <span
            :class="compact ? '-ml-[0.95em] text-3xl' : '-ml-[0.95em] sm:-ml-[0.85em] text-7xl sm:text-8xl'"
            class="font-logo-script translate-y-[65%] text-brand-primary dark:text-brand-cream [-webkit-text-stroke:2px_#fdf4f5] dark:[-webkit-text-stroke:2px_#151012] [paint-order:stroke_fill]"
            >Scents</span
        >
    </div>
</template>
