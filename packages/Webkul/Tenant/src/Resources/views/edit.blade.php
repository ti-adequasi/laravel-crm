<x-admin::layouts>
    <x-slot:title>
        @lang('tenant::app.edit.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.tenant.update', $tenant->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between px-4 py-2">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="tenant.edit" :entity="$tenant" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('tenant::app.edit.title')
                    </div>
                </div>

                <button
                    type="submit"
                    class="primary-button"
                >
                    @lang('tenant::app.edit.save-btn')
                </button>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        id="name"
                        rules="required"
                        :label="trans('tenant::app.edit.name')"
                        value="{{ old('name') ?? $tenant->name }}"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.code')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="code"
                        id="code"
                        rules="required"
                        :label="trans('tenant::app.edit.code')"
                        value="{{ old('code') ?? $tenant->code }}"
                    />

                    <x-admin::form.control-group.error control-name="code" />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="!mb-0 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0">
                        @lang('tenant::app.edit.active')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="switch"
                        class="cursor-pointer"
                        name="is_active"
                        id="is_active"
                        value="1"
                        :checked="(boolean) $tenant->is_active"
                        :label="trans('tenant::app.edit.active')"
                    />

                    <x-admin::form.control-group.error control-name="is_active" />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>

    <div class="mt-2 flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex items-center justify-between px-4 py-2">
            <div class="text-base font-semibold dark:text-white">
                @lang('tenant::app.edit.users-title')
            </div>
        </div>

        <div class="border-t border-gray-300 dark:border-gray-800">
            @if ($tenantUsers->isEmpty())
                <p class="px-4 py-3 text-xs italic text-gray-500 dark:text-gray-300">
                    @lang('tenant::app.edit.users-empty')
                </p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 dark:text-gray-300">
                            <th class="px-4 py-2 font-medium">@lang('tenant::app.edit.users-name')</th>
                            <th class="px-4 py-2 font-medium">@lang('tenant::app.edit.users-email')</th>
                            <th class="px-4 py-2 font-medium">@lang('tenant::app.edit.users-status')</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($tenantUsers as $tenantUser)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-2">{{ $tenantUser->name }}</td>
                                <td class="px-4 py-2">{{ $tenantUser->email }}</td>
                                <td class="px-4 py-2">
                                    @if ($tenantUser->status)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            @lang('tenant::app.edit.users-active')
                                        </span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs dark:bg-gray-800 dark:text-white">
                                            @lang('tenant::app.edit.users-inactive')
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <x-admin::form
        :action="route('admin.tenant.users.store', $tenant->id)"
        method="POST"
        class="mt-2"
    >
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between px-4 py-2">
                <div class="text-base font-semibold dark:text-white">
                    @lang('tenant::app.edit.add-user-title')
                </div>

                <button
                    type="submit"
                    class="secondary-button"
                >
                    @lang('tenant::app.edit.add-user-btn')
                </button>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.add-user-name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="new_user_name"
                        id="new_user_name"
                        rules="required"
                        :label="trans('tenant::app.edit.add-user-name')"
                        :placeholder="trans('tenant::app.edit.add-user-name')"
                    />

                    <x-admin::form.control-group.error control-name="new_user_name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.add-user-email')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="email"
                        name="new_user_email"
                        id="new_user_email"
                        rules="required|email"
                        :label="trans('tenant::app.edit.add-user-email')"
                        :placeholder="trans('tenant::app.edit.add-user-email')"
                    />

                    <x-admin::form.control-group.error control-name="new_user_email" />
                </x-admin::form.control-group>
            </div>

            <div class="flex gap-4 border-t border-gray-300 px-4 py-2 align-top dark:border-gray-800 max-sm:flex-wrap">
                <x-admin::form.control-group class="flex-1">
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.add-user-password')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="password"
                        name="new_user_password"
                        id="new_user_password"
                        rules="required|min:6"
                        :label="trans('tenant::app.edit.add-user-password')"
                        :placeholder="trans('tenant::app.edit.add-user-password')"
                        ref="new_user_password"
                    />

                    <x-admin::form.control-group.error control-name="new_user_password" />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="flex-1">
                    <x-admin::form.control-group.label class="required">
                        @lang('tenant::app.edit.add-user-password-confirmation')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="password"
                        name="new_user_confirm_password"
                        id="new_user_confirm_password"
                        rules="required|confirmed:@new_user_password"
                        :label="trans('tenant::app.edit.add-user-password-confirmation')"
                        :placeholder="trans('tenant::app.edit.add-user-password-confirmation')"
                    />

                    <x-admin::form.control-group.error control-name="new_user_confirm_password" />
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
