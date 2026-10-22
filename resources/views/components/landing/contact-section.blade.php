<section class="contact" id="contact">
    <svg class="contact__curve" viewBox="0 0 240 140" fill="none" aria-hidden="true">
        <path d="M18 108C52 36 118 18 214 48" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="1.5 8" />
        <circle cx="24" cy="100" r="4.5" fill="currentColor" />
        <circle cx="206" cy="52" r="3.5" fill="currentColor" />
    </svg>
    <span class="contact__ring" aria-hidden="true"></span>

    <div class="contact__inner">
        <header class="contact__intro">
            <p class="contact__eyebrow">تماس با ما</p>
            <h1><span class="is-accent">همیشه</span> در کنار شما هستیم</h1>
            <p class="contact__lead">اگر سوالی دارید، نیاز به مشاوره دارید یا می‌خواهید بیشتر با آوایار آشنا شوید از طریق فرم زیر یا راه‌های ارتباطی با ما در تماس باشید.</p>
        </header>

        <img
            class="contact__visual"
            src="{{ asset('images/contact/communication-icons.png') }}"
            width="1024"
            height="682"
            alt=""
            decoding="async"
        >

        <div class="contact__body">
            <div class="contact__card">
                <form
                    id="contact-form"
                    class="contact-form"
                    action="{{ route('contact-messages.store') }}"
                    method="post"
                    novalidate
                    @if (\App\Support\Recaptcha::enabled())
                        data-recaptcha-site-key="{{ \App\Support\Recaptcha::siteKey() }}"
                        data-recaptcha-action="{{ \App\Support\Recaptcha::CONTACT_ACTION }}"
                    @endif
                >
                    @csrf
                    <div class="consult-honeypot" aria-hidden="true">
                        <label>وب‌سایت<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <h2>ارسال پیام</h2>
                    <p class="contact-form__lead">پیام خود را برای ما ارسال کنید. در سریع‌ترین زمان پاسخ می‌دهیم.</p>

                    <div class="contact-form__pair">
                        <label class="contact-field">
                            <span class="sr-only">نام و نام خانوادگی</span>
                            <span class="contact-field__control">
                                <input type="text" name="full_name" autocomplete="name" maxlength="120" required placeholder="نام و نام خانوادگی">
                                <svg class="contact-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <circle cx="12" cy="8.2" r="3" />
                                    <path stroke-linecap="round" d="M6.4 18.4c.8-2.5 2.8-3.9 5.6-3.9s4.8 1.4 5.6 3.9" />
                                </svg>
                            </span>
                            <p class="contact-error" data-error-for="full_name" hidden></p>
                        </label>

                        <label class="contact-field">
                            <span class="sr-only">ایمیل</span>
                            <span class="contact-field__control">
                                <input type="email" name="email" autocomplete="email" maxlength="160" required placeholder="ایمیل">
                                <svg class="contact-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 7.5 7.5 5.5 7.5-5.5" />
                                </svg>
                            </span>
                            <p class="contact-error" data-error-for="email" hidden></p>
                        </label>
                    </div>

                    <label class="contact-field">
                        <span class="sr-only">موضوع</span>
                        <span class="contact-field__control">
                            <select name="subject" required>
                                <option value="" selected disabled>موضوع</option>
                                @foreach (\App\Enums\ContactMessageSubject::cases() as $subject)
                                    <option value="{{ $subject->value }}">{{ $subject->label() }}</option>
                                @endforeach
                            </select>
                            <svg class="contact-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linejoin="round" d="M6.5 16.8 4.2 19.2V15A7.2 7.2 0 1 1 12 19.2H7.2" />
                            </svg>
                        </span>
                        <p class="contact-error" data-error-for="subject" hidden></p>
                    </label>

                    <label class="contact-field contact-field--area">
                        <span class="sr-only">متن پیام شما</span>
                        <span class="contact-field__control">
                            <textarea name="message" maxlength="2000" required placeholder="متن پیام شما"></textarea>
                            <svg class="contact-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linejoin="round" d="M13.2 6.4 17.6 10.8 8.2 20.2H3.8v-4.4L13.2 6.4Z" />
                                <path stroke-linecap="round" d="m11.8 7.8 4.4 4.4" />
                            </svg>
                        </span>
                        <p class="contact-error" data-error-for="message" hidden></p>
                    </label>

                    <p class="contact-form-error" data-form-error hidden></p>

                    <button type="submit" id="contact-submit" class="contact-form__submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5 10.2 13.8" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5 13.2 19.5l-3-5.8-5.7-3L19.5 4.5Z" />
                        </svg>
                        <span data-submit-label>ارسال پیام</span>
                    </button>

                    @if (\App\Support\Recaptcha::enabled())
                        <p class="consult-recaptcha">
                            این سایت با <bdi>reCAPTCHA</bdi> محافظت می‌شود.
                            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">حریم&nbsp;خصوصی</a>
                            و
                            <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">شرایط</a>
                            گوگل اعمال می‌شود.
                        </p>
                        <p class="contact-error" data-error-for="captcha" hidden></p>
                    @endif
                </form>

                <div id="contact-success" class="contact-success" hidden>
                    <h2>پیام شما ارسال شد</h2>
                    <p>در سریع‌ترین زمان پاسخ می‌دهیم.</p>
                </div>
            </div>

            <aside class="contact__aside" aria-label="راه‌های ارتباطی">
                <div class="contact__item">
                    <div class="contact__copy">
                        <p class="contact__label">ایمیل ما</p>
                        <a class="contact__value" href="mailto:info@avayar.ir" dir="ltr">info@avayar.ir</a>
                    </div>
                    <span class="contact__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 7.5 7.5 5.5 7.5-5.5" />
                        </svg>
                    </span>
                </div>

                <div class="contact__item">
                    <div class="contact__copy">
                        <p class="contact__label">تماس با ما</p>
                        <a class="contact__value" href="tel:+982191000000" dir="ltr">021-91000000</a>
                    </div>
                    <span class="contact__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linejoin="round" d="M8.2 4.8h2.1l1.1 2.8-1.5 1a11.2 11.2 0 0 0 5.4 5.4l1-1.5 2.8 1.1v2.1a1.5 1.5 0 0 1-1.6 1.5A14.4 14.4 0 0 1 6.7 6.4a1.5 1.5 0 0 1 1.5-1.6Z" />
                        </svg>
                    </span>
                </div>

                <div class="contact__item">
                    <div class="contact__copy">
                        <p class="contact__label">آدرس ما</p>
                        <p class="contact__value">تهران، ایران<br>خیابان مثال، پلاک ۱۲</p>
                    </div>
                    <span class="contact__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linejoin="round" d="M12 21s6-5.1 6-10a6 6 0 1 0-12 0c0 4.9 6 10 6 10Z" />
                            <circle cx="12" cy="11" r="2" />
                        </svg>
                    </span>
                </div>
            </aside>
        </div>
    </div>
</section>
