<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <style>
        /* ! tailwindcss v3.4.1 | MIT License | https://tailwindcss.com */
        *,
        ::after,
        ::before {
            box-sizing: border-box;
            border-width: 0;
            border-style: solid;
            border-color: #e5e7eb
        }

        ::after,
        ::before {
            --tw-content: ''
        }

        :host,
        html {
            line-height: 1.5;
            -webkit-text-size-adjust: 100%;
            -moz-tab-size: 4;
            tab-size: 4;
            font-family: Figtree, ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
            font-feature-settings: normal;
            font-variation-settings: normal;
            -webkit-tap-highlight-color: transparent
        }

        body {
            margin: 0;
            line-height: inherit
        }

        hr {
            height: 0;
            color: inherit;
            border-top-width: 1px
        }

        abbr:where([title]) {
            -webkit-text-decoration: underline dotted;
            text-decoration: underline dotted
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-size: inherit;
            font-weight: inherit
        }

        a {
            color: inherit;
            text-decoration: inherit
        }

        b,
        strong {
            font-weight: bolder
        }

        code,
        kbd,
        pre,
        samp {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-feature-settings: normal;
            font-variation-settings: normal;
            font-size: 1em
        }

        small {
            font-size: 80%
        }

        sub,
        sup {
            font-size: 75%;
            line-height: 0;
            position: relative;
            vertical-align: baseline
        }

        sub {
            bottom: -.25em
        }

        sup {
            top: -.5em
        }

        table {
            text-indent: 0;
            border-color: inherit;
            border-collapse: collapse
        }

        button,
        input,
        optgroup,
        select,
        textarea {
            font-family: inherit;
            font-feature-settings: inherit;
            font-variation-settings: inherit;
            font-size: 100%;
            font-weight: inherit;
            line-height: inherit;
            color: inherit;
            margin: 0;
            padding: 0
        }

        button,
        select {
            text-transform: none
        }

        [type=button],
        [type=reset],
        [type=submit],
        button {
            appearance: button;
            -webkit-appearance: button;
            background-color: transparent;
            background-image: none
        }

        :-moz-focusring {
            outline: auto
        }

        :-moz-ui-invalid {
            box-shadow: none
        }

        progress {
            vertical-align: baseline
        }

        ::-webkit-inner-spin-button,
        ::-webkit-outer-spin-button {
            height: auto
        }

        [type=search] {
            appearance: textfield;
            -webkit-appearance: textfield;
            outline-offset: -2px
        }

        ::-webkit-search-decoration {
            -webkit-appearance: none
        }

        ::-webkit-file-upload-button {
            -webkit-appearance: button;
            font: inherit
        }

        summary {
            display: list-item
        }

        blockquote,
        dd,
        dl,
        figure,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        hr,
        p,
        pre {
            margin: 0
        }

        fieldset {
            margin: 0;
            padding: 0
        }

        legend {
            padding: 0
        }

        menu,
        ol,
        ul {
            list-style: none;
            margin: 0;
            padding: 0
        }

        dialog {
            padding: 0
        }
        
        textarea {
            resize: vertical
        }

        input::placeholder,
        textarea::placeholder {
            opacity: 1;
            color: #9ca3af
        }

        [role=button],
        button {
            cursor: pointer
        }

        :disabled {
            cursor: default
        }

        audio,
        canvas,
        embed,
        iframe,
        img,
        object,
        svg,
        video {
            display: block;
            vertical-align: middle
        }

        img,
        video {
            max-width: 100%;
            height: auto
        }

        [hidden] {
            display: none
        }

        *,
        ::before,
        ::after {
            --tw-border-spacing-x: 0;
            --tw-border-spacing-y: 0;
            --tw-translate-x: 0;
            --tw-translate-y: 0;
            --tw-rotate: 0;
            --tw-skew-x: 0;
            --tw-skew-y: 0;
            --tw-scale-x: 1;
            --tw-scale-y: 1;
            --tw-pan-x: ;
            --tw-pan-y: ;
            --tw-pinch-zoom: ;
            --tw-scroll-snap-strictness: proximity;
            --tw-gradient-from-position: ;
            --tw-gradient-via-position: ;
            --tw-gradient-to-position: ;
            --tw-ordinal: ;
            --tw-slashed-zero: ;
            --tw-numeric-figure: ;
            --tw-numeric-spacing: ;
            --tw-numeric-fraction: ;
            --tw-ring-inset: ;
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: rgb(59 130 246 / 0.5);
            --tw-ring-offset-shadow: 0 0 #0000;
            --tw-ring-shadow: 0 0 #0000;
            --tw-shadow: 0 0 #0000;
            --tw-shadow-colored: 0 0 #0000;
            --tw-blur: ;
            --tw-brightness: ;
            --tw-contrast: ;
            --tw-grayscale: ;
            --tw-hue-rotate: ;
            --tw-invert: ;
            --tw-saturate: ;
            --tw-sepia: ;
            --tw-drop-shadow: ;
            --tw-backdrop-blur: ;
            --tw-backdrop-brightness: ;
            --tw-backdrop-contrast: ;
            --tw-backdrop-grayscale: ;
            --tw-backdrop-hue-rotate: ;
            --tw-backdrop-invert: ;
            --tw-backdrop-opacity: ;
            --tw-backdrop-saturate: ;
            --tw-backdrop-sepia:
        }

        ::backdrop {
            --tw-border-spacing-x: 0;
            --tw-border-spacing-y: 0;
            --tw-translate-x: 0;
            --tw-translate-y: 0;
            --tw-rotate: 0;
            --tw-skew-x: 0;
            --tw-skew-y: 0;
            --tw-scale-x: 1;
            --tw-scale-y: 1;
            --tw-pan-x: ;
            --tw-pan-y: ;
            --tw-pinch-zoom: ;
            --tw-scroll-snap-strictness: proximity;
            --tw-gradient-from-position: ;
            --tw-gradient-via-position: ;
            --tw-gradient-to-position: ;
            --tw-ordinal: ;
            --tw-slashed-zero: ;
            --tw-numeric-figure: ;
            --tw-numeric-spacing: ;
            --tw-numeric-fraction: ;
            --tw-ring-inset: ;
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: rgb(59 130 246 / 0.5);
            --tw-ring-offset-shadow: 0 0 #0000;
            --tw-ring-shadow: 0 0 #0000;
            --tw-shadow: 0 0 #0000;
            --tw-shadow-colored: 0 0 #0000;
            --tw-blur: ;
            --tw-brightness: ;
            --tw-contrast: ;
            --tw-grayscale: ;
            --tw-hue-rotate: ;
            --tw-invert: ;
            --tw-saturate: ;
            --tw-sepia: ;
            --tw-drop-shadow: ;
            --tw-backdrop-blur: ;
            --tw-backdrop-brightness: ;
            --tw-backdrop-contrast: ;
            --tw-backdrop-grayscale: ;
            --tw-backdrop-hue-rotate: ;
            --tw-backdrop-invert: ;
            --tw-backdrop-opacity: ;
            --tw-backdrop-saturate: ;
            --tw-backdrop-sepia:
        }

        .absolute {
            position: absolute
        }

        .relative {
            position: relative
        }

        .-left-20 {
            left: -5rem
        }

        .top-0 {
            top: 0px
        }

        .-bottom-16 {
            bottom: -4rem
        }

        .-left-16 {
            left: -4rem
        }

        .-mx-3 {
            margin-left: -0.75rem;
            margin-right: -0.75rem
        }

        .mt-4 {
            margin-top: 1rem
        }

        .mt-6 {
            margin-top: 1.5rem
        }

        .flex {
            display: flex
        }

        .grow {
            flex-grow: 1;
        }

        .grid {
            display: grid
        }

        .hidden {
            display: none
        }

        .aspect-video {
            aspect-ratio: 16 / 9
        }

        .size-12 {
            width: 3rem;
            height: 3rem
        }

        .size-5 {
            width: 1.25rem;
            height: 1.25rem
        }

        .size-6 {
            width: 1.5rem;
            height: 1.5rem
        }

        .h-12 {
            height: 3rem
        }

        .h-40 {
            height: 10rem
        }

        .h-full {
            height: 100%
        }

        .min-h-screen {
            min-height: 100vh
        }

        .w-1\/2 {
            width: 50%
        }

        .w-full {
            width: 100%
        }

        .w-\[calc\(100\%\+8rem\)\] {
            width: calc(100% + 8rem)
        }

        .w-auto {
            width: auto
        }

        .max-w-\[877px\] {
            max-width: 877px
        }

        .max-w-2xl {
            max-width: 42rem
        }

        .flex-1 {
            flex: 1 1 0%
        }

        .shrink-0 {
            flex-shrink: 0
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .flex-col {
            flex-direction: column
        }

        .items-start {
            align-items: flex-start
        }

        .items-center {
            align-items: center
        }

        .items-stretch {
            align-items: stretch
        }

        .justify-end {
            justify-content: flex-end
        }

        .justify-center {
            justify-content: center
        }

        .gap-2 {
            gap: 0.5rem
        }

        .gap-4 {
            gap: 1rem
        }

        .gap-6 {
            gap: 1.5rem
        }

        .self-center {
            align-self: center
        }

        .overflow-hidden {
            overflow: hidden
        }

        .rounded-\[10px\] {
            border-radius: 10px
        }

        .rounded-full {
            border-radius: 9999px
        }

        .rounded-lg {
            border-radius: 0.5rem
        }

        .rounded-md {
            border-radius: 0.375rem
        }

        .rounded-sm {
            border-radius: 0.125rem
        }

        .bg-\[\#a5ac56\]\/10 {
            background-color: rgb(165 172 86 / 0.1)
        }

        .text-\[\#a5ac56\] {
            color: rgb(165 172 86)
        }

        .fill-\[\#a5ac56\] {
            fill: rgb(165 172 86)
        }

        .text-warning {
            color: rgb(247 170 68)
        }

        .bg-white {
            --tw-bg-opacity: 1;
            background-color: rgb(255 255 255 / var(--tw-bg-opacity))
        }

        .bg-gradient-to-b {
            background-image: linear-gradient(to bottom, var(--tw-gradient-stops))
        }

        .from-transparent {
            --tw-gradient-from: transparent var(--tw-gradient-from-position);
            --tw-gradient-to: rgb(0 0 0 / 0) var(--tw-gradient-to-position);
            --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to)
        }

        .via-white {
            --tw-gradient-to: rgb(255 255 255 / 0) var(--tw-gradient-to-position);
            --tw-gradient-stops: var(--tw-gradient-from), #fff var(--tw-gradient-via-position), var(--tw-gradient-to)
        }

        .to-white {
            --tw-gradient-to: #fff var(--tw-gradient-to-position)
        }

        .stroke-\[\#a5ac56\] {
            stroke: #a5ac56
        }

        .object-cover {
            object-fit: cover
        }

        .object-top {
            object-position: top
        }

        .p-6 {
            padding: 1.5rem
        }

        .px-6 {
            padding-left: 1.5rem;
            padding-right: 1.5rem
        }

        .py-10 {
            padding-top: 2.5rem;
            padding-bottom: 2.5rem
        }

        .px-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem
        }

        .py-16 {
            padding-top: 4rem;
            padding-bottom: 4rem
        }

        .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem
        }

        .pt-3 {
            padding-top: 0.75rem
        }

        .text-center {
            text-align: center
        }

        .font-sans {
            font-family: Figtree, ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji
        }

        .text-sm {
            font-size: 0.875rem;
            line-height: 1.25rem
        }

        .text-sm\/relaxed {
            font-size: 0.875rem;
            line-height: 1.625
        }

        .text-xl {
            font-size: 1.25rem;
            line-height: 1.75rem
        }

        .font-semibold {
            font-weight: 600
        }

        .text-black {
            --tw-text-opacity: 1;
            color: rgb(0 0 0 / var(--tw-text-opacity))
        }

        .text-white {
            --tw-text-opacity: 1;
            color: rgb(255 255 255 / var(--tw-text-opacity))
        }

        .text-gray-600	{
            color: rgb(75 85 99);
        }

        .underline {
            -webkit-text-decoration-line: underline;
            text-decoration-line: underline
        }

        .antialiased {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale
        }

        .shadow-\[0px_14px_34px_0px_rgba\(0\2c 0\2c 0\2c 0\.08\)\] {
            --tw-shadow: 0px 14px 34px 0px rgba(0, 0, 0, 0.08);
            --tw-shadow-colored: 0px 14px 34px 0px var(--tw-shadow-color);
            box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow)
        }

        .ring-1 {
            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000)
        }

        .ring-transparent {
            --tw-ring-color: transparent
        }

        .ring-white\/\[0\.05\] {
            --tw-ring-color: rgb(255 255 255 / 0.05)
        }

        .drop-shadow-\[0px_4px_34px_rgba\(0\2c 0\2c 0\2c 0\.06\)\] {
            --tw-drop-shadow: drop-shadow(0px 4px 34px rgba(0, 0, 0, 0.06));
            filter: var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow)
        }

        .drop-shadow-\[0px_4px_34px_rgba\(0\2c 0\2c 0\2c 0\.25\)\] {
            --tw-drop-shadow: drop-shadow(0px 4px 34px rgba(0, 0, 0, 0.25));
            filter: var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow)
        }

        .transition {
            transition-property: color, background-color, border-color, fill, stroke, opacity, box-shadow, transform, filter, -webkit-text-decoration-color, -webkit-backdrop-filter;
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter, -webkit-text-decoration-color, -webkit-backdrop-filter;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms
        }

        .duration-300 {
            transition-duration: 300ms
        }

        .selection\:bg-\[\#a5ac56\] *::selection {
            --tw-bg-opacity: 1;
            background-color: rgb(165 172 86 / var(--tw-bg-opacity))
        }

        .selection\:text-white *::selection {
            --tw-text-opacity: 1;
            color: rgb(255 255 255 / var(--tw-text-opacity))
        }

        .selection\:bg-\[\#a5ac56\]::selection {
            --tw-bg-opacity: 1;
            background-color: rgb(165 172 86 / var(--tw-bg-opacity))
        }

        .selection\:text-white::selection {
            --tw-text-opacity: 1;
            color: rgb(255 255 255 / var(--tw-text-opacity))
        }

        .hover\:text-black:hover {
            --tw-text-opacity: 1;
            color: rgb(0 0 0 / var(--tw-text-opacity))
        }

        .hover\:text-black\/70:hover {
            color: rgb(0 0 0 / 0.7)
        }

        .hover\:ring-black\/20:hover {
            --tw-ring-color: rgb(0 0 0 / 0.2)
        }

        .focus\:outline-none:focus {
            outline: 2px solid transparent;
            outline-offset: 2px
        }

        .focus-visible\:ring-1:focus-visible {
            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000)
        }

        .focus-visible\:ring-\[\#a5ac56\]:focus-visible {
            --tw-ring-opacity: 1;
            --tw-ring-color: rgb(165 172 86 / var(--tw-ring-opacity))
        }

        @media (min-width: 640px) {
            .sm\:size-16 {
                width: 4rem;
                height: 4rem
            }

            .sm\:size-6 {
                width: 1.5rem;
                height: 1.5rem
            }

            .sm\:pt-5 {
                padding-top: 1.25rem
            }
        }

        @media (min-width: 768px) {
            .md\:row-span-1 {
                grid-row: span 1 / span 1
            }

            .md\:row-span-2 {
                grid-row: span 2 / span 2
            }

            .md\:row-span-3 {
                grid-row: span 3 / span 3
            }

            .md\:row-span-4 {
                grid-row: span 4 / span 4
            }

            .md\:row-span-5 {
                grid-row: span 5 / span 5
            }

            .md\:row-span-6 {
                grid-row: span 6 / span 6
            }
        }

        @media (min-width: 1024px) {
            .lg\:col-start-2 {
                grid-column-start: 2
            }

            .lg\:h-16 {
                height: 4rem
            }

            .lg\:max-w-7xl {
                max-width: 80rem
            }

            .lg\:grid-cols-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }

            .lg\:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .lg\:flex-col {
                flex-direction: column
            }

            .lg\:items-end {
                align-items: flex-end
            }

            .lg\:justify-center {
                justify-content: center
            }

            .lg\:gap-8 {
                gap: 2rem
            }

            .lg\:p-10 {
                padding: 2.5rem
            }

            .lg\:pb-10 {
                padding-bottom: 2.5rem
            }

            .lg\:pt-0 {
                padding-top: 0px
            }

            .lg\:text-\[\#a5ac56\] {
                --tw-text-opacity: 1;
                color: rgb(165 172 86 / var(--tw-text-opacity))
            }
        }

        @media (prefers-color-scheme: dark) {
            .dark\:block {
                display: block
            }

            .dark\:hidden {
                display: none
            }

            .dark\:bg-black {
                --tw-bg-opacity: 1;
                background-color: rgb(0 0 0 / var(--tw-bg-opacity))
            }

            .dark\:bg-zinc-900 {
                --tw-bg-opacity: 1;
                background-color: rgb(24 24 27 / var(--tw-bg-opacity))
            }

            .dark\:via-zinc-900 {
                --tw-gradient-to: rgb(24 24 27 / 0) var(--tw-gradient-to-position);
                --tw-gradient-stops: var(--tw-gradient-from), #18181b var(--tw-gradient-via-position), var(--tw-gradient-to)
            }

            .dark\:to-zinc-900 {
                --tw-gradient-to: #18181b var(--tw-gradient-to-position)
            }

            .dark\:text-white\/50 {
                color: rgb(255 255 255 / 0.5)
            }

            .dark\:text-white {
                --tw-text-opacity: 1;
                color: rgb(255 255 255 / var(--tw-text-opacity))
            }

            .dark\:text-white\/70 {
                color: rgb(255 255 255 / 0.7)
            }

            .dark\:ring-zinc-800 {
                --tw-ring-opacity: 1;
                --tw-ring-color: rgb(39 39 42 / var(--tw-ring-opacity))
            }

            .dark\:hover\:text-white:hover {
                --tw-text-opacity: 1;
                color: rgb(255 255 255 / var(--tw-text-opacity))
            }

            .dark\:hover\:text-white\/70:hover {
                color: rgb(255 255 255 / 0.7)
            }

            .dark\:hover\:text-white\/80:hover {
                color: rgb(255 255 255 / 0.8)
            }

            .dark\:hover\:ring-zinc-700:hover {
                --tw-ring-opacity: 1;
                --tw-ring-color: rgb(63 63 70 / var(--tw-ring-opacity))
            }

            .dark\:focus-visible\:ring-\[\#a5ac56\]:focus-visible {
                --tw-ring-opacity: 1;
                --tw-ring-color: rgb(165 172 86 / var(--tw-ring-opacity))
            }

            .dark\:focus-visible\:ring-white:focus-visible {
                --tw-ring-opacity: 1;
                --tw-ring-color: rgb(255 255 255 / var(--tw-ring-opacity))
            }
        }

        .module {
            position: relative;
            overflow: hidden;
        }

        .module.disabled {
            opacity: 0.5;
        }

        .module svg.bi.bi-dash-circle-fill {
            font-size: 10rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: .1;
        }
    </style>
</head>

<body class="font-sans antialiased dark:bg-black dark:text-white/50">
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        {{-- <img id="background" class="absolute -left-20 top-0 max-w-[877px]" style="filter: grayscale(1);" src="https://laravel.com/assets/img/welcome/background.svg" /> --}}
        <div class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#a5ac56] selection:text-white">
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header class="grid items-center gap-2 py-10">
                    <div class="flex justify-center w-auto text-[#a5ac56] gap-2">
                        <img src="https://github.com/swolley/images/blob/master/swolley-1.jpg?raw=true" />
                    </div>

                    <!-- session -->
                    {{-- @if (Route::has('login'))
                    <nav class="-mx-3 flex flex-1 justify-end">
                        @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#a5ac56] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                            Dashboard
                        </a>
                        @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#a5ac56] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                            Log in
                        </a>

                        @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#a5ac56] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                            Register
                        </a>
                        @endif
                        @endauth
                    </nav>
                    @endif --}}
                </header>

                <main class="mt-6">
                    <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                        <div id="docs-card" class="flex flex-col items-start gap-4 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
                            <div>
                                <h2 class="text-xl font-semibold text-black dark:text-white">{{ config('app.name') }}</h2>
                            </div>

                            <div class="relative flex flex-col items-center gap-6 lg:items-end w-full">
                                <div id="docs-card-content" class="flex items-start gap-2 flex-col w-full">
                                    <!-- language -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Languages:</i>
                                        <span style="float: right">{{ implode(', ', $translations) }}</span>
                                    </div>

                                    <!-- active modules -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Active Modules:</i>
                                        <span style="float: right">{{ implode(', ', modules(true)) }}</span>
                                    </div>

                                    <!-- migrations -->
                                    <div class="text-sm/relaxed w-full">
                                        @php
                                        $done = migrations(true, true);
                                        $total = migrations(true, false);
                                        @endphp
                                        <i>Migrations:</i>
                                        <div class="ml-4 flex items-center gap-2" style="float: right">
                                            @if ($done !== $total)
                                                @include('core::components.alert-icon')
                                            @else
                                                @include('core::components.check-icon')
                                            @endif
                                            <span>{{ migrations(true, true) }} / {{ migrations(true, false) }}</span>
                                        </div>
                                    </div>

                                    <!-- api versions -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>API Versions:</i>
                                        <span style="float: right">{{ implode(', ', api_versions()) ?: 'v1' }}</span>
                                    </div>

                                    <!-- debug mode -->
                                    <div class="text-sm/relaxed w-full">
                                        @php
                                        $debug = config('app.debug');
                                        @endphp
                                        <i>Debug Mode:</i>
                                        <div class="flex items-center  gap-2" style="float: right">
                                            @if ($debug)
                                                @include('core::components.alert-icon')
                                            @else
                                                @include('core::components.cancel-icon')
                                            @endif
                                        </div>
                                    </div>

                                    <!-- environment -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Application Environment:</i>
                                        <span class="flex items-center" style="float: right">{{ config('app.env') }}</span>
                                    </div>

                                    <!-- database -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Database Drivers:</i>
                                        <span class="flex items-center" style="float: right">{{ implode(', ', connections()) }}</span>
                                    </div>

                                    <!-- user registration -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Enable User registration:</i>
                                        <div class="flex items-center" style="float: right">
                                            @if (config('core.enable_user_registration'))
                                                @include('core::components.check-icon')
                                            @else
                                                @include('core::components.cancel-icon')
                                            @endif
                                        </div>
                                    </div>

                                    <!-- user verification -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Verify new user:</i>
                                        <div class="flex items-center" style="float: right">
                                            @if (config('core.verify_new_user'))
                                                @include('core::components.check-icon')
                                            @else
                                                @include('core::components.cancel-icon')
                                            @endif
                                        </div>
                                    </div>

                                    <!-- dynamic entities -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Enable dynamic entities:</i>
                                        <div class="flex items-center" style="float: right">
                                            @if (config('crud.dynamic_entities'))
                                                @include('core::components.check-icon')
                                            @else
                                                @include('core::components.cancel-icon')
                                            @endif
                                        </div>
                                    </div>

                                    <!-- maintenance -->
                                    <div class="text-sm/relaxed w-full">
                                        <i>Maintenance mode:</i>
                                        <div class="flex items-center" style="float: right">
                                            @if (app()->isDownForMaintenance())
                                            @include('core::components.alert-icon')
                                            @else
                                            @include('core::components.cancel-icon')
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div id="docs-card-content" class="flex flex-col gap-2 w-full">
                                    <a href="{{route('swagger.index')}}" class="flex items-center justify-center gap-2">
                                        <div class="flex items-center justify-center" style="min-width:60px;">
                                            @include('core::components.swagger-icon')
                                        </div>
                                        <div class="text-sm w-full">Go to routes documentation</div>
                                        @include('core::components.arrow-icon')
                                    </a>

                                    <a href="{{route('core.docs.phpinfo')}}" class="flex items-center justify-center gap-2">
                                        <div class="flex items-center justify-center" style="min-width:60px;">
                                            @include('core::components.php-icon')
                                        </div>
                                        <div class="text-sm w-full">Go to PHP info</div>
                                        @include('core::components.arrow-icon')
                                    </a>
                                </div>

                            </div>
                        </div>

                        <!-- modules -->
                        @foreach ($grouped_modules as $module => $data)
                            <div class="module {{ !$data['isEnabled'] ? 'disabled' : '' }} flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
                                @if(!$data['isEnabled'])
                                    @include('core::components.cancel-icon')
                                @endif

                                <div class="w-full flex flex-col h-full">
                                    <div>
                                        <h2 class="text-xl font-semibold text-black dark:text-white">
                                            {{ $module }}
                                            @if (isset($data['version']))
                                                <span class="text-sm/relaxed dark:text-white/50">v{{ $data['version'] }}</span>
                                            @endif
                                        </h2>
                                    </div>

                                    @if (isset($data['description']))
                                        <p class="mt-4 text-sm/relaxed">{{ $data['description'] }}</p>
                                    @endif

                                    <div class="mt-4 text-sm/relaxed flex grow">
                                        <!-- models -->
                                        <div class="w-1/2 flex flex-col gap-2">
                                            <div class="flex items-center justify-center gap-2 py-2">
                                                @include('core::components.barcode-icon')
                                                <div>Models</div>
                                            </div>
                                            @if (empty($data['models']))
                                                <div class="text-sm text-gray-600">
                                                    <span>No Model found</span>
                                                </div>
                                            @else
                                                @foreach ($data['models'] as $model)
                                                    <div class="text-sm">
                                                        <span>{{ Str::afterLast($model, '\\') }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>

                                        <!-- controllers -->
                                        <div class="w-1/2 flex flex-col gap-2">
                                            <div class="flex items-center justify-center gap-2 py-2">
                                                @include('core::components.route-icon')
                                                <div>Controllers</div>
                                            </div>
                                            @if (empty($data['controllers']))
                                                <div class="text-sm text-gray-600">
                                                    <span>No Controller found</span>
                                                </div>
                                            @else
                                                @foreach ($data['controllers'] as $controller)
                                                    <div class="text-sm">
                                                        <span>{{ Str::afterLast($controller, '\\') }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>

                                        <!-- routes -->
                                        {{-- <div class="w-1/2 flex flex-col gap-2">
                                            <div class="flex items-center justify-center gap-2 py-2">
                                                @include('core::components.route-icon')
                                                <div>Routes</div>
                                            </div>
                                            @if (empty($data['controllers']))
                                                <div class="text-sm text-gray-600">
                                                    <span>No Route found</span>
                                                </div>
                                            @else
                                                @foreach ($data['routes'] as $route)
                                                    <div class="text-sm">
                                                        <span>{{ $route['uri'] }}</span>
                                                        <span class="ml-4">{{ $route['methods'] }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div> --}}
                                    </div>

                                    @if ($data['authors'] && !empty($data['authors']))
                                        <p class="mt-4 text-sm/relaxed">
                                            @foreach ($data['authors'] as $author)
                                                <span class="author">
                                                    <span>{{ $author['name'] }}</span>
                                                    @if ($author['email'])
                                                        <a href="mailto:{{ $author['email'] }}">({{ $author['email'] }})</a>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    Laravel v{{ app()::VERSION }} (PHP v{{ PHP_VERSION }})
                </footer>
            </div>
        </div>
    </div>
</body>

</html>