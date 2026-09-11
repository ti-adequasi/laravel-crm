<x-admin::layouts>
    <x-slot:title>
        @lang('tenant::app.create.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.tenant.store')"
        method="POST"
    >
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between px-4 py-2">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="tenant.create" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('tenant::app.create.title')
                    </div>
                </div>

                <button
                    type="submit"
                    class="primary-button"
                >
                    @lang('tenant::app.create.save-btn')
                </button>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        id="name"
                        rules="required"
                        :label="trans('tenant::app.create.name')"
                        :placeholder="trans('tenant::app.create.name')"
                        value="{{ old('name') }}"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.code')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="code"
                        id="code"
                        rules="required"
                        :label="trans('tenant::app.create.code')"
                        :placeholder="trans('tenant::app.create.code-placeholder')"
                        value="{{ old('code') }}"
                    />

                    <p class="mt-1 max-w-xs text-xs italic text-gray-500 dark:text-gray-300">
                        @lang('tenant::app.create.code-info')
                    </p>

                    <x-admin::form.control-group.error control-name="code" />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="!mb-0 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0">
                        @lang('tenant::app.create.active')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="switch"
                        class="cursor-pointer"
                        name="is_active"
                        id="is_active"
                        value="1"
                        :checked="true"
                        :label="trans('tenant::app.create.active')"
                    />

                    <x-admin::form.control-group.error control-name="is_active" />
                </x-admin::form.control-group>
            </div>
        </div>

        <div class="mt-2 flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1 px-4 py-2">
                <div class="text-base font-semibold dark:text-white">
                    @lang('tenant::app.create.admin-account-title')
                </div>

                <p class="max-w-2xl text-xs text-gray-500 dark:text-gray-300">
                    @lang('tenant::app.create.admin-account-info')
                </p>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.admin-name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="admin_name"
                        id="admin_name"
                        rules="required"
                        :label="trans('tenant::app.create.admin-name')"
                        :placeholder="trans('tenant::app.create.admin-name')"
                        value="{{ old('admin_name') }}"
                    />

                    <x-admin::form.control-group.error control-name="admin_name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.admin-email')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="email"
                        name="admin_email"
                        id="admin_email"
                        rules="required|email"
                        :label="trans('tenant::app.create.admin-email')"
                        :placeholder="trans('tenant::app.create.admin-email')"
                        value="{{ old('admin_email') }}"
                    />

                    <x-admin::form.control-group.error control-name="admin_email" />
                </x-admin::form.control-group>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group class="flex-1">
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.admin-password')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="password"
                        name="admin_password"
                        id="admin_password"
                        rules="required|min:6"
                        :label="trans('tenant::app.create.admin-password')"
                        :placeholder="trans('tenant::app.create.admin-password')"
                        ref="admin_password"
                    />

                    <x-admin::form.control-group.error control-name="admin_password" />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="flex-1">
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.create.admin-password-confirmation')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="password"
                        name="admin_confirm_password"
                        id="admin_confirm_password"
                        rules="required|confirmed:@admin_password"
                        :label="trans('tenant::app.create.admin-password-confirmation')"
                        :placeholder="trans('tenant::app.create.admin-password-confirmation')"
                    />

                    <x-admin::form.control-group.error control-name="admin_confirm_password" />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
