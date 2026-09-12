{{--
    Injected into the Lead-view page's activities tab bar as its own
    "Call History" tab — packages/Webkul/Admin/src/Resources/views/leads/view.blade.php
    has no hook to append a new tab from outside (its `extra-types` array
    is a plain PHP literal, not filtered through any view_render_event),
    so that file has one small, directly-justified addition (an array
    entry + this include) — see the comment left there, and
    crm-package-development/SKILL.md's note on this same situation
    recurring from Phase 3's person.blade.php change.

    Deliberately NOT server-rendered with data — this queries the PBX
    live, on tab open, via GET admin.pbx.history.index. See
    PbxCallHistoryController's own docblock for why this isn't synced into
    Activity rows the way the auto-logged click-to-call Activities are.
--}}
<v-pbx-call-history
    person-id="{{ $personId }}"
    history-url="{{ route('admin.pbx.history.index') }}"
    recording-url-template="{{ route('admin.pbx.history.recording-url', '__UUID__') }}"
></v-pbx-call-history>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-pbx-call-history-template"
    >
        <div class="p-4">
            <div v-if="isLoading" class="flex flex-col gap-2">
                <div class="shimmer h-12 w-full rounded-md"></div>
                <div class="shimmer h-12 w-full rounded-md"></div>
                <div class="shimmer h-12 w-full rounded-md"></div>
            </div>

            <div
                v-else-if="! calls.length"
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
                    v-for="call in calls"
                    :key="call.xml_cdr_uuid"
                    class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="directionBadgeClass(call.direction)"
                        >
                            @{{ directionLabel(call.direction) }}
                        </span>

                        <span
                            v-if="call.answered === false"
                            class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
                        >
                            @lang('pbx::app.calls.history-missed')
                        </span>

                        <span class="text-sm font-medium dark:text-white">
                            @{{ otherPartyNumber(call) }}
                        </span>

                        <span class="text-xs text-gray-500 dark:text-gray-300">
                            @{{ formatWhen(call.start_stamp) }}
                        </span>

                        <span v-if="call.duration != null" class="text-xs text-gray-500 dark:text-gray-300">
                            @{{ formatDuration(call.duration) }}
                        </span>

                        <button
                            v-if="call.has_recording"
                            type="button"
                            class="ml-auto cursor-pointer rounded-md px-2 py-1 text-xs font-medium text-brandColor transition-all hover:bg-gray-100 disabled:cursor-wait disabled:opacity-50 dark:hover:bg-gray-950"
                            :disabled="loadingRecordingUuid === call.xml_cdr_uuid"
                            @click="togglePlay(call)"
                        >
                            @{{ loadingRecordingUuid === call.xml_cdr_uuid
                                ? "@lang('pbx::app.calls.history-loading-recording')"
                                : (playingUuid === call.xml_cdr_uuid ? "@lang('pbx::app.calls.history-pause')" : "@lang('pbx::app.calls.history-play')") }}
                        </button>
                    </div>

                    <audio
                        v-if="audioUrls[call.xml_cdr_uuid]"
                        :ref="'audio-' + call.xml_cdr_uuid"
                        :src="audioUrls[call.xml_cdr_uuid]"
                        controls
                        class="h-8 w-full"
                        @ended="playingUuid = null"
                    ></audio>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-pbx-call-history', {
            template: '#v-pbx-call-history-template',

            props: {
                personId: {
                    type: [String, Number],
                    required: true,
                },

                historyUrl: {
                    type: String,
                    required: true,
                },

                recordingUrlTemplate: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    isLoading: true,
                    calls: [],
                    audioUrls: {},
                    playingUuid: null,
                    loadingRecordingUuid: null,
                };
            },

            created() {
                this.load();
            },

            methods: {
                load() {
                    this.isLoading = true;

                    this.$axios.get(this.historyUrl, { params: { person_id: this.personId } })
                        .then((response) => {
                            this.calls = response.data.calls;
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

                otherPartyNumber(call) {
                    return call.direction === 'inbound'
                        ? (call.caller_id_number || call.source_number || '?')
                        : (call.destination_number || '?');
                },

                formatWhen(startStamp) {
                    if (! startStamp) {
                        return '';
                    }

                    return new Date(startStamp).toLocaleString();
                },

                formatDuration(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;

                    return `${m}:${String(s).padStart(2, '0')}`;
                },

                togglePlay(call) {
                    const uuid = call.xml_cdr_uuid;

                    if (this.playingUuid === uuid) {
                        this.playingUuid = null;

                        return;
                    }

                    if (this.audioUrls[uuid]) {
                        this.playingUuid = uuid;

                        this.$nextTick(() => this.$refs['audio-' + uuid]?.[0]?.play?.());

                        return;
                    }

                    this.loadingRecordingUuid = uuid;

                    this.$axios.get(this.recordingUrlTemplate.replace('__UUID__', uuid))
                        .then((response) => {
                            this.audioUrls = { ...this.audioUrls, [uuid]: response.data.url };
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
            },
        });
    </script>
@endPushOnce
