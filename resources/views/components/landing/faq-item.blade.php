@props([
    'question',
    'answer',
])

<details class="faq__item">
    <summary>
        <span class="faq__qwrap">
            <span class="faq__chevron" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <span class="faq__q">{{ $question }}</span>
        </span>
        <span class="faq__icon" aria-hidden="true">
            {{ $slot }}
        </span>
    </summary>
    <div class="faq__panel">
        <p class="faq__answer">{{ $answer }}</p>
    </div>
</details>
