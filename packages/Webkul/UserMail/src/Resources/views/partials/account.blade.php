@php
    $userMailAccount = app(\Webkul\UserMail\Repositories\UserMailAccountRepository::class)
        ->findOneWhere(['user_id' => $user->id]);
@endphp

<div class="flex w-[360px] max-w-full flex-col gap-2 max-md:w-full">
    <v-user-mail-account
        :has-account="{{ $userMailAccount ? 'true' : 'false' }}"
        host="{{ $userMailAccount->host ?? '' }}"
        port="{{ $userMailAccount->port ?? 587 }}"
        username="{{ $userMailAccount->username ?? '' }}"
        encryption="{{ $userMailAccount->encryption ?? 'tls' }}"
        from-address="{{ $userMailAccount->from_address ?? $user->email }}"
        update-url="{{ route('admin.user_mail.account.update') }}"
        delete-url="{{ route('admin.user_mail.account.delete') }}"
        test-url="{{ route('admin.user_mail.account.test') }}"
    >
        <div class="shimmer h-32 w-full rounded-md"></div>
    </v-user-mail-account>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-user-mail-account-template"
    >
        <x-admin::accordion>
            <x-slot:header>
                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('user_mail::app.account.title')
                </p>
            </x-slot>

            <x-slot:content>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-300">
                    @lang('user_mail::app.account.info')
                </p>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="user_mail_host">
                        @lang('user_mail::app.account.host')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="user_mail_host"
                        type="text"
                        name="host"
                        v-model="form.host"
                        placeholder="smtp.example.com"
                    />
                </x-admin::form.control-group>

                <div class="mb-4 flex gap-2">
                    <x-admin::form.control-group class="!mb-0 flex-1">
                        <x-admin::form.control-group.label for="user_mail_port">
                            @lang('user_mail::app.account.port')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            id="user_mail_port"
                            type="number"
                            name="port"
                            v-model="form.port"
                        />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="!mb-0 flex-1">
                        <x-admin::form.control-group.label for="user_mail_encryption">
                            @lang('user_mail::app.account.encryption')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            id="user_mail_encryption"
                            type="select"
                            name="encryption"
                            v-model="form.encryption"
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                        </x-admin::form.control-group.control>
                    </x-admin::form.control-group>
                </div>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="user_mail_username">
                        @lang('user_mail::app.account.username')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="user_mail_username"
                        type="text"
                        name="username"
                        v-model="form.username"
                    />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="user_mail_password">
                        @lang('user_mail::app.account.password')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="user_mail_password"
                        type="password"
                        name="password"
                        v-model="form.password"
                        ::placeholder="hasAccount ? '••••••••' : ''"
                    />

                    <p
                        v-if="hasAccount"
                        class="mt-1 text-xs italic text-gray-500 dark:text-gray-300"
                    >
                        @lang('user_mail::app.account.password-hint')
                    </p>
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required" for="user_mail_from_address">
                        @lang('user_mail::app.account.from-address')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="user_mail_from_address"
                        type="email"
                        name="from_address"
                        v-model="form.from_address"
                    />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="!mb-4">
                    <x-admin::form.control-group.label class="required" for="user_mail_current_password">
                        @lang('admin::app.account.edit.current-password')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        id="user_mail_current_password"
                        type="password"
                        name="user_mail_current_password"
                        v-model="form.current_password"
                    />
                </x-admin::form.control-group>

                <div class="flex flex-wrap gap-2">
                    <button
                        id="user_mail_test_button"
                        type="button"
                        class="secondary-button"
                        :disabled="testing"
                        @click="test"
                    >
                        @{{ testing ? '@lang('user_mail::app.account.testing')' : '@lang('user_mail::app.account.test-connection')' }}
                    </button>

                    <button
                        id="user_mail_save_button"
                        type="button"
                        class="primary-button"
                        :disabled="saving"
                        @click="save"
                    >
                        @{{ saving ? '@lang('user_mail::app.account.saving')' : '@lang('user_mail::app.account.save-btn')' }}
                    </button>

                    <button
                        id="user_mail_remove_button"
                        type="button"
                        v-if="hasAccount"
                        class="transparent-button text-red-600"
                        @click="destroyAccount"
                    >
                        @lang('user_mail::app.account.remove')
                    </button>
                </div>
            </x-slot>
        </x-admin::accordion>
    </script>

    <script type="module">
        app.component('v-user-mail-account', {
            template: '#v-user-mail-account-template',

            props: [
                'hasAccount',
                'host',
                'port',
                'username',
                'encryption',
                'fromAddress',
                'updateUrl',
                'deleteUrl',
                'testUrl',
            ],

            data() {
                return {
                    form: {
                        host: this.host,
                        port: Number(this.port),
                        username: this.username,
                        password: '',
                        encryption: this.encryption,
                        from_address: this.fromAddress,
                        current_password: '',
                    },
                    testing: false,
                    saving: false,
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

                    this.$axios.post(this.testUrl, {
                        host: this.form.host,
                        port: Number(this.form.port),
                        username: this.form.username,
                        password: this.form.password,
                        encryption: this.form.encryption,
                    })
                        .then((response) => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch((error) => {
                            this.flashError(error, '@lang('user_mail::app.account.test-failed-generic')');
                        })
                        .finally(() => {
                            this.testing = false;
                        });
                },

                save() {
                    this.saving = true;

                    this.$axios.put(this.updateUrl, { ...this.form, port: Number(this.form.port) })
                        .then((response) => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            // A brief pause so the flash is actually readable —
                            // an immediate reload would wipe it off-screen
                            // before it renders.
                            setTimeout(() => window.location.reload(), 1000);
                        })
                        .catch((error) => {
                            this.flashError(error, '@lang('user_mail::app.account.save-failed-generic')');

                            this.saving = false;
                        });
                },

                destroyAccount() {
                    this.$axios.delete(this.deleteUrl)
                        .then((response) => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            setTimeout(() => window.location.reload(), 1000);
                        })
                        .catch((error) => {
                            this.flashError(error, '@lang('user_mail::app.account.save-failed-generic')');
                        });
                },
            },
        });
    </script>
@endPushOnce
