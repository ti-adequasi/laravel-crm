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
    </x-admin::form>
</x-admin::layouts>
