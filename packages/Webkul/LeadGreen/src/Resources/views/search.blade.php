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

                    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-4 py-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                            @lang('leadgreen::app.search.preview.title')
                        </div>

                        <p v-if="! preview.leads.length" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            @lang('leadgreen::app.search.preview.empty')
                        </p>

                        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-name')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-location')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-phone')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-website')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-rating')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadgreen::app.search.preview.col-status')</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="lead in preview.leads" :key="lead.business_id" :class="lead.is_duplicate ? 'bg-amber-50/40 dark:bg-amber-900/10' : ''">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">@{{ lead.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">@{{ lead.city }}<span v-if="lead.state"> / @{{ lead.state }}</span></td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">@{{ lead.phone_number || '—' }}</td>
                                    <td class="px-4 py-2 text-sm"><a :href="lead.website" target="_blank" class="text-brandColor hover:underline">@{{ shortWebsite(lead.website) }}</a></td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="lead.rating">★ @{{ lead.rating }} <span class="text-xs text-gray-400">(@{{ lead.review_count || 0 }})</span></span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span v-if="lead.is_duplicate" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadgreen::app.search.preview.badge-duplicate')</span>
                                        <span v-else class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadgreen::app.search.preview.badge-new')</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span v-if="preview.counts.new === 0" class="text-sm text-gray-500 dark:text-gray-400">
                            @lang('leadgreen::app.search.preview.nothing-new')
                        </span>

                        <button v-else type="button" class="primary-button" :disabled="importing" @click="confirmImport">
                            <span v-if="! importing">@{{ importLabel }}</span>
                            <span v-else>@lang('leadgreen::app.search.importing')</span>
                        </button>
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
                        form: {
                            query: '',
                            limit: 100,
                        },
                    };
                },

                computed: {
                    importLabel() {
                        const count = this.preview ? this.preview.counts.new : 0;

                        return "@lang('leadgreen::app.search.preview.import-btn')".replace(':count', count);
                    },
                },

                methods: {
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
                        if (this.importing || ! this.preview) {
                            return;
                        }

                        this.importing = true;

                        this.$axios.post("{{ route('admin.leadgreen.import') }}", { token: this.preview.token })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                this.preview = null;
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
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
