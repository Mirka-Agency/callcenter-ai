<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="آوایار مکالمه‌های مرکز تماس را تحلیل می‌کند؛ خلاصه، احساس مشتری، کیفیت لید، عملکرد کارشناسان و کوچینگ را در یک پلتفرم کنار هم می‌گذارد.">
    <title>{{ config('app.name') }} — هوش تماس برای مرکز تماس</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/saas.css'])
    <script>
        (function () {
            document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');
        })();
    </script>
    <style>
        .landing-frame {
            overflow-x: clip;
        }

        .landing-hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: calc(100dvh - 4.5rem);
            overflow: visible;
            background:
                radial-gradient(820px 420px at 16% 48%, rgba(124, 37, 253, 0.09), transparent 70%),
                radial-gradient(640px 360px at 82% 12%, rgba(99, 102, 241, 0.08), transparent 72%),
                linear-gradient(180deg, #fcfbff 0%, #f6f4ff 52%, #fafafa 100%);
        }

        .landing-hero__net,
        .landing-hero__ring {
            position: absolute;
            pointer-events: none;
        }

        .landing-hero__net {
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .landing-hero__ring {
            border: 1px dashed rgba(167, 139, 250, 0.55);
            border-radius: 999px;
        }

        .landing-hero__ring--a {
            width: 280px;
            height: 280px;
            top: 36px;
            inset-inline-start: -90px;
        }

        .landing-hero__ring--b {
            width: 168px;
            height: 168px;
            right: 7%;
            bottom: 28px;
        }

        .landing-hero__inner {
            position: relative;
            z-index: 1;
            display: grid;
            align-items: center;
            gap: 2.25rem;
            width: min(1160px, calc(100% - 2rem));
            min-width: 0;
            margin-inline: auto;
            padding-block: 0.35rem 1.25rem;
        }

        .landing-hero__copy,
        .landing-hero__stage,
        .landing-hero__tilt,
        .landing-window,
        .landing-main,
        .landing-split,
        .landing-panel {
            min-width: 0;
        }

        .landing-hero__copy h1 {
            margin: 0;
            color: var(--color-ink);
            font-size: clamp(1.65rem, 2.35vw, 2.85rem);
            font-weight: 700;
            line-height: 1.45;
        }

        .landing-hero__copy h1 span {
            display: block;
        }

        .landing-hero__copy h1 .is-accent {
            color: var(--color-brand);
            white-space: nowrap;
        }

        .landing-hero__copy p {
            max-width: 36rem;
            margin: 1.35rem 0 0;
            color: var(--color-muted);
            font-size: 1.02rem;
            line-height: 2;
        }

        .landing-hero__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.75rem;
        }

        .landing-hero__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            height: 3rem;
            padding-inline: 1.15rem;
            border: 1px solid var(--color-line);
            border-radius: 999px;
            background: #fff;
            color: var(--color-ink);
            font-size: 0.95rem;
            font-family: inherit;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(24, 24, 27, 0.04);
            transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
        }

        .landing-hero__btn svg {
            width: 1.15rem;
            height: 1.15rem;
            flex: none;
        }

        .landing-hero__btn--primary {
            border-color: transparent;
            background: var(--color-brand);
            color: #fff;
            box-shadow: 0 10px 24px rgba(124, 37, 253, 0.28);
        }

        .landing-hero__btn--primary:hover {
            background: var(--color-brand-hover);
        }

        .landing-hero__btn--ghost:hover {
            border-color: #ddd6fe;
            background: var(--color-lavender);
        }

        .landing-hero__btn:focus-visible {
            outline: 2px solid var(--color-brand);
            outline-offset: 3px;
        }

        .landing-hero__stage {
            position: relative;
            margin: 0;
            padding: 4.25rem 0.25rem 2.75rem;
        }

        .landing-hero__chips {
            position: absolute;
            inset: 0;
            z-index: 3;
            pointer-events: none;
        }

        .landing-hero__chip {
            position: absolute;
            display: inline-flex;
            align-items: center;
            padding: 0.42rem 0.8rem;
            border: 1px solid #ece9f8;
            border-radius: 999px;
            background: #fff;
            color: #3f3f46;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1.4;
            white-space: nowrap;
            box-shadow: 0 10px 28px rgba(99, 102, 241, 0.1);
        }

        .landing-hero__chip::after {
            content: "";
            position: absolute;
            background: repeating-linear-gradient(#c4b5fd, #c4b5fd 3px, transparent 3px, transparent 7px);
        }

        .landing-hero__chip--call { top: 1.7rem; inset-inline-start: 0; }
        .landing-hero__chip--ai { top: 0.35rem; inset-inline-start: 34%; }
        .landing-hero__chip--opp {
            top: auto;
            right: auto;
            bottom: 0.15rem;
            left: 1.15rem;
        }

        .landing-hero__chip--call::after,
        .landing-hero__chip--ai::after {
            top: calc(100% + 4px);
            inset-inline-start: 1.5rem;
            width: 1.5px;
            height: 28px;
        }

        .landing-hero__chip--opp::after {
            bottom: calc(100% + 3px);
            left: 28%;
            width: 1.5px;
            height: 118px;
        }

        .landing-hero__chip--voice {
            bottom: 0.15rem;
            inset-inline-start: 46%;
        }

        .landing-hero__chip--voice::after {
            bottom: calc(100% + 3px);
            left: 86%;
            width: 1.5px;
            height: 104px;
        }

        .landing-hero__sr {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @keyframes landing-hero-float {
            0%, 100% { transform: perspective(1700px) rotateX(7deg) rotateY(15deg) translateY(0); }
            50% { transform: perspective(1700px) rotateX(7deg) rotateY(15deg) translateY(-8px); }
        }

        @keyframes landing-hero-float-flat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .landing-hero__tilt {
            position: relative;
            transform-origin: 78% 50%;
            animation: landing-hero-float 6s ease-in-out infinite;
        }

        .landing-window {
            display: flex;
            max-width: 100%;
            min-height: 0;
            overflow: hidden;
            border: 1px solid rgba(231, 231, 238, 0.95);
            border-radius: 22px;
            background: #fff;
            box-shadow:
                0 28px 70px rgba(99, 102, 241, 0.13),
                0 10px 28px rgba(24, 24, 27, 0.05);
        }

        .landing-side {
            display: flex;
            flex: none;
            flex-direction: column;
            gap: 0.3rem;
            width: 148px;
            padding: 16px 12px;
            border-inline-end: 1px solid var(--color-line);
            background: #fff;
        }

        .landing-side__brand {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.85rem;
            padding-inline: 0.35rem;
            color: var(--color-ink);
            font-size: 0.92rem;
            font-weight: 700;
        }

        .landing-side__link {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 2.05rem;
            padding-inline: 0.55rem;
            border-radius: 0.7rem;
            color: #71717a;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .landing-side__link svg {
            width: 0.95rem;
            height: 0.95rem;
            flex: none;
        }

        .landing-side__link.is-active {
            background: var(--color-brand);
            color: #fff;
        }

        .landing-main {
            display: flex;
            min-width: 0;
            flex: 1;
            flex-direction: column;
            gap: 0.65rem;
            padding: 14px;
            background: #f7f6fc;
        }

        .landing-sample {
            display: inline-flex;
            align-items: center;
            height: 1.35rem;
            padding-inline: 0.5rem;
            border-radius: 999px;
            background: var(--color-lavender);
            color: var(--color-brand);
            font-size: 0.68rem;
            font-weight: 600;
        }

        .landing-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .landing-kpi {
            min-width: 0;
            padding: 0.7rem 0.75rem 0.65rem;
            border: 1px solid rgba(231, 231, 238, 0.9);
            border-radius: 14px;
            background: #fff;
        }

        .landing-kpi--green { background: #f0fdf6; }
        .landing-kpi--violet { background: #f5f3ff; }
        .landing-kpi--rose { background: #fff1f2; }

        .landing-kpi__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
        }

        .landing-kpi__label {
            color: #71717a;
            font-size: 0.68rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .landing-kpi__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.6rem;
            height: 1.6rem;
            flex: none;
            border-radius: 0.5rem;
            background: #fff;
            color: var(--color-ai);
        }

        .landing-kpi--green .landing-kpi__icon { color: #059669; }
        .landing-kpi--rose .landing-kpi__icon { color: #e11d48; }

        .landing-kpi__icon svg {
            width: 0.9rem;
            height: 0.9rem;
        }

        .landing-kpi__value {
            margin-top: 0.45rem;
            color: var(--color-ink);
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        .landing-kpi__delta {
            margin-top: 0.2rem;
            color: #059669;
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .landing-kpi__delta.is-alert { color: #e11d48; }

        .landing-split {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.95fr);
            gap: 0.5rem;
            flex: none;
        }

        .landing-panel {
            display: flex;
            min-width: 0;
            flex-direction: column;
            padding: 0.8rem 0.85rem 0.7rem;
            border: 1px solid var(--color-line);
            border-radius: 14px;
            background: #fff;
        }

        .landing-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.55rem;
        }

        .landing-panel__head strong {
            color: var(--color-ink);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .landing-panel__head span,
        .landing-chart__x,
        .landing-chart__y {
            color: #a1a1aa;
            font-size: 0.62rem;
        }

        .landing-panel__head .landing-sample {
            color: var(--color-brand);
            font-size: 0.68rem;
        }

        .landing-chart__body {
            display: flex;
            align-items: stretch;
            gap: 0.35rem;
            flex: none;
        }

        .landing-chart__y {
            position: relative;
            flex: none;
            width: 1.55rem;
            height: 118px;
            line-height: 1;
        }

        .landing-chart__y span {
            position: absolute;
            inset-inline-end: 0;
            transform: translateY(-50%);
        }

        .landing-chart__y span:nth-child(1) { top: 12.5%; }
        .landing-chart__y span:nth-child(2) { top: 37.5%; }
        .landing-chart__y span:nth-child(3) { top: 62.5%; }
        .landing-chart__y span:nth-child(4) { top: 87.5%; }

        .landing-chart__svg {
            display: block;
            width: auto;
            height: 118px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .landing-chart__x {
            display: flex;
            justify-content: space-between;
            margin-top: 0.35rem;
            margin-inline-start: 1.9rem;
        }

        .landing-note ul {
            display: grid;
            gap: 0.45rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .landing-note li {
            position: relative;
            padding-inline-start: 0.8rem;
            color: #3f3f46;
            font-size: 0.68rem;
            line-height: 1.75;
        }

        .landing-note li::before {
            content: "";
            position: absolute;
            top: 0.48rem;
            inset-inline-start: 0;
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: var(--color-brand);
        }

        .landing-note__more {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: auto;
            padding-top: 0.7rem;
            color: var(--color-brand);
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
        }

        .landing-note__more:hover { color: var(--color-brand-hover); }

        .landing-note__more svg {
            width: 0.85rem;
            height: 0.85rem;
        }

        .landing-moods {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.95fr);
            gap: 0.5rem;
        }

        .landing-mood {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
            padding: 0.55rem 0.7rem;
            border: 1px solid var(--color-line);
            border-radius: 14px;
            background: #fff;
        }

        .landing-mood__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            height: 1.7rem;
            flex: none;
            border-radius: 0.55rem;
        }

        .landing-mood__icon svg {
            width: 1rem;
            height: 1rem;
        }

        .landing-mood--good { background: #f0fdf6; }
        .landing-mood--good .landing-mood__icon { background: #fff; color: #059669; }
        .landing-mood--bad { background: #fff1f2; }
        .landing-mood--bad .landing-mood__icon { background: #fff; color: #e11d48; }

        .landing-mood p {
            margin: 0;
            color: #71717a;
            font-size: 0.68rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .landing-mood strong {
            display: block;
            margin-top: 0.1rem;
            color: var(--color-ink);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        @media (min-width: 1024px) {
            .landing-hero {
                height: calc(100dvh - 4.5rem);
                max-height: calc(100dvh - 4.5rem);
            }

            .landing-hero__inner {
                grid-template-columns: minmax(280px, 0.78fr) minmax(0, 1.22fr);
                gap: 1.25rem;
                padding-block: 0;
                transform: translateY(clamp(-2.75rem, -4vh, -1.5rem));
            }
        }

        @media (max-width: 1023px) {
            .landing-hero {
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .landing-hero__stage { padding: 0; }

            .landing-hero__chips {
                position: static;
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                max-width: 100%;
                margin-bottom: 0.9rem;
            }

            .landing-hero__chip,
            .landing-hero__chip--call,
            .landing-hero__chip--ai,
            .landing-hero__chip--opp,
            .landing-hero__chip--voice {
                position: static;
            }

            .landing-hero__chip::after { display: none; }

            .landing-hero__tilt { animation-name: landing-hero-float-flat; }
        }

        @media (max-width: 720px) {
            .landing-hero__copy h1 { font-size: clamp(1.35rem, 6.4vw, 1.7rem); }
            .landing-hero__actions { display: grid; grid-template-columns: 1fr; }
            .landing-hero__btn { width: 100%; }
            .landing-side { width: 52px; padding-inline: 8px; }
            .landing-side__brand strong,
            .landing-side__link span { display: none; }
            .landing-side__link { justify-content: center; padding-inline: 0; }
            .landing-kpis { grid-template-columns: 1fr 1fr; }
            .landing-split { grid-template-columns: 1fr; }
            .landing-moods { grid-template-columns: 1fr 1fr; }
            .landing-hero__ring { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .landing-hero__tilt { animation: none; }
        }

        .dark .landing-hero {
            background:
                radial-gradient(820px 420px at 16% 48%, rgba(124, 37, 253, 0.22), transparent 70%),
                radial-gradient(640px 360px at 82% 12%, rgba(99, 102, 241, 0.16), transparent 72%),
                #09090b;
        }

        .dark .landing-hero__copy h1 { color: #fafafa; }
        .dark .landing-hero__copy p { color: #a1a1aa; }

        .dark .landing-hero__btn--ghost {
            border-color: #3f3f46;
            background: #18181b;
            color: #fafafa;
        }

        .dark .landing-hero__chip {
            border-color: #3f3f46;
            background: #18181b;
            color: #e4e4e7;
        }

        .journey {
            --journey-ring: rgba(176, 162, 230, 0.72);
            --journey-ring-soft: rgba(186, 174, 232, 0.38);
            position: relative;
            height: 180vh;
            overflow: visible;
            background:
                radial-gradient(560px 440px at 74% 54%, rgba(124, 37, 253, 0.1), transparent 68%),
                radial-gradient(420px 360px at 18% 58%, rgba(99, 102, 241, 0.06), transparent 72%),
                linear-gradient(180deg, #fcfbff 0%, #f7f5ff 46%, #fafafa 100%);
            scroll-margin-top: 5rem;
        }

        .journey__sticky {
            position: sticky;
            top: 4.5rem;
            display: flex;
            align-items: stretch;
            height: calc(100vh - 4.5rem);
            overflow: visible;
            padding: 0.65rem 0 0.85rem;
        }

        .journey__rings {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: visible;
            pointer-events: none;
        }

        .journey__ring {
            position: absolute;
            border: 1.5px solid var(--journey-ring);
            border-radius: 999px;
            transform: translate3d(var(--ring-x, 0px), calc(var(--ring-y, 0px) + var(--ring-shift, 0px)), 0);
            will-change: transform;
        }

        .journey__ring--back {
            width: min(46rem, 74vh);
            height: min(46rem, 74vh);
            top: -32%;
            left: -28%;
            border-color: var(--journey-ring-soft);
            --ring-x: 0px;
            --ring-y: 0px;
        }

        .journey__ring--left {
            width: min(34rem, 60vh);
            height: min(34rem, 60vh);
            top: 50%;
            left: -2.5rem;
            --ring-x: -52%;
            --ring-y: -50%;
        }

        .journey__ring--corner {
            width: min(26rem, 46vh);
            height: min(26rem, 46vh);
            right: -8%;
            bottom: -18%;
            --ring-x: 0px;
            --ring-y: 0px;
        }

        .journey__stage {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            width: min(1480px, calc(100% - 1.5rem));
            height: 100%;
            margin-inline: auto;
        }

        .journey__head {
            flex: none;
            text-align: center;
        }

        .journey__kicker {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            width: fit-content;
            margin: 0 auto 0.7rem;
            padding: 0.38rem 0.95rem;
            border: 1px solid #ddd6fe;
            border-radius: 999px;
            background: #fff;
            color: #7c25fd;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.5;
        }

        .journey__kicker-dot {
            width: 0.38rem;
            height: 0.38rem;
            border-radius: 999px;
            background: #7c25fd;
        }

        .journey__title {
            margin: 0 0 0.4rem;
            color: #18181b;
            font-size: 1.85rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .journey__title span {
            color: var(--color-brand, #7c25fd);
        }

        .journey__lead {
            margin: 0 auto 0.85rem;
            max-width: 42rem;
            color: #71717a;
            font-size: 0.98rem;
            font-weight: 400;
            line-height: 1.9;
        }

        .journey__board {
            position: relative;
            z-index: 1;
            display: grid;
            flex: 1;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            align-items: center;
            min-height: 0;
            gap: 0.5rem;
            direction: ltr;
        }

        .journey__path {
            position: relative;
            justify-self: end;
            width: auto;
            max-width: 100%;
            height: min(100%, 760px);
            aspect-ratio: 520 / 460;
            container-type: size;
        }

        .journey__sr {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .journey__wires {
            position: absolute;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .journey__wires path {
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 3.2px;
        }

        .journey__track {
            stroke: rgba(176, 162, 230, 0.28);
        }

        .journey__draw {
            stroke: var(--color-brand, #7c25fd);
        }

        .journey__call,
        .journey__brand,
        .journey__bubble,
        .journey__spark,
        .journey__dash,
        .journey__dot {
            position: absolute;
            z-index: 2;
        }

        .journey__call {
            left: 42.3%;
            top: 2.2%;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .journey__label {
            color: var(--color-ink, #18181b);
            font-size: 3.4cqw;
            font-weight: 700;
            line-height: 1.4;
            white-space: nowrap;
        }

        .journey__icon {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 10.5cqw;
            height: 10.5cqw;
            border: 1px solid #efeafc;
            border-radius: 22%;
            background: #fff;
            color: var(--color-brand, #7c25fd);
            box-shadow: 0 10px 24px rgba(99, 70, 190, 0.12);
        }

        .journey__icon svg {
            width: 46%;
            height: 46%;
        }

        .journey__icon--phone {
            border-color: transparent;
            background: var(--color-brand, #7c25fd);
            color: #fff;
            box-shadow: 0 12px 26px rgba(124, 37, 253, 0.32);
        }

        .journey__icon--phone svg {
            width: 42%;
            height: 42%;
        }

        .journey__dot {
            width: 2.2cqw;
            height: 2.2cqw;
            border-radius: 999px;
            background: #c4c4ce;
            box-shadow: 0 0 0 0.55cqw rgba(228, 228, 231, 0.9);
            transform: translate(-50%, -50%);
            transition: background 0.35s ease, box-shadow 0.45s ease;
        }

        .journey__dot.is-on {
            background: var(--color-brand, #7c25fd);
            box-shadow: 0 0 0 0.7cqw rgba(124, 37, 253, 0.2);
            animation: journey-dot 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .journey__dot--before-brand {
            left: 30%;
            top: 33.6%;
        }

        .journey__dot--after-brand {
            left: 30%;
            top: 47.2%;
        }

        .journey__dot--before-spark {
            left: 30%;
            top: 57.5%;
        }

        .journey__dot--after-spark {
            left: 30%;
            top: 72.9%;
        }

        .journey__dot--end {
            left: 46.5%;
            top: 87.8%;
            width: 1.6cqw;
            height: 1.6cqw;
            box-shadow: 0 0 0 0.4cqw rgba(228, 228, 231, 0.95);
        }

        .journey__dot--end.is-on {
            box-shadow: 0 0 0 0.5cqw rgba(124, 37, 253, 0.2);
        }

        .journey__brand {
            left: 30%;
            top: 40.4%;
            margin: 0;
            padding: 0.9cqw 2.4cqw;
            border: 1px solid rgba(231, 231, 238, 0.95);
            border-radius: 1.15rem;
            background: #fff;
            box-shadow: none;
            color: #a1a1aa;
            border-color: #e7e7ee;
            font-size: 6.2cqw;
            font-weight: 700;
            line-height: 1;
            transform: translate(-50%, -50%);
            transition: color 0.4s ease, border-color 0.4s ease;
        }

        .journey__brand img {
            display: block;
            height: 4.6cqw;
            width: auto;
            opacity: 0.38;
            transition: opacity 0.4s ease;
        }

        .journey__brand.is-on {
            color: var(--color-ink, #18181b);
            border-color: #ddd6fe;
            animation: journey-brand 0.55s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .journey__brand.is-on img {
            opacity: 1;
        }

        .journey__bubble {
            left: 40%;
            top: 52%;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            padding: 0.7cqw 1.3cqw;
            border: 1px solid rgba(231, 231, 238, 0.95);
            border-radius: 999px;
            background: #fff;
            box-shadow: none;
            color: #a1a1aa;
            border-color: #ececf1;
            font-size: 2.9cqw;
            font-weight: 600;
            line-height: 1.4;
            white-space: nowrap;
            transform: translateY(-50%);
            transition: color 0.4s ease, border-color 0.4s ease;
        }

        .journey__bubble.is-on {
            color: #3f3f46;
            border-color: rgba(231, 231, 238, 0.95);
            animation: journey-bubble 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .journey__wave {
            width: 4.2cqw;
            height: 3.1cqw;
            flex: none;
            color: #c4c4ce;
            transition: color 0.4s ease;
        }

        .journey__bubble.is-on .journey__wave {
            color: var(--color-brand, #7c25fd);
        }

        .journey__status {
            display: inline-block;
        }

        .journey__status.is-out {
            animation: journey-status-out 0.18s ease forwards;
        }

        .journey__status.is-in {
            animation: journey-status-in 0.34s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes journey-status-out {
            to {
                opacity: 0;
                transform: translateY(-0.4em);
            }
        }

        @keyframes journey-status-in {
            from {
                opacity: 0;
                transform: translateY(0.45em);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .journey__wave rect {
            fill: currentColor;
        }

        .journey__spark {
            left: 30%;
            top: 65.2%;
            transform: translate(-50%, -50%);
        }

        .journey__spark.is-on {
            animation: journey-brand 0.55s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .journey__icon--spark {
            box-shadow: none;
            color: #a1a1aa;
            border-color: #e4e4e7;
            transition: color 0.4s ease, border-color 0.4s ease;
        }

        .journey__spark.is-on .journey__icon--spark {
            color: var(--color-brand, #7c25fd);
            border-color: #ddd6fe;
        }

        .journey__spark::before {
            content: "";
            position: absolute;
            inset: -0.9rem;
            z-index: -1;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(124, 37, 253, 0.4), rgba(124, 37, 253, 0) 68%);
            opacity: 0;
            transition: opacity 0.45s ease;
        }

        .journey__spark.is-on::before {
            opacity: 1;
        }

        @keyframes journey-dot {
            0% { transform: translate(-50%, -50%) scale(0.65); }
            55% { transform: translate(-50%, -50%) scale(1.28); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes journey-brand {
            0% { transform: translate(-50%, -50%) scale(0.9); }
            55% { transform: translate(-50%, -50%) scale(1.08); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes journey-bubble {
            0% { transform: translateY(-50%) scale(0.96); }
            100% { transform: translateY(-50%) scale(1); }
        }

        @keyframes journey-pop {
            0% { transform: scale(0.82); }
            55% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .journey__dash {
            left: 51%;
            top: 87.8%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            transform: translateY(-50%);
        }

        .journey__poster {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            height: 100%;
            min-height: 0;
            margin: 0;
        }

        .journey__poster img {
            display: block;
            width: auto;
            max-width: 100%;
            height: auto;
            max-height: 100%;
            object-fit: contain;
        }

        @media (max-width: 1279px) {
            .journey {
                height: auto;
                padding: 3.25rem 1rem 3.5rem;
            }

            .journey__sticky {
                position: static;
                display: block;
                height: auto;
            }

            .journey__ring--back {
                width: 18rem;
                height: 18rem;
                top: -8rem;
                left: -14rem;
            }

            .journey__ring--left {
                width: 15rem;
                height: 15rem;
                top: 18%;
                left: -3.5rem;
            }

            .journey__ring--corner {
                width: 13rem;
                height: 13rem;
                top: auto;
                right: -4.5rem;
                bottom: 8%;
            }

            .journey__stage {
                display: block;
                height: auto;
            }

            .journey__title {
                font-size: 1.35rem;
            }

            .journey__lead {
                margin-bottom: 1.25rem;
                font-size: 0.92rem;
            }

            .journey__board {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 1.75rem;
            }

            .journey__path {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-self: center;
                gap: 1.35rem;
                width: min(100%, 20rem);
                height: auto;
                aspect-ratio: auto;
                margin-inline: auto;
                padding-block: 0.25rem;
                container-type: normal;
            }

            .journey__call::after,
            .journey__brand::after,
            .journey__bubble::after,
            .journey__spark::after {
                content: "";
                position: absolute;
                z-index: 0;
                top: 100%;
                left: 50%;
                width: 2px;
                height: 1.35rem;
                border-radius: 999px;
                background: #c4b5fd;
                transform: translateX(-50%);
                pointer-events: none;
            }

            .journey__label { font-size: 1rem; }

            .journey__icon {
                width: 3.25rem;
                height: 3.25rem;
                border-radius: 1rem;
            }

            .journey__icon svg,
            .journey__icon--phone svg {
                width: 1.35rem;
                height: 1.35rem;
            }

            .journey__dot,
            .journey__dot--end {
                width: 11px;
                height: 11px;
                box-shadow: 0 0 0 5px rgba(124, 37, 253, 0.14);
            }

            .journey__brand {
                padding: 0.95rem 1.7rem;
                font-size: 1.7rem;
            }

            .journey__brand img {
                height: 1.45rem;
            }

            .journey__bubble {
                padding: 0.55rem 0.95rem;
                font-size: 0.92rem;
            }

            .journey__wave {
                width: 1.35rem;
                height: 1rem;
            }

            .journey__wires,
            .journey__dot {
                display: none;
            }

            .journey__brand.is-on,
            .journey__bubble.is-on,
            .journey__spark.is-on {
                animation-name: journey-pop;
            }

            .journey__call,
            .journey__dash {
                flex-direction: column;
                gap: 0.45rem;
                text-align: center;
            }

            .journey__call,
            .journey__brand,
            .journey__bubble,
            .journey__spark,
            .journey__dash {
                position: relative;
                z-index: 1;
                left: auto;
                top: auto;
                transform: none;
            }

            .journey__bubble {
                white-space: normal;
                justify-content: center;
                max-width: min(100%, 22rem);
                text-align: center;
            }

            .journey__poster {
                height: auto;
                justify-content: center;
            }

            .journey__poster img {
                width: min(100%, 28rem);
                height: auto;
                max-height: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .journey {
                height: auto;
            }

            .journey__sticky {
                position: static;
                display: block;
                height: auto;
                padding-block: 4.25rem;
            }

            .journey__ring {
                transform: none;
            }

            .journey__ring--left {
                transform: translate(-52%, -50%);
            }

            .journey__dot,
            .journey__brand,
            .journey__bubble,
            .journey__spark,
            .journey__icon--spark,
            .journey__status {
                animation: none;
            }
        }

        .dark .journey {
            --journey-ring: rgba(167, 139, 250, 0.45);
            --journey-ring-soft: rgba(167, 139, 250, 0.22);
            background:
                radial-gradient(560px 440px at 74% 54%, rgba(124, 37, 253, 0.22), transparent 68%),
                radial-gradient(420px 360px at 16% 62%, rgba(99, 102, 241, 0.14), transparent 72%),
                #09090b;
        }

        .dark .journey__track {
            stroke: #3f3f46;
        }

        .dark .journey__call::after,
        .dark .journey__brand::after,
        .dark .journey__bubble::after,
        .dark .journey__spark::after {
            background: #7c3aed;
        }

        .dark .journey__kicker {
            border-color: #6d28d9;
            background: #18181b;
            color: #c4b5fd;
        }

        .dark .journey__kicker-dot {
            background: #c4b5fd;
        }

        .dark .journey__title {
            color: #fafafa;
        }

        .dark .journey__title span {
            color: #c4b5fd;
        }

        .dark .journey__brand,
        .dark .journey__bubble,
        .dark .journey__icon:not(.journey__icon--phone) {
            border-color: #3f3f46;
            background: #18181b;
            color: #fafafa;
        }

        .dark .journey__lead {
            color: #a1a1aa;
        }

        .dark .journey__label {
            color: #e4e4e7;
        }

        .dark .journey__brand,
        .dark .journey__bubble {
            color: #71717a;
            box-shadow: none;
        }

        .dark .journey__brand.is-on {
            color: #fafafa;
        }

        .dark .journey__brand img {
            filter: invert(1);
            opacity: 0.45;
        }

        .dark .journey__brand.is-on img {
            opacity: 1;
        }

        .dark .journey__bubble.is-on {
            color: #e4e4e7;
        }

        .dark .journey__icon:not(.journey__icon--phone) {
            color: #c4b5fd;
        }

        .dark .journey__spark .journey__icon--spark {
            color: #71717a;
            box-shadow: none;
        }

        .dark .journey__spark.is-on .journey__icon--spark {
            color: #c4b5fd;
        }

        .insight__main {
            position: relative;
        }

        .insight {
            position: relative;
            isolation: isolate;
            background:
                radial-gradient(520px 280px at 78% 58%, rgba(124, 37, 253, 0.07), transparent 70%),
                radial-gradient(420px 240px at 10% 82%, rgba(99, 102, 241, 0.06), transparent 72%),
                #fafafa;
        }

        .challenges {
            position: relative;
            z-index: 1;
            overflow: visible;
            padding-block: 5.25rem 5.5rem;
            background: transparent;
            scroll-margin-top: 5rem;
        }

        .challenges__inner {
            width: min(1160px, calc(100% - 2rem));
            margin-inline: auto;
        }

        .challenges__top {
            display: grid;
            grid-template-columns: minmax(0, 0.86fr) minmax(0, 1.14fr);
            align-items: center;
            gap: 1.5rem 2rem;
        }

        .challenges__kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0 0 1.15rem;
            padding: 0.42rem 0.9rem;
            border: 1px solid #eceaf3;
            border-radius: 999px;
            background: #fff;
            color: #3f3f46;
            font-size: 0.86rem;
            font-weight: 600;
            line-height: 1;
            box-shadow: 0 8px 18px rgba(24, 24, 27, 0.04);
        }

        .challenges__kicker svg {
            width: 0.95rem;
            height: 0.95rem;
            color: #7c25fd;
        }

        .challenges__title {
            margin: 0;
            color: #18181b;
            font-size: clamp(1.85rem, 3.1vw, 2.7rem);
            font-weight: 700;
            line-height: 1.4;
        }

        .challenges__title span {
            display: block;
            color: #7c25fd;
        }

        .challenges__lead {
            max-width: 26rem;
            margin: 1rem 0 0;
            color: #71717a;
            font-size: 1rem;
            line-height: 1.95;
        }

        .challenges__visual {
            position: relative;
            margin: 0;
        }

        .challenges__visual::before {
            content: "";
            position: absolute;
            z-index: 0;
            width: 72%;
            height: 78%;
            inset-inline-start: 12%;
            top: 12%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(196, 181, 253, 0.55) 0%, rgba(245, 243, 255, 0.15) 48%, transparent 72%);
            pointer-events: none;
        }

        .challenges__visual img {
            position: relative;
            z-index: 1;
            display: block;
            width: 100%;
            height: auto;
        }

        .challenges__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.15rem;
            margin-top: 3.25rem;
        }

        .challenges__card {
            min-width: 0;
            padding: 1.4rem 1.35rem 1.5rem;
            border: 1px solid #eceaf3;
            border-radius: 1.35rem;
            background: #fff;
            box-shadow: 0 14px 34px rgba(24, 24, 27, 0.045);
        }

        .challenges__icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.7rem;
            height: 3.7rem;
            border-radius: 1.05rem;
            background: #f3efff;
            color: #7c25fd;
        }

        .challenges__glyph {
            width: 1.8rem;
            height: 1.8rem;
        }

        .challenges__x {
            position: absolute;
            width: 1.2rem;
            height: 1.2rem;
            inset-inline-end: 0.38rem;
            bottom: 0.34rem;
        }

        .challenges__mark {
            fill: #fff;
        }

        .challenges__card h3 {
            margin: 1.05rem 0 0;
            color: #18181b;
            font-size: 1.02rem;
            font-weight: 700;
            line-height: 1.7;
        }

        .challenges__card p {
            margin: 0.4rem 0 0;
            color: #71717a;
            font-size: 0.92rem;
            line-height: 1.95;
        }

        @media (max-width: 900px) {
            .challenges {
                padding-block: 3.75rem 3.5rem;
            }

            .challenges__top,
            .challenges__grid {
                grid-template-columns: 1fr;
            }

            .challenges__grid {
                margin-top: 2.25rem;
            }

            .challenges__visual {
                max-width: 36rem;
                margin-inline: auto;
            }
        }

        .dark .insight {
            background:
                radial-gradient(520px 280px at 78% 58%, rgba(124, 37, 253, 0.16), transparent 70%),
                #09090b;
        }

        .dark .challenges {
            background: transparent;
        }

        .dark .challenges__kicker {
            border-color: #3f3f46;
            background: #18181b;
            color: #e4e4e7;
            box-shadow: none;
        }

        .dark .challenges__title,
        .dark .challenges__card h3 {
            color: #fafafa;
        }

        .dark .challenges__title span {
            color: #c4b5fd;
        }

        .dark .challenges__lead,
        .dark .challenges__card p {
            color: #a1a1aa;
        }

        .dark .challenges__card {
            border-color: #27272a;
            background: #18181b;
            box-shadow: none;
        }

        .dark .challenges__icon {
            background: rgba(124, 37, 253, 0.16);
            color: #c4b5fd;
        }

        .dark .challenges__mark {
            fill: #18181b;
        }

        .sample-call {
            position: relative;
            z-index: 1;
            overflow: visible;
            padding-block: 5.25rem 5.5rem;
            background: transparent;
            scroll-margin-top: 5rem;
        }

        .sample-call__scene {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: visible;
            pointer-events: none;
        }

        .sample-call__net {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            transform: translate3d(0, var(--shift, 0px), 0);
        }

        .sample-call__shape {
            position: absolute;
            border-radius: 999px;
            transform: translate3d(0, var(--shift, 0px), 0);
            will-change: transform;
        }

        .sample-call__shape--ring {
            width: 240px;
            height: 240px;
            top: 46%;
            inset-inline-end: -48px;
            border: 1.5px dashed rgba(167, 139, 250, 0.75);
        }

        .sample-call__shape--orbit {
            width: 168px;
            height: 168px;
            top: 14%;
            inset-inline-start: -54px;
            border: 1px dashed rgba(124, 37, 253, 0.4);
        }

        .sample-call__shape--soft {
            width: min(22rem, 46vw);
            height: min(22rem, 46vw);
            right: 18%;
            bottom: -18%;
            border: 1.5px solid rgba(196, 181, 253, 0.55);
        }

        .sample-call__shape--dot {
            width: 9px;
            height: 9px;
            background: #ddd6fe;
        }

        .sample-call__shape--dot-a { top: 22%; inset-inline-start: 12%; }
        .sample-call__shape--dot-b { top: 68%; inset-inline-end: 8%; }
        .sample-call__shape--dot-c { bottom: 12%; inset-inline-start: 42%; width: 6px; height: 6px; }

        .sample-call__inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(16rem, 0.72fr) minmax(0, 1.28fr);
            align-items: center;
            gap: 2rem 2.5rem;
            width: min(1160px, calc(100% - 2rem));
            margin-inline: auto;
        }

        .sample-call__kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0 0 1.15rem;
            padding: 0.42rem 0.9rem;
            border: 1px solid #eceaf3;
            border-radius: 999px;
            background: #fff;
            color: #3f3f46;
            font-size: 0.86rem;
            font-weight: 600;
            line-height: 1;
            box-shadow: 0 8px 18px rgba(24, 24, 27, 0.04);
        }

        .sample-call__kicker svg {
            width: 0.95rem;
            height: 0.95rem;
            color: #7c25fd;
        }

        .sample-call__title {
            margin: 0;
            color: #18181b;
            font-size: clamp(1.7rem, 2.7vw, 2.35rem);
            font-weight: 700;
            line-height: 1.45;
        }

        .sample-call__title span {
            color: #7c25fd;
        }

        .sample-call__lead {
            margin: 1rem 0 0;
            color: #71717a;
            font-size: 1rem;
            line-height: 1.95;
        }

        .sample-call__points {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            margin: 1.6rem 0 0;
            padding: 0;
            list-style: none;
        }

        .sample-call__point {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.85rem 0.95rem;
            border: 1px solid #eceaf3;
            border-radius: 1.05rem;
            background: #fff;
            box-shadow: 0 10px 24px rgba(24, 24, 27, 0.04);
        }

        .sample-call__point h3 {
            margin: 0;
            color: #18181b;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.6;
        }

        .sample-call__point p {
            margin: 0.1rem 0 0;
            color: #71717a;
            font-size: 0.8rem;
            line-height: 1.7;
        }

        .sample-call__icon {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            margin-inline-start: auto;
            border-radius: 0.75rem;
            background: #f5f3ff;
            color: #7c25fd;
        }

        .sample-call__icon svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        .sample-call__stage {
            position: relative;
            min-width: 0;
        }

        .sample-call__preview {
            margin: 0;
            padding: 0.85rem;
            border: 1px solid #eceaf3;
            border-radius: 1.35rem;
            background: #fff;
            box-shadow: 0 18px 40px rgba(24, 24, 27, 0.06);
        }

        .sample-call__board {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            min-width: 0;
            padding: 0.75rem;
            border-radius: 1rem;
            background: #fafafa;
            color: #18181b;
            font-size: 0.72rem;
            line-height: 1.7;
        }

        .sample-call__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.7rem 0.75rem;
            border: 1px solid #eceaf3;
            border-radius: 0.9rem;
            background: #fff;
        }

        .sample-call__eyebrow {
            margin: 0;
            color: #6366f1;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .sample-call__name {
            margin: 0.15rem 0 0;
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .sample-call__role,
        .sample-call__meta {
            margin: 0.1rem 0 0;
            color: #71717a;
        }

        .sample-call__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.7rem;
        }

        .sample-call__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.45rem;
        }

        .sample-call__chip {
            display: inline-flex;
            align-items: center;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
            background: #f4f4f5;
            color: #3f3f46;
            font-size: 0.66rem;
            font-weight: 600;
        }

        .sample-call__chip.is-good {
            background: #ecfdf5;
            color: #059669;
        }

        .sample-call__scores {
            display: flex;
            flex: none;
            gap: 0.4rem;
        }

        .sample-call__score {
            min-width: 4.6rem;
            padding: 0.35rem 0.5rem 0.4rem;
            border: 2px solid #a7f3d0;
            border-radius: 0.55rem;
            background: #ecfdf5;
            text-align: center;
        }

        .sample-call__score span {
            display: block;
            color: #71717a;
            font-size: 0.62rem;
        }

        .sample-call__score strong {
            display: block;
            color: #059669;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .sample-call__wave {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.45rem 0.6rem;
            border: 1px solid #eceaf3;
            border-radius: 0.85rem;
            background: #fff;
        }

        .sample-call__play {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 999px;
            background: #7c25fd;
            color: #fff;
        }

        .sample-call__play svg {
            width: 0.7rem;
            height: 0.7rem;
            margin-inline-start: 0.08rem;
        }

        .sample-call__bars {
            flex: 1;
            height: 1.7rem;
            min-width: 0;
        }

        .sample-call__time {
            flex: none;
            color: #71717a;
            font-variant-numeric: tabular-nums;
        }

        .sample-call__split,
        .sample-call__mid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .sample-call__panel {
            min-width: 0;
            padding: 0.6rem 0.7rem;
            border: 1px solid #eceaf3;
            border-radius: 0.85rem;
            background: #fff;
        }

        .sample-call__panel h3,
        .sample-call__note h3 {
            margin: 0;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .sample-call__panel p,
        .sample-call__note p {
            margin: 0.25rem 0 0;
            color: #3f3f46;
        }

        .sample-call__panel.is-eval {
            background: #f5f3ff;
            border-color: #e4e0f5;
        }

        .sample-call__facts {
            display: grid;
            gap: 0.25rem;
            margin: 0.4rem 0 0;
        }

        .sample-call__facts div {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .sample-call__facts dt {
            color: #71717a;
        }

        .sample-call__facts dd {
            margin: 0;
            font-weight: 700;
        }

        .sample-call__strengths {
            margin: 0.35rem 0 0;
            padding: 0;
            list-style: none;
        }

        .sample-call__strengths li {
            position: relative;
            padding-inline-start: 0.75rem;
        }

        .sample-call__strengths li::before {
            content: "";
            position: absolute;
            inset-inline-start: 0;
            top: 0.55rem;
            width: 0.28rem;
            height: 0.28rem;
            border-radius: 999px;
            background: currentColor;
        }

        .sample-call__panel.is-good {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #065f46;
        }

        .sample-call__panel.is-warn {
            border-color: #fde68a;
            background: #fffbeb;
            color: #92400e;
        }

        .sample-call__dims {
            display: grid;
            gap: 0.45rem;
        }

        .sample-call__dim-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.2rem;
        }

        .sample-call__dim-head strong {
            color: #059669;
            font-variant-numeric: tabular-nums;
        }

        .sample-call__track {
            height: 0.45rem;
            overflow: hidden;
            border-radius: 999px;
            background: #f4f4f5;
        }

        .sample-call__track i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(to left, #6366f1, #8b5cf6);
        }

        .sample-call__trio {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.45rem;
        }

        .sample-call__note {
            min-width: 0;
            padding: 0.55rem 0.6rem;
            border-radius: 0.8rem;
        }

        .sample-call__note ul {
            margin: 0.3rem 0 0;
            padding: 0;
            list-style: none;
        }

        .sample-call__note li {
            position: relative;
            padding-inline-start: 0.7rem;
        }

        .sample-call__note li::before {
            content: "";
            position: absolute;
            inset-inline-start: 0;
            top: 0.5rem;
            width: 0.28rem;
            height: 0.28rem;
            border-radius: 999px;
            background: currentColor;
        }

        .sample-call__note.is-action {
            background: #f5f3ff;
            color: #3f3f46;
        }

        .sample-call__note.is-warn {
            background: #fffbeb;
            color: #92400e;
        }

        .sample-call__note.is-good {
            background: #ecfdf5;
            color: #065f46;
        }

        .sample-call__banners {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 0.45rem;
        }

        .sample-call__banner {
            padding: 0.55rem 0.65rem;
            border-radius: 0.8rem;
        }

        .sample-call__banner h3 {
            margin: 0;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .sample-call__banner p,
        .sample-call__banner li {
            margin: 0.2rem 0 0;
            color: #3f3f46;
        }

        .sample-call__banner ul {
            margin: 0.25rem 0 0;
            padding: 0;
            list-style: none;
        }

        .sample-call__banner.is-buy {
            background: #eef2ff;
        }

        .sample-call__banner.is-lead {
            background: #fff1f2;
        }

        .sample-call__missed {
            margin: 0;
            color: #be123c;
            font-size: 0.66rem;
        }

        @media (max-width: 980px) {
            .sample-call {
                padding-block: 3.75rem 3.5rem;
            }

            .sample-call__inner {
                grid-template-columns: 1fr;
            }

            .sample-call__shape--soft,
            .sample-call__shape--orbit {
                display: none;
            }

        }

        @media (max-width: 640px) {
            .sample-call__split,
            .sample-call__mid,
            .sample-call__trio,
            .sample-call__banners,
            .sample-call__head {
                grid-template-columns: 1fr;
            }

            .sample-call__head {
                display: grid;
            }

        }

        .dark .sample-call {
            background: transparent;
        }

        .dark .sample-call__shape--ring,
        .dark .sample-call__shape--orbit {
            border-color: rgba(167, 139, 250, 0.35);
        }

        .dark .sample-call__shape--soft {
            border-color: rgba(124, 37, 253, 0.28);
        }

        .dark .sample-call__shape--dot {
            background: #6d28d9;
        }

        .dark .sample-call__kicker,
        .dark .sample-call__point,
        .dark .sample-call__preview,
        .dark .sample-call__head,
        .dark .sample-call__wave,
        .dark .sample-call__panel {
            border-color: #27272a;
            background: #18181b;
            box-shadow: none;
        }

        .dark .sample-call__title,
        .dark .sample-call__point h3,
        .dark .sample-call__name,
        .dark .sample-call__score strong,
        .dark .sample-call__panel h3 {
            color: #fafafa;
        }

        .dark .sample-call__title span {
            color: #c4b5fd;
        }

        .dark .sample-call__lead,
        .dark .sample-call__point p,
        .dark .sample-call__role,
        .dark .sample-call__meta,
        .dark .sample-call__panel p,
        .dark .sample-call__facts dt {
            color: #a1a1aa;
        }

        .dark .sample-call__board {
            background: #09090b;
        }

        .dark .sample-call__icon {
            background: rgba(124, 37, 253, 0.16);
            color: #e4e4e7;
        }

        .dark .sample-call__score {
            border-color: #065f46;
            background: rgba(6, 78, 59, 0.45);
        }

        .dark .sample-call__score strong,
        .dark .sample-call__dim-head strong {
            color: #34d399;
        }

        .dark .sample-call__panel.is-eval,
        .dark .sample-call__note.is-action {
            background: rgba(124, 37, 253, 0.16);
            color: #e4e4e7;
        }

        .dark .sample-call__panel.is-good {
            border-color: #065f46;
            background: rgba(6, 78, 59, 0.45);
            color: #a7f3d0;
        }

        .dark .sample-call__panel.is-warn {
            border-color: #92400e;
            background: rgba(120, 53, 15, 0.35);
            color: #fde68a;
        }

        .dark .sample-call__chip {
            background: #27272a;
            color: #e4e4e7;
        }

        .dark .sample-call__track {
            background: #27272a;
        }

        .coaching {
            position: relative;
            z-index: 1;
            overflow: visible;
            padding-block: 5.25rem 5.5rem;
            background: transparent;
            scroll-margin-top: 5rem;
        }

        .coaching__inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.18fr) minmax(16rem, 0.82fr);
            align-items: center;
            gap: 1.5rem 2rem;
            width: min(1160px, calc(100% - 2rem));
            margin-inline: auto;
        }

        .coaching__visual {
            order: 1;
            margin: 0;
            min-width: 0;
        }

        .coaching__visual img {
            display: block;
            width: 100%;
            height: auto;
        }

        .coaching__copy {
            order: 2;
            min-width: 0;
        }

        .coaching__kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0 0 1.15rem;
            padding: 0.42rem 0.85rem;
            border-radius: 999px;
            background: #f5f3ff;
            color: #7c25fd;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1;
        }

        .coaching__kicker svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .coaching__title {
            margin: 0;
            color: #18181b;
            font-size: clamp(1.85rem, 3vw, 2.55rem);
            font-weight: 800;
            line-height: 1.45;
        }

        .coaching__title span {
            display: block;
            color: #7c25fd;
        }

        .coaching__lead {
            margin: 1.05rem 0 0;
            color: #71717a;
            font-size: 0.98rem;
            line-height: 1.95;
        }

        .coaching__points {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin: 1.5rem 0 0;
            padding: 0;
            list-style: none;
        }

        .coaching__point {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.95rem 1rem;
            border: 1px solid #eceaf3;
            border-radius: 1.15rem;
            background: #fff;
            box-shadow: 0 10px 28px rgba(24, 24, 27, 0.04);
        }

        .coaching__icon {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 2.7rem;
            height: 2.7rem;
            border-radius: 0.85rem;
            background: #f5f3ff;
            color: #7c25fd;
        }

        .coaching__icon svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .coaching__point h3 {
            margin: 0;
            color: #18181b;
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.6;
        }

        .coaching__point p {
            margin: 0.15rem 0 0;
            color: #71717a;
            font-size: 0.84rem;
            line-height: 1.7;
        }

        @media (max-width: 980px) {
            .coaching {
                padding-block: 3.75rem 3.5rem;
            }

            .coaching__inner {
                grid-template-columns: 1fr;
            }

            .coaching__copy {
                order: 1;
            }

            .coaching__visual {
                order: 2;
                max-width: 40rem;
                margin-inline: auto;
            }
        }

        .dark .coaching {
            background: transparent;
        }

        .dark .coaching__kicker,
        .dark .coaching__icon {
            background: rgba(124, 37, 253, 0.16);
            color: #c4b5fd;
        }

        .dark .coaching__title,
        .dark .coaching__point h3 {
            color: #fafafa;
        }

        .dark .coaching__title span {
            color: #c4b5fd;
        }

        .dark .coaching__lead,
        .dark .coaching__point p {
            color: #a1a1aa;
        }

        .dark .coaching__point {
            border-color: #27272a;
            background: #18181b;
            box-shadow: none;
        }

        .faq {
            position: relative;
            z-index: 1;
            overflow: hidden;
            padding-block: 4.5rem 5.25rem;
            background:
                radial-gradient(520px 220px at 12% 18%, rgba(124, 37, 253, 0.05), transparent 70%),
                #fafafa;
            scroll-margin-top: 5rem;
        }

        .faq__inner {
            width: min(1160px, calc(100% - 2rem));
            margin-inline: auto;
        }

        .faq__head {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 42rem;
            margin-inline: auto;
            text-align: center;
        }

        .faq__head::before {
            content: "";
            position: absolute;
            z-index: 0;
            width: 6.5rem;
            height: 3.6rem;
            inset-inline-end: -2.25rem;
            top: 2.5rem;
            background-image: radial-gradient(circle, #d4d4d8 1.05px, transparent 1.15px);
            background-size: 11px 11px;
            opacity: 0.8;
            pointer-events: none;
        }

        .faq__curve {
            position: absolute;
            z-index: 0;
            width: 8.5rem;
            height: 2.2rem;
            inset-inline-end: -0.75rem;
            top: 4.15rem;
            pointer-events: none;
        }

        .faq__kicker,
        .faq__title,
        .faq__lead {
            position: relative;
            z-index: 1;
        }

        .faq__kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0 0 1.05rem;
            padding: 0.42rem 0.9rem;
            border-radius: 999px;
            background: #f5f3ff;
            color: #7c25fd;
            font-size: 0.84rem;
            font-weight: 700;
            line-height: 1;
        }

        .faq__kicker svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .faq__title {
            display: inline-flex;
            align-items: flex-start;
            justify-content: center;
            gap: 0.35rem;
            margin: 0;
            color: #18181b;
            font-size: clamp(1.7rem, 3vw, 2.35rem);
            font-weight: 800;
            line-height: 1.55;
        }

        .faq__spark {
            flex: none;
            width: 1.35rem;
            height: 1.35rem;
            margin-top: 0.35rem;
            color: #18181b;
        }

        .faq__lead {
            margin: 0.85rem auto 0;
            max-width: 34rem;
            color: #71717a;
            font-size: 0.95rem;
            line-height: 1.9;
        }

        .faq__layout {
            display: grid;
            grid-template-columns: minmax(0, 1.28fr) minmax(17.5rem, 0.78fr);
            align-items: stretch;
            gap: 1.15rem;
            margin-top: 2.35rem;
        }

        .faq__list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            min-width: 0;
        }

        .faq__item {
            border: 1px solid #eceaf3;
            border-radius: 1.15rem;
            background: #fff;
            box-shadow: 0 10px 28px rgba(24, 24, 27, 0.035);
        }

        .faq__item summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            padding: 1.05rem 1.15rem;
            cursor: pointer;
            list-style: none;
        }

        .faq__item summary::-webkit-details-marker {
            display: none;
        }

        .faq__item summary:focus-visible {
            outline: 2px solid rgba(124, 37, 253, 0.35);
            outline-offset: 2px;
            border-radius: 1rem;
        }

        .faq__qwrap {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
        }

        .faq__q {
            color: #18181b;
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.7;
        }

        .faq__chevron {
            display: inline-flex;
            flex: none;
            color: #a1a1aa;
            transition: transform 0.2s ease;
        }

        .faq__chevron svg {
            width: 1rem;
            height: 1rem;
        }

        .faq__item[open] .faq__chevron {
            transform: rotate(180deg);
        }

        .faq__icon {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            height: 1.7rem;
            color: #7c25fd;
        }

        .faq__icon svg {
            width: 1.35rem;
            height: 1.35rem;
        }

        .faq__answer {
            margin: 0;
            padding: 0 1.15rem 1.1rem;
            padding-inline-start: 3.4rem;
            color: #71717a;
            font-size: 0.9rem;
            line-height: 1.95;
        }

        .faq__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 0;
            padding: 1.35rem 1.25rem 1.05rem;
            border: 1px solid #efeaf8;
            border-radius: 1.6rem;
            background: linear-gradient(180deg, #fcfbff 0%, #f6f3ff 100%);
            box-shadow: 0 16px 40px rgba(124, 37, 253, 0.06);
            text-align: center;
        }

        .faq__art {
            display: block;
            width: min(100%, 19.5rem);
            height: auto;
            margin: 0.15rem 0 0.35rem;
        }

        .faq__card h3 {
            margin: 0.35rem 0 0;
            color: #18181b;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.6;
        }

        .faq__card p {
            margin: 0.55rem 0 0;
            max-width: 18rem;
            color: #71717a;
            font-size: 0.86rem;
            line-height: 1.85;
        }

        .faq__cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            margin-top: 1.15rem;
            height: 2.7rem;
            padding-inline: 1.25rem;
            border-radius: 999px;
            background: #7c25fd;
            color: #fff;
            border: 0;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .faq__cta:hover {
            background: #6820db;
        }

        .faq__cta:focus-visible {
            outline: 2px solid rgba(124, 37, 253, 0.4);
            outline-offset: 3px;
        }

        .faq__cta svg {
            width: 1rem;
            height: 1rem;
        }

        .faq__perks {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.4rem;
            width: 100%;
            margin: 1.25rem 0 0;
            padding: 0.95rem 0.15rem 0.15rem;
            border-top: 1px solid #eceaf3;
            list-style: none;
        }

        .faq__perks li {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            color: #3f3f46;
            font-size: 0.74rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .faq__perk-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            height: 1.7rem;
            color: #7c25fd;
        }

        .faq__perk-icon svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        @media (max-width: 980px) {
            .faq {
                padding-block: 3.5rem 3.75rem;
            }

            .faq__layout {
                grid-template-columns: 1fr;
            }

            .faq__card {
                max-width: 28rem;
                margin-inline: auto;
                width: 100%;
            }

            .faq__head::before,
            .faq__curve {
                display: none;
            }
        }

        .dark .faq {
            background:
                radial-gradient(520px 220px at 12% 18%, rgba(124, 37, 253, 0.12), transparent 70%),
                transparent;
        }

        .dark .faq__kicker {
            background: rgba(124, 37, 253, 0.16);
            color: #c4b5fd;
        }

        .dark .faq__title,
        .dark .faq__spark,
        .dark .faq__q,
        .dark .faq__card h3 {
            color: #fafafa;
        }

        .dark .faq__lead,
        .dark .faq__answer,
        .dark .faq__card p {
            color: #a1a1aa;
        }

        .dark .faq__item,
        .dark .faq__card {
            border-color: #27272a;
            background: #18181b;
            box-shadow: none;
        }

        .dark .faq__icon,
        .dark .faq__perk-icon {
            color: #c4b5fd;
        }

        .dark .faq__perks {
            border-top-color: #27272a;
        }

        .dark .faq__perks li {
            color: #d4d4d8;
        }

        .dark .faq__cta {
            color: #fff;
        }

        .site-footer {
            position: relative;
            overflow: hidden;
            border-top: 1px solid var(--color-line);
            background:
                radial-gradient(520px 180px at 8% 100%, rgba(124, 37, 253, 0.05), transparent 70%),
                #fff;
        }

        .site-footer__inner {
            width: min(72rem, calc(100% - 2rem));
            margin-inline: auto;
            padding-block: 3.25rem 1.5rem;
        }

        .site-footer__grid {
            display: grid;
            gap: 2.5rem 2rem;
        }

        .site-footer__brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .site-footer__logo {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--color-ink);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .site-footer__logo img {
            display: block;
            height: 2rem;
            width: auto;
        }

        .site-footer__logo svg {
            width: 1.75rem;
            height: 1.5rem;
        }

        .site-footer__lead {
            max-width: 18rem;
            color: #71717a;
            font-size: 0.92rem;
            line-height: 1.9;
        }

        .site-footer__pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .site-footer__pills li {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            height: 2.15rem;
            padding-inline: 0.8rem;
            border: 1px solid #ececf4;
            border-radius: 999px;
            background: #fff;
            color: #3f3f46;
            font-size: 0.78rem;
            font-weight: 500;
        }

        .site-footer__pills svg {
            width: 0.95rem;
            height: 0.95rem;
            color: var(--color-brand);
        }

        .site-footer__col h2 {
            margin: 0 0 1rem;
            color: var(--color-ink);
            font-size: 0.98rem;
            font-weight: 700;
        }

        .site-footer__links,
        .site-footer__contacts,
        .site-footer__integrations {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.7rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .site-footer__links a {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #52525b;
            font-size: 0.9rem;
            transition: color 0.15s ease;
        }

        .site-footer__links a:hover {
            color: var(--color-brand);
        }

        .site-footer__links svg {
            width: 0.85rem;
            height: 0.85rem;
            color: #a1a1aa;
        }

        .site-footer__contacts a,
        .site-footer__contacts button {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0;
            border: 0;
            background: transparent;
            color: #3f3f46;
            font: inherit;
            font-size: 0.9rem;
            text-align: start;
            cursor: pointer;
        }

        .site-footer__contacts a:hover span:last-child,
        .site-footer__contacts button:hover span:last-child {
            color: var(--color-brand);
        }

        .site-footer__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 0.7rem;
            background: #f5f3ff;
            color: var(--color-brand);
        }

        .site-footer__icon svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .site-footer__contacts small {
            display: block;
            color: #a1a1aa;
            font-size: 0.72rem;
            line-height: 1.4;
        }

        .site-footer__integrations a {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 11.5rem;
            height: 2.7rem;
            padding-inline: 0.9rem;
            border: 1px solid #ececf4;
            border-radius: 0.9rem;
            background: #fff;
            color: #27272a;
            font-size: 0.9rem;
            font-weight: 500;
            transition: border-color 0.15s ease, color 0.15s ease;
        }

        .site-footer__integrations a:hover {
            border-color: #ddd6fe;
            color: var(--color-brand);
        }

        .site-footer__integrations svg {
            width: 1rem;
            height: 1rem;
            color: var(--color-ai);
        }

        .site-footer__bar {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            margin-top: 2.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f0f0f5;
        }

        .site-footer__notes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .site-footer__notes a {
            display: inline-flex;
            align-items: center;
            height: 2rem;
            padding-inline: 0.75rem;
            border-radius: 999px;
            background: #fafafa;
            color: #71717a;
            font-size: 0.75rem;
        }

        .site-footer__notes a:hover {
            color: var(--color-brand);
        }

        .site-footer__wave {
            width: min(100%, 16rem);
            height: 2.2rem;
            color: #ddd6fe;
        }

        .site-footer__copy {
            margin: 0;
            color: #a1a1aa;
            font-size: 0.8rem;
        }

        @media (min-width: 900px) {
            .site-footer__grid {
                grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr) minmax(0, 1fr) minmax(0, 0.95fr);
            }

            .site-footer__bar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .site-footer__wave {
                order: 0;
            }
        }

        .dark .site-footer {
            border-top-color: #27272a;
            background:
                radial-gradient(520px 180px at 8% 100%, rgba(124, 37, 253, 0.12), transparent 70%),
                #09090b;
        }

        .dark .site-footer__logo img {
            filter: brightness(0) invert(1);
        }

        .dark .site-footer__logo,
        .dark .site-footer__col h2,
        .dark .site-footer__integrations a,
        .dark .site-footer__contacts a,
        .dark .site-footer__contacts button {
            color: #fafafa;
        }

        .dark .site-footer__lead,
        .dark .site-footer__links a,
        .dark .site-footer__copy,
        .dark .site-footer__notes a,
        .dark .site-footer__contacts small {
            color: #a1a1aa;
        }

        .dark .site-footer__pills li,
        .dark .site-footer__integrations a,
        .dark .site-footer__notes a {
            border-color: #27272a;
            background: #18181b;
            color: #e4e4e7;
        }

        .dark .site-footer__icon {
            background: rgba(124, 37, 253, 0.16);
        }

        .dark .site-footer__bar {
            border-top-color: #27272a;
        }

        .consult-dialog {
            width: min(32rem, calc(100% - 1.5rem));
            max-height: calc(100dvh - 1.5rem);
            margin: auto;
            padding: 0;
            overflow: auto;
            border: 1px solid #e7e7ee;
            border-radius: 1.25rem;
            background: #fff;
            color: #18181b;
            box-shadow: 0 24px 60px rgba(24, 24, 27, 0.16);
        }

        .consult-dialog::backdrop {
            background: rgba(24, 24, 27, 0.48);
        }

        .consult-dialog__panel {
            padding: 1.35rem 1.25rem 1.25rem;
        }

        .consult-dialog__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .consult-dialog__head h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.6;
        }

        .consult-dialog__head p {
            margin: 0.35rem 0 0;
            color: #71717a;
            font-size: 0.88rem;
            line-height: 1.8;
        }

        .consult-dialog__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            flex: none;
            border: 1px solid #e7e7ee;
            border-radius: 0.7rem;
            background: #fff;
            color: #3f3f46;
            cursor: pointer;
        }

        .consult-dialog__close svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .consult-form {
            display: grid;
            gap: 0.85rem;
            margin-top: 1.15rem;
        }

        .consult-field {
            display: grid;
            gap: 0.35rem;
        }

        .consult-field span {
            font-size: 0.84rem;
            font-weight: 600;
        }

        .consult-field input,
        .consult-field textarea {
            width: 100%;
            border: 1px solid #e7e7ee;
            border-radius: 0.8rem;
            background: #fff;
            color: inherit;
            font: inherit;
            font-size: 0.92rem;
            line-height: 1.7;
            padding: 0.65rem 0.8rem;
        }

        .consult-field textarea {
            min-height: 6.5rem;
            resize: vertical;
        }

        .consult-field input:focus,
        .consult-field textarea:focus {
            outline: 2px solid rgba(124, 37, 253, 0.35);
            outline-offset: 1px;
            border-color: #c4b5fd;
        }

        .consult-field.is-invalid input,
        .consult-field.is-invalid textarea {
            border-color: #e11d48;
        }

        .consult-error,
        .consult-form-error {
            margin: 0;
            color: #e11d48;
            font-size: 0.78rem;
            line-height: 1.6;
        }

        .consult-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 2.75rem;
            margin-top: 0.2rem;
            border: 0;
            border-radius: 0.8rem;
            background: #7c25fd;
            color: #fff;
            font: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
        }

        .consult-submit:hover {
            background: #6820db;
        }

        .consult-submit:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .consult-success {
            margin-top: 1.25rem;
            padding: 1rem;
            border-radius: 0.9rem;
            background: #f5f3ff;
            text-align: center;
        }

        .consult-success h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .consult-success p {
            margin: 0.4rem 0 0;
            color: #71717a;
            font-size: 0.88rem;
            line-height: 1.8;
        }

        .consult-success button {
            margin-top: 0.9rem;
            height: 2.5rem;
            padding-inline: 1.1rem;
            border: 0;
            border-radius: 0.75rem;
            background: #7c25fd;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .consult-honeypot {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
        }

        .dark .consult-dialog {
            border-color: #27272a;
            background: #18181b;
            color: #fafafa;
        }

        .dark .consult-dialog__head p,
        .dark .consult-success p {
            color: #a1a1aa;
        }

        .dark .consult-dialog__close,
        .dark .consult-field input,
        .dark .consult-field textarea {
            border-color: #3f3f46;
            background: #09090b;
            color: #fafafa;
        }

        .dark .consult-success {
            background: rgba(124, 37, 253, 0.16);
        }

    </style>
</head>
<body class="bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="landing-frame">
    <header class="sticky top-0 z-40 border-b border-line bg-white dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto grid h-[4.5rem] max-w-6xl grid-cols-[auto_1fr_auto] items-center gap-3 px-4 sm:px-6 lg:grid-cols-[1fr_auto_1fr] lg:gap-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 justify-self-start">
                <img src="{{ asset('images/journey/avayar-logo.webp') }}" width="480" height="151" alt="آوایار" class="h-8 w-auto dark:brightness-0 dark:invert" decoding="async">
                <svg viewBox="0 0 28 24" class="h-6 w-7 shrink-0" aria-hidden="true">
                    <rect x="1" y="10" width="3.2" height="4" rx="1.6" fill="var(--color-ai)" opacity="0.55" />
                    <rect x="6.4" y="6" width="3.2" height="12" rx="1.6" fill="var(--color-brand)" />
                    <rect x="11.8" y="8" width="3.2" height="8" rx="1.6" fill="var(--color-ai)" />
                    <rect x="17.2" y="3" width="3.2" height="18" rx="1.6" fill="var(--color-brand)" />
                    <rect x="22.6" y="7" width="3.2" height="10" rx="1.6" fill="var(--color-ai)" opacity="0.8" />
                </svg>
            </a>

            <nav class="hidden items-center justify-center gap-6 text-sm font-medium text-ink lg:flex dark:text-zinc-100" aria-label="بخش‌های صفحه">
                <a href="#sample-call" class="transition hover:text-brand">امکانات</a>
                <a href="#journey" class="transition hover:text-brand">نحوه کار</a>
                <a href="#sample-call" class="transition hover:text-brand">داشبورد</a>
                <a href="#faq" class="transition hover:text-brand">سوالات متداول</a>
            </nav>

            <div class="flex items-center justify-self-end gap-2">
                <a href="{{ route('login') }}" class="hidden h-10 items-center justify-center rounded-lg border border-line bg-white px-4 text-sm font-medium text-ink transition hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 sm:inline-flex dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">ورود</a>
                <button type="button" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg border-0 bg-brand px-3.5 text-sm font-medium text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 sm:px-4" data-consult-open>درخواست مشاوره</button>

                <details class="relative lg:hidden">
                    <summary class="inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-line bg-white text-ink marker:content-none [&::-webkit-details-marker]:hidden dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" aria-label="باز کردن منو">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </summary>
                    <div class="absolute end-0 z-50 mt-2 w-56 rounded-xl border border-line bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <a href="#sample-call" class="block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" onclick="this.closest('details').open=false">امکانات</a>
                        <a href="#journey" class="block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" onclick="this.closest('details').open=false">نحوه کار</a>
                        <a href="#sample-call" class="block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" onclick="this.closest('details').open=false">داشبورد</a>
                        <a href="#faq" class="block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" onclick="this.closest('details').open=false">سوالات متداول</a>
                        <button type="button" class="block w-full rounded-lg border-0 bg-transparent px-3 py-2 text-start text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" data-consult-open>درخواست مشاوره</button>
                        <a href="{{ route('login') }}" class="mt-1 block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas sm:hidden dark:text-zinc-100 dark:hover:bg-zinc-800">ورود</a>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main>
        <section class="landing-hero" aria-labelledby="landing-hero-title">
            <svg class="landing-hero__net" viewBox="0 0 1200 720" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g fill="none" stroke="#c4b5fd" stroke-width="1.25" stroke-dasharray="2 7" stroke-linecap="round" opacity="0.85">
                    <path d="M40 150C140 90 220 210 360 140" />
                    <path d="M860 70C980 150 1080 80 1180 160" />
                    <path d="M30 560C160 500 250 640 430 560" />
                </g>
                <g fill="#ddd6fe">
                    <circle cx="40" cy="150" r="4" />
                    <circle cx="360" cy="140" r="3" />
                    <circle cx="860" cy="70" r="4" />
                    <circle cx="1180" cy="160" r="3" />
                    <circle cx="30" cy="560" r="3.5" />
                    <circle cx="430" cy="560" r="3" />
                </g>
            </svg>
            <span class="landing-hero__ring landing-hero__ring--a" aria-hidden="true"></span>
            <span class="landing-hero__ring landing-hero__ring--b" aria-hidden="true"></span>

            <div class="landing-hero__inner">
                <div class="landing-hero__copy">
                    <h1 id="landing-hero-title">
                        <span>آوایار،</span>
                        <span class="is-accent">تحلیلگر هوشمند تماس</span>
                    </h1>
                    <p>هر تماس، فرصتی برای شناخت بهتر مشتری و رشد کسب‌وکار شماست. آوایار با تحلیل هوشمند مکالمات، عملکرد تیم و فرصت‌های فروش را آشکار می‌کند تا تصمیم‌های دقیق‌تری بگیرید.</p>
                    <div class="landing-hero__actions">
                        <button type="button" class="landing-hero__btn landing-hero__btn--primary" data-consult-open>
                            <span>درخواست مشاوره</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M5 12l6-6M5 12l6 6" />
                            </svg>
                        </button>
                        <a class="landing-hero__btn landing-hero__btn--ghost" href="#sample-call">
                            <span>مشاهده داشبورد</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6" />
                                <path d="M10.2 8.6v6.8l5.6-3.4-5.6-3.4z" fill="currentColor" />
                            </svg>
                        </a>
                    </div>
                </div>

                <figure class="landing-hero__stage">
                    <figcaption class="landing-hero__sr">پیش‌نمایش نمونه داشبورد آوایار. عددها نمایشی هستند و نتیجه واقعی سازمان نیستند.</figcaption>
                    <div class="landing-hero__chips">
                        <span class="landing-hero__chip landing-hero__chip--call">تحلیل در کمتر از چند ثانیه</span>
                        <span class="landing-hero__chip landing-hero__chip--ai">عملکرد امروز در یک نگاه</span>
                        <span class="landing-hero__chip landing-hero__chip--opp">هر گفتگو یک اتفاق مهم</span>
                        <span class="landing-hero__chip landing-hero__chip--voice">روند پیشرفت کارشناس</span>
                    </div>
                    <div class="landing-hero__tilt">
                        <div class="landing-window">
                            <aside class="landing-side" aria-hidden="true">
                                <div class="landing-side__brand">
                                    <svg viewBox="0 0 28 24" width="22" height="18" aria-hidden="true">
                                        <rect x="1" y="10" width="3.2" height="4" rx="1.6" fill="var(--color-ai)" opacity="0.55" />
                                        <rect x="6.4" y="6" width="3.2" height="12" rx="1.6" fill="var(--color-brand)" />
                                        <rect x="11.8" y="8" width="3.2" height="8" rx="1.6" fill="var(--color-ai)" />
                                        <rect x="17.2" y="3" width="3.2" height="18" rx="1.6" fill="var(--color-brand)" />
                                        <rect x="22.6" y="7" width="3.2" height="10" rx="1.6" fill="var(--color-ai)" opacity="0.8" />
                                    </svg>
                                    <strong>{{ config('app.name') }}</strong>
                                </div>
                                <div class="landing-side__link is-active">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="4" width="6.5" height="6.5" rx="1.4"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.4"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.4"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.4"/></svg>
                                    <span>داشبورد</span>
                                </div>
                                <div class="landing-side__link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                    <span>تماس‌ها</span>
                                </div>
                                <div class="landing-side__link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.1"/><path stroke-linecap="round" d="M5.6 19.2c1.15-2.6 3.4-3.9 6.4-3.9s5.25 1.3 6.4 3.9"/></svg>
                                    <span>کارشناسان</span>
                                </div>
                                <div class="landing-side__link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="9" r="2.6"/><circle cx="16" cy="9.5" r="2.1"/><path stroke-linecap="round" d="M4.8 18.2c.8-2 2.5-3 4.4-3s3.5 1 4.2 2.6M13.2 15.4c1.3-.3 2.6.1 3.5.9 1 .9 1.5 1.6 1.8 2"/></svg>
                                    <span>مشتریان</span>
                                </div>
                                <div class="landing-side__link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4.5v15M6 5.5h11l-2.3 3.3L17 12H6"/></svg>
                                    <span>فرصت‌ها</span>
                                </div>
                                <div class="landing-side__link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" d="M4 8h16M4 16h16"/><circle cx="9" cy="8" r="2.1" fill="#fff"/><circle cx="15" cy="16" r="2.1" fill="#fff"/></svg>
                                    <span>تنظیمات</span>
                                </div>
                            </aside>
                            <div class="landing-main">
                                <div class="landing-kpis" aria-hidden="true">
                                    <article class="landing-kpi">
                                        <div class="landing-kpi__top">
                                            <span class="landing-kpi__label">تماس‌های امروز</span>
                                            <span class="landing-kpi__icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4.5h3l1 3-2 1a12 12 0 006.5 6.5l1-2 3 1v3A2 2 0 0017.6 19 15 15 0 015 6.4 2 2 0 017 4.5z"/></svg>
                                            </span>
                                        </div>
                                        <div class="landing-kpi__value">۲٬۸۵۰</div>
                                        <div class="landing-kpi__delta" dir="ltr">+۱۴٪</div>
                                    </article>
                                    <article class="landing-kpi landing-kpi--green">
                                        <div class="landing-kpi__top">
                                            <span class="landing-kpi__label">رضایت مشتری</span>
                                            <span class="landing-kpi__icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M8.6 13.4c.9 1.7 2 2.5 3.4 2.5s2.5-.8 3.4-2.5"/><circle cx="9" cy="10" r="0.8" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="0.8" fill="currentColor" stroke="none"/></svg>
                                            </span>
                                        </div>
                                        <div class="landing-kpi__value">۸۴٫۷٪</div>
                                        <div class="landing-kpi__delta" dir="ltr">+۲٫۱٪</div>
                                    </article>
                                    <article class="landing-kpi landing-kpi--violet">
                                        <div class="landing-kpi__top">
                                            <span class="landing-kpi__label">میانگین کیفیت</span>
                                            <span class="landing-kpi__icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16.5l4.2-4.6 3.3 2.8L20 6.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5H20V12"/></svg>
                                            </span>
                                        </div>
                                        <div class="landing-kpi__value">۸۶٫۶</div>
                                        <div class="landing-kpi__delta" dir="ltr">+۳٫۸٪</div>
                                    </article>
                                    <article class="landing-kpi landing-kpi--rose">
                                        <div class="landing-kpi__top">
                                            <span class="landing-kpi__label">فرصت پیگیری</span>
                                            <span class="landing-kpi__icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4.5v15M6 5.5h11l-2.3 3.3L17 12H6"/></svg>
                                            </span>
                                        </div>
                                        <div class="landing-kpi__value">۱۲۴</div>
                                        <div class="landing-kpi__delta is-alert" dir="ltr">+۲۲٪</div>
                                    </article>
                                </div>
                                <div class="landing-split">
                                    <div class="landing-panel landing-chart" aria-hidden="true">
                                        <div class="landing-panel__head">
                                            <strong>روند کیفیت تماس‌ها</strong>
                                            <span class="landing-sample">داده نمونه</span>
                                        </div>
                                        <div class="landing-chart__body">
                                            <div class="landing-chart__y">
                                                <span>۱۰۰</span>
                                                <span>۷۵</span>
                                                <span>۵۰</span>
                                                <span>۲۵</span>
                                            </div>
                                            <svg class="landing-chart__svg" viewBox="0 0 300 128" role="img" aria-label="نمودار نمونه روند کیفیت">
                                                    <defs>
                                                        <linearGradient id="landingArea" x1="0" y1="0" x2="0" y2="1">
                                                            <stop offset="0%" stop-color="#7C25FD" stop-opacity="0.28" />
                                                            <stop offset="100%" stop-color="#7C25FD" stop-opacity="0.02" />
                                                        </linearGradient>
                                                    </defs>
                                                    <g stroke="#E7E7EE" stroke-width="1">
                                                        <line x1="0" y1="16" x2="300" y2="16" />
                                                        <line x1="0" y1="48" x2="300" y2="48" />
                                                        <line x1="0" y1="80" x2="300" y2="80" />
                                                        <line x1="0" y1="112" x2="300" y2="112" />
                                                    </g>
                                                    <g transform="translate(300 0) scale(-1 1)">
                                                        <path d="M0 70C22 66 34 40 58 46C84 52 98 96 126 86C154 76 166 36 194 42C220 48 236 78 260 66C276 58 288 40 300 46L300 128L0 128Z" fill="url(#landingArea)" />
                                                        <path d="M0 70C22 66 34 40 58 46C84 52 98 96 126 86C154 76 166 36 194 42C220 48 236 78 260 66C276 58 288 40 300 46" fill="none" stroke="#7C25FD" stroke-width="2.4" stroke-linecap="round" />
                                                    </g>
                                                </svg>
                                        </div>
                                        <div class="landing-chart__x">
                                            <span>۱</span>
                                            <span>۸</span>
                                            <span>۱۵</span>
                                            <span>۲۲</span>
                                            <span>۳۰</span>
                                        </div>
                                    </div>
                                    <div class="landing-panel landing-note">
                                        <div class="landing-panel__head">
                                            <strong>خلاصه هوش مصنوعی</strong>
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#7C25FD" stroke-width="1.7" aria-hidden="true">
                                                <path stroke-linejoin="round" d="M12 3.2l1.3 4.3L17.6 8.8l-4.3 1.3L12 14.4l-1.3-4.3L6.4 8.8l4.3-1.3L12 3.2z" />
                                                <path stroke-linecap="round" d="M17.5 14.5l.6 1.8 1.8.6-1.8.6-.6 1.8-.6-1.8-1.8-.6 1.8-.6.6-1.8z" />
                                            </svg>
                                        </div>
                                        <ul>
                                            <li>رضایت این هفته از هفته قبل بالاتر است.</li>
                                            <li>موضوع غالب: تمدید، اشکال سرویس و پیگیری.</li>
                                            <li>۳۲ تماس نشانه فرصت فروش داشتند.</li>
                                        </ul>
                                        <a class="landing-note__more" href="#sample-call">
                                            مشاهده جزئیات
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 6l-6 6 6 6"/></svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="landing-moods" aria-hidden="true">
                                    <article class="landing-mood landing-mood--good">
                                        <span class="landing-mood__icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M8.6 13.4c.9 1.7 2 2.5 3.4 2.5s2.5-.8 3.4-2.5"/><circle cx="9" cy="10" r="0.8" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="0.8" fill="currentColor" stroke="none"/></svg>
                                        </span>
                                        <div>
                                            <p>مشتریان راضی</p>
                                            <strong>۱۸</strong>
                                        </div>
                                    </article>
                                    <article class="landing-mood landing-mood--bad">
                                        <span class="landing-mood__icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M8.6 15.2c.9-1.5 2-2.2 3.4-2.2s2.5.7 3.4 2.2"/><circle cx="9" cy="10" r="0.8" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="0.8" fill="currentColor" stroke="none"/></svg>
                                        </span>
                                        <div>
                                            <p>مشتریان ناراضی</p>
                                            <strong>۵</strong>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </div>
                </figure>
            </div>
        </section>

        <section id="journey" class="journey" data-journey aria-labelledby="journey-title">
            <div class="journey__sticky">
            <div class="journey__rings" aria-hidden="true">
                <span class="journey__ring journey__ring--back" data-ring="240"></span>
                <span class="journey__ring journey__ring--left" data-ring="170"></span>
                <span class="journey__ring journey__ring--corner" data-ring="120"></span>
            </div>
            <div class="journey__stage">
                <div class="journey__head">
                    <p class="journey__kicker">
                        <span class="journey__kicker-dot" aria-hidden="true"></span>
                        از تماس تا تحلیل
                        <span class="journey__kicker-dot" aria-hidden="true"></span>
                    </p>
                    <h2 id="journey-title" class="journey__title">مسیر یک تماس تا رسیدن به <span>نتیجه</span></h2>
                    <p class="journey__lead">آوایار پس از پایان تماس، مکالمه را تحلیل می‌کند و خروجی قابل‌اقدام را در اختیار تیم شما قرار می‌دهد.</p>
                </div>
                <div class="journey__board">
                    <div class="journey__path">
                        <p class="journey__sr">تماس برقرار می‌شود، به آوایار می‌رسد و نتیجه در داشبورد نمایش داده می‌شود.</p>
                        <svg class="journey__wires" viewBox="0 0 520 460" preserveAspectRatio="none" aria-hidden="true">
                            <path class="journey__track" d="M246 64 V78 Q246 104 214 104 H188 Q156 104 156 129 V154.56" />
                            <path class="journey__track" d="M156 217.12 V264.5" />
                            <path class="journey__track" d="M156 335.34 V378 Q156 404 182 404 H242" />
                            <path class="journey__draw" data-gap="62.56" d="M246 64 V78 Q246 104 214 104 H188 Q156 104 156 129 V154.56" />
                            <path class="journey__draw" data-gap="70.84" d="M156 217.12 V264.5" />
                            <path class="journey__draw" d="M156 335.34 V378 Q156 404 182 404 H242" />
                        </svg>
                        <div class="journey__call">
                            <span class="journey__icon journey__icon--phone">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                            </span>
                            <span class="journey__label">برقراری تماس</span>
                        </div>
                        <span class="journey__dot journey__dot--before-brand" data-step="0.35" aria-hidden="true"></span>
                        <p class="journey__brand" data-step="0.40"><img src="{{ asset('images/journey/avayar-logo.webp') }}" width="480" height="151" alt="آوایار" decoding="async"></p>
                        <p class="journey__bubble" data-step="0">
                            <svg class="journey__wave" viewBox="0 0 22 16" aria-hidden="true">
                                <rect x="1" y="5" width="2.4" height="6" rx="1.2" />
                                <rect x="5.4" y="3" width="2.4" height="10" rx="1.2" />
                                <rect x="9.8" y="1" width="2.4" height="14" rx="1.2" />
                                <rect x="14.2" y="3.5" width="2.4" height="9" rx="1.2" />
                                <rect x="18.6" y="5.5" width="2.4" height="5" rx="1.2" />
                            </svg>
                            <span class="journey__status" data-journey-status>در حال ارسال به آوایار</span>
                        </p>
                        <span class="journey__dot journey__dot--after-brand" data-step="0.47" aria-hidden="true"></span>
                        <span class="journey__dot journey__dot--before-spark" data-step="0.57" aria-hidden="true"></span>
                        <span class="journey__spark" data-step="0.63">
                            <span class="journey__icon journey__icon--spark">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2.4 13.7 8.1 19.4 9.8 13.7 11.5 12 17.2 10.3 11.5 4.6 9.8 10.3 8.1 12 2.4Z" />
                                    <path d="M18.1 14.2 18.8 16.4 21 17.1 18.8 17.8 18.1 20 17.4 17.8 15.2 17.1 17.4 16.4 18.1 14.2Z" />
                                </svg>
                            </span>
                        </span>
                        <span class="journey__dot journey__dot--after-spark" data-step="0.71" aria-hidden="true"></span>
                        <span class="journey__dot journey__dot--end" data-step="0.98" aria-hidden="true"></span>
                        <div class="journey__dash">
                            <span class="journey__icon journey__icon--grid">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <rect x="3" y="3" width="7.2" height="7.2" rx="1.8" />
                                    <rect x="13.8" y="3" width="7.2" height="7.2" rx="1.8" />
                                    <rect x="3" y="13.8" width="7.2" height="7.2" rx="1.8" />
                                    <rect x="13.8" y="13.8" width="7.2" height="7.2" rx="1.8" />
                                </svg>
                            </span>
                            <span class="journey__label">نمایش در داشبورد</span>
                        </div>
                    </div>
                    <figure class="journey__poster">
                        <img src="{{ asset('images/journey/call-path.webp') }}?v=4" srcset="{{ asset('images/journey/call-path.webp') }}?v=4 1x, {{ asset('images/journey/call-path@2x.webp') }}?v=4 2x" width="1024" height="682" alt="تماس ورودی به آوایار می‌رسد؛ تحلیل کارشناس، احساس، موضوع و اقدام پیشنهادی انجام می‌شود و نتیجه در داشبورد دیده می‌شود." decoding="async">
                    </figure>
                </div>
            </div>
            </div>
            <script>
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
            </script>
        </section>

        <div class="insight" data-insight>
        <div class="insight__main">
        <section id="challenges" class="challenges" aria-labelledby="challenges-title">
            <div class="challenges__inner">
                <div class="challenges__top">
                    <div class="challenges__copy">
                        <p class="challenges__kicker">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 3.15 17.35 16.2H2.65L10 3.15Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                <path d="M10 8.15v3.35" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                <circle cx="10" cy="13.85" r="0.7" fill="currentColor" />
                            </svg>
                            چالش‌ها
                        </p>
                        <h2 id="challenges-title" class="challenges__title">چالش‌هایی که آوایار <span>حل می‌کند</span></h2>
                        <p class="challenges__lead">بخش مهمی از واقعیت تماس‌ها بدون تحلیل، پنهان می‌ماند.</p>
                    </div>
                    <figure class="challenges__visual">
                        <img src="{{ asset('images/journey/challenges.webp') }}" width="1024" height="614" alt="تحلیل یک تماس: نشانه خرید شناسایی شد، مقایسه با رقبا مشخص است و واقعیت‌های پنهان مکالمه دیده می‌شوند." loading="lazy" decoding="async">
                    </figure>
                </div>

                <div class="challenges__grid">
                    <article class="challenges__card">
                        <span class="challenges__icon" aria-hidden="true">
                            <svg class="challenges__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <svg class="challenges__x" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
                                <circle class="challenges__mark" cx="8" cy="8" r="6.1" />
                                <path d="M5.7 5.7 10.3 10.3M10.3 5.7 5.7 10.3" />
                            </svg>
                        </span>
                        <h3>تماس‌ها بررسی نمی‌شوند</h3>
                        <p>گوش دادن دستی به همه مکالمات زمان‌بر و غیرممکن است.</p>
                    </article>
                    <article class="challenges__card">
                        <span class="challenges__icon" aria-hidden="true">
                            <svg class="challenges__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                                <path d="M5.5 18.5V12M10.2 18.5V7.5M14.8 18.5v-5" />
                            </svg>
                            <svg class="challenges__x" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
                                <circle class="challenges__mark" cx="8" cy="8" r="6.1" />
                                <path d="M5.7 5.7 10.3 10.3M10.3 5.7 5.7 10.3" />
                            </svg>
                        </span>
                        <h3>فرصت‌های فروش از دست می‌روند</h3>
                        <p>نشانه‌های خرید و سرنخ‌های مهم در تماس‌ها پنهان می‌مانند.</p>
                    </article>
                    <article class="challenges__card">
                        <span class="challenges__icon" aria-hidden="true">
                            <svg class="challenges__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                                <circle cx="11" cy="8" r="3" />
                                <path d="M5.4 18.6c.6-3 2.8-4.6 5.6-4.6s5 1.6 5.6 4.6" />
                            </svg>
                            <svg class="challenges__x" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
                                <circle class="challenges__mark" cx="8" cy="8" r="6.1" />
                                <path d="M5.7 5.7 10.3 10.3M10.3 5.7 5.7 10.3" />
                            </svg>
                        </span>
                        <h3>نقاط ضعف کارشناسان دیده نمی‌شود</h3>
                        <p>بدون تحلیل تماس، بازخورد دقیق و قابل اقدام در دسترس نیست.</p>
                    </article>
                </div>
            </div>
        </section>

            <div class="sample-call__scene" aria-hidden="true">
                <svg class="sample-call__net" data-drift="36" viewBox="0 0 1200 720" preserveAspectRatio="xMidYMid slice">
                    <g fill="none" stroke="#c4b5fd" stroke-width="1.25" stroke-dasharray="2 7" stroke-linecap="round">
                        <path d="M40 120C160 70 240 190 380 130" />
                        <path d="M820 80C960 150 1060 60 1180 140" />
                        <path d="M60 620C180 540 280 680 460 600" />
                    </g>
                    <g fill="#ddd6fe">
                        <circle cx="40" cy="120" r="4" />
                        <circle cx="380" cy="130" r="3" />
                        <circle cx="820" cy="80" r="4" />
                        <circle cx="1180" cy="140" r="3" />
                        <circle cx="60" cy="620" r="3.5" />
                        <circle cx="460" cy="600" r="3" />
                    </g>
                </svg>
                <span class="sample-call__shape sample-call__shape--ring" data-drift="110"></span>
                <span class="sample-call__shape sample-call__shape--orbit" data-drift="-80"></span>
                <span class="sample-call__shape sample-call__shape--soft" data-drift="64"></span>
                <span class="sample-call__shape sample-call__shape--dot sample-call__shape--dot-a" data-drift="48"></span>
                <span class="sample-call__shape sample-call__shape--dot sample-call__shape--dot-b" data-drift="-56"></span>
                <span class="sample-call__shape sample-call__shape--dot sample-call__shape--dot-c" data-drift="28"></span>
            </div>

        <section id="sample-call" class="sample-call" aria-labelledby="sample-call-title">
            <div class="sample-call__inner">
                <div class="sample-call__copy">
                    <p class="sample-call__kicker">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M3.5 14.5V9.2M8 14.5V5.5M12.5 14.5V8M17 14.5V3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        تحلیل هوشمند تماس‌ها
                    </p>
                    <h2 id="sample-call-title" class="sample-call__title">پشت هر <span>مکالمه</span>، اطلاعاتی ارزشمند پنهان است</h2>
                    <p class="sample-call__lead">یک نمونه تحلیل تماس با خلاصه مکالمه، امتیاز کارشناس، احساس مشتری، کیفیت سرنخ و پیشنهاد بهبود نمایش بده</p>
                    <ul class="sample-call__points">
                        <li class="sample-call__point">
                            <div>
                                <h3>خلاصه هوشمند مکالمه</h3>
                                <p>استخراج نکات کلیدی و نتیجه مکالمه در چند ثانیه.</p>
                            </div>
                            <span class="sample-call__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 3.75h7.5L19 8.25V20.25H7A1.5 1.5 0 0 1 5.5 18.75V5.25A1.5 1.5 0 0 1 7 3.75Z" />
                                    <path d="M14.5 3.75V8.25H19M8.5 12.25h7M8.5 15.75h5" />
                                </svg>
                            </span>
                        </li>
                        <li class="sample-call__point">
                            <div>
                                <h3>امتیاز کارشناس</h3>
                                <p>ارزیابی مهارت‌ها بر اساس داده‌های واقعی تماس.</p>
                            </div>
                            <span class="sample-call__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                                    <path d="M4 19V10M9.5 19V5.5M15 19v-6.5M20.5 19V8" />
                                </svg>
                            </span>
                        </li>
                        <li class="sample-call__point">
                            <div>
                                <h3>احساس مشتری و کیفیت سرنخ</h3>
                                <p>تشخیص فرصت فروش، فوریت و سطح ریسک.</p>
                            </div>
                            <span class="sample-call__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3.5c.4 2.2 1.6 3.8 3.4 4.6-1.8.8-3 2.4-3.4 4.6-.4-2.2-1.6-3.8-3.4-4.6 1.8-.8 3-2.4 3.4-4.6Z" />
                                    <path d="M6.2 13.2c.25 1.35 1 2.3 2.1 2.8-1.1.5-1.85 1.45-2.1 2.8-.25-1.35-1-2.3-2.1-2.8 1.1-.5 1.85-1.45 2.1-2.8Z" />
                                </svg>
                            </span>
                        </li>
                        <li class="sample-call__point">
                            <div>
                                <h3>پیشنهاد بهبود</h3>
                                <p>راهنمایی مشخص برای تماس بعدی کارشناس.</p>
                            </div>
                            <span class="sample-call__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4.5 12.5 9 17l10.5-10.5" />
                                </svg>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="sample-call__stage">
                    <figure class="sample-call__preview">
                        <div class="sample-call__board" role="img" aria-label="تحلیل واقعی تماس ۱۳۰۵ برای مشتری نصیری، کارشناس تینا فتحی از پارسین، امتیاز ۸۵ و کیفیت لید ۹۱.">
                            <div class="sample-call__head">
                                <div>
                                    <p class="sample-call__eyebrow">تحلیل مکالمه</p>
                                    <p class="sample-call__name">نصیری</p>
                                    <p class="sample-call__role">کارشناس: تینا فتحی · پارسین</p>
                                    <p class="sample-call__meta">
                                        <span>تاریخ تحلیل: ۱۴۰۵/۰۷/۱۸</span>
                                        <span>منبع: ویپ</span>
                                    </p>
                                    <div class="sample-call__chips">
                                        <span class="sample-call__chip is-good">احساس مشتری: مثبت</span>
                                        <span class="sample-call__chip is-good">سطح لید: بالا</span>
                                    </div>
                                </div>
                                <div class="sample-call__scores">
                                    <div class="sample-call__score">
                                        <span>کیفیت لید</span>
                                        <strong>۹۱</strong>
                                        <span>از ۱۰۰</span>
                                    </div>
                                    <div class="sample-call__score">
                                        <span>امتیاز مکالمه</span>
                                        <strong>۸۵</strong>
                                        <span>از ۱۰۰</span>
                                    </div>
                                </div>
                            </div>

                            <div class="sample-call__wave" aria-hidden="true">
                                <span class="sample-call__play">
                                    <svg viewBox="0 0 12 12" fill="currentColor"><path d="M3.2 1.6v8.8L10.4 6 3.2 1.6Z" /></svg>
                                </span>
                                <svg class="sample-call__bars" viewBox="0 0 280 28" preserveAspectRatio="none" aria-hidden="true">
                                    <g fill="#c4b5fd">
                                        <rect x="0" y="11" width="3" height="6" rx="1.5" /><rect x="7" y="8" width="3" height="12" rx="1.5" /><rect x="14" y="5" width="3" height="18" rx="1.5" /><rect x="21" y="9" width="3" height="10" rx="1.5" /><rect x="28" y="3" width="3" height="22" rx="1.5" /><rect x="35" y="7" width="3" height="14" rx="1.5" /><rect x="42" y="4" width="3" height="20" rx="1.5" /><rect x="49" y="10" width="3" height="8" rx="1.5" /><rect x="56" y="6" width="3" height="16" rx="1.5" /><rect x="63" y="2" width="3" height="24" rx="1.5" /><rect x="70" y="8" width="3" height="12" rx="1.5" /><rect x="77" y="4" width="3" height="20" rx="1.5" /><rect x="84" y="9" width="3" height="10" rx="1.5" /><rect x="91" y="6" width="3" height="16" rx="1.5" /><rect x="98" y="3" width="3" height="22" rx="1.5" /><rect x="105" y="8" width="3" height="12" rx="1.5" /><rect x="112" y="5" width="3" height="18" rx="1.5" /><rect x="119" y="11" width="3" height="6" rx="1.5" /><rect x="126" y="4" width="3" height="20" rx="1.5" /><rect x="133" y="7" width="3" height="14" rx="1.5" /><rect x="140" y="9" width="3" height="10" rx="1.5" /><rect x="147" y="2" width="3" height="24" rx="1.5" /><rect x="154" y="6" width="3" height="16" rx="1.5" /><rect x="161" y="4" width="3" height="20" rx="1.5" /><rect x="168" y="10" width="3" height="8" rx="1.5" /><rect x="175" y="7" width="3" height="14" rx="1.5" /><rect x="182" y="3" width="3" height="22" rx="1.5" /><rect x="189" y="8" width="3" height="12" rx="1.5" /><rect x="196" y="5" width="3" height="18" rx="1.5" /><rect x="203" y="9" width="3" height="10" rx="1.5" /><rect x="210" y="4" width="3" height="20" rx="1.5" /><rect x="217" y="7" width="3" height="14" rx="1.5" /><rect x="224" y="11" width="3" height="6" rx="1.5" /><rect x="231" y="5" width="3" height="18" rx="1.5" /><rect x="238" y="3" width="3" height="22" rx="1.5" /><rect x="245" y="8" width="3" height="12" rx="1.5" /><rect x="252" y="6" width="3" height="16" rx="1.5" /><rect x="259" y="4" width="3" height="20" rx="1.5" /><rect x="266" y="9" width="3" height="10" rx="1.5" /><rect x="273" y="6" width="3" height="16" rx="1.5" />
                                    </g>
                                </svg>
                            </div>

                            <div class="sample-call__split">
                                <article class="sample-call__panel">
                                    <h3>خلاصه مکالمه</h3>
                                    <p>کارشناس فروش برای اطلاع‌رسانی درباره ارسال پیش‌فاکتور با مشتری تماس گرفت. مشتری تأیید کرد که پیش‌فاکتور را دریافت کرده و آن را برای یکی از دوستانش نیز فرستاده تا در صورت نیاز، خرید مشترک و با تعداد بالاتر انجام دهند. همچنین کارشناس اشاره کرد که پیش‌فاکتور را به‌صورت غیررسمی تنظیم کرده است، زیرا مشتری قصد خرید شخصی دارد و مشتری نیز این موضوع را تأیید کرد. مکالمه با لحنی مثبت و توافق طرفین به پایان رسید.</p>
                                </article>
                                <article class="sample-call__panel">
                                    <h3>ابعاد عملکرد</h3>
                                    <div class="sample-call__dims">
                                        <div>
                                            <div class="sample-call__dim-head"><span>مهارت ارتباطی</span><strong>۸۸</strong></div>
                                            <div class="sample-call__track"><i style="width:88%"></i></div>
                                        </div>
                                        <div>
                                            <div class="sample-call__dim-head"><span>دانش محصول</span><strong>۸۲</strong></div>
                                            <div class="sample-call__track"><i style="width:82%"></i></div>
                                        </div>
                                        <div>
                                            <div class="sample-call__dim-head"><span>مدیریت اعتراض</span><strong>۸۰</strong></div>
                                            <div class="sample-call__track"><i style="width:80%"></i></div>
                                        </div>
                                        <div>
                                            <div class="sample-call__dim-head"><span>توان بستن معامله</span><strong>۸۶</strong></div>
                                            <div class="sample-call__track"><i style="width:86%"></i></div>
                                        </div>
                                        <div>
                                            <div class="sample-call__dim-head"><span>حرفه‌ای‌گری</span><strong>۸۹</strong></div>
                                            <div class="sample-call__track"><i style="width:89%"></i></div>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div class="sample-call__mid">
                                <article class="sample-call__panel is-good">
                                    <h3>نقاط قوت</h3>
                                    <ul class="sample-call__strengths">
                                        <li>پیگیری سریع وضعیت پیش‌فاکتور</li>
                                        <li>لحن محترمانه در تمام مکالمه</li>
                                        <li>جمع‌بندی واضح در پایان تماس</li>
                                    </ul>
                                </article>
                                <article class="sample-call__panel is-warn">
                                    <h3>نقاط قابل بهبود</h3>
                                    <ul class="sample-call__strengths">
                                        <li>گزینه‌های پرداخت توضیح داده نشد</li>
                                        <li>زمان دقیق تحویل اعلام نشد</li>
                                        <li>مزیت خرید تعدادی پررنگ نشد</li>
                                    </ul>
                                </article>
                            </div>

                            <div class="sample-call__banners">
                                <article class="sample-call__banner is-buy">
                                    <h3>اقدامات پیشنهادی</h3>
                                    <ul>
                                        <li>ارسال نسخه رسمی پیش‌فاکتور</li>
                                        <li>پیگیری تصمیم مشتری تا فردا</li>
                                        <li>پیشنهاد شرایط خرید تعدادی</li>
                                    </ul>
                                </article>
                                <article class="sample-call__banner is-lead">
                                    <h3>سیگنال‌های لید</h3>
                                    <ul>
                                        <li>ارسال پیش‌فاکتور برای دوست جهت افزایش تعداد سفارش</li>
                                        <li>تأیید صدور پیش‌فاکتور شخصی</li>
                                    </ul>
                                </article>
                            </div>
                        </div>
                    </figure>
                </div>
            </div>
            <script>
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
            </script>
        </section>
        </div>

        <section id="coaching" class="coaching" aria-labelledby="coaching-title">
            <div class="coaching__inner">
                <figure class="coaching__visual">
                    <img src="{{ asset('images/journey/coaching.webp') }}" width="1024" height="682" alt="نمونه پروفایل کوچینگ تماس: امتیاز کوچینگ، مهارت‌های قابل بهبود، شاهد مکالمه، پیشنهاد بهبود و نتیجه کوچینگ. عددها نمایشی هستند." loading="lazy" decoding="async">
                </figure>
                <div class="coaching__copy">
                    <p class="coaching__kicker">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 2.2c.35 2.2 1.45 3.8 3.35 4.55-1.9.75-3 2.35-3.35 4.55-.35-2.2-1.45-3.8-3.35-4.55C8.55 6 9.65 4.4 10 2.2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                            <path d="M15.4 12.1c.2 1.15.8 2 1.85 2.4-1.05.4-1.65 1.25-1.85 2.4-.2-1.15-.8-2-1.85-2.4 1.05-.4 1.65-1.25 1.85-2.4Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" />
                        </svg>
                        کوچینگ هوشمند
                    </p>
                    <h2 id="coaching-title" class="coaching__title">مسیر رشد<span>برای پیشرفت کارشناس</span></h2>
                    <p class="coaching__lead">آوایار از هر تماس، نقاط قوت و ضعف کارشناسان فروش را شناسایی می‌کند، شواهد واقعی از مکالمه را نشان می‌دهد و پیشنهادهای عملی برای گام بعدی ارائه می‌کند تا تیم فروش شما به‌صورت مداوم بهتر شود.</p>
                    <ul class="coaching__points">
                        <li class="coaching__point">
                            <span class="coaching__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="7.25" />
                                    <circle cx="12" cy="12" r="3.25" />
                                    <path d="M12 2.75v2.1M12 19.15v2.1M2.75 12h2.1M19.15 12h2.1" />
                                </svg>
                            </span>
                            <div>
                                <h3>شناسایی نقاط قوت و ضعف</h3>
                                <p>بر اساس تماس‌های واقعی، نه حدس و گمان</p>
                            </div>
                        </li>
                        <li class="coaching__point">
                            <span class="coaching__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7.5 7.2h7.2c1.7 0 2.8 1.1 2.8 2.7v4.2c0 1.6-1.1 2.7-2.8 2.7H11l-3.2 2.3v-2.3H7.5c-1.7 0-2.8-1.1-2.8-2.7V9.9c0-1.6 1.1-2.7 2.8-2.7Z" />
                                </svg>
                            </span>
                            <div>
                                <h3>شاهد واقعی از مکالمه</h3>
                                <p>بازخورد همراه با نمونه و توضیح روشن</p>
                            </div>
                        </li>
                        <li class="coaching__point">
                            <span class="coaching__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 18.25h6" />
                                    <path d="M10 21h4" />
                                    <path d="M12 3.75a5.25 5.25 0 0 0-2.15 10.05c.45.22.75.66.75 1.15v.3h2.8v-.3c0-.49.3-.93.75-1.15A5.25 5.25 0 0 0 12 3.75Z" />
                                </svg>
                            </span>
                            <div>
                                <h3>اقدام بعدی مشخص</h3>
                                <p>پیشنهاد کاربردی برای تماس بعدی</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="faq" class="faq" aria-labelledby="faq-title">
            <div class="faq__inner">
                <header class="faq__head">
                    <p class="faq__kicker">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M4.2 5.4c0-1.3 1.1-2.3 2.5-2.3h6.6c1.4 0 2.5 1 2.5 2.3v4.1c0 1.3-1.1 2.3-2.5 2.3H9.1L6.2 14.2v-2.4H6.7c-1.4 0-2.5-1-2.5-2.3V5.4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            <path d="M8 7.4h.01M10 7.4h.01M12 7.4h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        سوالات متداول
                    </p>
                    <h2 id="faq-title" class="faq__title">
                        پاسخ سوالات رایج درباره آوایار
                        <svg class="faq__spark" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                            <path d="M14.2 3.2 15.1 6l2.8.9-2.8.9-.9 2.8-.9-2.8-2.8-.9 2.8-.9.9-2.8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                            <path d="M6.4 11.2 7 13.1l1.9.6-1.9.6-.6 1.9-.6-1.9-1.9-.6 1.9-.6.6-1.9Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" />
                        </svg>
                    </h2>
                    <svg class="faq__curve" viewBox="0 0 160 40" fill="none" aria-hidden="true">
                        <path d="M4 28c28-22 58-22 86-6 18 10 36 12 66 2" stroke="#d4d4d8" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <p class="faq__lead">هرآنچه برای شروع کار با آوایار نیاز دارید، به‌صورت شفاف و کوتاه پاسخ شده است.</p>
                </header>

                <div class="faq__layout">
                    <div class="faq__list">
                        <details class="faq__item">
                            <summary>
                                <span class="faq__qwrap">
                                    <span class="faq__q">آوایار به چه مرکز تلفن‌هایی متصل می‌شود؟</span>
                                    <span class="faq__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </span>
                                <span class="faq__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 13.5a4.2 4.2 0 0 0 5.9.2l2.2-2.2a4.2 4.2 0 0 0-5.9-5.9l-1.2 1.2" />
                                        <path d="M14 10.5a4.2 4.2 0 0 0-5.9-.2l-2.2 2.2a4.2 4.2 0 0 0 5.9 5.9l1.2-1.2" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="faq__answer">آوایار به Navatel، Simotel و وب‌هوک سفارشی (از جمله Asterisk) وصل می‌شود و رویداد تماس را برای تحلیل دریافت می‌کند.</p>
                        </details>
                        <details class="faq__item">
                            <summary>
                                <span class="faq__qwrap">
                                    <span class="faq__q">تحلیل تماس‌ها چه زمانی آماده می‌شود؟</span>
                                    <span class="faq__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </span>
                                <span class="faq__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 19.5V13" />
                                        <path d="M10 19.5V8.5" />
                                        <path d="M15.5 19.5v-6" />
                                        <path d="M21 19.5V5.5" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="faq__answer">پس از پایان تماس، تحلیل به‌صورت خودکار آغاز می‌شود و نتیجه در داشبورد مدیر و فضای کار کارشناس نمایش داده می‌شود.</p>
                        </details>
                        <details class="faq__item">
                            <summary>
                                <span class="faq__qwrap">
                                    <span class="faq__q">آیا آوایار حین مکالمه هم پیشنهاد می‌دهد؟</span>
                                    <span class="faq__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </span>
                                <span class="faq__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 18.25h6" />
                                        <path d="M10 21h4" />
                                        <path d="M12 3.5a5.25 5.25 0 0 0-2.15 10.05c.45.22.75.66.75 1.15v.3h2.8v-.3c0-.49.3-.93.75-1.15A5.25 5.25 0 0 0 12 3.5Z" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="faq__answer">تحلیل روی تماس‌های پایان‌یافته انجام می‌شود. خلاصه، نکات مکالمه و پیشنهاد کوچینگ بعد از اتمام تماس آماده می‌شود، نه در حین گفتگو.</p>
                        </details>
                        <details class="faq__item">
                            <summary>
                                <span class="faq__qwrap">
                                    <span class="faq__q">خروجی تحلیل شامل چه مواردی است؟</span>
                                    <span class="faq__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </span>
                                <span class="faq__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 4.5h6.2L18.5 8.8V19a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 19V6A1.5 1.5 0 0 1 8 4.5Z" />
                                        <path d="M14 4.7V8.6h3.8" />
                                        <path d="M9.2 12.2h5.6M9.2 15.4h4.2" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="faq__answer">خلاصه مکالمه، احساس مشتری، موضوعات، کیفیت لید، نقاط قوت و ضعف کارشناس و پیشنهاد کوچینگ برای تماس بعدی.</p>
                        </details>
                        <details class="faq__item">
                            <summary>
                                <span class="faq__qwrap">
                                    <span class="faq__q">آیا امکان اتصال به CRM وجود دارد؟</span>
                                    <span class="faq__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </span>
                                <span class="faq__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="6.5" cy="12" r="2.2" />
                                        <circle cx="17.5" cy="6.5" r="2.2" />
                                        <circle cx="17.5" cy="17.5" r="2.2" />
                                        <path d="M8.6 11.1 15.4 7.6M8.6 12.9l6.8 3.5" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="faq__answer">بله. آوایار به دیدار و مایکروسافت داینامیکس ۳۶۵ وصل می‌شود تا سابقه مشتری کنار مکالمه بماند.</p>
                        </details>
                    </div>

                    <aside class="faq__card">
                        <img class="faq__art" src="{{ asset('images/journey/faq-bubbles.webp') }}" width="640" height="320" alt="" loading="lazy" decoding="async">
                        <h3>پاسخ سوال خود را پیدا نکردید؟</h3>
                        <p>برای دریافت اطلاعات بیشتر، درخواست مشاوره ثبت کنید.</p>
                        <button type="button" class="faq__cta" data-consult-open>
                            <span>درخواست مشاوره</span>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M12.5 5 7.5 10l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="faq__perks">
                            <li>
                                <span class="faq__perk-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3.6 19 6.4v5.3c0 4.2-2.8 7.2-7 8.7-4.2-1.5-7-4.5-7-8.7V6.4L12 3.6Z" />
                                        <path d="m8.8 12.1 2.1 2.1 4.3-4.4" />
                                    </svg>
                                </span>
                                همراهی تا اجرا
                            </li>
                            <li>
                                <span class="faq__perk-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="9" r="2.4" />
                                        <circle cx="16.2" cy="10.2" r="1.9" />
                                        <path d="M4.8 17.6c.6-2.2 2.3-3.4 4.2-3.4s3.6 1.2 4.2 3.4" />
                                        <path d="M13.4 14.6c.7-.5 1.6-.8 2.6-.8 1.5 0 2.8.8 3.4 2.4" />
                                    </svg>
                                </span>
                                مشاوره تخصصی
                            </li>
                            <li>
                                <span class="faq__perk-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M13 3.5 6.2 13.2h5.1L10.6 20.5 17.8 10.4h-5.2L13 3.5Z" />
                                    </svg>
                                </span>
                                پاسخ سریع
                            </li>
                        </ul>
                    </aside>
                </div>
            </div>
        </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a href="{{ route('home') }}" class="site-footer__logo">
                        <img src="{{ asset('images/journey/avayar-logo.webp') }}" width="480" height="151" alt="آوایار" decoding="async">
                        <svg viewBox="0 0 28 24" aria-hidden="true">
                            <rect x="1" y="10" width="3.2" height="4" rx="1.6" fill="var(--color-ai)" opacity="0.55" />
                            <rect x="6.4" y="6" width="3.2" height="12" rx="1.6" fill="var(--color-brand)" />
                            <rect x="11.8" y="8" width="3.2" height="8" rx="1.6" fill="var(--color-ai)" />
                            <rect x="17.2" y="3" width="3.2" height="18" rx="1.6" fill="var(--color-brand)" />
                            <rect x="22.6" y="7" width="3.2" height="10" rx="1.6" fill="var(--color-ai)" opacity="0.8" />
                        </svg>
                    </a>
                    <p class="site-footer__lead">تحلیل تماس‌های پایان‌یافته برای رشد تیم فروش، پشتیبانی و کیفیت. خلاصه، احساس مشتری، کیفیت لید و کوچینگ در یک داشبورد.</p>
                    <ul class="site-footer__pills">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="5" width="16" height="12" rx="2" /><path d="M8 19h8" /></svg>
                            رابط فارسی
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3.5V7M16 3.5V7M4 10h16" /></svg>
                            تاریخ شمسی
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3.6 19 6.4v5.3c0 4.2-2.8 7.2-7 8.7-4.2-1.5-7-4.5-7-8.7V6.4L12 3.6Z" /></svg>
                            نصب داخلی
                        </li>
                    </ul>
                </div>

                <nav class="site-footer__col" aria-label="دسترسی سریع">
                    <h2>دسترسی سریع</h2>
                    <ul class="site-footer__links">
                        <li><a href="#sample-call">ویژگی‌ها <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                        <li><a href="#journey">مسیر تماس <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                        <li><a href="#challenges">چالش‌ها <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                        <li><a href="#sample-call">نمونه تحلیل <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                        <li><a href="#coaching">کوچینگ <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                        <li><a href="#faq">سوالات متداول <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></a></li>
                    </ul>
                </nav>

                <div class="site-footer__col">
                    <h2>راه‌های ارتباطی</h2>
                    <ul class="site-footer__contacts">
                        <li>
                            <button type="button" data-consult-open>
                                <span class="site-footer__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 10.5h8M8 14h5" /><path d="M7 6.5h10a2 2 0 0 1 2 2v7.2a2 2 0 0 1-2 2H10l-3.2 2.2v-2.2H7a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Z" /></svg>
                                </span>
                                <span>درخواست مشاوره<small>گفتگو درباره نیاز تیم فروش، پشتیبانی یا کیفیت</small></span>
                            </button>
                        </li>
                        <li>
                            <a href="{{ route('login') }}">
                                <span class="site-footer__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8.5" r="2.6" /><path d="M7.2 17.4c.7-2.2 2.5-3.3 4.8-3.3s4.1 1.1 4.8 3.3" /></svg>
                                </span>
                                <span>ورود به پنل<small>فضای مدیر و کارشناس</small></span>
                            </a>
                        </li>
                        <li>
                            <a href="#faq">
                                <span class="site-footer__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8" /><path d="M9.6 9.4a2.4 2.4 0 1 1 3.3 2.2c-.6.3-.9.8-.9 1.4V13.4" /><path d="M12 16.6h.01" /></svg>
                                </span>
                                <span>پرسش‌های اتصال<small>زمان تحلیل، خروجی‌ها و CRM</small></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="site-footer__col">
                    <h2>یکپارچگی‌ها</h2>
                    <ul class="site-footer__integrations">
                        <li><a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 13.5a4 4 0 0 0 5.6.2l1.8-1.8a4 4 0 0 0-5.6-5.6L10.6 7.5" /><path d="M14 10.5a4 4 0 0 0-5.6-.2l-1.8 1.8a4 4 0 0 0 5.6 5.6l1.2-1.2" /></svg>Navatel</a></li>
                        <li><a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 13.5a4 4 0 0 0 5.6.2l1.8-1.8a4 4 0 0 0-5.6-5.6L10.6 7.5" /><path d="M14 10.5a4 4 0 0 0-5.6-.2l-1.8 1.8a4 4 0 0 0 5.6 5.6l1.2-1.2" /></svg>Simotel</a></li>
                        <li><a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 13.5a4 4 0 0 0 5.6.2l1.8-1.8a4 4 0 0 0-5.6-5.6L10.6 7.5" /><path d="M14 10.5a4 4 0 0 0-5.6-.2l-1.8 1.8a4 4 0 0 0 5.6 5.6l1.2-1.2" /></svg>وب‌هوک و Asterisk</a></li>
                        <li><a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 13.5a4 4 0 0 0 5.6.2l1.8-1.8a4 4 0 0 0-5.6-5.6L10.6 7.5" /><path d="M14 10.5a4 4 0 0 0-5.6-.2l-1.8 1.8a4 4 0 0 0 5.6 5.6l1.2-1.2" /></svg>دیدار</a></li>
                        <li><a href="#faq"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 13.5a4 4 0 0 0 5.6.2l1.8-1.8a4 4 0 0 0-5.6-5.6L10.6 7.5" /><path d="M14 10.5a4 4 0 0 0-5.6-.2l-1.8 1.8a4 4 0 0 0 5.6 5.6l1.2-1.2" /></svg>Dynamics 365</a></li>
                    </ul>
                </div>
            </div>

            <div class="site-footer__bar">
                <p class="site-footer__copy">© تمامی حقوق برای آوایار محفوظ است.</p>
                <svg class="site-footer__wave" viewBox="0 0 260 36" fill="none" aria-hidden="true">
                    <path d="M4 24c28-16 48-16 76-4 22 10 40 12 68 2 24-8 48-8 76 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    <circle cx="18" cy="22" r="1.4" fill="currentColor" />
                    <circle cx="48" cy="16" r="1.4" fill="currentColor" />
                    <circle cx="86" cy="18" r="1.4" fill="currentColor" />
                    <circle cx="132" cy="20" r="1.4" fill="currentColor" />
                    <circle cx="178" cy="16" r="1.4" fill="currentColor" />
                    <circle cx="226" cy="22" r="1.4" fill="currentColor" />
                </svg>
                <ul class="site-footer__notes">
                    <li><a href="#journey">تحلیل پس از پایان تماس</a></li>
                    <li><a href="#sample-call">داشبورد مدیر</a></li>
                    <li><a href="#coaching">فضای کارشناس</a></li>
                </ul>
            </div>
        </div>
    </footer>
    </div>

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

            <form id="consult-form" class="consult-form" action="{{ route('consultation-requests.store') }}" method="post" novalidate>
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
            </form>

            <div id="consult-success" class="consult-success" hidden>
                <h3>درخواست شما ثبت شد</h3>
                <p>به‌زودی با شما تماس می‌گیریم.</p>
                <button type="button" data-consult-close>بستن</button>
            </div>
        </div>
    </dialog>

    <script>
        (function () {
            const dialog = document.getElementById('consult-dialog');
            const form = document.getElementById('consult-form');
            const success = document.getElementById('consult-success');
            const submit = document.getElementById('consult-submit');
            const formError = dialog.querySelector('[data-form-error]');

            if (!dialog || !form || !success || !submit) {
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

            document.querySelectorAll('[data-consult-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    const menu = button.closest('details');
                    if (menu) {
                        menu.open = false;
                    }
                    openDialog();
                });
            });

            dialog.querySelectorAll('[data-consult-close]').forEach((button) => {
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

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearErrors();
                submit.disabled = true;
                submit.textContent = 'در حال ثبت...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: new FormData(form),
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
        })();
    </script>
</body>
</html>
