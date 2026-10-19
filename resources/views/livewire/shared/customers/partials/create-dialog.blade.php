@if ($showCreateDialog)
    <div
        class="crm-create-dialog"
        wire:click.self="closeCreateDialog"
        wire:keydown.escape.window="closeCreateDialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="crm-create-dialog-title"
    >
        <form wire:submit="saveCreateDialog" class="crm-create-dialog__panel">
            <header class="crm-create-dialog__header">
                <div class="crm-create-dialog__switch" role="tablist" aria-label="نوع رکورد">
                    <button
                        type="button"
                        role="tab"
                        id="crm-create-dialog-title"
                        wire:click="setCreateMode('company')"
                        @class(['crm-create-dialog__tab', 'is-active' => $createMode === 'company'])
                        aria-selected="{{ $createMode === 'company' ? 'true' : 'false' }}"
                    >
                        <span>شرکت جدید</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h16M6 20V6.5A1.5 1.5 0 0 1 7.5 5h5A1.5 1.5 0 0 1 14 6.5V20M14 10h2.5A1.5 1.5 0 0 1 18 11.5V20" />
                            <path stroke-linecap="round" d="M8.5 8.5h2M8.5 12h2M8.5 15.5h2" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        wire:click="setCreateMode('contact')"
                        @class(['crm-create-dialog__tab', 'is-active' => $createMode === 'contact'])
                        aria-selected="{{ $createMode === 'contact' ? 'true' : 'false' }}"
                    >
                        <span>شخص جدید</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.2" />
                            <path stroke-linecap="round" d="M6.2 19.2c.8-2.6 3-4 5.8-4s5 1.4 5.8 4" />
                        </svg>
                    </button>
                </div>
                <button type="button" class="crm-create-dialog__close" wire:click="closeCreateDialog" aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>

            <div class="crm-create-dialog__body">
                @if ($createMode === 'company')
                    <div class="crm-create-dialog__grid">
                        <label class="crm-create-dialog__field crm-create-dialog__field--full">
                            <span>نام شرکت <span class="crm-create-dialog__required">*</span></span>
                            <input wire:model="companyName" class="crm-create-dialog__input" placeholder="نام شرکت را وارد کنید" autofocus>
                            @error('companyName') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>صنعت</span>
                            <select wire:model="industry" class="crm-create-dialog__input">
                                <option value="">انتخاب صنعت</option>
                                @foreach ($this->industryOptions() as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('industry') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>وب‌سایت</span>
                            <input wire:model="website" class="crm-create-dialog__input" dir="ltr" placeholder="example.com">
                            @error('website') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>تلفن</span>
                            <input wire:model="companyPhone" class="crm-create-dialog__input" dir="ltr" placeholder="021 000 0000">
                            @error('companyPhone') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>ایمیل</span>
                            <input wire:model="companyEmail" type="email" class="crm-create-dialog__input" dir="ltr" placeholder="info@example.com">
                            @error('companyEmail') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field crm-create-dialog__field--full">
                            <span>آدرس</span>
                            <textarea wire:model="address" rows="3" class="crm-create-dialog__input" placeholder="آدرس شرکت را وارد کنید"></textarea>
                            @error('address') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field crm-create-dialog__field--full">
                            <span>یادداشت</span>
                            <textarea wire:model="notes" rows="3" class="crm-create-dialog__input" placeholder="توضیحات تکمیلی درباره شرکت..."></textarea>
                            @error('notes') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                    </div>
                @else
                    <div class="crm-create-dialog__grid">
                        <label class="crm-create-dialog__field">
                            <span>نام</span>
                            <input wire:model="contactName" class="crm-create-dialog__input" placeholder="نام شخص" autofocus>
                            @error('contactName') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>سمت / نقش</span>
                            <input wire:model="jobTitle" class="crm-create-dialog__input" placeholder="مثلاً مدیر خرید">
                            @error('jobTitle') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                    </div>

                    <section class="crm-create-dialog__company">
                        <h3>ارتباط با شرکت</h3>
                        <p>شخص را به یک شرکت موجود متصل کنید یا همزمان شرکت جدیدی بسازید.</p>
                        <div class="crm-create-dialog__grid">
                            <label class="crm-create-dialog__field">
                                <span>شرکت موجود</span>
                                <select wire:model.live="customerCompanyId" class="crm-create-dialog__input">
                                    <option value="">بدون شرکت</option>
                                    @foreach ($this->createDialogCompanies() as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                @error('customerCompanyId') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                            </label>
                            <label class="crm-create-dialog__field">
                                <span>یا شرکت جدید</span>
                                <input
                                    wire:model="linkedCompanyName"
                                    class="crm-create-dialog__input"
                                    placeholder="نام شرکت جدید"
                                    @disabled($customerCompanyId !== '')
                                >
                                @error('linkedCompanyName') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                            </label>
                        </div>
                    </section>

                    <div class="crm-create-dialog__grid">
                        <label class="crm-create-dialog__field">
                            <span>شماره تماس <span class="crm-create-dialog__required">*</span></span>
                            <input wire:model="phoneNumber" class="crm-create-dialog__input" dir="ltr" placeholder="0912 000 0000" required>
                            @error('phoneNumber') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                        <label class="crm-create-dialog__field">
                            <span>ایمیل</span>
                            <input wire:model="contactEmail" type="email" class="crm-create-dialog__input" dir="ltr" placeholder="name@example.com">
                            @error('contactEmail') <p class="crm-create-dialog__error">{{ $message }}</p> @enderror
                        </label>
                    </div>

                    <p class="crm-create-dialog__note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="8" />
                            <path stroke-linecap="round" d="M12 11v5M12 8h.01" />
                        </svg>
                        <span>امتیاز لید و شاخص‌های هوش مصنوعی پس از تحلیل تماس‌ها به‌صورت خودکار به‌روز می‌شوند.</span>
                    </p>
                @endif
            </div>

            <footer class="crm-create-dialog__footer">
                <p>فیلدهای ستاره‌دار الزامی هستند</p>
                <div class="crm-create-dialog__footer-actions">
                    <button type="button" class="crm-create-dialog__cancel" wire:click="closeCreateDialog">انصراف</button>
                    <button type="submit" class="crm-create-dialog__submit" wire:loading.attr="disabled" wire:target="saveCreateDialog">
                        <span wire:loading.remove wire:target="saveCreateDialog">{{ $createMode === 'company' ? 'ایجاد شرکت' : 'ایجاد شخص' }}</span>
                        <span wire:loading wire:target="saveCreateDialog">در حال ذخیره…</span>
                    </button>
                </div>
            </footer>
        </form>
    </div>
@endif
