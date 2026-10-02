<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import BackLink from '@/Components/BackLink.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { tiersFromPackage, tiersToMap, useStartsAt, validTiers } from '@/Components/usePaxTiers.js';
import { ref } from 'vue';

const props = defineProps({
    package: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    _method: 'put',
    name: props.package.name,
    description: props.package.description,
    price: props.package.price,
    inclusions: props.package.inclusions && props.package.inclusions.length ? props.package.inclusions : [''],
    tiers: tiersFromPackage(props.package),
    freebies: props.package.freebies && props.package.freebies.length ? props.package.freebies : [''],
    images: [
        (props.package.images && props.package.images[0]) ? props.package.images[0] : '',
        (props.package.images && props.package.images[1]) ? props.package.images[1] : '',
        (props.package.images && props.package.images[2]) ? props.package.images[2] : '',
    ], // Will hold a mix of strings (urls) and files
});

// Convert the existing image paths to full URLs if needed, but for preview we can just show them
const getImageUrl = (img) => {
    if (!img) return '';
    if (typeof img === 'string') {
        return img.startsWith('http') ? img : `/storage/${img}`;
    }
    return URL.createObjectURL(img);
};

const imagePreviews = ref([
    getImageUrl(form.images[0]),
    getImageUrl(form.images[1]),
    getImageUrl(form.images[2]),
]);

const handleImageUpload = (index, event) => {
    const file = event.target.files[0];
    if (file) {
        form.images[index] = file;
        imagePreviews.value[index] = URL.createObjectURL(file);
    }
};

const removeImage = (index) => {
    form.images[index] = '';
    imagePreviews.value[index] = '';
};

const addField = (field) => {
    form[field].push('');
};

const removeField = (field, index) => {
    form[field].splice(index, 1);
};

const startsAt = useStartsAt(() => form.tiers);

const addTier = () => {
    form.tiers.push({ pax: '', price: '' });
};

const removeTier = (index) => {
    if (form.tiers.length > 1) {
        form.tiers.splice(index, 1);
    }
};

const submit = () => {
    // We post to the update route because we have files and PUT requests don't handle files well in PHP
    form.transform((data) => {
        const { tiers, pax_options, ...rest } = data;
        return { ...rest, pax_prices: tiersToMap(tiers) };
    }).post(route('admin.packages.update', props.package.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Edit Package" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-brand-primary dark:text-brand-cream">
                    Edit Package: {{ package.name }}
                </h2>
                <BackLink :href="route('admin.packages.index')" label="Packages" />
            </div>
            <p class="text-sm text-brand-muted dark:text-brand-cream/70">Manages the perfume-bar packages.</p>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-brand-dark-surface shadow-sm rounded-3xl overflow-hidden p-6 sm:p-10 border border-[#c4acac]/30 flex flex-col lg:flex-row gap-10">
                    
                    <!-- Left Column: Form -->
                    <div class="flex-1">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Images -->
                            <div>
                                <InputLabel value="Add Image" class="text-brand-primary dark:text-brand-cream" />
                                <div class="flex gap-4 mt-2">
                                    <div v-for="(preview, index) in imagePreviews" :key="index" class="relative w-32 h-24 bg-[#fdf4f5] dark:bg-brand-dark-base border border-[#c4acac] dark:border-brand-dark-border rounded-lg overflow-hidden flex items-center justify-center transition-colors group">
                                        <input type="file" @change="e => handleImageUpload(index, e)" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10" />
                                        <img alt="Image" v-if="preview" :src="preview" class="w-full h-full object-cover" />
                                        <div v-else class="text-[#c4acac] dark:text-brand-cream/50 text-2xl transition-colors">+</div>
                                        
                                        <!-- Remove button -->
                                        <button v-if="preview" type="button" @click.stop.prevent="removeImage(index)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity z-20 hover:bg-red-600 shadow-sm" title="Remove Image">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Name -->
                            <div>
                                <InputLabel for="name" value="Package Name:" class="text-brand-primary dark:text-brand-cream" />
                                <input
                                    id="name"
                                    type="text"
                                    class="bg-white dark:bg-brand-dark-base mt-1 block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                    v-model="form.name"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="description" value="Package Description:" class="text-brand-primary dark:text-brand-cream" />
                                <textarea
                                    id="description"
                                    class="bg-white dark:bg-brand-dark-base mt-1 block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                    v-model="form.description"
                                    rows="3"
                                    maxlength="200"
                                ></textarea>
                                <div class="text-right text-xs text-brand-muted dark:text-brand-cream/70 mt-1">200 Char</div>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- Inclusions -->
                            <div>
                                <InputLabel value="Add Inclusion/s:" class="text-brand-primary dark:text-brand-cream" />
                                <div v-for="(inclusion, index) in form.inclusions" :key="`inc-${index}`" class="flex gap-2 mt-1 items-center">
                                    <input
                                        type="text"
                                        class="bg-white dark:bg-brand-dark-base block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                        v-model="form.inclusions[index]"
                                    />
                                    <button type="button" @click="removeField('inclusions', index)" v-if="form.inclusions.length > 1" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors" title="Remove Inclusion">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-right mt-1">
                                    <button type="button" @click="addField('inclusions')" class="text-sm text-brand-primary dark:text-brand-cream hover:underline">Add More +</button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.inclusions" />
                            </div>

                            <!-- Pax tiers: each row is one client card (pax + price) -->
                            <div>
                                <InputLabel value="Pax pricing (one row per client card):" class="text-brand-primary dark:text-brand-cream" />
                                <div v-for="(tier, index) in form.tiers" :key="`tier-${index}`" class="flex gap-2 mt-1 items-center">
                                    <select
                                        class="bg-white dark:bg-brand-dark-base block w-28 pl-4 pr-10 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                        v-model="tier.pax"
                                    >
                                        <option value="" disabled>Pax</option>
                                        <option value="50">50</option>
                                        <option value="70">70</option>
                                        <option value="100">100</option>
                                        <option value="150">150</option>
                                    </select>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Price (₱)"
                                        class="bg-white dark:bg-brand-dark-base block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                        v-model="tier.price"
                                    />
                                    <button type="button" @click="removeTier(index)" v-if="form.tiers.length > 1" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors" title="Remove Row">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <p class="text-xs text-brand-muted dark:text-brand-cream/70">
                                        Starts at: {{ startsAt !== null ? '₱' + startsAt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—' }}
                                    </p>
                                    <button type="button" @click="addTier" class="text-sm text-brand-primary dark:text-brand-cream hover:underline">Add More +</button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.pax_prices" />
                            </div>

                            <!-- Freebies -->
                            <div>
                                <InputLabel value="Freebie/s:" class="text-brand-primary dark:text-brand-cream" />
                                <div v-for="(freebie, index) in form.freebies" :key="`freebie-${index}`" class="flex gap-2 mt-1 items-center">
                                    <input
                                        type="text"
                                        class="bg-white dark:bg-brand-dark-base block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                        v-model="form.freebies[index]"
                                    />
                                    <button type="button" @click="removeField('freebies', index)" v-if="form.freebies.length > 1" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors" title="Remove Freebie">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-right mt-1">
                                    <button type="button" @click="addField('freebies')" class="text-sm text-brand-primary dark:text-brand-cream hover:underline">Add More +</button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.freebies" />
                            </div>

                            <!-- Price -->
                            <div>
                                <InputLabel for="price" value="Price" class="text-brand-primary dark:text-brand-cream" />
                                <input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    class="bg-white dark:bg-brand-dark-base mt-1 block w-full px-4 py-2 border border-brand-primary/20 dark:border-brand-dark-border rounded-lg text-brand-primary dark:text-brand-cream focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-colors"
                                    v-model="form.price"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.price" />
                            </div>

                            <div class="flex items-center justify-end mt-8">
                                <PrimaryButton class="ms-4 bg-brand-primary hover:bg-brand-muted rounded-full px-8 py-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Update Package
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column: Mobile App Preview -->
                    <div class="hidden md:flex lg:block flex-col items-center w-full lg:w-80 relative flex-shrink-0 mt-10 lg:mt-0">
                        <h3 class="text-sm font-medium text-brand-primary dark:text-brand-cream mb-4 text-center">Preview on Mobile App</h3>
                        
                        <div class="relative w-80 h-[600px] bg-black rounded-[40px] p-2 shadow-xl border-4 border-gray-800 flex flex-col overflow-hidden">
                            <!-- Dynamic Island notch -->
                            <div class="absolute top-0 inset-x-0 h-6 flex justify-center z-20">
                                <div class="w-24 h-5 bg-black rounded-b-xl"></div>
                            </div>

                            <!-- App UI Content -->
                            <div class="flex-1 bg-white dark:bg-brand-dark-surface rounded-[32px] overflow-hidden flex flex-col text-sm pb-16 relative">
                                
                                <!-- Screen header: mirrors packages screen (no app bar) -->
                                <div class="pt-8 pb-2 px-4 bg-[#fdf4f5] dark:bg-brand-dark">
                                    <div class="text-2xl leading-tight text-brand-primary dark:text-brand-cream" style="font-family: 'Great Vibes', cursive;">Our Collections</div>
                                    <div class="text-[11px] text-brand-muted dark:text-brand-cream/70">Discover your perfect scent.</div>
                                </div>

                                <!-- Scrollable App Content: mirrors packages screen -->
                                <div class="flex-1 overflow-y-auto pb-6 px-3 pt-1 bg-[#fdf4f5] dark:bg-brand-dark">

                                    <!-- Offering hero -->
                                    <div class="bg-white dark:bg-brand-dark-surface rounded-[20px] shadow flex flex-row overflow-hidden">
                                        <div class="w-[132px] h-[200px] flex-shrink-0 bg-[#f6e8ea] dark:bg-brand-dark flex items-center justify-center overflow-hidden">
                                            <img alt="Package image" v-if="imagePreviews[0]" :src="imagePreviews[0]" class="w-full h-full object-cover" />
                                            <div v-else class="w-10 h-10 rounded-full bg-[#e9d5d8] text-brand-primary flex items-center justify-center font-semibold text-lg">{{ (form.name || 'P').charAt(0).toUpperCase() }}</div>
                                        </div>
                                        <div class="flex-1 p-3 flex flex-col justify-center min-w-0">
                                            <div class="font-semibold text-[17px] leading-snug text-brand-primary dark:text-brand-cream" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ form.name || 'Package Name' }}</div>
                                            <div class="text-sm text-brand-muted dark:text-brand-cream/70 mt-1">Starting at &#8369;{{ ((startsAt !== null ? startsAt : parseFloat(form.price)) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</div>
                                            <div class="text-xs text-brand-muted dark:text-brand-cream/70 mt-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ [...form.inclusions.filter(Boolean), ...form.freebies.filter(Boolean)].slice(0, 2).join(' · ') || form.description || '' }}</div>
                                        </div>
                                    </div>

                                    <!-- Pax Choices -->
                                    <div class="mt-4 mb-2 font-semibold text-brand-primary dark:text-brand-cream">Choose your Pax Choice</div>
                                    <div v-for="(tier, i) in validTiers(form.tiers)" :key="i" class="bg-white dark:bg-brand-dark-surface rounded-2xl shadow-sm px-3 py-2.5 mb-2 flex items-center justify-between">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-brand-primary dark:text-brand-cream">{{ tier.pax }} Pax Choice</div>
                                            <div class="text-xs text-brand-muted dark:text-brand-cream/70" v-if="String(tier.price).trim() !== ''">&#8369;{{ parseFloat(tier.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</div>
                                        </div>
                                        <div class="text-brand-muted dark:text-brand-cream/70">&rarr;</div>
                                    </div>
                                    <div v-if="!validTiers(form.tiers).length" class="text-xs text-brand-muted dark:text-brand-cream/70">No Pax Choices yet</div>
                                </div>

                                <!-- App Bottom Nav: mirrors mobile tabs -->
                                <div class="absolute bottom-0 inset-x-0 h-16 bg-brand-primary/90 backdrop-blur text-white flex justify-around items-center text-[10px] rounded-b-[32px]">
                                    <div class="flex flex-col items-center opacity-50">
                                        <span class="text-lg mb-0.5">&#8962;</span>
                                        <span>HOME</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-lg mb-0.5">&#127873;</span>
                                        <span>PACKAGES</span>
                                    </div>
                                    <div class="flex flex-col items-center opacity-50">
                                        <span class="text-lg mb-0.5">&#128197;</span>
                                        <span>BOOKINGS</span>
                                    </div>
                                    <div class="flex flex-col items-center opacity-50">
                                        <span class="text-lg mb-0.5">&#128198;</span>
                                        <span>CALENDAR</span>
                                    </div>
                                    <div class="flex flex-col items-center opacity-50">
                                        <span class="text-lg mb-0.5">&#128100;</span>
                                        <span>PROFILE</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
