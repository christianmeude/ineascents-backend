<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';

const showingNavigationDropdown = ref(false);

const navigation = [
    { name: 'Dashboard', href: route('admin.dashboard'), active: route().current('admin.dashboard') },
    { name: 'Bookings', href: route('admin.bookings.index'), active: route().current('admin.bookings.*') },
    { name: 'Inquiries', href: route('admin.inquiries.index'), active: route().current('admin.inquiries.*') },
    { name: 'Calendar', href: route('admin.calendar.index'), active: route().current('admin.calendar.*') },
    { name: 'Packages', href: route('admin.packages.index'), active: route().current('admin.packages.*') },
    { name: 'Customers', href: route('admin.customers.index'), active: route().current('admin.customers.*') },
    { name: 'Feedback', href: route('admin.feedbacks.index'), active: route().current('admin.feedbacks.*') },
    { name: 'Payments', href: route('admin.payments.index'), active: route().current('admin.payments.*') },
    { name: 'Settings', href: '#', active: false, disabled: true },
];
</script>

<template>
    <div class="flex h-screen bg-brand-cream dark:bg-brand-dark-base font-sans transition-colors duration-200">
        <!-- Desktop Sidebar -->
        <aside class="w-64 flex-shrink-0 bg-white dark:bg-brand-dark-surface border-r border-brand-primary/20 dark:border-brand-dark-border hidden md:flex md:flex-col shadow-sm z-10 relative transition-colors duration-200">
            <!-- Logo area -->
            <div class="flex h-20 items-center px-6 justify-center">
                <Link :href="route('admin.dashboard')">
                    <ApplicationLogo compact />
                </Link>
            </div>

            <!-- Navigation Links -->
            <OverlayScrollbarsComponent defer :options="{ scrollbars: { theme: 'os-theme-custom', autoHide: 'scroll' } }" class="flex-1 mt-2">
                <nav class="py-6 px-4 space-y-3">
                    <component
                        :is="item.disabled ? 'span' : Link"
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.disabled ? undefined : item.href"
                        :class="[
                            'group flex justify-between items-center px-5 py-2.5 text-sm font-medium rounded-full',
                            item.disabled 
                                ? 'opacity-60 cursor-not-allowed text-brand-primary dark:text-brand-cream bg-transparent transition-none'
                                : (item.active
                                    ? 'bg-brand-primary dark:bg-brand-dark-accent text-white hover:bg-brand-primary/90 dark:hover:bg-brand-dark-accent/90 shadow-sm transition-all duration-200 active:scale-[0.98]'
                                    : 'bg-transparent text-brand-primary dark:text-brand-cream hover:bg-brand-primary/5 dark:hover:bg-brand-dark-accent hover:translate-x-1 transition-all duration-200 active:scale-[0.98]')
                        ]"
                    >
                        <span>{{ item.name }}</span>
                        
                        <span v-if="item.disabled" 
                              class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none bg-brand-primary dark:bg-brand-dark-surface text-white dark:text-brand-cream text-[10px] px-2 py-1 rounded-md shadow-sm border border-brand-primary/20 dark:border-brand-dark-border whitespace-nowrap relative flex items-center">
                            <span class="absolute right-full top-1/2 -translate-y-1/2 border-[4px] border-transparent border-r-brand-primary dark:border-r-brand-dark-surface"></span>
                            Coming soon!
                        </span>
                    </component>
                </nav>
            </OverlayScrollbarsComponent>

            <!-- User / Sign out area -->
            <div class="p-4 mb-4 px-4">
                <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center justify-center px-5 py-2.5 text-sm font-medium text-brand-primary dark:text-brand-cream border border-gray-200 dark:border-brand-dark-border rounded-full hover:border-brand-primary/50 dark:hover:border-brand-dark-border hover:bg-brand-primary/5 dark:hover:bg-brand-dark-accent transition-all duration-200">
                    SIGN OUT
                </Link>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <!-- Topbar (Mobile only mostly, but kept for user dropdown on desktop) -->
            <header class="bg-transparent h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-10 relative">
                <!-- Mobile Hamburger -->
                <div class="md:hidden flex items-center">
                    <button @click="showingNavigationDropdown = !showingNavigationDropdown" type="button" class="text-brand-primary dark:text-brand-cream hover:text-brand-primary dark:text-brand-cream/80 dark:hover:text-brand-cream/80 focus:outline-none">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="flex-1 md:hidden flex justify-center">
                    <ApplicationLogo compact />
                </div>

                <!-- Right side -->
                <div class="flex items-center ml-auto gap-2">
                    <NotificationBell />
                    
                    <div class="hidden md:flex items-center gap-2">
                        <ThemeToggle />
                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-full">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-full border border-gray-200 dark:border-brand-dark-border bg-white dark:bg-brand-dark-surface px-4 py-2 text-sm font-medium leading-4 text-brand-primary dark:text-brand-cream transition duration-150 ease-in-out hover:bg-brand-primary/5 dark:hover:bg-brand-dark-accent focus:outline-none"
                                        >
                                            {{ $page.props.auth.user.name }}

                                            <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('admin.profile.edit')"> Profile </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button"> Log Out </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Navigation Menu -->
            <div :class="{'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown}" class="md:hidden bg-white dark:bg-brand-dark-surface border-b border-brand-primary/20 dark:border-brand-dark-border absolute w-full z-20">
                <div class="flex justify-end px-4 pt-3">
                    <ThemeToggle />
                </div>
                <div class="space-y-1 pb-3 pt-2">
                    <component 
                        :is="item.disabled ? 'div' : ResponsiveNavLink" 
                        v-for="item in navigation" 
                        :key="item.name" 
                        :href="item.disabled ? undefined : item.href" 
                        :active="item.active"
                        :class="[item.disabled ? 'opacity-60 cursor-not-allowed pointer-events-none flex justify-between items-center group pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium' : '']"
                    >
                        <span>{{ item.name }}</span>
                        
                        <span v-if="item.disabled" 
                              class="opacity-50 transition-opacity duration-300 pointer-events-none bg-brand-primary dark:bg-brand-dark-surface text-white dark:text-brand-cream text-[10px] px-2 py-1 rounded-md shadow-sm border border-brand-primary/20 dark:border-brand-dark-border whitespace-nowrap relative flex items-center">
                            <span class="absolute right-full top-1/2 -translate-y-1/2 border-[4px] border-transparent border-r-brand-primary dark:border-r-brand-dark-surface"></span>
                            Coming soon!
                        </span>
                    </component>
                </div>
                <div class="border-t border-brand-primary/10 dark:border-brand-dark-border pb-1 pt-4">
                    <div class="px-4">
                        <div class="text-base font-medium text-brand-primary dark:text-brand-cream">{{ $page.props.auth.user.name }}</div>
                        <div class="text-sm font-medium text-brand-muted dark:text-brand-cream/70">{{ $page.props.auth.user.email }}</div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('admin.profile.edit')">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                    </div>
                </div>
            </div>

            <!-- Page Heading (Removed background to let cream flow, just content) -->
            <header v-if="$slots.header" class="pt-6 pb-2">
                <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                    <slot name="header" />
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <OverlayScrollbarsComponent defer :options="{ scrollbars: { theme: 'os-theme-custom', autoHide: 'scroll' } }" class="flex-1 h-full">
                <main :class="route().current('admin.dashboard') ? 'p-4 sm:p-6 lg:p-8' : 'px-4 sm:px-6 lg:px-8 pb-4 sm:pb-6 lg:pb-8 pt-2'">
                    <div class="max-w-7xl mx-auto">
                        <slot />
                    </div>
                </main>
            </OverlayScrollbarsComponent>
        </div>
    </div>
</template>
