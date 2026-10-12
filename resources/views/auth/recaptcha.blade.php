@if (\App\Support\Recaptcha::enabled())
    <div
        wire:ignore
        x-data="{
            submitting: false,
            executeToken() {
                const siteKey = @js(\App\Support\Recaptcha::siteKey());
                const action = @js(\App\Support\Recaptcha::ACTION);

                return new Promise((resolve, reject) => {
                    const timer = setTimeout(() => reject(new Error('timeout')), 8000);

                    const run = () => {
                        window.grecaptcha.ready(() => {
                            window.grecaptcha.execute(siteKey, { action })
                                .then((token) => {
                                    clearTimeout(timer);
                                    resolve(token);
                                })
                                .catch((error) => {
                                    clearTimeout(timer);
                                    reject(error);
                                });
                        });
                    };

                    if (window.grecaptcha && window.grecaptcha.execute) {
                        run();
                        return;
                    }

                    window.addEventListener('recaptcha-api-loaded', () => {
                        if (window.grecaptcha && window.grecaptcha.execute) {
                            run();
                            return;
                        }

                        clearTimeout(timer);
                        reject(new Error('unavailable'));
                    }, { once: true });
                });
            },
            async onSubmit(event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (this.submitting) {
                    return;
                }

                this.submitting = true;
                let token = '';

                try {
                    token = await this.executeToken();
                } catch (error) {
                    token = '';
                }

                try {
                    await this.$wire.set(@js($statePath), token);
                    await this.$wire.authenticate();
                } finally {
                    this.submitting = false;
                }
            },
            boot() {
                const form = this.$el.closest('form');

                if (! form || form.dataset.recaptchaBound === '1') {
                    return;
                }

                form.dataset.recaptchaBound = '1';
                form.addEventListener('submit', (event) => this.onSubmit(event), true);
            },
        }"
        x-init="boot()"
    ></div>
    @error($statePath)
        <p class="fi-fo-field-wrp-error-message mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @once
        <script>
            window.onRecaptchaApiLoad = () => window.dispatchEvent(new Event('recaptcha-api-loaded'));
        </script>
        <script src="https://www.google.com/recaptcha/api.js?hl=fa&onload=onRecaptchaApiLoad&render={{ urlencode((string) \App\Support\Recaptcha::siteKey()) }}" async defer></script>
    @endonce
@endif
