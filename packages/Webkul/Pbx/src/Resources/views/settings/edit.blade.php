<x-admin::layouts>
    <x-slot:title>
        @lang('pbx::app.settings.title')
    </x-slot>

    <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex items-center justify-between px-4 py-2">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="pbx.edit" />

                <div class="text-xl font-bold dark:text-white">
                    @lang('pbx::app.settings.title')
                </div>
            </div>
        </div>

        <div class="border-t border-gray-300 px-4 py-2 dark:border-gray-800">
            <v-pbx-settings
                :enabled="{{ $setting->enabled ? 'true' : 'false' }}"
                :auto-log-activity="{{ $setting->auto_log_activity ? 'true' : 'false' }}"
                :has-key="{{ ! empty($setting->api_key) ? 'true' : 'false' }}"
                update-url="{{ route('admin.pbx.update') }}"
                test-url="{{ route('admin.pbx.test') }}"
            >
                <div class="shimmer h-32 w-full rounded-md"></div>
            </v-pbx-settings>
        </div>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-pbx-settings-template"
        >
            <div>
                <p class="mb-4 max-w-2xl text-xs text-gray-500 dark:text-gray-300">
                    @lang('pbx::app.settings.info')
                </p>

                <x-admin::form.control-group class="!mb-4 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0" for="enabled">
                        @lang('pbx::app.settings.enabled')
                    </x-admin::form.control-group.label>

                    <!-- A plain native checkbox, not x-admin::form.control-group.control
                         type="switch" — that control wraps a vee-validate <v-field>
                         plus an internal <v-checked-handler> purpose-built for a real
                         <VForm> to collect on native submit (see Tenant's own edit
                         screen, which uses a static :checked and lets <VForm> handle
                         the rest). This component instead POSTs a manually-tracked
                         `form` object via $axios (the same shape as UserMail's own
                         settings component) with no <VForm> anywhere, so nothing
                         ever reads the checkbox back into it — v-model="form.enabled"
                         on that control silently never fires, confirmed by
                         inspecting the actual PUT payload sent (no `enabled` key at
                         all). A plain HTML checkbox's v-model has no such
                         indirection. Same visual track/thumb classes as the shared
                         component's own switch, styled here directly. -->
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            id="enabled"
                            class="peer sr-only"
                            v-model="form.enabled"
                        />

                        <span class="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:top-0.5 after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-brandColor peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-blue-300 dark:bg-gray-800 dark:after:border-white dark:after:bg-white dark:peer-checked:bg-gray-950 after:ltr:left-0.5 peer-checked:after:ltr:translate-x-full after:rtl:right-0.5 peer-checked:after:rtl:-translate-x-full"></span>
                    </label>
                </x-admin::form.control-group>

                <x-admin::form.control-group class="!mb-4 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0" for="auto_log_activity">
                        @lang('pbx::app.settings.auto-log-activity')
                    </x-admin::form.control-group.label>

                    <!-- Same plain-native-checkbox reasoning as #enabled above. -->
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            id="auto_log_activity"
                            class="peer sr-only"
                            v-model="form.auto_log_activity"
                        />

                        <span class="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:top-0.5 after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-brandColor peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-blue-300 dark:bg-gray-800 dark:after:border-white dark:after:bg-white dark:peer-checked:bg-gray-950 after:ltr:left-0.5 peer-checked:after:ltr:translate-x-full after:rtl:right-0.5 peer-checked:after:rtl:-translate-x-full"></span>
                    </label>
                </x-admin::form.control-group>

                <p class="mb-4 max-w-2xl text-xs text-gray-500 dark:text-gray-300">
                    @lang('pbx::app.settings.auto-log-activity-hint')
                </p>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="pbx_api_key">
                        @lang('pbx::app.settings.api-key')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="pbx_api_key"
                        type="password"
                        v-model="form.api_key"
                        ::placeholder="hasKey ? '••••••••' : ''"
                    />

                    <p
                        v-if="hasKey"
                        class="mt-1 text-xs italic text-gray-500 dark:text-gray-300"
                    >
                        @lang('pbx::app.settings.api-key-hint')
                    </p>
                </x-admin::form.control-group>

                <div
                    v-if="testResult"
                    class="mb-4 rounded-md px-3 py-2 text-xs"
                    :class="testResult.type === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/20 dark:text-rose-400'"
                >
                    @{{ testResult.message }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        id="pbx_test_button"
                        type="button"
                        class="secondary-button"
                        :disabled="testing"
                        @click="test"
                    >
                        @{{ testing ? '@lang('pbx::app.settings.testing')' : '@lang('pbx::app.settings.test-connection')' }}
                    </button>

                    <button
                        id="pbx_save_button"
                        type="button"
                        class="primary-button"
                        :disabled="saving"
                        @click="save"
                    >
                        @{{ saving ? '@lang('pbx::app.settings.saving')' : '@lang('pbx::app.settings.save-btn')' }}
                    </button>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-pbx-settings', {
                template: '#v-pbx-settings-template',

                props: [
                    'enabled',
                    'autoLogActivity',
                    'hasKey',
                    'updateUrl',
                    'testUrl',
                ],

                data() {
                    return {
                        form: {
                            enabled: this.enabled,
                            auto_log_activity: this.autoLogActivity,
                            api_key: '',
                        },
                        testing: false,
                        saving: false,
                        testResult: null,
                    };
                },

                methods: {
                    flashError(error, fallbackMessage) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error?.response?.data?.message || fallbackMessage,
                        });
                    },

                    test() {
                        this.testing = true;
                        this.testResult = null;

                        this.$axios.post(this.testUrl, { api_key: this.form.api_key })
                            .then((response) => {
                                this.testResult = { type: 'success', message: response.data.message };
                            })
                            .catch((error) => {
                                this.testResult = {
                                    type: 'error',
                                    message: error?.response?.data?.message || '@lang('pbx::app.settings.test-failed-generic')',
                                };
                            })
                            .finally(() => {
                                this.testing = false;
                            });
                    },

                    save() {
                        this.saving = true;

                        this.$axios.put(this.updateUrl, this.form)
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                // A brief pause so the flash is actually readable —
                                // an immediate reload would wipe it off-screen
                                // before it renders.
                                setTimeout(() => window.location.reload(), 1000);
                            })
                            .catch((error) => {
                                this.flashError(error, '@lang('pbx::app.settings.save-failed-generic')');

                                this.saving = false;
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
