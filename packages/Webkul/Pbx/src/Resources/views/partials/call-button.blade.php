{{--
    Injected into the Lead-view Person panel's contact-numbers loop, once
    per number, at admin.leads.view.person.contact_numbers.row (see
    PbxServiceProvider::boot() and the one-line addition this required in
    packages/Webkul/Admin/src/Resources/views/leads/view/person.blade.php —
    the existing .before/.after pair around that loop wraps the whole list,
    not each row, so there was no way to reach "next to this one number"
    without either a per-row hook or a whole-file view override; a new hook
    following the file's own existing view_render_event idiom is the
    smaller, more transparent change of the two, and keeps this package
    exactly as ignorant of Pbx as it was before this line — see
    crm-package-development/SKILL.md for the general rule this is an
    instance of.

    All the "should this even be clickable" checks happen here, server-
    side, rather than in the Vue component — hiding the button entirely
    when it can't succeed (no PBX connected, no extension, no permission)
    beats showing a button that 422s/403s on click.
--}}
@php
    $pbxCanCall = app(\Webkul\Pbx\Services\PbxClient::class)->isConfigured()
        && ! empty(auth()->user()->extension)
        && bouncer()->hasPermission('leads.calls');
@endphp

@if ($pbxCanCall)
    <v-pbx-call-button
        phone="{{ $contactNumber['value'] }}"
        lead-id="{{ $lead->id }}"
        person-id="{{ $lead->person->id }}"
        originate-url="{{ route('admin.pbx.calls.originate') }}"
    ></v-pbx-call-button>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-pbx-call-button-template"
        >
            <span class="inline-flex items-center gap-1">
                <button
                    v-if="state === 'idle'"
                    type="button"
                    class="icon-call cursor-pointer rounded-md p-1 text-lg transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                    title="@lang('pbx::app.calls.call-button')"
                    @click="call"
                ></button>

                <span
                    v-else-if="state === 'calling' || state === 'in-progress'"
                    class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-300"
                >
                    <span class="icon-call animate-pulse text-lg text-brandColor"></span>

                    @{{ state === 'calling' ? '@lang('pbx::app.calls.calling')' : '@lang('pbx::app.calls.in-progress')' }}

                    <button
                        v-if="state === 'in-progress'"
                        type="button"
                        class="ml-1 cursor-pointer rounded-md px-1.5 py-0.5 text-xs text-rose-600 transition-all hover:bg-rose-100 dark:text-rose-400 dark:hover:bg-rose-900/20"
                        @click="hangup"
                    >
                        @lang('pbx::app.calls.hangup-button')
                    </button>
                </span>

                <span
                    v-else
                    class="text-xs text-gray-500 dark:text-gray-300"
                >
                    @lang('pbx::app.calls.ended')
                </span>
            </span>
        </script>

        <script type="module">
            app.component('v-pbx-call-button', {
                template: '#v-pbx-call-button-template',

                props: {
                    phone: {
                        type: String,
                        required: true,
                    },

                    leadId: {
                        type: [String, Number],
                        required: true,
                    },

                    personId: {
                        type: [String, Number],
                        default: null,
                    },

                    originateUrl: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        // idle -> calling -> in-progress -> ended -> idle
                        state: 'idle',
                        statusUrl: null,
                        hangupUrl: null,
                        pollTimeout: null,
                        pollStartedAt: null,
                    };
                },

                // The poll loop is a recursive setTimeout (each response
                // schedules the next one, rather than a setInterval that
                // could overlap a slow request) — cleared here so a call
                // still "in-progress" when this row disappears (navigating
                // away, or the Lead-view page's own live refresh replacing
                // this DOM node) doesn't keep polling forever from a
                // detached component. Losing server-side track of the
                // call is a separate, already-solved problem — see
                // ReconcilePbxCalls.
                beforeUnmount() {
                    clearTimeout(this.pollTimeout);
                },

                methods: {
                    flashError(error, fallbackMessage) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error?.response?.data?.message || fallbackMessage,
                        });
                    },

                    call() {
                        this.state = 'calling';

                        this.$axios.post(this.originateUrl, {
                            lead_id: this.leadId,
                            person_id: this.personId,
                            phone: this.phone,
                        })
                            .then((response) => {
                                this.statusUrl = response.data.status_url;
                                this.hangupUrl = response.data.hangup_url;
                                this.state = 'in-progress';
                                this.pollStartedAt = Date.now();
                                this.poll();
                            })
                            .catch((error) => {
                                this.flashError(error, "@lang('pbx::app.calls.originate-failed-generic')");
                                this.state = 'idle';
                            });
                    },

                    poll() {
                        // A generous but finite cap (~20 minutes) — if the
                        // status endpoint never reports a terminal state
                        // for some reason (an unrecognized PBX response
                        // shape, most plausibly — see
                        // PbxCallStatusInterpreter), this stops an
                        // abandoned-but-still-open tab from polling
                        // forever. The reconciliation command is the real
                        // safety net for the call itself either way.
                        if (Date.now() - this.pollStartedAt > 20 * 60 * 1000) {
                            return;
                        }

                        this.$axios.get(this.statusUrl)
                            .then((response) => {
                                if (response.data.ended) {
                                    this.finish();

                                    return;
                                }

                                this.pollTimeout = setTimeout(() => this.poll(), 3000);
                            })
                            .catch(() => {
                                // A network blip shouldn't strand the
                                // button mid-call — try again rather than
                                // giving up after one failed poll.
                                this.pollTimeout = setTimeout(() => this.poll(), 3000);
                            });
                    },

                    hangup() {
                        this.$axios.delete(this.hangupUrl)
                            .then(() => this.finish())
                            .catch((error) => {
                                this.flashError(error, "@lang('pbx::app.calls.hangup-failed-generic')");
                            });
                    },

                    finish() {
                        clearTimeout(this.pollTimeout);
                        this.state = 'ended';

                        setTimeout(() => {
                            this.state = 'idle';
                        }, 3000);
                    },
                },
            });
        </script>
    @endPushOnce
@endif
