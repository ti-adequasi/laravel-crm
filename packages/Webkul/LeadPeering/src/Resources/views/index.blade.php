<x-admin::layouts>
    <x-slot:title>
        @lang('leadpeering::app.title')
    </x-slot>

    @php
        $pipelines = \Webkul\Lead\Models\PipelineProxy::modelClass()::orderBy('name')->get(['id', 'name', 'is_default']);
    @endphp

    <v-leadpeering></v-leadpeering>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-leadpeering-template">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('leadpeering::app.title')
                    </div>

                    <div class="flex items-center gap-x-2.5">
                        <a href="{{ route('admin.leadpeering.search.form') }}" class="primary-button">
                            @lang('leadpeering::app.search-button')
                        </a>
                    </div>
                </div>

                <!-- Enrichment progress banner. leadpeering:enrich-pending runs on a
                     schedule, not synchronously on import — without this, a user who
                     just imported prospects has no way to tell it's working versus
                     stuck. Polls only while something is actually pending, and stops
                     itself the moment the backlog clears (see pollEnrichmentStatus()). -->
                <div v-if="enrichmentStatus && enrichmentStatus.pending > 0" class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm dark:border-amber-900 dark:bg-amber-900/20">
                    <x-admin::spinner class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    <span class="text-amber-800 dark:text-amber-400">
                        @lang('leadpeering::app.enrichment.in-progress')
                        @{{ enrichmentStatus.pending }} @lang('leadpeering::app.enrichment.pending-suffix') (@{{ enrichmentStatus.percent }}%)
                    </span>
                    <div class="ml-auto h-1.5 w-32 overflow-hidden rounded-full bg-amber-200 dark:bg-amber-900/40">
                        <div class="h-full bg-amber-500" :style="{ width: enrichmentStatus.percent + '%' }"></div>
                    </div>
                </div>

                <x-admin::datagrid :src="route('admin.leadpeering.index')" ref="datagrid" />

                <div v-if="selected" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="close">
                    <div class="relative max-h-[90vh] w-full max-w-2xl overflow-auto rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@{{ selected.name }}</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="close">&times;</button>
                        </div>

                        <div v-if="selected.lead_status === 'convertido' && selected.opportunity_id" class="mx-6 mt-4 flex items-center justify-between rounded-md bg-green-50 px-4 py-3 text-sm dark:bg-green-900/20">
                            <span class="font-medium text-green-800 dark:text-green-400">@lang('leadpeering::app.modal.already-converted')</span>
                            <a :href="`{{ url(config('app.admin_path').'/leads/view') }}/${selected.opportunity_id}`" class="font-medium text-brandColor hover:underline">@lang('leadpeering::app.datagrid.view-opportunity') →</a>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-6 text-sm">
                            <div v-if="selected.website" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.website')</label>
                                <p class="mt-1"><a :href="selected.website" target="_blank" class="break-all text-brandColor hover:underline">@{{ selected.website }}</a></p>
                            </div>

                            <div v-if="selected.asn">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.asn')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.asn }}</p>
                            </div>

                            <div v-if="selected.info_type">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.network-type')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.info_type }}</p>
                            </div>

                            <div v-if="selected.info_scope">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.scope')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.info_scope }}</p>
                            </div>

                            <div v-if="selected.info_traffic">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.info-traffic')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.info_traffic }}</p>
                            </div>

                            <div v-if="selected.policy_general">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.policy')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.policy_general }}</p>
                            </div>

                            <div class="col-span-2" v-if="fullAddress">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.address')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ fullAddress }}</p>
                            </div>

                            <div v-if="selected.region_continent">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.region')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.region_continent }}</p>
                            </div>

                            <div v-if="presenceLabel">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.presence')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ presenceLabel }}</p>
                            </div>

                            <!-- sales_email/tech_email/*_phone come straight from
                                 PeeringDB's own facility listing — real contacts, not
                                 something enrichment had to scrape a website for. -->
                            <div v-if="selected.sales_email || selected.sales_phone" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.sales-contact')</label>
                                <p class="mt-1 break-all text-gray-900 dark:text-white">@{{ [selected.sales_email, selected.sales_phone].filter(Boolean).join(' · ') }}</p>
                            </div>

                            <div v-if="selected.tech_email || selected.tech_phone" class="min-w-0">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.tech-contact')</label>
                                <p class="mt-1 break-all text-gray-900 dark:text-white">@{{ [selected.tech_email, selected.tech_phone].filter(Boolean).join(' · ') }}</p>
                            </div>

                            <div class="col-span-2" v-if="socialLinks.length">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.modal.social-media')</label>
                                <div class="mt-1 flex flex-col gap-1">
                                    <a v-for="s in socialLinks" :key="s.identifier" :href="s.identifier" target="_blank" class="break-all text-brandColor hover:underline">@{{ s.service }}: @{{ s.identifier }}</a>
                                </div>
                            </div>

                            <div class="col-span-2">
                                <a :href="`https://www.peeringdb.com/${selected.peeringdb_type}/${selected.peeringdb_id}`" target="_blank" class="inline-flex items-center gap-1 text-sm text-brandColor hover:underline">
                                    PeeringDB →
                                </a>
                            </div>

                            <template v-if="selected.enrichment_status === 'enriched'">
                                <div class="col-span-2 mt-2 border-t border-gray-200 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                                    @lang('leadpeering::app.enrichment.title')
                                </div>
                                <div v-if="selected.email" class="min-w-0">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.email')</label>
                                    <p class="mt-1 flex flex-wrap items-center gap-2">
                                        <a :href="'mailto:' + selected.email" class="break-all text-brandColor hover:underline">@{{ selected.email }}</a>
                                        <span v-if="selected.email_verified === true" class="inline-flex shrink-0 items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/20 dark:text-green-400" title="@lang('leadpeering::app.enrichment.email-verified-info')">✓ @lang('leadpeering::app.enrichment.email-verified')</span>
                                        <span v-else-if="selected.email_verified === false" class="inline-flex shrink-0 items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/20 dark:text-red-400" title="@lang('leadpeering::app.enrichment.email-unverified-info')">⚠ @lang('leadpeering::app.enrichment.email-unverified')</span>
                                    </p>
                                </div>
                                <div v-if="selected.whatsapp">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.whatsapp')</label>
                                    <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.whatsapp }}</p>
                                </div>
                                <div v-if="selected.instagram" class="min-w-0">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.instagram')</label>
                                    <p class="mt-1"><a :href="selected.instagram" target="_blank" class="break-all text-brandColor hover:underline">@{{ selected.instagram }}</a></p>
                                </div>
                                <div v-if="selected.facebook" class="min-w-0">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.facebook')</label>
                                    <p class="mt-1"><a :href="selected.facebook" target="_blank" class="break-all text-brandColor hover:underline">@{{ selected.facebook }}</a></p>
                                </div>

                                <template v-if="selected.cnpj">
                                    <div class="col-span-2 mt-2 border-t border-gray-200 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                                        @lang('leadpeering::app.enrichment.company-title')
                                    </div>
                                    <div><label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.cnpj')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.cnpj }}</p></div>
                                    <div v-if="selected.razao_social"><label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.razao-social')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.razao_social }}</p></div>
                                    <div v-if="selected.situacao_cadastral"><label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.situacao')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.situacao_cadastral }}</p></div>
                                    <div v-if="selected.porte"><label class="text-xs font-medium text-gray-500 dark:text-gray-400">@lang('leadpeering::app.enrichment.porte')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.porte }}</p></div>
                                </template>
                            </template>
                            <div class="col-span-2 text-sm text-gray-500 dark:text-gray-400" v-else>
                                @lang('leadpeering::app.enrichment.not-enriched')
                            </div>
                        </div>

                        <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <button type="button" class="secondary-button" @click="close">@lang('leadpeering::app.modal.close')</button>
                        </div>
                    </div>
                </div>

                <!-- Convert-to-opportunity modal — replaces a plain confirm() so the
                     pipeline is always a deliberate choice, never a silent default. -->
                <div v-if="convertId" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="closeConvert">
                    <div class="w-full max-w-sm rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@lang('leadpeering::app.modal.convert-title')</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeConvert">&times;</button>
                        </div>

                        <div class="flex flex-col gap-3 p-6 text-sm">
                            <p class="text-gray-600 dark:text-gray-300">@lang('leadpeering::app.modal.confirm-convert')</p>

                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadpeering::app.modal.convert-pipeline')</label>
                                <select
                                    v-model.number="convertPipelineId"
                                    class="custom-select rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">@{{ pipeline.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                            <button type="button" class="secondary-button" @click="closeConvert">@lang('leadpeering::app.modal.cancel')</button>
                            <button type="button" class="primary-button" :disabled="! convertPipelineId" @click="confirmConvert">@lang('leadpeering::app.modal.convert-btn')</button>
                        </div>
                    </div>
                </div>

                <!-- Discard modal — a themed, dark-mode-aware dialog instead of a
                     native prompt(), matching the convert modal above. -->
                <div v-if="discardId" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="closeDiscard">
                    <div class="w-full max-w-sm rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@lang('leadpeering::app.datagrid.discard')</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeDiscard">&times;</button>
                        </div>

                        <div class="flex flex-col gap-3 p-6 text-sm">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadpeering::app.modal.discard-reason-prompt')</label>
                                <input
                                    type="text"
                                    v-model="discardReason"
                                    maxlength="255"
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    @keyup.enter="confirmDiscard"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                            <button type="button" class="secondary-button" @click="closeDiscard">@lang('leadpeering::app.modal.cancel')</button>
                            <button type="button" class="primary-button" :disabled="! discardReason" @click="confirmDiscard">@lang('leadpeering::app.datagrid.discard')</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-leadpeering', {
                template: '#v-leadpeering-template',

                data() {
                    return {
                        selected: null,
                        pipelines: {!! $pipelines->values()->toJson() !!},
                        convertId: null,
                        convertPipelineId: {{ optional($pipelines->firstWhere('is_default', true) ?? $pipelines->first())->id ?? 'null' }},
                        discardId: null,
                        discardReason: '',
                        enrichmentStatus: null,
                        pollTimer: null,
                    };
                },

                computed: {
                    fullAddress() {
                        if (! this.selected) return '';

                        return [this.selected.address1, this.selected.city, this.selected.state, this.selected.country]
                            .filter(Boolean)
                            .join(', ');
                    },

                    presenceLabel() {
                        if (! this.selected) return '';

                        const parts = [];
                        if (this.selected.fac_count) parts.push(`${this.selected.fac_count} data centers`);
                        if (this.selected.net_count) parts.push(`${this.selected.net_count} redes`);
                        if (this.selected.ix_count) parts.push(`${this.selected.ix_count} IXs`);

                        return parts.join(' · ');
                    },

                    socialLinks() {
                        if (! this.selected || ! Array.isArray(this.selected.social_media)) return [];

                        return this.selected.social_media.filter((s) => s && s.identifier && s.service !== 'website');
                    },
                },

                created() {
                    window.openLeadPeeringModal = (id) => this.open(id);

                    window.convertLeadPeering = (id) => this.openConvert(id);

                    window.discardLeadPeering = (id) => this.openDiscard(id);

                    this.pollEnrichmentStatus();
                },

                beforeUnmount() {
                    // leadpeering:enrich-pending runs on a schedule, independent of
                    // this page — nothing breaks if the poll stops early — but a
                    // pending setTimeout would otherwise keep firing (and keep this
                    // component's methods alive) after the user navigates away.
                    clearTimeout(this.pollTimer);
                },

                methods: {
                    // Polls the enrichment-status endpoint only while a backlog
                    // actually exists, and stops itself the moment it clears —
                    // never runs forever on a page nobody imported anything on.
                    pollEnrichmentStatus() {
                        this.$axios.get("{{ route('admin.leadpeering.enrichment-status') }}")
                            .then((response) => {
                                const wasPending = this.enrichmentStatus?.pending > 0;

                                this.enrichmentStatus = response.data;

                                if (response.data.pending > 0) {
                                    this.pollTimer = setTimeout(() => this.pollEnrichmentStatus(), 8000);
                                } else if (wasPending) {
                                    // The backlog just cleared — refresh once so newly-
                                    // enriched badges appear without the user having to
                                    // reload by hand. Not refreshed on every tick while
                                    // still pending, so an in-progress import doesn't
                                    // keep disrupting whatever the user is doing on the
                                    // grid (scroll position, open filters, selection).
                                    this.$refs.datagrid?.get?.();
                                }
                            })
                            .catch(() => {
                                // Transient failure — try again on the same schedule
                                // rather than surfacing an error for a background
                                // convenience banner.
                                this.pollTimer = setTimeout(() => this.pollEnrichmentStatus(), 8000);
                            });
                    },

                    open(id) {
                        this.$axios.get(`{{ url(config('app.admin_path').'/leadpeering/view') }}/${id}`)
                            .then((response) => {
                                this.selected = response.data.lead;
                            });
                    },

                    close() {
                        this.selected = null;
                    },

                    openConvert(id) {
                        this.convertId = id;
                    },

                    closeConvert() {
                        this.convertId = null;
                    },

                    confirmConvert() {
                        if (! this.convertId || ! this.convertPipelineId) {
                            return;
                        }

                        this.$axios.post(`{{ url(config('app.admin_path').'/leadpeering/convert') }}/${this.convertId}`, {
                            pipeline_id: this.convertPipelineId,
                        })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                window.location.href = response.data.redirect;
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Error' });
                            })
                            .finally(() => {
                                this.closeConvert();
                            });
                    },

                    openDiscard(id) {
                        this.discardId = id;
                        this.discardReason = '';
                    },

                    closeDiscard() {
                        this.discardId = null;
                        this.discardReason = '';
                    },

                    confirmDiscard() {
                        if (! this.discardId || ! this.discardReason) {
                            return;
                        }

                        this.$axios.post(`{{ url(config('app.admin_path').'/leadpeering/discard') }}/${this.discardId}`, { reason: this.discardReason })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                this.$refs.datagrid?.get?.();
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Error' });
                            })
                            .finally(() => {
                                this.closeDiscard();
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
