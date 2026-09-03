<v-lead-enrichment-button lead-id="{{ $lead->id }}"></v-lead-enrichment-button>

@pushOnce('scripts')
    <script type="text/x-template" id="v-lead-enrichment-button-template">
        <button
            type="button"
            class="flex items-center rounded-md bg-gray-100 p-1.5 text-2xl transition-all hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-950"
            :disabled="loading"
            :title="'@lang('lead_enrichment::app.button-title')'"
            @click="enrich"
        >
            <span :class="loading ? 'icon-repeat animate-spin' : 'icon-search'"></span>
        </button>
    </script>

    <script type="module">
        app.component('v-lead-enrichment-button', {
            template: '#v-lead-enrichment-button-template',

            props: ['leadId'],

            data() {
                return { loading: false };
            },

            methods: {
                enrich() {
                    if (this.loading) {
                        return;
                    }

                    this.loading = true;

                    this.$axios.post(`{{ url(config('app.admin_path').'/leads') }}/${this.leadId}/enrich`)
                        .then((response) => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            setTimeout(() => window.location.reload(), 800);
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? 'Error' });
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                },
            },
        });
    </script>
@endPushOnce
