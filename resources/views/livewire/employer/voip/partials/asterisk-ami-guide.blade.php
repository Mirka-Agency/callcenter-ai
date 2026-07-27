@props([
    'amiHost' => null,
    'amiPort' => 5038,
    'amiUsername' => 'callcenter-ai',
    'connectionId' => null,
])

<div class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm text-violet-950 dark:border-violet-900/50 dark:bg-violet-950/40 dark:text-violet-100 space-y-4" data-tour="asterisk-ami-guide">
    <div>
        <p class="font-medium text-base">{{ __('ui.voip.asterisk_ami_guide_title') }}</p>
        <p class="mt-1 text-violet-900/90 dark:text-violet-200/90">{{ __('ui.voip.asterisk_ami_guide_intro') }}</p>
        <p class="mt-2 text-xs text-violet-800 dark:text-violet-300">{{ __('ui.voip.asterisk_ami_network_note') }}</p>
    </div>

    <ol class="list-decimal list-inside space-y-1.5 text-violet-900/90 dark:text-violet-200/90">
        <li>{{ __('ui.voip.asterisk_ami_step_1') }}</li>
        <li>{{ __('ui.voip.asterisk_ami_step_2') }}</li>
        <li>{{ __('ui.voip.asterisk_ami_step_3') }}</li>
        <li>{{ __('ui.voip.asterisk_ami_step_4') }}</li>
        <li>{{ __('ui.voip.asterisk_ami_step_5') }}</li>
        <li>{{ __('ui.voip.asterisk_ami_step_6') }}</li>
    </ol>

    <x-saas.copy-code
        :label="__('ui.voip.asterisk_ami_manager_title')"
        :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleManagerConfig($amiUsername)"
        :open="true"
    >
        <p>{{ __('ui.voip.asterisk_ami_manager_hint') }}</p>
    </x-saas.copy-code>

    <x-saas.copy-code
        :label="__('ui.voip.asterisk_ami_verify_title')"
        :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleAmiVerifyCommand($amiUsername)"
    >
        <p>{{ __('ui.voip.asterisk_ami_verify_hint') }}</p>
    </x-saas.copy-code>

    @if ($connectionId)
        <div class="rounded-lg border border-violet-200/80 bg-white/70 p-3 text-xs dark:border-violet-800 dark:bg-violet-950/60 space-y-2">
            <p class="font-medium">{{ __('ui.voip.asterisk_ami_app_title') }}</p>
            <p class="text-violet-800 dark:text-violet-300">{{ __('ui.voip.asterisk_ami_app_hint') }}</p>
            <pre class="overflow-x-auto rounded bg-zinc-950 p-3 font-mono text-[11px] text-emerald-300" dir="ltr">php artisan voip:ami-listen --connection={{ $connectionId }}</pre>
            @if ($amiHost)
                <p class="text-violet-800 dark:text-violet-300" dir="ltr">
                    AMI: <code class="rounded bg-white/80 px-1 dark:bg-zinc-950">{{ $amiUsername }}@{{ $amiHost }}:{{ $amiPort }}</code>
                </p>
            @endif
        </div>
    @else
        <p class="text-xs text-violet-800 dark:text-violet-300">{{ __('ui.voip.asterisk_ami_save_first') }}</p>
    @endif
</div>
