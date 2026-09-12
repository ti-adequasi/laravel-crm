{{--
    Takes over the Lead-view page's existing, native "Chamadas" tab —
    rather than adding a separate new tab, per direct product feedback: the
    tab already existed and already had real use logging calls by hand, so
    a second, separate live-PBX tab would just split one kind of
    information across two places.

    Mechanically this replaces the *content* of the tab named 'call', not
    just adds to it — <x-admin::activities>'s content area picks native
    Activity-list rendering vs. a slot purely by checking whether
    `extraTypes` contains an entry with the current tab's name (see
    components/activities/index.blade.php's own
    `v-if="! extraTypes.find(type => type.name == selectedType)"`), so
    leads/view.blade.php now passes its own `:types` (the same 8 built-ins,
    minus 'call') alongside an extraTypes entry NAMED 'call' pointing here
    — no edit to that shared component itself, and the other three pages
    using it (Person/Product/Warehouse) are untouched, since they don't
    pass a `:types` override at all and keep the plain default.

    This means the rich native per-Activity rendering (attachments,
    participants, edit/delete "more actions") isn't available inside this
    consolidated view — it's a deliberately simpler, read-only merged list
    of both sources (see crm-package-development/SKILL.md's note on this
    same "no hook to add a whole tab" situation). Creating/editing a call
    note still works exactly as before, through the page's own
    Nota/Atividade quick-add buttons; this tab is where both kinds of call
    history are read back.
--}}
<v-pbx-unified-calls
    lead-id="{{ $lead->id }}"
    activities-url="{{ route('admin.leads.activities.index', $lead->id) }}"
    person-id="{{ $personId ?? '' }}"
    :pbx-history-available="{{ $pbxHistoryAvailable ? 'true' : 'false' }}"
    history-url="{{ route('admin.pbx.history.index') }}"
    recording-url-template="{{ route('admin.pbx.history.recording-url', '__UUID__') }}"
    intel-url-template="{{ route('admin.pbx.history.intel', '__UUID__') }}"
    analyze-url-template="{{ route('admin.pbx.history.analyze', '__UUID__') }}"
></v-pbx-unified-calls>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-pbx-unified-calls-template"
    >
        <div class="p-4">
            <div v-if="isLoading" class="flex flex-col gap-2">
                <div class="shimmer h-12 w-full rounded-md"></div>
                <div class="shimmer h-12 w-full rounded-md"></div>
                <div class="shimmer h-12 w-full rounded-md"></div>
            </div>

            <div
                v-else-if="! entries.length"
                class="flex flex-col items-center gap-2 py-12 text-center"
            >
                <span class="icon-call text-4xl text-gray-400 dark:text-gray-600"></span>

                <p class="text-sm font-semibold dark:text-white">
                    @lang('pbx::app.calls.history-empty-title')
                </p>

                <p class="max-w-sm text-xs text-gray-500 dark:text-gray-300">
                    @lang('pbx::app.calls.history-empty-description')
                </p>
            </div>

            <div v-else class="flex flex-col gap-2">
                <div
                    v-for="entry in entries"
                    :key="entry._key"
                    class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                >
                    <!-- A call logged in the CRM (manually, or auto-logged by click-to-call) -->
                    <template v-if="entry._source === 'activity'">
                        <div class="flex flex-col gap-1">
                            <p v-if="entry.title" class="font-medium dark:text-white">
                                @{{ entry.title }}
                            </p>

                            <p v-if="entry.comment" class="text-sm dark:text-white">
                                @{{ entry.comment }}
                            </p>

                            <p class="text-xs text-gray-500 dark:text-gray-300">
                                @{{ $admin.formatDate(entry.created_at, 'd MMM yyyy, h:mm A', timezone) }},

                                @{{ "@lang('admin::app.components.activities.index.by-user', ['user' => 'replace'])".replace('replace', entry.user?.name ?? "@lang('admin::app.components.activities.index.system')") }}
                            </p>
                        </div>
                    </template>

                    <!-- A call the PBX has on record, whether or not it went through the CRM -->
                    <template v-else>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="directionBadgeClass(entry.direction)"
                            >
                                @{{ directionLabel(entry.direction) }}
                            </span>

                            <span
                                v-if="entry.answered === false"
                                class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
                            >
                                @lang('pbx::app.calls.history-missed')
                            </span>

                            <span class="text-sm font-medium dark:text-white">
                                @{{ otherPartyNumber(entry) }}
                            </span>

                            <span class="text-xs text-gray-500 dark:text-gray-300">
                                @{{ $admin.formatDate(entry.start_stamp, 'd MMM yyyy, h:mm A', timezone) }}
                            </span>

                            <span v-if="entry.duration != null" class="text-xs text-gray-500 dark:text-gray-300">
                                @{{ formatDuration(entry.duration) }}
                            </span>

                            <span v-if="entry.has_recording" class="ml-auto flex items-center gap-1">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md px-2 py-1 text-xs font-medium text-brandColor transition-all hover:bg-gray-100 disabled:cursor-wait disabled:opacity-50 dark:hover:bg-gray-950"
                                    :disabled="loadingRecordingUuid === entry.xml_cdr_uuid"
                                    @click="togglePlay(entry)"
                                >
                                    @{{ loadingRecordingUuid === entry.xml_cdr_uuid
                                        ? "@lang('pbx::app.calls.history-loading-recording')"
                                        : (playingUuid === entry.xml_cdr_uuid ? "@lang('pbx::app.calls.history-pause')" : "@lang('pbx::app.calls.history-play')") }}
                                </button>

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md px-2 py-1 text-xs font-medium text-brandColor transition-all hover:bg-gray-100 disabled:cursor-wait disabled:opacity-50 dark:hover:bg-gray-950"
                                    :disabled="downloadingUuid === entry.xml_cdr_uuid"
                                    @click="downloadRecording(entry)"
                                >
                                    @{{ downloadingUuid === entry.xml_cdr_uuid
                                        ? "@lang('pbx::app.calls.history-loading-recording')"
                                        : "@lang('pbx::app.calls.history-download')" }}
                                </button>

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md px-2 py-1 text-xs font-medium text-brandColor transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                    @click="toggleIntel(entry)"
                                >
                                    @lang('pbx::app.calls.history-transcript-button')
                                </button>
                            </span>
                        </div>

                        <audio
                            v-if="audioUrls[entry.xml_cdr_uuid]"
                            :ref="'audio-' + entry.xml_cdr_uuid"
                            :src="audioUrls[entry.xml_cdr_uuid]"
                            controls
                            class="h-8 w-full"
                            @ended="playingUuid = null"
                        ></audio>

                        <!-- AI transcript + summary, fetched on demand -->
                        <div
                            v-if="openIntelUuid === entry.xml_cdr_uuid"
                            class="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-950"
                        >
                            <div v-if="intelLoading[entry.xml_cdr_uuid]" class="flex flex-col gap-2">
                                <div class="shimmer h-4 w-full rounded-md"></div>
                                <div class="shimmer h-4 w-2/3 rounded-md"></div>
                            </div>

                            <div v-else-if="hasCompletedAnalysis(entry.xml_cdr_uuid)" class="flex flex-col gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        v-if="qaResult(entry.xml_cdr_uuid)?.sentimento"
                                        class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        @lang('pbx::app.calls.history-sentiment-label'): @{{ qaResult(entry.xml_cdr_uuid).sentimento }}
                                    </span>

                                    <span
                                        v-if="qaResult(entry.xml_cdr_uuid)?.nota != null"
                                        class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        @lang('pbx::app.calls.history-score-label'): @{{ qaResult(entry.xml_cdr_uuid).nota }}
                                    </span>

                                    <span
                                        v-if="qaResult(entry.xml_cdr_uuid)?.categoria"
                                        class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        @{{ qaResult(entry.xml_cdr_uuid).categoria }}
                                    </span>
                                </div>

                                <div v-if="qaResult(entry.xml_cdr_uuid)?.resumo">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        @lang('pbx::app.calls.history-summary-label')
                                    </p>
                                    <p class="dark:text-white">@{{ qaResult(entry.xml_cdr_uuid).resumo }}</p>
                                </div>

                                <details v-if="transcriptResult(entry.xml_cdr_uuid)?.text">
                                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        @lang('pbx::app.calls.history-transcript-label')
                                    </summary>

                                    <p class="mt-2 whitespace-pre-line dark:text-white">@{{ transcriptResult(entry.xml_cdr_uuid).text }}</p>
                                </details>
                            </div>

                            <div v-else class="flex flex-col items-start gap-2">
                                <p class="text-xs text-gray-500 dark:text-gray-300">
                                    @{{ analyzing[entry.xml_cdr_uuid]
                                        ? "@lang('pbx::app.calls.history-analysis-pending')"
                                        : "@lang('pbx::app.calls.history-no-analysis-yet')" }}
                                </p>

                                <button
                                    type="button"
                                    class="secondary-button"
                                    :disabled="analyzing[entry.xml_cdr_uuid]"
                                    @click="generateAnalysis(entry)"
                                >
                                    @{{ analyzing[entry.xml_cdr_uuid]
                                        ? "@lang('pbx::app.calls.history-generating')"
                                        : "@lang('pbx::app.calls.history-generate-analysis')" }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-pbx-unified-calls', {
            template: '#v-pbx-unified-calls-template',

            props: {
                leadId: {
                    type: [String, Number],
                    required: true,
                },

                activitiesUrl: {
                    type: String,
                    required: true,
                },

                personId: {
                    type: [String, Number],
                    default: null,
                },

                pbxHistoryAvailable: {
                    type: Boolean,
                    default: false,
                },

                historyUrl: {
                    type: String,
                    required: true,
                },

                recordingUrlTemplate: {
                    type: String,
                    required: true,
                },

                intelUrlTemplate: {
                    type: String,
                    required: true,
                },

                analyzeUrlTemplate: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    isLoading: true,
                    entries: [],
                    audioUrls: {},
                    playingUuid: null,
                    loadingRecordingUuid: null,
                    downloadingUuid: null,
                    timezone: "{{ config('app.timezone') }}",
                    openIntelUuid: null,
                    intelData: {},
                    intelLoading: {},
                    analyzing: {},
                    intelPollTimeouts: {},
                };
            },

            created() {
                this.load();
            },

            // Mirrors v-pbx-call-button's own reasoning (see
            // call-button.blade.php): a poll started for one call must not
            // keep running once this whole tab's component is gone.
            beforeUnmount() {
                Object.values(this.intelPollTimeouts).forEach((timeout) => clearTimeout(timeout));
            },

            methods: {
                load() {
                    this.isLoading = true;

                    const requests = [
                        this.$axios.get(this.activitiesUrl).then((response) => {
                            return (response.data.data ?? [])
                                .filter((activity) => activity.type === 'call')
                                .map((activity) => ({ ...activity, _source: 'activity', _key: 'activity-' + activity.id, _sortAt: activity.created_at }));
                        }),
                    ];

                    if (this.pbxHistoryAvailable) {
                        requests.push(
                            this.$axios.get(this.historyUrl, { params: { person_id: this.personId } }).then((response) => {
                                return (response.data.calls ?? [])
                                    .map((call) => ({ ...call, _source: 'pbx', _key: 'pbx-' + call.xml_cdr_uuid, _sortAt: call.start_stamp }));
                            })
                        );
                    }

                    Promise.all(requests)
                        .then((results) => {
                            this.entries = results.flat().sort((a, b) => new Date(b._sortAt) - new Date(a._sortAt));
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error?.response?.data?.message || "@lang('pbx::app.calls.history-fetch-failed-generic')",
                            });
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                },

                directionLabel(direction) {
                    return {
                        inbound: "@lang('pbx::app.calls.history-inbound')",
                        outbound: "@lang('pbx::app.calls.history-outbound')",
                        local: "@lang('pbx::app.calls.history-local')",
                    }[direction] || direction || '?';
                },

                directionBadgeClass(direction) {
                    return {
                        inbound: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
                        outbound: 'bg-sky-100 text-sky-800 dark:bg-sky-900/20 dark:text-sky-400',
                        local: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                    }[direction] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
                },

                otherPartyNumber(entry) {
                    return entry.direction === 'inbound'
                        ? (entry.caller_id_number || entry.source_number || '?')
                        : (entry.destination_number || '?');
                },

                formatDuration(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;

                    return `${m}:${String(s).padStart(2, '0')}`;
                },

                // Shared by togglePlay() and downloadRecording() — both need
                // the same short-lived signed URL, cached once fetched.
                fetchRecordingUrl(uuid) {
                    if (this.audioUrls[uuid]) {
                        return Promise.resolve(this.audioUrls[uuid]);
                    }

                    return this.$axios.get(this.recordingUrlTemplate.replace('__UUID__', uuid))
                        .then((response) => {
                            this.audioUrls = { ...this.audioUrls, [uuid]: response.data.url };

                            return response.data.url;
                        });
                },

                togglePlay(entry) {
                    const uuid = entry.xml_cdr_uuid;

                    if (this.playingUuid === uuid) {
                        this.playingUuid = null;

                        return;
                    }

                    this.loadingRecordingUuid = uuid;

                    this.fetchRecordingUrl(uuid)
                        .then(() => {
                            this.playingUuid = uuid;

                            this.$nextTick(() => this.$refs['audio-' + uuid]?.[0]?.play?.());
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error?.response?.data?.message || "@lang('pbx::app.calls.recording-fetch-failed-generic')",
                            });
                        })
                        .finally(() => {
                            this.loadingRecordingUuid = null;
                        });
                },

                // The signed URL comes from a different origin than the CRM
                // itself (the PBX's own host), so the `download` attribute
                // isn't reliably honored by every browser on a cross-origin
                // link — opening it in a new tab is the fallback that
                // always at least gets the user to the audio file, where
                // they can save it manually if their browser didn't already
                // prompt to.
                downloadRecording(entry) {
                    const uuid = entry.xml_cdr_uuid;

                    this.downloadingUuid = uuid;

                    this.fetchRecordingUrl(uuid)
                        .then((url) => {
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = '';
                            link.target = '_blank';
                            link.rel = 'noopener';
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error?.response?.data?.message || "@lang('pbx::app.calls.recording-fetch-failed-generic')",
                            });
                        })
                        .finally(() => {
                            this.downloadingUuid = null;
                        });
                },

                qaResult(uuid) {
                    return this.intelData[uuid]?.modules?.qa?.result ?? null;
                },

                transcriptResult(uuid) {
                    return this.intelData[uuid]?.modules?.transcript?.result ?? null;
                },

                // "done" is the only status value confirmed against a real
                // analyzed call — the API gives no enumeration of the
                // others, so anything else (absent, "processing", "error",
                // ...) is treated as "not ready to show yet" rather than
                // guessed at.
                hasCompletedAnalysis(uuid) {
                    return this.intelData[uuid]?.modules?.transcript?.status === 'done';
                },

                toggleIntel(entry) {
                    const uuid = entry.xml_cdr_uuid;

                    if (this.openIntelUuid === uuid) {
                        this.openIntelUuid = null;

                        return;
                    }

                    this.openIntelUuid = uuid;

                    if (! this.intelData[uuid]) {
                        this.loadIntel(uuid);
                    }
                },

                loadIntel(uuid) {
                    this.intelLoading = { ...this.intelLoading, [uuid]: true };

                    this.$axios.get(this.intelUrlTemplate.replace('__UUID__', uuid))
                        .then((response) => {
                            this.intelData = { ...this.intelData, [uuid]: response.data };
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error?.response?.data?.message || "@lang('pbx::app.calls.intel-fetch-failed-generic')",
                            });
                        })
                        .finally(() => {
                            this.intelLoading = { ...this.intelLoading, [uuid]: false };
                        });
                },

                // A real, costed PBX AI operation — only ever runs from an
                // explicit click on "Gerar", never automatically. "roda em
                // background" per the API's own docs, so this polls
                // loadIntel() afterward rather than expecting the analysis
                // done by the time this request returns.
                generateAnalysis(entry) {
                    const uuid = entry.xml_cdr_uuid;

                    this.analyzing = { ...this.analyzing, [uuid]: true };

                    this.$axios.post(this.analyzeUrlTemplate.replace('__UUID__', uuid))
                        .then(() => this.pollIntelUntilDone(uuid, Date.now()))
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error?.response?.data?.message || "@lang('pbx::app.calls.analyze-failed-generic')",
                            });
                            this.analyzing = { ...this.analyzing, [uuid]: false };
                        });
                },

                pollIntelUntilDone(uuid, startedAt) {
                    // A generous but finite cap (~10 minutes), matching
                    // v-pbx-call-button's own reasoning: transcription time
                    // scales with call length and this app has no visibility
                    // into how long the PBX's own AI backend actually takes.
                    if (Date.now() - startedAt > 10 * 60 * 1000) {
                        this.analyzing = { ...this.analyzing, [uuid]: false };

                        return;
                    }

                    this.$axios.get(this.intelUrlTemplate.replace('__UUID__', uuid))
                        .then((response) => {
                            this.intelData = { ...this.intelData, [uuid]: response.data };

                            if (response.data?.modules?.transcript?.status === 'done') {
                                this.analyzing = { ...this.analyzing, [uuid]: false };

                                return;
                            }

                            this.intelPollTimeouts[uuid] = setTimeout(() => this.pollIntelUntilDone(uuid, startedAt), 4000);
                        })
                        .catch(() => {
                            this.intelPollTimeouts[uuid] = setTimeout(() => this.pollIntelUntilDone(uuid, startedAt), 4000);
                        });
                },
            },
        });
    </script>
@endPushOnce
