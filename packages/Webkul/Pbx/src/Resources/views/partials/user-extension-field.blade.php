{{--
    Injected into the Users create/edit modal at
    admin.settings.users.index.form.status.after (see
    PbxServiceProvider::boot()) — no edit to that core Blade file needed.
    Shares the same reactive `user` object every other field in that form
    binds to (`v-model="user.name"`, etc.), so it's saved/validated exactly
    like any of Krayin's own fields there.
--}}
<x-admin::form.control-group>
    <x-admin::form.control-group.label>
        @lang('admin::app.settings.users.index.extension')
    </x-admin::form.control-group.label>

    <x-admin::form.control-group.control
        type="text"
        id="extension"
        name="extension"
        v-model="user.extension"
        :label="trans('admin::app.settings.users.index.extension')"
        :placeholder="trans('admin::app.settings.users.index.extension')"
    />

    <x-admin::form.control-group.error control-name="extension" />
</x-admin::form.control-group>
