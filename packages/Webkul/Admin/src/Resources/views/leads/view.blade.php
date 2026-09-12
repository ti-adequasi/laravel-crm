<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.view.title', ['title' => strip_tags($lead->title)])
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">

        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-300 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="leads.view"
                        :entity="$lead"
                    />
                </div>

                <div class="mb-2">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name' => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}

                    <!-- Tags -->
                    <x-admin::tags
                        :attach-endpoint="route('admin.leads.tags.attach', $lead->id)"
                        :detach-endpoint="route('admin.leads.tags.detach', $lead->id)"
                        :added-tags="$lead->tags"
                    />

                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}
                </div>


                {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                <!-- Title -->
                <h1 class="text-lg font-bold dark:text-white">
                    {{ $lead->title }}
                </h1>

                {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                    @if (bouncer()->hasPermission('mail.compose'))
                        <!-- Mail Activity Action -->
                        <x-admin::activities.actions.mail
                            :entity="$lead"
                            :emails="collect($lead->person?->emails ?? [])->pluck('value')->filter()->values()->toArray()"
                            entity-control-name="lead_id"
                        />
                    @endif

                    @if (bouncer()->hasPermission('activities.create'))
                        <!-- File Activity Action -->
                        <x-admin::activities.actions.file
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Note Activity Action -->
                        <x-admin::activities.actions.note
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Activity Action -->
                        <x-admin::activities.actions.activity
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                </div>
            </div>

            <!-- Lead Attributes -->
            @include ('admin::leads.view.attributes')

            <!-- Contact Person -->
            @include ('admin::leads.view.person')
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include ('admin::leads.view.stages')

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            @php
                // The native "Chamadas" tab (built into <x-admin::activities>'s
                // own `types` default) is retargeted here to the Pbx
                // package's own merged view — the CRM's own logged call
                // Activities plus, when there's a Person and the acting
                // user holds 'leads.calls', the PBX's live call history
                // too (see call-history-panel.blade.php's own docblock for
                // why this replaces rather than adds a tab, and why that
                // needs a `:types` override here rather than a
                // view_render_event: `extraTypes` is a plain PHP literal
                // in this file, filtered through no hook at all).
                // The exact same 8 entries components/activities/index.blade.php
                // itself defaults `types` to, minus 'call' — 'call' moves to
                // extraTypes below instead. Mirrored by hand rather than
                // computed, since that default array only exists as a
                // hardcoded JS literal in that file, nothing PHP-reachable.
                $typesWithoutNativeCall = [
                    ['name' => 'all', 'label' => trans('admin::app.components.activities.index.all')],
                    ['name' => 'planned', 'label' => trans('admin::app.components.activities.index.planned')],
                    ['name' => 'note', 'label' => trans('admin::app.components.activities.index.notes')],
                    ['name' => 'meeting', 'label' => trans('admin::app.components.activities.index.meetings')],
                    ['name' => 'lunch', 'label' => trans('admin::app.components.activities.index.lunches')],
                    ['name' => 'file', 'label' => trans('admin::app.components.activities.index.files')],
                    ['name' => 'email', 'label' => trans('admin::app.components.activities.index.emails')],
                    ['name' => 'system', 'label' => trans('admin::app.components.activities.index.change-log')],
                ];

                $pbxHistoryAvailable = (bool) ($lead?->person && bouncer()->hasPermission('leads.calls'));
            @endphp

            <x-admin::activities
                :endpoint="route('admin.leads.activities.index', $lead->id)"
                :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
                :activeType="request()->query('tab') ?? (request()->query('from') === 'quotes' ? 'quotes' : 'all')"
                :types="$typesWithoutNativeCall"
                :extra-types="[
                    ['name' => 'call', 'label' => trans('admin::app.components.activities.index.calls')],
                    ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
                    ['name' => 'products', 'label' => trans('admin::app.leads.view.tabs.products')],
                    ['name' => 'quotes', 'label' => trans('admin::app.leads.view.tabs.quotes')],
                ]"
            >
                <!-- Pbx-merged call history (replaces the native Activity-only "Chamadas" content) -->
                <x-slot:call>
                    @include ('pbx::partials.call-history-panel', [
                        'personId' => $lead->person->id ?? null,
                        'pbxHistoryAvailable' => $pbxHistoryAvailable,
                    ])
                </x-slot>

                <!-- Products -->
                <x-slot:products>
                    @include ('admin::leads.view.products')
                </x-slot>

                <!-- Quotes -->
                <x-slot:quotes>
                    @include ('admin::leads.view.quotes')
                </x-slot>

                <!-- Description -->
                <x-slot:description>
                    <div class="p-4 dark:text-white">
                        {{ $lead->description }}
                    </div>
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>
</x-admin::layouts>
