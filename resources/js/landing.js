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
    var root = document.querySelector('[data-partners]');
    if (!root) return;

    var viewport = root.querySelector('[data-partners-viewport]');
    var track = root.querySelector('[data-partners-track]');
    var cards = track ? Array.prototype.slice.call(track.children) : [];
    var prev = root.querySelector('[data-partners-prev]');
    var next = root.querySelector('[data-partners-next]');
    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-partners-dot]'));
    var status = root.querySelector('[data-partners-status]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var count = Number(root.getAttribute('data-count')) || 0;

    if (!viewport || !track || !count || cards.length !== count * 3) return;

    var index = count;
    var startX = 0;
    var pointerId = null;

    function activePartner() {
        return ((index % count) + count) % count;
    }

    function layout(animate) {
        var card = cards[0];
        var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;
        var cardWidth = card.offsetWidth;
        var step = cardWidth + gap;
        var x = (viewport.clientWidth - cardWidth) / 2 - index * step;

        track.style.transition = animate && !reduce.matches
            ? 'transform 520ms cubic-bezier(.22, .8, .28, 1)'
            : 'none';
        track.style.transform = 'translate3d(' + x.toFixed(2) + 'px, 0, 0)';

        cards.forEach(function (cardNode, cardIndex) {
            var distance = Math.abs(cardIndex - index);
            cardNode.classList.toggle('is-active', distance === 0);
            cardNode.classList.toggle('is-near', distance === 1);
            cardNode.classList.toggle('is-far', distance >= 2);
            cardNode.setAttribute('aria-hidden', distance === 0 ? 'false' : 'true');
        });

        var current = activePartner();
        dots.forEach(function (dot, dotIndex) {
            var on = dotIndex === current;
            dot.classList.toggle('is-on', on);
            if (on) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });

        if (status) {
            var label = dots[current] ? dots[current].getAttribute('aria-label') : '';
            if (label) status.textContent = label;
        }
    }

    function normalize() {
        var shifted = index;
        while (shifted >= count * 2) shifted -= count;
        while (shifted < count) shifted += count;
        if (shifted === index) return;
        index = shifted;
        layout(false);
    }

    function move(delta) {
        index += delta;
        layout(true);
    }

    if (prev) prev.addEventListener('click', function () { move(-1); });
    if (next) next.addEventListener('click', function () { move(1); });

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            var target = Number(dot.getAttribute('data-partners-dot'));
            if (!Number.isFinite(target)) return;
            var current = activePartner();
            var delta = target - current;
            if (delta > count / 2) delta -= count;
            if (delta < -count / 2) delta += count;
            move(delta);
        });
    });

    track.addEventListener('transitionend', function (event) {
        if (event.target !== track || event.propertyName !== 'transform') return;
        normalize();
    });

    root.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            move(-1);
        }
    });

    viewport.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        pointerId = event.pointerId;
        startX = event.clientX;
        if (viewport.setPointerCapture) viewport.setPointerCapture(event.pointerId);
    });

    viewport.addEventListener('pointerup', function (event) {
        if (pointerId !== event.pointerId) return;
        var delta = event.clientX - startX;
        pointerId = null;
        if (Math.abs(delta) < 48) return;
        move(delta < 0 ? 1 : -1);
    });

    viewport.addEventListener('pointercancel', function () {
        pointerId = null;
    });

    window.addEventListener('resize', function () { layout(false); });
    reduce.addEventListener('change', function () { layout(false); });
    layout(false);
})();

(function () {
    var form = document.getElementById('contact-form');
    var success = document.getElementById('contact-success');
    var submit = document.getElementById('contact-submit');
    var formError = form ? form.querySelector('[data-form-error]') : null;

    if (!form || !success || !submit || !formError) {
        return;
    }

    var submitLabel = submit.querySelector('[data-submit-label]');

    function clearErrors() {
        form.querySelectorAll('[data-error-for]').forEach(function (node) {
            node.hidden = true;
            node.textContent = '';
            node.closest('.contact-field')?.classList.remove('is-invalid');
        });
        formError.hidden = true;
        formError.textContent = '';
    }

    function showErrors(errors) {
        Object.entries(errors).forEach(function (entry) {
            var field = entry[0];
            var messages = entry[1];
            var node = form.querySelector('[data-error-for="' + field + '"]');
            if (!node) {
                return;
            }
            node.textContent = Array.isArray(messages) ? messages[0] : messages;
            node.hidden = false;
            node.closest('.contact-field')?.classList.add('is-invalid');
        });
    }

    function captchaToken() {
        var siteKey = form.dataset.recaptchaSiteKey;
        var action = form.dataset.recaptchaAction || 'contact';

        if (!siteKey) {
            return Promise.resolve('');
        }

        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () { reject(new Error('timeout')); }, 8000);

            var run = function () {
                window.grecaptcha.ready(function () {
                    window.grecaptcha.execute(siteKey, { action: action })
                        .then(function (token) {
                            clearTimeout(timer);
                            resolve(token);
                        })
                        .catch(function (error) {
                            clearTimeout(timer);
                            reject(error);
                        });
                });
            };

            if (window.grecaptcha && window.grecaptcha.execute) {
                run();
                return;
            }

            window.addEventListener('recaptcha-api-loaded', function () {
                if (window.grecaptcha && window.grecaptcha.execute) {
                    run();
                    return;
                }

                clearTimeout(timer);
                reject(new Error('unavailable'));
            }, { once: true });
        });
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearErrors();
        submit.disabled = true;
        if (submitLabel) {
            submitLabel.textContent = 'در حال ارسال...';
        }

        try {
            var body = new FormData(form);

            if (form.dataset.recaptchaSiteKey) {
                try {
                    body.set('captcha', await captchaToken());
                } catch (error) {
                    formError.textContent = 'تأیید امنیتی انجام نشد. لطفاً دوباره تلاش کنید.';
                    formError.hidden = false;
                    return;
                }
            }

            var response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: body,
            });
            var data = await response.json().catch(function () { return {}; });

            if (response.status === 422) {
                showErrors(data.errors || {});
                if (!data.errors) {
                    formError.textContent = data.message || 'اطلاعات واردشده معتبر نیست.';
                    formError.hidden = false;
                }
                return;
            }

            if (response.status === 429) {
                formError.textContent = 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.';
                formError.hidden = false;
                return;
            }

            if (!response.ok) {
                formError.textContent = 'ارسال پیام انجام نشد. دوباره تلاش کنید.';
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
            if (submitLabel) {
                submitLabel.textContent = 'ارسال پیام';
            }
        }
    });
})();
