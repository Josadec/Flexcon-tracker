<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FlexCon Tracker - Sistema de Control y Gestión de Producción Manufacturera (MES). Gestiona órdenes de compra, trabajo, producción, calidad y envíos en un solo sistema.">

    <title>FlexCon Tracker &mdash; Sistema de Control de Producción</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    <style>
        /* ===== CSS CUSTOM PROPERTIES ===== */
        :root {
            --bg-main:        #f8fafc;
            --bg-dark:        #0f172a;
            --bg-dark-alt:    #1e293b;
            --text-primary:   #0f172a;
            --text-secondary: #64748b;
            --text-muted:     #94a3b8;
            --color-primary:  #1d4ed8;
            --color-primary-hover: #1e40af;
            --color-teal:     #0f766e;
            --color-teal-light: #14b8a6;
            --color-amber:    #d97706;
            --color-amber-light: #fbbf24;
            --color-green:    #15803d;
            --color-green-light: #22c55e;
            --white:          #ffffff;
            --shadow-card:    0 1px 3px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.05);
            --shadow-card-hover: 0 4px 8px rgba(0,0,0,0.10), 0 12px 24px rgba(0,0,0,0.08);
            --radius-btn:     6px;
            --radius-card:    8px;
            --radius-badge:   20px;
            --transition:     all 0.2s ease;
            --max-width:      1200px;
        }

        /* ===== RESET & BASE ===== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-main);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ul, ol {
            list-style: none;
        }

        /* ===== UTILITIES ===== */
        .container {
            width: 100%;
            max-width: var(--max-width);
            margin-left: auto;
            margin-right: auto;
            padding-left: 24px;
            padding-right: 24px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border-width: 0;
        }

        /* ===== FADE-IN ANIMATION ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease both;
        }

        .fade-in-delay-1 { animation-delay: 0.1s; }
        .fade-in-delay-2 { animation-delay: 0.2s; }
        .fade-in-delay-3 { animation-delay: 0.3s; }
        .fade-in-delay-4 { animation-delay: 0.4s; }

        /* ===== NAVBAR ===== */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: var(--white);
            box-shadow: 0 1px 0 rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04);
        }

        .navbar__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            gap: 16px;
        }

        .navbar__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .navbar__logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 6px;
        }

        .navbar__brand-text {
            display: flex;
            flex-direction: column;
        }

        .navbar__brand-name {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            letter-spacing: -0.3px;
        }

        .navbar__tagline {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .navbar__right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .navbar__system-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            display: none;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 20px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-btn);
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            line-height: 1.4;
            letter-spacing: 0.1px;
        }

        .btn:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        .btn--primary {
            background-color: var(--color-primary);
            color: var(--white);
            border-color: var(--color-primary);
        }

        .btn--primary:hover {
            background-color: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);
        }

        .btn--primary:active {
            transform: translateY(0);
        }

        .btn--outline-dark {
            background-color: transparent;
            color: var(--white);
            border-color: rgba(255,255,255,0.35);
        }

        .btn--outline-dark:hover {
            background-color: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.6);
        }

        .btn--lg {
            padding: 13px 28px;
            font-size: 15px;
            border-radius: 7px;
        }

        .btn--xl {
            padding: 16px 36px;
            font-size: 16px;
            border-radius: 8px;
        }

        .btn--navbar {
            padding: 7px 16px;
            font-size: 13px;
        }

        /* ===== HERO SECTION ===== */
        .hero {
            background-color: var(--bg-dark);
            padding: 80px 0 72px;
            overflow: hidden;
        }

        .hero__content {
            text-align: center;
            max-width: 780px;
            margin: 0 auto;
        }

        .hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: var(--radius-badge);
            margin-bottom: 32px;
            letter-spacing: 0.2px;
        }

        .hero__badge-dot {
            width: 7px;
            height: 7px;
            background-color: var(--color-green-light);
            border-radius: 50%;
            animation: pulse-dot 2s ease-in-out infinite;
            flex-shrink: 0;
        }

        .hero__headline {
            font-size: clamp(36px, 5vw, 60px);
            font-weight: 700;
            color: var(--white);
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
        }

        .hero__headline span {
            color: var(--color-amber-light);
        }

        .hero__subheadline {
            font-size: clamp(16px, 2vw, 19px);
            color: rgba(255,255,255,0.6);
            line-height: 1.65;
            margin-bottom: 40px;
            max-width: 620px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero__cta-group {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 64px;
        }

        /* STATS */
        .hero__stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1px;
            background-color: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-card);
            overflow: hidden;
            max-width: 680px;
            margin: 0 auto;
        }

        .hero__stat {
            background-color: rgba(255,255,255,0.03);
            padding: 24px 28px;
            text-align: center;
            transition: var(--transition);
        }

        .hero__stat:hover {
            background-color: rgba(255,255,255,0.06);
        }

        .hero__stat-icon {
            width: 32px;
            height: 32px;
            margin: 0 auto 10px;
            color: var(--color-amber-light);
        }

        .hero__stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--white);
            letter-spacing: -0.8px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .hero__stat-label {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
            letter-spacing: 0.2px;
        }

        /* ===== SECTION COMMON ===== */
        .section {
            padding: 80px 0;
        }

        .section--alt {
            background-color: var(--bg-main);
        }

        .section--white {
            background-color: var(--white);
        }

        .section--dark {
            background-color: var(--bg-dark);
        }

        .section__header {
            text-align: center;
            margin-bottom: 56px;
        }

        .section__label {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--color-primary);
            margin-bottom: 12px;
        }

        .section__title {
            font-size: clamp(26px, 3.5vw, 38px);
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.8px;
            line-height: 1.2;
            margin-bottom: 14px;
        }

        .section__title--light {
            color: var(--white);
        }

        .section__subtitle {
            font-size: 17px;
            color: var(--text-secondary);
            max-width: 520px;
            margin: 0 auto;
            line-height: 1.65;
        }

        .section__subtitle--light {
            color: rgba(255,255,255,0.55);
        }

        /* ===== MODULES GRID ===== */
        .modules-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .module-card {
            background-color: var(--white);
            border-radius: var(--radius-card);
            padding: 28px 24px;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .module-card__icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .module-card__icon-wrap svg {
            width: 22px;
            height: 22px;
        }

        .module-card__icon-wrap--blue {
            background-color: #eff6ff;
            color: var(--color-primary);
        }

        .module-card__icon-wrap--teal {
            background-color: #f0fdfa;
            color: var(--color-teal);
        }

        .module-card__icon-wrap--amber {
            background-color: #fffbeb;
            color: var(--color-amber);
        }

        .module-card__icon-wrap--green {
            background-color: #f0fdf4;
            color: var(--color-green);
        }

        .module-card__icon-wrap--slate {
            background-color: #f8fafc;
            color: #475569;
        }

        .module-card__icon-wrap--rose {
            background-color: #fff1f2;
            color: #be123c;
        }

        .module-card__title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.2px;
        }

        .module-card__description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .module-card__tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .tag {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 4px;
            letter-spacing: 0.2px;
        }

        .tag--blue {
            background-color: #eff6ff;
            color: var(--color-primary);
        }

        .tag--teal {
            background-color: #f0fdfa;
            color: var(--color-teal);
        }

        .tag--amber {
            background-color: #fffbeb;
            color: #92400e;
        }

        .tag--green {
            background-color: #f0fdf4;
            color: var(--color-green);
        }

        .tag--slate {
            background-color: #f1f5f9;
            color: #475569;
        }

        /* ===== DEPARTMENTS SECTION ===== */
        .departments-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .dept-card {
            background-color: var(--white);
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .dept-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .dept-card__icon-col {
            flex-shrink: 0;
        }

        .dept-card__icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dept-card__icon-wrap svg {
            width: 26px;
            height: 26px;
        }

        .dept-card__icon-wrap--blue {
            background-color: #dbeafe;
            color: var(--color-primary);
        }

        .dept-card__icon-wrap--amber {
            background-color: #fef3c7;
            color: var(--color-amber);
        }

        .dept-card__icon-wrap--teal {
            background-color: #ccfbf1;
            color: var(--color-teal);
        }

        .dept-card__icon-wrap--green {
            background-color: #dcfce7;
            color: var(--color-green);
        }

        .dept-card__body {
            flex: 1;
            min-width: 0;
        }

        .dept-card__name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.2px;
            margin-bottom: 4px;
        }

        .dept-card__description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .dept-card__features {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        /* ===== WORKFLOW / PROCESS SECTION ===== */
        .workflow-steps {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            position: relative;
        }

        .workflow-step {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 28px 0;
            position: relative;
        }

        .workflow-step:not(:last-child) {
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        .workflow-step__number-col {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .workflow-step__number {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--color-primary);
            color: var(--white);
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            letter-spacing: -0.3px;
        }

        .workflow-step__body {
            flex: 1;
            padding-top: 8px;
        }

        .workflow-step__phase {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--color-primary);
            margin-bottom: 4px;
        }

        .workflow-step__title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }

        .workflow-step__description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.65;
        }

        .workflow-step__icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            align-self: center;
        }

        .workflow-step__icon svg {
            width: 24px;
            height: 24px;
        }

        /* ===== CTA SECTION ===== */
        .cta {
            background-color: var(--bg-dark);
            padding: 80px 0;
            text-align: center;
        }

        .cta__content {
            max-width: 600px;
            margin: 0 auto;
        }

        .cta__eyebrow {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--color-amber-light);
            margin-bottom: 16px;
        }

        .cta__headline {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 700;
            color: var(--white);
            letter-spacing: -1px;
            line-height: 1.2;
            margin-bottom: 14px;
        }

        .cta__subtext {
            font-size: 16px;
            color: rgba(255,255,255,0.55);
            line-height: 1.65;
            margin-bottom: 36px;
        }

        /* ===== FOOTER ===== */
        .footer {
            background-color: #080f1e;
            padding: 36px 0;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer__inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            text-align: center;
        }

        .footer__brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer__logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            opacity: 0.9;
        }

        .footer__brand-name {
            font-size: 15px;
            font-weight: 700;
            color: rgba(255,255,255,0.85);
        }

        .footer__divider {
            width: 1px;
            height: 16px;
            background-color: rgba(255,255,255,0.15);
        }

        .footer__copy {
            font-size: 13px;
            color: rgba(255,255,255,0.35);
        }

        /* ===== RESPONSIVE: TABLET (640px+) ===== */
        @media (min-width: 640px) {
            .navbar__system-label {
                display: block;
            }

            .hero__stats {
                grid-template-columns: repeat(4, 1fr);
            }

            .departments-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer__inner {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }
        }

        /* ===== RESPONSIVE: DESKTOP (768px+) ===== */
        @media (min-width: 768px) {
            .modules-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .workflow-steps {
                grid-template-columns: repeat(2, 1fr);
                gap: 1px;
                background-color: rgba(0,0,0,0.06);
                border-radius: var(--radius-card);
                overflow: hidden;
            }

            .workflow-step {
                background-color: var(--white);
                padding: 32px 28px;
                border-bottom: none;
            }

            .workflow-step:not(:last-child) {
                border-bottom: none;
            }
        }

        /* ===== RESPONSIVE: LARGE (1024px+) ===== */
        @media (min-width: 1024px) {
            .container {
                padding-left: 40px;
                padding-right: 40px;
            }

            .modules-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .departments-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .workflow-steps {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>

    {{-- ===== NAVBAR ===== --}}
    <header class="navbar" role="banner">
        <div class="container">
            <div class="navbar__inner">

                <a href="{{ url('/') }}" class="navbar__brand" aria-label="FlexCon Tracker - Inicio">
                    <img
                        src="/flexcon.png"
                        alt="FlexCon Tracker logotipo"
                        class="navbar__logo"
                        width="36"
                        height="36"
                    >
                    <div class="navbar__brand-text">
                        <span class="navbar__brand-name">FlexCon Tracker</span>
                        <span class="navbar__tagline">Manufacturing Execution System</span>
                    </div>
                </a>

                <div class="navbar__right">
                    <span class="navbar__system-label">Sistema de Control de Producción</span>

                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn--primary btn--navbar" aria-label="Ir al panel principal">
                            Acceder al Sistema
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn--primary btn--navbar" aria-label="Iniciar sesión en FlexCon Tracker">
                            Acceder al Sistema
                        </a>
                    @endauth
                </div>

            </div>
        </div>
    </header>

    <main id="main-content">

        {{-- ===== HERO SECTION ===== --}}
        <section class="hero" aria-label="Presentación principal de FlexCon Tracker">
            <div class="container">
                <div class="hero__content">

                    <div class="hero__badge fade-in" role="status" aria-label="Estado del sistema: en producción">
                        <span class="hero__badge-dot" aria-hidden="true"></span>
                        Sistema MES en producción
                    </div>

                    <h1 class="hero__headline fade-in fade-in-delay-1">
                        Control total de tu<br>
                        <span>planta de producción</span>
                    </h1>

                    <p class="hero__subheadline fade-in fade-in-delay-2">
                        FlexCon Tracker integra órdenes de compra, producción, materiales, calidad y envíos en una sola plataforma. Visibilidad completa, trazabilidad total y decisiones más rápidas.
                    </p>

                    <div class="hero__cta-group fade-in fade-in-delay-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn--primary btn--lg" aria-label="Ir al panel de control">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                                </svg>
                                Acceder al Sistema
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn--primary btn--lg" aria-label="Iniciar sesión">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                                Acceder al Sistema
                            </a>
                        @endauth

                        <a href="#modulos" class="btn btn--outline-dark btn--lg" aria-label="Ver los módulos disponibles">
                            Ver Módulos
                        </a>
                    </div>

                    {{-- STATS ROW --}}
                    <div class="hero__stats fade-in fade-in-delay-4" role="list" aria-label="Estadísticas del sistema">

                        <div class="hero__stat" role="listitem">
                            <div class="hero__stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                                </svg>
                            </div>
                            <div class="hero__stat-number">PO</div>
                            <div class="hero__stat-label">Purchase Orders</div>
                        </div>

                        <div class="hero__stat" role="listitem">
                            <div class="hero__stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div class="hero__stat-number">WO</div>
                            <div class="hero__stat-label">Work Orders</div>
                        </div>

                        <div class="hero__stat" role="listitem">
                            <div class="hero__stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="hero__stat-number">4</div>
                            <div class="hero__stat-label">Departamentos</div>
                        </div>

                        <div class="hero__stat" role="listitem">
                            <div class="hero__stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <div class="hero__stat-number">Multi</div>
                            <div class="hero__stat-label">Roles de acceso</div>
                        </div>

                    </div>
                    {{-- /STATS ROW --}}

                </div>
            </div>
        </section>
        {{-- /HERO --}}


        {{-- ===== MODULES SECTION ===== --}}
        <section id="modulos" class="section section--white" aria-labelledby="modulos-title">
            <div class="container">

                <div class="section__header">
                    <span class="section__label">Módulos del sistema</span>
                    <h2 class="section__title" id="modulos-title">
                        Todo lo que necesitas, en un solo sistema
                    </h2>
                    <p class="section__subtitle">
                        Cada módulo está diseñado para el flujo real de una planta manufacturera, con trazabilidad completa desde la orden de compra hasta el envío final.
                    </p>
                </div>

                <div class="modules-grid" role="list">

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--blue" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Gestión de Órdenes</h3>
                            <p class="module-card__description">
                                Controla el ciclo completo desde la recepción de una Purchase Order hasta la creación y cierre de Work Orders con validación automática de precios.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--blue">Purchase Orders</span>
                            <span class="tag tag--blue">Work Orders</span>
                            <span class="tag tag--slate">Back Orders</span>
                        </div>
                    </article>

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--amber" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Producción</h3>
                            <p class="module-card__description">
                                Gestiona pesadas, armado de kits, control de lotes y cálculo de capacidad. Trazabilidad de cada unidad producida en tiempo real.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--amber">Pesadas</span>
                            <span class="tag tag--amber">Kits</span>
                            <span class="tag tag--amber">Lotes</span>
                            <span class="tag tag--slate">Capacidad</span>
                        </div>
                    </article>

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--slate" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Materiales</h3>
                            <p class="module-card__description">
                                Distribución de materiales por lote, gestión de inventario y control de disponibilidad para cada orden de trabajo activa.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--slate">Distribución</span>
                            <span class="tag tag--slate">Inventario</span>
                            <span class="tag tag--slate">Lotes</span>
                        </div>
                    </article>

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Control de Calidad</h3>
                            <p class="module-card__description">
                                Inspección por lote, pesadas de calidad, registro de rechazos y flujo de acciones correctivas integrado al proceso de producción.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--teal">Inspección</span>
                            <span class="tag tag--teal">Pesadas QC</span>
                            <span class="tag tag--slate">Rechazos</span>
                        </div>
                    </article>

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--green" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Empaques &amp; Envíos</h3>
                            <p class="module-card__description">
                                Genera Packing Slips, Shipping Lists e Invoices. Controla el empaque final y coordina el despacho con documentación completa.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--green">Packing Slips</span>
                            <span class="tag tag--green">Shipping Lists</span>
                            <span class="tag tag--slate">Invoices</span>
                        </div>
                    </article>

                    <article class="module-card" role="listitem">
                        <div class="module-card__icon-wrap module-card__icon-wrap--rose" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/><path d="M19 3l-5 5m0 0l-5-5m5 5V3"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="module-card__title">Administración</h3>
                            <p class="module-card__description">
                                Gestión de usuarios, roles de acceso por departamento, catálogos de productos y configuración general del sistema.
                            </p>
                        </div>
                        <div class="module-card__tags">
                            <span class="tag tag--slate">Usuarios</span>
                            <span class="tag tag--slate">Roles</span>
                            <span class="tag tag--slate">Catálogos</span>
                        </div>
                    </article>

                </div>
            </div>
        </section>
        {{-- /MODULES --}}


        {{-- ===== DEPARTMENTS SECTION ===== --}}
        <section class="section section--alt" aria-labelledby="departamentos-title">
            <div class="container">

                <div class="section__header">
                    <span class="section__label">Areas operativas</span>
                    <h2 class="section__title" id="departamentos-title">
                        Módulos por departamento
                    </h2>
                    <p class="section__subtitle">
                        Cada departamento tiene su propio espacio de trabajo con las herramientas específicas que necesita para operar con eficiencia.
                    </p>
                </div>

                <div class="departments-grid" role="list">

                    <article class="dept-card" role="listitem">
                        <div class="dept-card__icon-col">
                            <div class="dept-card__icon-wrap dept-card__icon-wrap--blue" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                </svg>
                            </div>
                        </div>
                        <div class="dept-card__body">
                            <h3 class="dept-card__name">Produccion</h3>
                            <p class="dept-card__description">
                                Gestiona el ciclo completo de fabricación. Desde la recepción de la orden de trabajo hasta el ensamble, incluyendo pesadas, kits y control de lotes.
                            </p>
                            <div class="dept-card__features">
                                <span class="tag tag--blue">Pesadas</span>
                                <span class="tag tag--blue">Kits</span>
                                <span class="tag tag--blue">Ensamble</span>
                                <span class="tag tag--blue">Lotes</span>
                            </div>
                        </div>
                    </article>

                    <article class="dept-card" role="listitem">
                        <div class="dept-card__icon-col">
                            <div class="dept-card__icon-wrap dept-card__icon-wrap--amber" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="dept-card__body">
                            <h3 class="dept-card__name">Materiales</h3>
                            <p class="dept-card__description">
                                Controla la distribución de materia prima por lote, asegura disponibilidad y gestiona el inventario de insumos para cada orden activa.
                            </p>
                            <div class="dept-card__features">
                                <span class="tag tag--amber">Distribucion</span>
                                <span class="tag tag--amber">Inventario</span>
                                <span class="tag tag--amber">Trazabilidad</span>
                            </div>
                        </div>
                    </article>

                    <article class="dept-card" role="listitem">
                        <div class="dept-card__icon-col">
                            <div class="dept-card__icon-wrap dept-card__icon-wrap--teal" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="dept-card__body">
                            <h3 class="dept-card__name">Calidad</h3>
                            <p class="dept-card__description">
                                Ejecuta inspecciones por lote, registra pesadas de control, gestiona rechazos y genera acciones correctivas con seguimiento completo.
                            </p>
                            <div class="dept-card__features">
                                <span class="tag tag--teal">Inspeccion</span>
                                <span class="tag tag--teal">Pesadas QC</span>
                                <span class="tag tag--teal">Rechazos</span>
                            </div>
                        </div>
                    </article>

                    <article class="dept-card" role="listitem">
                        <div class="dept-card__icon-col">
                            <div class="dept-card__icon-wrap dept-card__icon-wrap--green" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                                </svg>
                            </div>
                        </div>
                        <div class="dept-card__body">
                            <h3 class="dept-card__name">Empaques</h3>
                            <p class="dept-card__description">
                                Coordina el empaque final del producto, genera documentación de envío, Packing Slips y Shipping Lists para despacho al cliente.
                            </p>
                            <div class="dept-card__features">
                                <span class="tag tag--green">Packing</span>
                                <span class="tag tag--green">Shipping</span>
                                <span class="tag tag--green">Documentos</span>
                            </div>
                        </div>
                    </article>

                </div>
            </div>
        </section>
        {{-- /DEPARTMENTS --}}


        {{-- ===== WORKFLOW SECTION ===== --}}
        <section class="section section--white" aria-labelledby="flujo-title">
            <div class="container">

                <div class="section__header">
                    <span class="section__label">Flujo de trabajo</span>
                    <h2 class="section__title" id="flujo-title">
                        Flujo de trabajo integrado
                    </h2>
                    <p class="section__subtitle">
                        Desde la orden de compra hasta el envío al cliente, cada etapa del proceso esta conectada y con trazabilidad completa.
                    </p>
                </div>

                <div class="workflow-steps" role="list">

                    <div class="workflow-step" role="listitem">
                        <div class="workflow-step__number-col">
                            <div class="workflow-step__number" aria-label="Paso 1">1</div>
                        </div>
                        <div class="workflow-step__body">
                            <div class="workflow-step__phase">Inicio</div>
                            <h3 class="workflow-step__title">Orden de Compra</h3>
                            <p class="workflow-step__description">
                                Se recibe la PO del cliente. El sistema valida precios automáticamente y la prepara para generar la Work Order correspondiente.
                            </p>
                        </div>
                    </div>

                    <div class="workflow-step" role="listitem">
                        <div class="workflow-step__number-col">
                            <div class="workflow-step__number" aria-label="Paso 2">2</div>
                        </div>
                        <div class="workflow-step__body">
                            <div class="workflow-step__phase">Planificacion</div>
                            <h3 class="workflow-step__title">Orden de Trabajo</h3>
                            <p class="workflow-step__description">
                                Se crea la WO con calculo de capacidad, distribucion de materiales y lista de envio preliminar para coordinar produccion.
                            </p>
                        </div>
                    </div>

                    <div class="workflow-step" role="listitem">
                        <div class="workflow-step__number-col">
                            <div class="workflow-step__number" aria-label="Paso 3">3</div>
                        </div>
                        <div class="workflow-step__body">
                            <div class="workflow-step__phase">Ejecucion</div>
                            <h3 class="workflow-step__title">Produccion &amp; Calidad</h3>
                            <p class="workflow-step__description">
                                Preparacion de kits, ensamble, inspeccion de calidad y empaque. Los rechazos generan acciones correctivas antes de continuar.
                            </p>
                        </div>
                    </div>

                    <div class="workflow-step" role="listitem">
                        <div class="workflow-step__number-col">
                            <div class="workflow-step__number" aria-label="Paso 4">4</div>
                        </div>
                        <div class="workflow-step__body">
                            <div class="workflow-step__phase">Cierre</div>
                            <h3 class="workflow-step__title">Envio &amp; Entrega</h3>
                            <p class="workflow-step__description">
                                Shipping List final, Invoice generado y WO cerrada. Si quedan pendientes, se crea un BackOrder automaticamente para el siguiente ciclo.
                            </p>
                        </div>
                    </div>

                </div>

            </div>
        </section>
        {{-- /WORKFLOW --}}


        {{-- ===== CTA SECTION ===== --}}
        <section class="cta" aria-labelledby="cta-title">
            <div class="container">
                <div class="cta__content">
                    <p class="cta__eyebrow">Listo para comenzar</p>
                    <h2 class="cta__headline" id="cta-title">
                        Listo para optimizar<br>tu produccion?
                    </h2>
                    <p class="cta__subtext">
                        Accede al sistema y toma el control total de tu planta manufacturera. Trazabilidad completa desde la primera PO hasta el ultimo envio.
                    </p>

                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn--primary btn--xl" aria-label="Ingresar al panel de control del sistema">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                            </svg>
                            Ingresar al Sistema
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn--primary btn--xl" aria-label="Iniciar sesion en FlexCon Tracker">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            Ingresar al Sistema
                        </a>
                    @endauth
                </div>
            </div>
        </section>
        {{-- /CTA --}}

    </main>


    {{-- ===== FOOTER ===== --}}
    <footer class="footer" role="contentinfo">
        <div class="container">
            <div class="footer__inner">

                <div class="footer__brand">
                    <img
                        src="/flexcon.png"
                        alt="FlexCon Tracker"
                        class="footer__logo"
                        width="28"
                        height="28"
                    >
                    <span class="footer__brand-name">FlexCon Tracker</span>
                    <div class="footer__divider" aria-hidden="true"></div>
                    <span class="footer__copy" style="color: rgba(255,255,255,0.45); font-size: 13px;">
                        Manufacturing Execution System
                    </span>
                </div>

                <p class="footer__copy">
                    &copy; 2026 FlexCon Tracker. Todos los derechos reservados.
                </p>

            </div>
        </div>
    </footer>

</body>
</html>
