@if (\App\Support\Recaptcha::enabled())
    <div
        wire:ignore
        dir="ltr"
        class="flex justify-center"
        x-data="{
            widgetId: null,
            render() {
                if (this.widgetId !== null || ! window.grecaptcha || ! window.grecaptcha.render) {
                    return;
                }

                this.widgetId = window.grecaptcha.render(this.$refs.widget, {
                    sitekey: @js(\App\Support\Recaptcha::siteKey()),
                    callback: (token) => this.$wire.set(@js($statePath), token),
                    'expired-callback': () => this.$wire.set(@js($statePath), ''),
                    'error-callback': () => this.$wire.set(@js($statePath), ''),
                });
            },
            boot() {
                if (window.grecaptcha && window.grecaptcha.render) {
                    this.render();
                    return;
                }

                window.addEventListener('recaptcha-api-loaded', () => this.render(), { once: true });
            },
        }"
        x-init="boot()"
    >
        <div x-ref="widget"></div>
    </div>
    @error($statePath)
        <p class="fi-fo-field-wrp-error-message mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @once
        <script>
            window.onRecaptchaApiLoad = () => window.dispatchEvent(new Event('recaptcha-api-loaded'));
        </script>
        <script src="https://www.google.com/recaptcha/api.js?hl=fa&onload=onRecaptchaApiLoad&render=explicit" async defer></script>
    @endonce
@endif
