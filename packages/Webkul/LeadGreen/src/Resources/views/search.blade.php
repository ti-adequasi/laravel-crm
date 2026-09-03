<x-admin::layouts>
    <x-slot:title>
        @lang('leadgreen::app.search.title')
    </x-slot>

    <v-leadgreen-search></v-leadgreen-search>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-leadgreen-search-template">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('leadgreen::app.search.title')
                    </div>

                    <a href="{{ route('admin.leadgreen.index') }}" class="secondary-button">
                        @lang('leadgreen::app.search.back-to-list')
                    </a>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-1 text-gray-600 dark:text-gray-300">
                        @lang('leadgreen::app.search.description')
                    </p>
                    <p class="mb-4 text-sm text-amber-600 dark:text-amber-400">
                        @lang('leadgreen::app.search.website-only-note')
                    </p>

                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="font-medium text-gray-800 dark:text-white">
                                @lang('leadgreen::app.search.query-label')
                            </label>

                            <input
                                type="text"
                                v-model="form.query"
                                :disabled="loading || importing"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="@lang('leadgreen::app.search.query-placeholder')"
                                @keyup.enter="search"
                            />

                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                @lang('leadgreen::app.search.query-hint')
                            </span>
                        </div>

                        <div class="flex w-full max-w-xs flex-col gap-1">
                            <label class="font-medium text-gray-800 dark:text-white">
                                @lang('leadgreen::app.search.limit-label')
                            </label>

                            <input
                                type="number"
                                v-model.number="form.limit"
                                :disabled="loading || importing"
                                min="1"
                                max="300"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="primary-button"
                                :disabled="loading || importing || ! form.query"
                                @click="search"
                            >
                                <span v-if="! loading">@lang('leadgreen::app.search.submit')</span>
                                <span v-else>@lang('leadgreen::app.search.searching')</span>
                            </button>

                            <button
                                v-if="preview"
                                type="button"
                                class="secondary-button"
                                :disabled="loading || importing"
                                @click="reset"
                            >
                                @lang('leadgreen::app.search.new-search')
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="preview" class="flex flex-col gap-4">
                    <div class="grid grid-cols-4 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-2xl font-bold text-gray-800 dark:text-white">@{{ preview.counts.total }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.total')</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-2xl font-bold text-gray-800 dark:text-white">@{{ preview.counts.with_website }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.with-website')</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center dark:border-amber-900 dark:bg-amber-900/20">
                            <div class="text-2xl font-bold text-amber-700 dark:text-amber-400">@{{ preview.counts.duplicates }}</div>
                            <div class="text-sm text-amber-600 dark:text-amber-400">@lang('leadgreen::app.search.preview.duplicates')</div>
                        </div>
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-center dark:border-green-900 dark:bg-green-900/20">
                            <div class="text-2xl font-bold text-green-700 dark:text-green-400">@{{ preview.counts.new }}</div>
                            <div class="text-sm text-green-600 dark:text-green-400">@lang('leadgreen::app.search.preview.new')</div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-end gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadgreen::app.search.filters.website')</label>
                            <select
                                v-model="filters.hasWebsite"
                                class="custom-select rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="yes">@lang('leadgreen::app.search.filters.website-yes')</option>
                                <option value="all">@lang('leadgreen::app.search.filters.website-all')</option>
                                <option value="no">@lang('leadgreen::app.search.filters.website-no')</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadgreen::app.search.filters.min-rating')</label>
                            <select
                                v-model.number="filters.minRating"
                                class="custom-select rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option :value="0">@lang('leadgreen::app.search.filters.any')</option>
                                <option :value="3">★ 3+</option>
                                <option :value="3.5">★ 3.5+</option>
                                <option :value="4">★ 4+</option>
                                <option :value="4.5">★ 4.5+</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadgreen::app.search.filters.min-reviews')</label>
                            <input
                                type="number"
                                v-model.number="filters.minReviews"
                                min="0"
                                placeholder="0"
                                class="w-28 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                                <input type="checkbox" v-model="filters.hideDuplicates" />
                                @lang('leadgreen::app.search.filters.hide-duplicates')
                            </label>
                        </div>

                        <div class="ml-auto text-sm text-gray-500 dark:text-gray-400">
                            @{{ filteredLeads.length }} / @{{ preview.leads.length }}
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                            <span>@lang('leadgreen::app.search.preview.title')</span>

                            <button
                                type="button"
                                class="text-sm font-normal text-brandColor hover:underline"
                                @click="toggleSelectAll"
                            >
                                @{{ allSelectableSelected ? '@lang('leadgreen::app.search.preview.deselect-all')' : '@lang('leadgreen::app.search.preview.select-all')' }}
                            </button>
                        </div>

                        <p v-if="! filteredLeads.length" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            @lang('leadgreen::app.search.preview.empty')
                        </p>

                        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="w-10 px-4 py-2"></th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-name')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-location')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-phone')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-website')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-rating')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-status')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="lead in filteredLeads" :key="lead.business_id" :class="lead.is_duplicate ? 'bg-amber-50/40 dark:bg-amber-900/10' : ''">
                                    <td class="px-4 py-2">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.has(lead.business_id)"
                                            :disabled="! isSelectable(lead)"
                                            @change="toggleSelect(lead)"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">@{{ lead.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">@{{ lead.city }}<span v-if="lead.state"> / @{{ lead.state }}</span></td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">@{{ lead.phone_number || '—' }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <a v-if="lead.has_website" :href="lead.website" target="_blank" class="text-brandColor hover:underline">@{{ shortWebsite(lead.website) }}</a>
                                        <span v-else class="text-xs text-gray-400">@lang('leadgreen::app.search.filters.website-no')</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="lead.rating">★ @{{ lead.rating }} <span class="text-xs text-gray-400">(@{{ lead.review_count || 0 }})</span></span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span v-if="lead.is_duplicate" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadgreen::app.search.preview.badge-duplicate')</span>
                                        <span v-else-if="! lead.has_website" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">@lang('leadgreen::app.search.filters.website-no')</span>
                                        <span v-else class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadgreen::app.search.preview.badge-new')</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <button type="button" class="text-brandColor hover:underline" @click="openLead(lead)">
                                            @lang('leadgreen::app.search.preview.view-btn')
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span v-if="! selectedIds.size" class="text-sm text-gray-500 dark:text-gray-400">
                            @lang('leadgreen::app.search.preview.nothing-selected')
                        </span>

                        <button v-else type="button" class="primary-button" :disabled="importing" @click="confirmImport">
                            <span v-if="! importing">@{{ importLabel }}</span>
                            <span v-else>@lang('leadgreen::app.search.importing')</span>
                        </button>
                    </div>
                </div>

                <!-- Detail modal -->
                <div v-if="selectedLead" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="closeLead">
                    <div class="relative max-h-[90vh] w-full max-w-2xl overflow-auto rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@{{ selectedLead.name }}</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeLead">&times;</button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-6 text-sm">
                            <div class="col-span-2">
                                <span v-if="selectedLead.is_duplicate" class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadgreen::app.search.preview.badge-duplicate')</span>
                                <span v-else class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadgreen::app.search.preview.badge-new')</span>
                            </div>

                            <div v-if="selectedLead.phone_number">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.phone')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.phone_number }}</p>
                            </div>

                            <div v-if="selectedLead.has_website">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.website')</label>
                                <p class="mt-1"><a :href="selectedLead.website" target="_blank" class="text-brandColor hover:underline">@{{ shortWebsite(selectedLead.website) }}</a></p>
                            </div>

                            <div class="col-span-2" v-if="selectedLead.full_address">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.address')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.full_address }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.location')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.city }}<span v-if="selectedLead.state"> / @{{ selectedLead.state }}</span></p>
                            </div>

                            <div v-if="selectedLead.rating">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.rating')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">
                                    <span class="text-yellow-500">★</span> @{{ selectedLead.rating }}
                                    <span class="text-xs text-gray-400">(@{{ selectedLead.review_count || 0 }} @lang('leadgreen::app.modal.reviews'))</span>
                                </p>
                            </div>

                            <div v-if="selectedLead.price_level">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.price-level')</label>
                                <p class="mt-1 font-semibold text-green-600 dark:text-green-400">@{{ '$'.repeat(selectedLead.price_level) }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.situation')</label>
                                <p class="mt-1">
                                    <span v-if="selectedLead.is_permanently_closed" class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">@lang('leadgreen::app.modal.permanently-closed')</span>
                                    <span v-else-if="selectedLead.is_temporarily_closed" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadgreen::app.modal.temporarily-closed')</span>
                                    <span v-else class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadgreen::app.modal.open')</span>
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.verified')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">
                                    <span v-if="selectedLead.verified" class="text-green-600 dark:text-green-400">✓ @lang('leadgreen::app.modal.yes')</span>
                                    <span v-else>@lang('leadgreen::app.modal.no')</span>
                                </p>
                            </div>

                            <div class="col-span-2" v-if="selectedLead.types && selectedLead.types.length">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.search.preview.segment')</label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <span v-for="(type, i) in selectedLead.types" :key="i" class="inline-flex rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-900/20 dark:text-blue-400">@{{ type }}</span>
                                </div>
                            </div>

                            <div class="col-span-2" v-if="hasHours">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.hours')</label>
                                <table class="mt-1 w-full max-w-sm text-sm">
                                    <tr v-for="(hours, day) in selectedLead.working_hours" :key="day">
                                        <td class="py-0.5 pr-4 capitalize text-gray-700 dark:text-gray-300">@{{ day }}</td>
                                        <td class="py-0.5 text-gray-500 dark:text-gray-400">@{{ hours }}</td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-span-2" v-if="selectedLead.place_link">
                                <a :href="selectedLead.place_link" target="_blank" class="inline-flex items-center gap-1 text-sm text-brandColor hover:underline">
                                    <span class="icon-location"></span>
                                    @lang('leadgreen::app.modal.view-on-google')
                                </a>
                            </div>

                            <div class="col-span-2" v-if="selectedLead.photos && selectedLead.photos.length">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.search.preview.photos')</label>
                                <div class="mt-2 grid grid-cols-4 gap-2">
                                    <a
                                        v-for="(photo, i) in selectedLead.photos.slice(0, 8)"
                                        :key="i"
                                        :href="photoSrc(photo)"
                                        target="_blank"
                                        class="aspect-square overflow-hidden rounded-md border border-gray-200 hover:opacity-80 dark:border-gray-800"
                                    >
                                        <img :src="photoSrc(photo)" class="h-full w-full object-cover" loading="lazy" />
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <label v-if="isSelectable(selectedLead)" class="mr-auto flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" :checked="selectedIds.has(selectedLead.business_id)" @change="toggleSelect(selectedLead)" />
                                @lang('leadgreen::app.search.preview.select-this')
                            </label>

                            <button type="button" class="secondary-button" @click="closeLead">@lang('leadgreen::app.search.preview.close')</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-leadgreen-search', {
                template: '#v-leadgreen-search-template',

                data() {
                    return {
                        loading: false,
                        importing: false,
                        preview: null,
                        selectedLead: null,
                        selectedIds: new Set(),
                        form: {
                            query: '',
                            limit: 100,
                        },
                        filters: {
                            hasWebsite: 'yes',
                            minRating: 0,
                            minReviews: 0,
                            hideDuplicates: false,
                        },
                    };
                },

                computed: {
                    filteredLeads() {
                        if (! this.preview) {
                            return [];
                        }

                        return this.preview.leads.filter((lead) => {
                            if (this.filters.hasWebsite === 'yes' && ! lead.has_website) return false;
                            if (this.filters.hasWebsite === 'no' && lead.has_website) return false;
                            if (this.filters.minRating && (! lead.rating || lead.rating < this.filters.minRating)) return false;
                            if (this.filters.minReviews && (lead.review_count || 0) < this.filters.minReviews) return false;
                            if (this.filters.hideDuplicates && lead.is_duplicate) return false;

                            return true;
                        });
                    },

                    allSelectableSelected() {
                        const selectable = this.filteredLeads.filter((lead) => this.isSelectable(lead));

                        return selectable.length > 0 && selectable.every((lead) => this.selectedIds.has(lead.business_id));
                    },

                    importLabel() {
                        return "@lang('leadgreen::app.search.preview.import-btn')".replace(':count', this.selectedIds.size);
                    },

                    hasHours() {
                        const wh = this.selectedLead?.working_hours;

                        return wh && typeof wh === 'object' && Object.keys(wh).length > 0;
                    },
                },

                methods: {
                    isSelectable(lead) {
                        return lead.has_website && ! lead.is_duplicate;
                    },

                    toggleSelect(lead) {
                        if (! this.isSelectable(lead)) {
                            return;
                        }

                        if (this.selectedIds.has(lead.business_id)) {
                            this.selectedIds.delete(lead.business_id);
                        } else {
                            this.selectedIds.add(lead.business_id);
                        }

                        // Sets aren't reactive in-place — reassign so computed properties refresh.
                        this.selectedIds = new Set(this.selectedIds);
                    },

                    toggleSelectAll() {
                        const selectable = this.filteredLeads.filter((lead) => this.isSelectable(lead));
                        const next = new Set(this.selectedIds);

                        if (this.allSelectableSelected) {
                            selectable.forEach((lead) => next.delete(lead.business_id));
                        } else {
                            selectable.forEach((lead) => next.add(lead.business_id));
                        }

                        this.selectedIds = next;
                    },

                    openLead(lead) {
                        this.selectedLead = lead;
                    },

                    closeLead() {
                        this.selectedLead = null;
                    },

                    photoSrc(photo) {
                        return typeof photo === 'string' ? photo : (photo?.src ?? '');
                    },

                    shortWebsite(url) {
                        if (! url) return '—';

                        return url.replace(/^https?:\/\//, '').replace(/\/$/, '').substring(0, 40);
                    },

                    search() {
                        if (this.loading || ! this.form.query) {
                            return;
                        }

                        this.loading = true;
                        this.preview = null;
                        this.selectedIds = new Set();

                        this.$axios.post("{{ route('admin.leadgreen.search') }}", this.form)
                            .then((response) => {
                                this.preview = response.data;
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message ?? "@lang('leadgreen::app.search.error.request-failed', ['status' => ''])",
                                });
                            })
                            .finally(() => {
                                this.loading = false;
                            });
                    },

                    confirmImport() {
                        if (this.importing || ! this.preview || ! this.selectedIds.size) {
                            return;
                        }

                        this.importing = true;

                        this.$axios.post("{{ route('admin.leadgreen.import') }}", {
                            token: this.preview.token,
                            business_ids: Array.from(this.selectedIds),
                        })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                // Drop the imported rows from the preview and clear their selection;
                                // the rest of the batch stays, so a second selective import is possible.
                                const imported = new Set(this.selectedIds);

                                this.preview.leads = this.preview.leads.filter((lead) => ! imported.has(lead.business_id));
                                this.selectedIds = new Set();
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Error' });
                            })
                            .finally(() => {
                                this.importing = false;
                            });
                    },

                    reset() {
                        this.preview = null;
                        this.selectedIds = new Set();
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
