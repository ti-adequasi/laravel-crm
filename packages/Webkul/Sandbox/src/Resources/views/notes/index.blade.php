<x-admin::layouts>
    <x-slot:title>
        @lang('sandbox::app.notes.title')
    </x-slot>

    <div class="mb-7 flex items-center justify-between">
        <p class="py-3 text-xl font-bold text-gray-800 dark:text-white">
            @lang('sandbox::app.notes.title')
        </p>
    </div>

    <div class="box-shadow rounded bg-white p-4 dark:bg-gray-900">
        <form
            method="POST"
            action="{{ route('admin.sandbox.notes.store') }}"
            class="mb-6 grid gap-3"
        >
            @csrf

            <input
                type="text"
                name="title"
                placeholder="@lang('sandbox::app.notes.title-placeholder')"
                class="rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                required
            />

            <textarea
                name="body"
                rows="3"
                placeholder="@lang('sandbox::app.notes.body-placeholder')"
                class="rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            ></textarea>

            <button
                type="submit"
                class="w-fit rounded-md bg-blue-600 px-4 py-2 text-white"
            >
                @lang('sandbox::app.notes.save')
            </button>
        </form>

        <div class="grid gap-3">
            @forelse ($notes as $note)
                <div class="flex items-start justify-between gap-4 rounded-md border border-gray-200 p-3 dark:border-gray-700">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white">{{ $note->title }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $note->body }}</p>
                    </div>

                    <form method="POST" action="{{ route('admin.sandbox.notes.destroy', $note->id) }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="text-sm text-red-600">
                            @lang('sandbox::app.notes.delete')
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400">@lang('sandbox::app.notes.empty')</p>
            @endforelse
        </div>
    </div>
</x-admin::layouts>
