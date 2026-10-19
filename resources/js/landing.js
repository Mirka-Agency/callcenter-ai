(function () {
                    var root = document.querySelector('[data-journey]');
                    if (!root) return;

                    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
                    var draws = root.querySelectorAll('.journey__draw');
                    var rings = root.querySelectorAll('[data-ring]');
                    var steps = root.querySelectorAll('[data-step]');
                    var status = root.querySelector('[data-journey-status]');
                    var frame = 0;
                    var length = 0;
                    var statusText = '';
                    var statusTimer = 0;

                    function setStatus(next) {
                        if (!status || next === statusText) return;
                        var first = statusText === '';
                        statusText = next;
                        window.clearTimeout(statusTimer);
                        if (first || reduce.matches) {
                            status.textContent = next;
                            status.classList.remove('is-out', 'is-in');
                            return;
                        }
                        status.classList.remove('is-in');
                        status.classList.add('is-out');
                        statusTimer = window.setTimeout(function () {
                            status.textContent = next;
                            status.classList.remove('is-out');
                            void status.offsetWidth;
                            status.classList.add('is-in');
                        }, 180);
                    }

                    function measure() {
                        var wires = root.querySelector('.journey__wires');
                        length = 0;
                        if (!wires || getComputedStyle(wires).display === 'none') return;

                        for (var i = 0; i < draws.length; i++) {
                            var seg = draws[i];
                            var len = seg.getTotalLength ? (seg.getTotalLength() || 0) : 0;
                            var gap = Number(seg.getAttribute('data-gap')) || 0;
                            seg._len = len;
                            seg._gap = gap;
                            length += len + gap;
                            if (len) seg.style.strokeDasharray = len + 'px';
                        }
                    }

                    function progressOf() {
                        if (reduce.matches) return 1;
                        var rect = root.getBoundingClientRect();
                        var span = root.offsetHeight - window.innerHeight;
                        if (span <= 8) {
                            var seen = window.innerHeight - rect.top;
                            return Math.min(1, Math.max(0, seen / (window.innerHeight + rect.height)));
                        }
                        return Math.min(1, Math.max(0, -rect.top / span));
                    }

                    function paint() {
                        frame = 0;
                        var progress = progressOf();
                        if (length) {
                            var drawn = length * progress;
                            for (var d = 0; d < draws.length; d++) {
                                var seg = draws[d];
                                var len = seg._len || 0;
                                var show = drawn <= 0 ? 0 : Math.min(len, drawn);
                                seg.style.strokeDashoffset = (len - show) + 'px';
                                drawn -= len + (seg._gap || 0);
                            }
                        }
                        var wires = root.querySelector('.journey__wires');
                        var lineHidden = !wires || getComputedStyle(wires).display === 'none';
                        for (var s = 0; s < steps.length; s++) {
                            var at = Number(steps[s].getAttribute('data-step'));
                            var on = reduce.matches || lineHidden || progress >= at;
                            if (steps[s].classList.contains('is-on') !== on) {
                                steps[s].classList.toggle('is-on', on);
                            }
                        }
                        setStatus(progress < 0.47
                            ? 'در حال ارسال به آوایار'
                            : progress < 0.71
                                ? 'در حال ارسال به هوش مصنوعی'
                                : 'دریافت نتیجه در کمتر از چند ثانیه');
                        for (var i = 0; i < rings.length; i++) {
                            var travel = Number(rings[i].getAttribute('data-ring')) || 120;
                            var shift = reduce.matches ? 0 : (0.5 - progress) * travel;
                            rings[i].style.setProperty('--ring-shift', shift.toFixed(1) + 'px');
                        }
                    }

                    function request() {
                        if (frame) return;
                        frame = window.requestAnimationFrame(paint);
                    }

                    measure();
                    paint();
                    window.addEventListener('scroll', request, { passive: true });
                    window.addEventListener('resize', function () {
                        measure();
                        request();
                    });
                    reduce.addEventListener('change', function () {
                        measure();
                        request();
                    });
                })();

(function () {
                    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
                    var frame = 0;
                    var band = null;
                    var shapes = [];

                    function paint() {
                        frame = 0;
                        if (!band) return;
                        var rect = band.getBoundingClientRect();
                        var span = band.offsetHeight - window.innerHeight;
                        var progress = reduce.matches || span <= 8
                            ? 0.5
                            : Math.min(1, Math.max(0, -rect.top / span));
                        for (var i = 0; i < shapes.length; i++) {
                            var travel = Number(shapes[i].getAttribute('data-drift')) || 40;
                            var shift = reduce.matches ? 0 : (0.5 - progress) * travel;
                            shapes[i].style.setProperty('--shift', shift.toFixed(1) + 'px');
                        }
                    }

                    function request() {
                        if (frame) return;
                        frame = window.requestAnimationFrame(paint);
                    }

                    function boot() {
                        band = document.querySelector('[data-insight]');
                        shapes = band ? band.querySelectorAll('[data-drift]') : [];
                        paint();
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', boot);
                    } else {
                        boot();
                    }
                    window.addEventListener('scroll', request, { passive: true });
                    window.addEventListener('resize', request);
                    reduce.addEventListener('change', request);
                })();

function bindLeadDialog(config) {
            const dialog = document.getElementById(config.dialogId);
            const form = document.getElementById(config.formId);
            const success = document.getElementById(config.successId);
            const submit = document.getElementById(config.submitId);
            const formError = dialog ? dialog.querySelector('[data-form-error]') : null;

            if (!dialog || !form || !success || !submit || !formError) {
                return;
            }

            function clearErrors() {
                form.querySelectorAll('[data-error-for]').forEach((node) => {
                    node.hidden = true;
                    node.textContent = '';
                    node.closest('.consult-field')?.classList.remove('is-invalid');
                });
                formError.hidden = true;
                formError.textContent = '';
            }

            function showErrors(errors) {
                Object.entries(errors).forEach(([field, messages]) => {
                    const node = form.querySelector('[data-error-for="' + field + '"]');
                    if (!node) {
                        return;
                    }
                    node.textContent = Array.isArray(messages) ? messages[0] : messages;
                    node.hidden = false;
                    node.closest('.consult-field')?.classList.add('is-invalid');
                });
            }

            function openDialog() {
                form.hidden = false;
                success.hidden = true;
                if (!dialog.open) {
                    dialog.showModal();
                }
                form.querySelector('input[name="full_name"]')?.focus();
            }

            document.querySelectorAll(config.openSelector).forEach((button) => {
                button.addEventListener('click', () => {
                    const menu = button.closest('details');
                    if (menu) {
                        menu.open = false;
                    }
                    openDialog();
                });
            });

            dialog.querySelectorAll(config.closeSelector).forEach((button) => {
                button.addEventListener('click', () => dialog.close());
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            dialog.addEventListener('close', () => {
                form.hidden = false;
                success.hidden = true;
                form.reset();
                clearErrors();
                submit.disabled = false;
                submit.textContent = 'ثبت درخواست';
            });

            function captchaToken() {
                const siteKey = form.dataset.recaptchaSiteKey;
                const action = form.dataset.recaptchaAction || config.defaultAction;

                if (!siteKey) {
                    return Promise.resolve('');
                }

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
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearErrors();
                submit.disabled = true;
                submit.textContent = 'در حال ثبت...';

                try {
                    const body = new FormData(form);

                    if (form.dataset.recaptchaSiteKey) {
                        try {
                            body.set('captcha', await captchaToken());
                        } catch (error) {
                            formError.textContent = 'تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.';
                            formError.hidden = false;
                            return;
                        }
                    }

                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body,
                    });
                    const data = await response.json().catch(() => ({}));

                    if (response.status === 422) {
                        showErrors(data.errors || {});
                        if (!data.errors) {
                            formError.textContent = data.message || 'اطلاعات واردشده معتبر نیست.';
                            formError.hidden = false;
                        }
                        return;
                    }

                    if (!response.ok) {
                        formError.textContent = 'ثبت درخواست انجام نشد. دوباره تلاش کنید.';
                        formError.hidden = false;
                        return;
                    }

                    form.hidden = true;
                    success.hidden = false;
                } catch (error) {
                    formError.textContent = 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.';
                    formError.hidden = false;
                } finally {
                    submit.disabled = false;
                    submit.textContent = 'ثبت درخواست';
                }
            });
        }

        bindLeadDialog({
            dialogId: 'consult-dialog',
            formId: 'consult-form',
            successId: 'consult-success',
            submitId: 'consult-submit',
            openSelector: '[data-consult-open]',
            closeSelector: '[data-consult-close]',
            defaultAction: 'consultation',
        });

        bindLeadDialog({
            dialogId: 'demo-dialog',
            formId: 'demo-form',
            successId: 'demo-success',
            submitId: 'demo-submit',
            openSelector: '[data-demo-open]',
            closeSelector: '[data-demo-close]',
            defaultAction: 'demo',
        });

        var faqItems = document.querySelectorAll('.faq__item');
        var faqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');

        function faqLock(panel, height) {
            panel.style.height = height;
        }

        faqItems.forEach(function (item) {
            var summary = item.querySelector('summary');
            var panel = item.querySelector('.faq__panel');
            if (!summary || !panel) return;

            var animation = null;

            summary.addEventListener('click', function (event) {
                event.preventDefault();

                var from = panel.getBoundingClientRect().height;
                var opening = !item.open;

                faqLock(panel, from + 'px');
                if (animation) animation.cancel();

                if (faqReduce.matches) {
                    item.open = opening;
                    panel.style.height = '';
                    return;
                }

                item.open = opening;
                var target = opening ? panel.scrollHeight : 0;
                if (Math.abs(target - from) < 1) {
                    panel.style.height = opening ? target + 'px' : '';
                    return;
                }

                animation = panel.animate(
                    [{ height: from + 'px' }, { height: target + 'px' }],
                    { duration: 180, easing: 'cubic-bezier(0.2, 0, 0, 1)', fill: 'forwards' }
                );
                animation.onfinish = function () {
                    panel.style.height = opening ? target + 'px' : '';
                    animation.cancel();
                    animation = null;
                };
            });
        });

        window.addEventListener('resize', function () {
            faqItems.forEach(function (item) {
                if (!item.open) return;
                var panel = item.querySelector('.faq__panel');
                if (!panel || panel.getAnimations().length) return;
                panel.style.height = 'auto';
                panel.style.height = panel.scrollHeight + 'px';
            });
        });

(function () {
            var root = document.querySelector('[data-cost-calculator]');
            if (!root) return;

            var quote;
            try {
                quote = JSON.parse(root.getAttribute('data-quote') || '{}');
            } catch (error) {
                return;
            }

            var models = Array.isArray(quote.models) ? quote.models : [];
            if (!models.length) return;

            var callsInput = root.querySelector('[data-calls-input]');
            var minutesInput = root.querySelector('[data-minutes-input]');
            var callsRange = root.querySelector('[data-calls-range]');
            var minutesRange = root.querySelector('[data-minutes-range]');
            var totalNode = root.querySelector('[data-total]');
            var modelInputs = root.querySelectorAll('[data-model]');
            var bars = root.querySelectorAll('[data-compare-bar]');
            var state = {
                calls: Number(quote.calls) || 0,
                minutes: Number(quote.minutes) || 0.5,
                modelId: Number(quote.selectedId) || Number(models[0].id),
            };

            function digits(value) {
                return String(value)
                    .replace(/[۰-۹]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit); })
                    .replace(/[٠-٩]/g, function (digit) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit); })
                    .replace(/[٬,\s]/g, '')
                    .replace('٫', '.');
            }

            function formatFa(value, fraction) {
                return new Intl.NumberFormat('fa-IR', {
                    minimumFractionDigits: fraction,
                    maximumFractionDigits: fraction,
                }).format(value);
            }

            function formatMinutes(value) {
                var rounded = Math.round(value * 10) / 10;
                return formatFa(rounded, Number.isInteger(rounded) ? 0 : 1);
            }

            function formatMoney(amount) {
                var abs = Math.abs(amount);
                var fraction = abs === 0 || abs >= 100 ? 0 : abs >= 1 ? 1 : 2;
                var factor = Math.pow(10, fraction);
                var rounded = Math.round((amount + Number.EPSILON) * factor) / factor;
                return formatFa(rounded, fraction);
            }

            function clamp(value, min, max) {
                return Math.min(max, Math.max(min, value));
            }

            function currentModel() {
                return models.find(function (model) { return Number(model.id) === state.modelId; }) || models[0];
            }

            function setFill(input, value, min, max) {
                var span = max - min;
                var ratio = span <= 0 ? 0 : (value - min) / span;
                input.style.setProperty('--fill', (Math.min(1, Math.max(0, ratio)) * 100).toFixed(2) + '%');
            }

            function paint(syncInputs) {
                var model = currentModel();
                var total = state.calls * state.minutes * Number(model.perMinute || 0);
                if (totalNode) totalNode.textContent = formatMoney(total);
                if (syncInputs) {
                    callsInput.value = formatFa(state.calls, 0);
                    minutesInput.value = formatMinutes(state.minutes);
                }
                callsRange.value = String(state.calls);
                minutesRange.value = String(state.minutes);
                setFill(callsRange, state.calls, 0, 15000);
                setFill(minutesRange, state.minutes, 0.5, 10);
                bars.forEach(function (bar) {
                    bar.classList.toggle('is-on', Number(bar.getAttribute('data-compare-bar')) === state.modelId);
                });
            }

            function readCalls(commit) {
                var parsed = Number(digits(callsInput.value));
                if (!Number.isFinite(parsed)) {
                    if (commit) state.calls = 0;
                    return;
                }
                state.calls = commit ? clamp(Math.round(parsed), 0, 15000) : clamp(parsed, 0, 15000);
            }

            function readMinutes(commit) {
                var parsed = Number(digits(minutesInput.value));
                if (!Number.isFinite(parsed)) {
                    if (commit) state.minutes = 0.5;
                    return;
                }
                var next = clamp(parsed, 0.5, 10);
                state.minutes = commit ? Math.round(next * 10) / 10 : next;
            }

            callsRange.addEventListener('input', function () {
                state.calls = Number(callsRange.value);
                paint(true);
            });

            minutesRange.addEventListener('input', function () {
                state.minutes = Number(minutesRange.value);
                paint(true);
            });

            callsInput.addEventListener('input', function () {
                readCalls(false);
                paint(false);
            });

            minutesInput.addEventListener('input', function () {
                readMinutes(false);
                paint(false);
            });

            callsInput.addEventListener('blur', function () {
                readCalls(true);
                paint(true);
            });

            minutesInput.addEventListener('blur', function () {
                readMinutes(true);
                paint(true);
            });

            modelInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    state.modelId = Number(input.value);
                    paint(false);
                });
            });

            paint(true);
        })();
