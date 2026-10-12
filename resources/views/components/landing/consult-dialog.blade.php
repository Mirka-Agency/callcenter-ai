    <dialog id="consult-dialog" class="consult-dialog" aria-labelledby="consult-title">
        <div class="consult-dialog__panel">
            <div class="consult-dialog__head">
                <div>
                    <h2 id="consult-title">درخواست مشاوره</h2>
                    <p>اطلاعات تماس را بگذارید تا درباره نیاز سازمانتان صحبت کنیم.</p>
                </div>
                <button type="button" class="consult-dialog__close" data-consult-close aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <form
                id="consult-form"
                class="consult-form"
                action="{{ route('consultation-requests.store') }}"
                method="post"
                novalidate
                @if (\App\Support\Recaptcha::enabled())
                    data-recaptcha-site-key="{{ \App\Support\Recaptcha::siteKey() }}"
                    data-recaptcha-action="{{ \App\Support\Recaptcha::CONSULTATION_ACTION }}"
                @endif
            >
                <div class="consult-honeypot" aria-hidden="true">
                    <label>وب‌سایت<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <label class="consult-field">
                    <span>نام و نام خانوادگی</span>
                    <input type="text" name="full_name" autocomplete="name" maxlength="120" required>
                    <p class="consult-error" data-error-for="full_name" hidden></p>
                </label>

                <label class="consult-field">
                    <span>شماره تماس</span>
                    <input type="tel" name="phone" inputmode="tel" autocomplete="tel" maxlength="20" required placeholder="۰۹۱۲۱۲۳۴۵۶۷">
                    <p class="consult-error" data-error-for="phone" hidden></p>
                </label>

                <label class="consult-field">
                    <span>نام شرکت</span>
                    <input type="text" name="company_name" autocomplete="organization" maxlength="160" required>
                    <p class="consult-error" data-error-for="company_name" hidden></p>
                </label>

                <label class="consult-field">
                    <span>واحدی که نیاز به آوایار دارد</span>
                    <input type="text" name="department" maxlength="160" required placeholder="مثلاً فروش، پشتیبانی یا کنترل کیفیت">
                    <p class="consult-error" data-error-for="department" hidden></p>
                </label>

                <label class="consult-field">
                    <span>توضیحات</span>
                    <textarea name="notes" maxlength="2000" placeholder="اگر نکته‌ای درباره نیاز تیم دارید بنویسید"></textarea>
                    <p class="consult-error" data-error-for="notes" hidden></p>
                </label>

                <p class="consult-form-error" data-form-error hidden></p>
                <button type="submit" id="consult-submit" class="consult-submit">ثبت درخواست</button>

                @if (\App\Support\Recaptcha::enabled())
                    <p class="consult-recaptcha">
                        این سایت با <bdi>reCAPTCHA</bdi> محافظت می‌شود.
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">حریم&nbsp;خصوصی</a>
                        و
                        <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">شرایط</a>
                        گوگل اعمال می‌شود.
                    </p>
                    <p class="consult-error" data-error-for="captcha" hidden></p>
                @endif
            </form>

            <div id="consult-success" class="consult-success" hidden>
                <h3>درخواست شما ثبت شد</h3>
                <p>به‌زودی با شما تماس می‌گیریم.</p>
                <button type="button" data-consult-close>بستن</button>
            </div>
        </div>
    </dialog>
    @if (\App\Support\Recaptcha::enabled())
        @once
            <script>
                window.onRecaptchaApiLoad = () => window.dispatchEvent(new Event('recaptcha-api-loaded'));
            </script>
            <script src="https://www.google.com/recaptcha/api.js?hl=fa&onload=onRecaptchaApiLoad&render={{ urlencode((string) \App\Support\Recaptcha::siteKey()) }}" async defer></script>
        @endonce
    @endif
