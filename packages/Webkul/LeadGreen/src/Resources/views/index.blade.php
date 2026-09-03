<x-admin::layouts>
    <x-slot:title>
        @lang('leadgreen::app.title')
    </x-slot>

    @php
        $pipelines = \Webkul\Lead\Models\PipelineProxy::modelClass()::orderBy('name')->get(['id', 'name', 'is_default']);
    @endphp

    <v-leadgreen></v-leadgreen>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-leadgreen-template">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('leadgreen::app.title')
                    </div>

                    <div class="flex items-center gap-x-2.5">
                        <a href="{{ route('admin.leadgreen.search.form') }}" class="primary-button">
                            @lang('leadgreen::app.search-button')
                        </a>
                    </div>
                </div>

                <x-admin::datagrid :src="route('admin.leadgreen.index')" ref="datagrid" />

                <div v-if="selected" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="close">
                    <div class="relative max-h-[90vh] w-full max-w-2xl overflow-auto rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@{{ selected.name }}</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="close">&times;</button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-6 text-sm">
                            <div v-if="selected.phone_number">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.phone')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.phone_number }}</p>
                            </div>
                            <div v-if="selected.website">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.website')</label>
                                <p class="mt-1"><a :href="selected.website" target="_blank" class="text-brandColor hover:underline">@{{ selected.website }}</a></p>
                            </div>
                            <div class="col-span-2" v-if="selected.full_address">
                                <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.modal.address')</label>
                                <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.full_address }}</p>
                            </div>

                            <template v-if="selected.enrichment_status === 'enriched'">
                                <div class="col-span-2 mt-2 border-t border-gray-200 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                                    @lang('leadgreen::app.enrichment.title')
                                </div>
                                <div v-if="selected.email">
                                    <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.email')</label>
                                    <p class="mt-1"><a :href="'mailto:' + selected.email" class="text-brandColor hover:underline">@{{ selected.email }}</a></p>
                                </div>
                                <div v-if="selected.whatsapp">
                                    <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.whatsapp')</label>
                                    <p class="mt-1 text-gray-900 dark:text-white">@{{ selected.whatsapp }}</p>
                                </div>
                                <div v-if="selected.instagram">
                                    <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.instagram')</label>
                                    <p class="mt-1"><a :href="selected.instagram" target="_blank" class="text-brandColor hover:underline">@{{ selected.instagram }}</a></p>
                                </div>
                                <div v-if="selected.facebook">
                                    <label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.facebook')</label>
                                    <p class="mt-1"><a :href="selected.facebook" target="_blank" class="text-brandColor hover:underline">@{{ selected.facebook }}</a></p>
                                </div>

                                <template v-if="selected.cnpj">
                                    <div class="col-span-2 mt-2 border-t border-gray-200 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                                        @lang('leadgreen::app.enrichment.company-title')
                                    </div>
                                    <div><label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.cnpj')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.cnpj }}</p></div>
                                    <div v-if="selected.razao_social"><label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.razao-social')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.razao_social }}</p></div>
                                    <div v-if="selected.situacao_cadastral"><label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.situacao')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.situacao_cadastral }}</p></div>
                                    <div v-if="selected.porte"><label class="text-xs font-medium text-gray-500">@lang('leadgreen::app.enrichment.porte')</label><p class="mt-1 text-gray-900 dark:text-white">@{{ selected.porte }}</p></div>
                                </template>

                                <div class="col-span-2" v-if="selected.has_privacy_policy || selected.has_dpo">
                                    <div class="mt-2 border-t border-gray-200 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white">
                                        @lang('leadgreen::app.enrichment.privacy-title')
                                    </div>
                                    <p v-if="selected.has_dpo" class="mt-1 text-gray-900 dark:text-white">@{{ selected.dpo_name }} @{{ selected.dpo_email ? '<' + selected.dpo_email + '>' : '' }}</p>
                                </div>
                            </template>
                            <div class="col-span-2 text-sm text-gray-500 dark:text-gray-400" v-else>
                                @lang('leadgreen::app.enrichment.not-enriched')
                            </div>
                        </div>

                        <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                            <button type="button" class="secondary-button" @click="close">@lang('leadgreen::app.modal.close')</button>
                        </div>
                    </div>
                </div>

                <!-- Convert-to-opportunity modal — replaces a plain confirm() so the
                     pipeline is always a deliberate choice, never a silent default. -->
                <div v-if="convertId" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="closeConvert">
                    <div class="w-full max-w-sm rounded-lg bg-white shadow-xl dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">@lang('leadgreen::app.modal.convert-title')</h3>
                            <button type="button" class="text-2xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeConvert">&times;</button>
                        </div>

                        <div class="flex flex-col gap-3 p-6 text-sm">
                            <p class="text-gray-600 dark:text-gray-300">@lang('leadgreen::app.modal.confirm-convert')</p>

                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('leadgreen::app.modal.convert-pipeline')</label>
                                <select
                                    v-model.number="convertPipelineId"
                                    class="custom-select rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">@{{ pipeline.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                            <button type="button" class="secondary-button" @click="closeConvert">@lang('leadgreen::app.modal.cancel')</button>
                            <button type="button" class="primary-button" :disabled="! convertPipelineId" @click="confirmConvert">@lang('leadgreen::app.modal.convert-btn')</button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-leadgreen', {
                template: '#v-leadgreen-template',

                data() {
                    return {
                        selected: null,
                        pipelines: {!! $pipelines->values()->toJson() !!},
                        convertId: null,
                        convertPipelineId: {{ optional($pipelines->firstWhere('is_default', true) ?? $pipelines->first())->id ?? 'null' }},
                    };
                },

                created() {
                    window.openLeadGreenModal = (id) => this.open(id);

                    window.convertLead = (id) => this.openConvert(id);

                    window.discardLead = (id) => this.discard(id);
                },

                methods: {
                    open(id) {
                        this.$axios.get(`{{ url(config('app.admin_path').'/leadgreen/view') }}/${id}`)
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

                        this.$axios.get(`{{ url(config('app.admin_path').'/leadgreen/convert') }}/${this.convertId}`, {
                            params: { pipeline_id: this.convertPipelineId },
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

                    discard(id) {
                        const reason = prompt("@lang('leadgreen::app.modal.discard-reason-prompt')");

                        if (! reason) {
                            return;
                        }

                        this.$axios.post(`{{ url(config('app.admin_path').'/leadgreen/discard') }}/${id}`, { reason })
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                this.$refs.datagrid?.get?.();
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Error' });
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
