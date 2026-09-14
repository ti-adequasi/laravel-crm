<x-admin::layouts>
    <x-slot:title>
        @lang('leadpeering::app.search.title')
    </x-slot>

    @php
        $pipelines = \Webkul\Lead\Models\PipelineProxy::modelClass()::orderBy('name')->get(['id', 'name', 'is_default']);
    @endphp

    <v-leadpeering-search></v-leadpeering-search>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-leadpeering-search-template">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('leadpeering::app.search.title')
                    </div>

                    <a href="{{ route('admin.leadpeering.index') }}" class="secondary-button">
                        @lang('leadpeering::app.search.back-to-list')
                    </a>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-gray-600 dark:text-gray-300">
                        @lang('leadpeering::app.search.description')
                    </p>

                    <div class="flex flex-col gap-4">
                        <!-- Step 1: what kind of PeeringDB record to search. Changes which
                             filters make sense (a network has no country/city of its own —
                             see the note below) and how a result gets displayed, so it's
                             locked once a preview exists — "Nova busca" clears it first
                             rather than mixing filters meant for a different object shape. -->
                        <div class="flex flex-col gap-1">
                            <label class="font-medium text-gray-800 dark:text-white">
                                @lang('leadpeering::app.search.type-label')
                            </label>

                            <div class="inline-flex w-fit rounded-md border border-gray-300 dark:border-gray-700" role="group">
                                <button
                                    type="button"
                                    :disabled="loading || importing || preview"
                                    class="ltr:rounded-l-md rtl:rounded-r-md px-4 py-2 text-sm font-medium transition-colors"
                                    :class="form.type === 'net' ? 'bg-brandColor text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="form.type = 'net'"
                                >
                                    @lang('leadpeering::app.search.type-net')
                                </button>
                                <button
                                    type="button"
                                    :disabled="loading || importing || preview"
                                    class="border-l border-gray-300 px-4 py-2 text-sm font-medium transition-colors dark:border-gray-700"
                                    :class="form.type === 'org' ? 'bg-brandColor text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="form.type = 'org'"
                                >
                                    @lang('leadpeering::app.search.type-org')
                                </button>
                                <button
                                    type="button"
                                    :disabled="loading || importing || preview"
                                    class="ltr:rounded-r-md rtl:rounded-l-md border-l border-gray-300 px-4 py-2 text-sm font-medium transition-colors dark:border-gray-700"
                                    :class="form.type === 'fac' ? 'bg-brandColor text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="form.type = 'fac'"
                                >
                                    @lang('leadpeering::app.search.type-fac')
                                </button>
                            </div>

                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                @lang('leadpeering::app.search.type-hint')
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 dark:border-gray-800 md:grid-cols-3">
                            <div class="flex flex-col gap-1 md:col-span-2">
                                <label class="font-medium text-gray-800 dark:text-white">
                                    @lang('leadpeering::app.search.query-label')
                                </label>

                                <input
                                    type="text"
                                    v-model="form.query"
                                    :disabled="loading || importing"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    placeholder="@lang('leadpeering::app.search.query-placeholder')"
                                    @keyup.enter="search"
                                />

                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    @lang('leadpeering::app.search.query-hint')
                                </span>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="font-medium text-gray-800 dark:text-white">
                                    @lang('leadpeering::app.search.limit-label')
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
                        </div>

                        <!-- Step 2: fields specific to the chosen type, for a finer search.
                             Location fields are meaningless for a 'net' search — PeeringDB's
                             network object carries no country/city/state of its own
                             (confirmed live: the API silently ignores one rather than
                             erroring) — hidden instead of sent-and-ignored, with an
                             explanation, rather than leaving the user to wonder why a
                             filter "did nothing". -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <template v-if="form.type !== 'net'">
                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.country-label')</label>
                                    <input
                                        type="text"
                                        v-model="form.country"
                                        :disabled="loading || importing"
                                        maxlength="2"
                                        placeholder="BR"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 uppercase dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.city-label')</label>
                                    <input
                                        type="text"
                                        v-model="form.city"
                                        :disabled="loading || importing"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.state-label')</label>
                                    <select
                                        v-model="form.state"
                                        :disabled="loading || importing"
                                        class="custom-select w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">@lang('leadpeering::app.search.state-any')</option>
                                        <option v-for="uf in brazilianStates" :key="uf.code" :value="uf.code">@{{ uf.code }} — @{{ uf.name }}</option>
                                    </select>
                                </div>
                            </template>

                            <div v-if="form.type === 'fac'" class="flex flex-col gap-1">
                                <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.region-label')</label>
                                <select
                                    v-model="form.region_continent"
                                    :disabled="loading || importing"
                                    class="custom-select w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="">@lang('leadpeering::app.search.info-type-any')</option>
                                    <option v-for="r in continentChoices" :key="r" :value="r">@{{ r }}</option>
                                </select>
                            </div>

                            <template v-if="form.type === 'net'">
                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.asn-label')</label>
                                    <input
                                        type="number"
                                        v-model.number="form.asn"
                                        :disabled="loading || importing"
                                        min="0"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.info-type-label')</label>
                                    <select
                                        v-model="form.info_type"
                                        :disabled="loading || importing"
                                        class="custom-select w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">@lang('leadpeering::app.search.info-type-any')</option>
                                        <option v-for="t in networkTypeChoices" :key="t" :value="t">@{{ t }}</option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.scope-label')</label>
                                    <select
                                        v-model="form.info_scope"
                                        :disabled="loading || importing"
                                        class="custom-select w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">@lang('leadpeering::app.search.info-type-any')</option>
                                        <option v-for="s in scopeChoices" :key="s" :value="s">@{{ s }}</option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="font-medium text-gray-800 dark:text-white">@lang('leadpeering::app.search.policy-label')</label>
                                    <select
                                        v-model="form.policy_general"
                                        :disabled="loading || importing"
                                        class="custom-select w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">@lang('leadpeering::app.search.info-type-any')</option>
                                        <option v-for="p in policyChoices" :key="p" :value="p">@{{ p }}</option>
                                    </select>
                                </div>

                                <div class="flex items-start gap-2 rounded-sm border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-400 md:col-span-2">
                                    <span class="icon-info mt-0.5 dark:!text-blue-400"></span>
                                    <span>@lang('leadpeering::app.search.net-no-geography-note')</span>
                                </div>
                            </template>
                        </div>

                        <!-- Filters — re-appliable client-side on the current result set
                             without another call. -->
                        <div v-if="preview" class="flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="font-medium text-gray-800 dark:text-white">
                                    @lang('leadpeering::app.search.filters.section-title')
                                </label>

                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    @{{ filteredLeads.length }} / @{{ preview.leads.length }}
                                </span>
                            </div>

                            <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-800/40 md:w-fit md:min-w-[280px]">
                                <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <span class="icon-filter"></span>
                                    @lang('leadpeering::app.search.filters.group-status')
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadpeering::app.search.filters.min-net-count')</label>
                                    <input
                                        type="number"
                                        v-model.number="filters.minNetCount"
                                        min="0"
                                        placeholder="0"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>

                                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" v-model="filters.hideDuplicates" />
                                    @lang('leadpeering::app.search.filters.hide-duplicates')
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="primary-button"
                                :disabled="loading || importing"
                                @click="search"
                            >
                                <span v-if="! loading">@lang('leadpeering::app.search.submit')</span>
                                <span v-else>@lang('leadpeering::app.search.searching')</span>
                            </button>

                            <button
                                v-if="preview"
                                type="button"
                                class="secondary-button"
                                :disabled="loading || importing"
                                @click="reset"
                            >
                                @lang('leadpeering::app.search.new-search')
                            </button>
                        </div>

                        <p class="text-sm text-amber-600 dark:text-amber-400">
                            @lang('leadpeering::app.search.website-only-note')
                        </p>
                    </div>
                </div>

                <div v-if="preview" class="flex flex-col gap-4">
                    <div class="grid grid-cols-4 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-2xl font-bold text-gray-800 dark:text-white">@{{ preview.counts.total }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.total')</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-2xl font-bold text-gray-800 dark:text-white">@{{ preview.counts.with_website }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.with-website')</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center dark:border-amber-900 dark:bg-amber-900/20">
                            <div class="text-2xl font-bold text-amber-700 dark:text-amber-400">@{{ preview.counts.duplicates }}</div>
                            <div class="text-sm text-amber-600 dark:text-amber-400">@lang('leadpeering::app.search.preview.duplicates')</div>
                        </div>
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-center dark:border-green-900 dark:bg-green-900/20">
                            <div class="text-2xl font-bold text-green-700 dark:text-green-400">@{{ preview.counts.new }}</div>
                            <div class="text-sm text-green-600 dark:text-green-400">@lang('leadpeering::app.search.preview.new')</div>
                        </div>
                    </div>

                    <!-- Network-type chips — refine within this result set. Only shown
                         for 'net' searches, built from the real values present in this
                         batch, not a fixed guessed list. -->
                    <div v-if="availableTypes.length" class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="font-medium text-gray-800 dark:text-white">
                                    @lang('leadpeering::app.search.filters.types-label')
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    @lang('leadpeering::app.search.filters.types-hint')
                                </p>
                            </div>

                            <button
                                v-if="filters.types.length"
                                type="button"
                                class="text-xs text-brandColor hover:underline"
                                @click="filters.types = []"
                            >
                                @lang('leadpeering::app.search.filters.types-clear')
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="entry in availableTypes"
                                :key="entry.type"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset transition-colors"
                                :class="isTypeSelected(entry.type)
                                    ? 'bg-brandColor text-white ring-brandColor'
                                    : 'bg-blue-50 text-blue-700 ring-blue-700/10 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:ring-blue-400/20'"
                                @click="toggleType(entry.type)"
                            >
                                @{{ entry.type }}
                                <span :class="isTypeSelected(entry.type) ? 'text-white/80' : 'text-blue-700/60 dark:text-blue-400/60'">(@{{ entry.count }})</span>
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                            <span>@lang('leadpeering::app.search.preview.title')</span>

                            <button
                                type="button"
                                class="text-sm font-normal text-brandColor hover:underline"
                                @click="toggleSelectAll"
                            >
                                @{{ allSelectableSelected ? '@lang('leadpeering::app.search.preview.deselect-all')' : '@lang('leadpeering::app.search.preview.select-all')' }}
                            </button>
                        </div>

                        <p v-if="! filteredLeads.length" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            @lang('leadpeering::app.search.preview.empty')
                        </p>

                        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="w-10 px-4 py-2"></th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.col-name')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.col-location')</th>
                                    <th v-if="form.type === 'net'" class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.col-asn')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.col-website')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.col-status')</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="lead in filteredLeads" :key="lead.key" :class="lead.is_duplicate ? 'bg-amber-50/40 dark:bg-amber-900/10' : ''">
                                    <td class="px-4 py-2">
                                        <input
                                            type="checkbox"
                                            :checked="selectedKeys.has(lead.key)"
                                            :disabled="! isSelectable(lead)"
                                            @change="toggleSelect(lead)"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">@{{ lead.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="lead.city || lead.country">@{{ lead.city }}<span v-if="lead.city && lead.country">, </span>@{{ lead.country }}</span>
                                        <span v-else-if="lead.fac_count || lead.net_count">@{{ presenceLabel(lead) }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td v-if="form.type === 'net'" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">@{{ lead.asn || '—' }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <a v-if="lead.has_website" :href="lead.website" target="_blank" class="text-brandColor hover:underline">@{{ shortWebsite(lead.website) }}</a>
                                        <span v-else class="text-xs text-gray-400">@lang('leadpeering::app.search.filters.website-no')</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span v-if="lead.is_duplicate" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadpeering::app.search.preview.badge-duplicate')</span>
                                        <span v-else-if="! lead.has_website" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">@lang('leadpeering::app.search.filters.website-no')</span>
                                        <span v-else class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadpeering::app.search.preview.badge-new')</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <button type="button" class="text-brandColor hover:underline" @click="openLead(lead)">
                                            @lang('leadpeering::app.search.preview.view-btn')
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span v-if="! selectedKeys.size" class="text-sm text-gray-500 dark:text-gray-400">
                            @lang('leadpeering::app.search.preview.nothing-selected')
                        </span>

                        <template v-else>
                            <div class="flex flex-col items-end gap-1">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadpeering::app.search.preview.pipeline-label')</label>
                                    <select
                                        v-model.number="selectedPipelineId"
                                        class="custom-select rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">@{{ pipeline.name }}</option>
                                    </select>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">@lang('leadpeering::app.search.preview.pipeline-hint')</span>
                            </div>

                            <button type="button" class="primary-button" :disabled="importing || ! selectedPipelineId" @click="confirmImport">
                                <span v-if="! importing">@{{ importLabel }}</span>
                                <span v-else>@lang('leadpeering::app.search.importing')</span>
                            </button>
                        </template>
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
                                <span v-if="selectedLead.is_duplicate" class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">@lang('leadpeering::app.search.preview.badge-duplicate')</span>
                                <span v-else class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">@lang('leadpeering::app.search.preview.badge-new')</span>
                            </div>

                            <div v-if="selectedLead.has_website" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.website')</label>
                                <p class="mt-1"><a :href="selectedLead.website" target="_blank" class="break-all text-brandColor hover:underline">@{{ shortWebsite(selectedLead.website) }}</a></p>
                            </div>

                            <div v-if="selectedLead.asn">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.asn')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.asn }}</p>
                            </div>

                            <div v-if="selectedLead.info_type">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.network-type')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.info_type }}</p>
                            </div>

                            <div v-if="selectedLead.info_scope">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.scope')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.info_scope }}</p>
                            </div>

                            <div v-if="selectedLead.policy_general">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.policy')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.policy_general }}</p>
                            </div>

                            <div class="col-span-2" v-if="selectedLead.address1 || selectedLead.city">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.address')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ [selectedLead.address1, selectedLead.city, selectedLead.state, selectedLead.country].filter(Boolean).join(', ') }}</p>
                            </div>

                            <div v-if="selectedLead.region_continent">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.region')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selectedLead.region_continent }}</p>
                            </div>

                            <div v-if="presenceLabel(selectedLead)">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.presence')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ presenceLabel(selectedLead) }}</p>
                            </div>

                            <div v-if="selectedLead.sales_email || selectedLead.sales_phone" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.sales-contact')</label>
                                <p class="mt-1 break-all text-gray-900 dark:text-white">@{{ [selectedLead.sales_email, selectedLead.sales_phone].filter(Boolean).join(' · ') }}</p>
                            </div>

                            <div v-if="selectedLead.tech_email || selectedLead.tech_phone" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.tech-contact')</label>
                                <p class="mt-1 break-all text-gray-900 dark:text-white">@{{ [selectedLead.tech_email, selectedLead.tech_phone].filter(Boolean).join(' · ') }}</p>
                            </div>

                            <div class="col-span-2">
                                <a :href="`https://www.peeringdb.com/${selectedLead.peeringdb_type}/${selectedLead.peeringdb_id}`" target="_blank" class="inline-flex items-center gap-1 text-sm text-brandColor hover:underline">
                                    PeeringDB →
                                </a>
                            </div>
                        </div>

                        <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <label v-if="isSelectable(selectedLead)" class="mr-auto flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" :checked="selectedKeys.has(selectedLead.key)" @change="toggleSelect(selectedLead)" />
                                @lang('leadpeering::app.search.preview.select-this')
                            </label>

                            <button type="button" class="secondary-button" @click="closeLead">@lang('leadpeering::app.search.preview.close')</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-leadpeering-search', {
                template: '#v-leadpeering-search-template',

                data() {
                    return {
                        loading: false,
                        importing: false,
                        preview: null,
                        selectedLead: null,
                        selectedKeys: new Set(),
                        pipelines: {!! $pipelines->values()->toJson() !!},
                        selectedPipelineId: {{ optional($pipelines->firstWhere('is_default', true) ?? $pipelines->first())->id ?? 'null' }},
                        // PeeringDB's own fixed enums — offered as dropdowns rather than
                        // free text, since these are the API's real values, not a guessed
                        // list. info_type/policy_general were both directly confirmed
                        // against live data ("NSP"/"Content"/etc, "Open"/"Restrictive");
                        // info_scope's "Global" and region_continent's "North America"
                        // were directly confirmed too, the remaining entries in each are
                        // PeeringDB's documented, long-stable standard set.
                        networkTypeChoices: [
                            'NSP', 'Content', 'Cable/DSL/ISP', 'Enterprise',
                            'Educational/Research', 'Non-Profit', 'Route Server',
                            'Network Services', 'Route Collector', 'Government', 'Not Disclosed',
                        ],
                        scopeChoices: [
                            'Regional', 'North America', 'Asia Pacific', 'Europe',
                            'South America', 'Africa', 'Middle East', 'Australia', 'Global',
                        ],
                        policyChoices: ['Open', 'Selective', 'Restrictive', 'No'],
                        continentChoices: ['North America', 'South America', 'Europe', 'Asia', 'Africa', 'Oceania'],
                        brazilianStates: [
                            { code: 'AC', name: 'Acre' }, { code: 'AL', name: 'Alagoas' }, { code: 'AP', name: 'Amapá' },
                            { code: 'AM', name: 'Amazonas' }, { code: 'BA', name: 'Bahia' }, { code: 'CE', name: 'Ceará' },
                            { code: 'DF', name: 'Distrito Federal' }, { code: 'ES', name: 'Espírito Santo' }, { code: 'GO', name: 'Goiás' },
                            { code: 'MA', name: 'Maranhão' }, { code: 'MT', name: 'Mato Grosso' }, { code: 'MS', name: 'Mato Grosso do Sul' },
                            { code: 'MG', name: 'Minas Gerais' }, { code: 'PA', name: 'Pará' }, { code: 'PB', name: 'Paraíba' },
                            { code: 'PR', name: 'Paraná' }, { code: 'PE', name: 'Pernambuco' }, { code: 'PI', name: 'Piauí' },
                            { code: 'RJ', name: 'Rio de Janeiro' }, { code: 'RN', name: 'Rio Grande do Norte' }, { code: 'RS', name: 'Rio Grande do Sul' },
                            { code: 'RO', name: 'Rondônia' }, { code: 'RR', name: 'Roraima' }, { code: 'SC', name: 'Santa Catarina' },
                            { code: 'SP', name: 'São Paulo' }, { code: 'SE', name: 'Sergipe' }, { code: 'TO', name: 'Tocantins' },
                        ],
                        form: {
                            type: 'net',
                            query: '',
                            country: 'BR',
                            city: '',
                            state: '',
                            region_continent: '',
                            asn: null,
                            info_type: '',
                            info_scope: '',
                            policy_general: '',
                            limit: 50,
                        },
                        filters: {
                            minNetCount: 0,
                            hideDuplicates: false,
                            types: [],
                        },
                    };
                },

                computed: {
                    filteredLeads() {
                        if (! this.preview) {
                            return [];
                        }

                        return this.preview.leads.filter((lead) => {
                            if (this.filters.minNetCount && (lead.net_count || 0) < this.filters.minNetCount) return false;
                            if (this.filters.hideDuplicates && lead.is_duplicate) return false;
                            if (this.filters.types.length && ! this.filters.types.includes(lead.info_type)) return false;

                            return true;
                        });
                    },

                    // Every distinct info_type across the current 'net' result set,
                    // with how many results carry it — built from real data, not the
                    // fixed dropdown list, so it only ever offers types that can
                    // actually match something in this batch.
                    availableTypes() {
                        if (! this.preview || this.form.type !== 'net') {
                            return [];
                        }

                        const counts = new Map();

                        this.preview.leads.forEach((lead) => {
                            if (lead.info_type) {
                                counts.set(lead.info_type, (counts.get(lead.info_type) || 0) + 1);
                            }
                        });

                        return Array.from(counts, ([type, count]) => ({ type, count }))
                            .sort((a, b) => b.count - a.count || a.type.localeCompare(b.type));
                    },

                    allSelectableSelected() {
                        const selectable = this.filteredLeads.filter((lead) => this.isSelectable(lead));

                        return selectable.length > 0 && selectable.every((lead) => this.selectedKeys.has(lead.key));
                    },

                    importLabel() {
                        return "@lang('leadpeering::app.search.preview.import-btn')".replace(':count', this.selectedKeys.size);
                    },
                },

                methods: {
                    isSelectable(lead) {
                        return lead.has_website && ! lead.is_duplicate;
                    },

                    presenceLabel(lead) {
                        if (! lead) return '';

                        const parts = [];
                        if (lead.fac_count) parts.push(`${lead.fac_count} DCs`);
                        if (lead.net_count) parts.push(`${lead.net_count} redes`);
                        if (lead.ix_count) parts.push(`${lead.ix_count} IXs`);

                        return parts.join(' · ');
                    },

                    toggleSelect(lead) {
                        if (! this.isSelectable(lead)) {
                            return;
                        }

                        if (this.selectedKeys.has(lead.key)) {
                            this.selectedKeys.delete(lead.key);
                        } else {
                            this.selectedKeys.add(lead.key);
                        }

                        // Sets aren't reactive in-place — reassign so computed properties refresh.
                        this.selectedKeys = new Set(this.selectedKeys);
                    },

                    toggleSelectAll() {
                        const selectable = this.filteredLeads.filter((lead) => this.isSelectable(lead));
                        const next = new Set(this.selectedKeys);

                        if (this.allSelectableSelected) {
                            selectable.forEach((lead) => next.delete(lead.key));
                        } else {
                            selectable.forEach((lead) => next.add(lead.key));
                        }

                        this.selectedKeys = next;
                    },

                    isTypeSelected(type) {
                        return this.filters.types.includes(type);
                    },

                    toggleType(type) {
                        this.filters.types = this.isTypeSelected(type)
                            ? this.filters.types.filter((t) => t !== type)
                            : [...this.filters.types, type];
                    },

                    openLead(lead) {
                        this.selectedLead = lead;
                    },

                    closeLead() {
                        this.selectedLead = null;
                    },

                    shortWebsite(url) {
                        if (! url) return '—';

                        return url.replace(/^https?:\/\//, '').replace(/\/$/, '').substring(0, 40);
                    },

                    search() {
                        if (this.loading) {
                            return;
                        }

                        this.loading = true;
                        this.preview = null;
                        this.selectedKeys = new Set();
                        // A previous search's category picks won't line up with a new
                        // query's results — start each search with no type restriction.
                        this.filters.types = [];

                        this.$axios.post("{{ route('admin.leadpeering.search') }}", this.form)
                            .then((response) => {
                                this.preview = response.data;
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message ?? "@lang('leadpeering::app.search.error.request-failed', ['status' => ''])",
                                });
                            })
                            .finally(() => {
                                this.loading = false;
                            });
                    },

                    confirmImport() {
                        if (this.importing || ! this.preview || ! this.selectedKeys.size || ! this.selectedPipelineId) {
                            return;
                        }

                        this.importing = true;

                        this.$axios.post("{{ route('admin.leadpeering.import') }}", {
                            token: this.preview.token,
                            prospect_keys: Array.from(this.selectedKeys),
                            pipeline_id: this.selectedPipelineId,
                        })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                // Drop the imported rows from the preview and clear their selection;
                                // the rest of the batch stays, so a second selective import is possible.
                                const imported = new Set(this.selectedKeys);

                                this.preview.leads = this.preview.leads.filter((lead) => ! imported.has(lead.key));
                                this.selectedKeys = new Set();
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
                        this.selectedKeys = new Set();
                        this.filters.types = [];
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
