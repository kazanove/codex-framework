<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Вход в систему CodeX Framework">
    <title>@yield('title', 'CodeX Framework')</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔐</text></svg>">
    <style>
        /* ===== СОВРЕМЕННЫЕ CSS-ПЕРЕМЕННЫЕ ===== */
        :root {
            /* Цвета */
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #818cf8;
            --danger: #ef4444;
            --danger-light: #fecaca;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            /* Типографика */
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

            /* Анимации */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;

            /* Тени */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);

            /* Радиусы */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;

            /* Размеры */
            --container-max: 480px;
        }

        /* ===== DARK MODE ===== */
        @media (prefers-color-scheme: dark) {
            :root {
                --gray-50: #111827;
                --gray-100: #1f2937;
                --gray-200: #374151;
                --gray-300: #4b5563;
                --gray-400: #6b7280;
                --gray-500: #9ca3af;
                --gray-600: #d1d5db;
                --gray-700: #e5e7eb;
                --gray-800: #f3f4f6;
                --gray-900: #f9fafb;
            }
        }

        /* ===== БАЗОВЫЕ СТИЛИ ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            line-height: 1.6;
            color: var(--gray-900);
            background: linear-gradient(135deg, var(--gray-50) 0%, #eef2ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, var(--gray-900) 0%, #0f172a 100%);
                color: var(--gray-100);
            }
        }

        /* ===== КОНТЕЙНЕР ===== */
        .login-container {
            width: 100%;
            max-width: var(--container-max);
        }

        /* ===== КАРТОЧКА ===== */
        .login-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        @media (prefers-color-scheme: dark) {
            .login-card {
                background: var(--gray-800);
            }
        }

        /* ===== ЗАГОЛОВОК ===== */
        .login-header {
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
        }

        .logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--gray-900) 100%, var(--gray-700) 0%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            /*color: var(--gray-900);*/
        }

        @media (prefers-color-scheme: dark) {
            .login-header h1 {
                background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-300) 100%);
                -webkit-text-fill-color: transparent;
                /*color: var(--gray-100);*/
            }
        }

        .login-header p {
            color: var(--gray-600);
            font-size: 1rem;
        }

        @media (prefers-color-scheme: dark) {
            .login-header p {
                color: var(--gray-400);
            }
        }

        /* ===== ФОРМА ===== */
        .login-form {
            padding: 0 2rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        @media (prefers-color-scheme: dark) {
            .form-label {
                color: var(--gray-100);
            }
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 0.875rem 0.875rem 2.5rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-family: var(--font-sans);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            color: var(--gray-900);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            .form-input {
                background: var(--gray-700);
                border-color: var(--gray-600);
                color: var(--gray-100);
            }
            .form-input:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            }
        }

        /* ===== КНОПКИ ===== */
        .form-actions {
            margin-top: 1rem;
        }

        .btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-family: var(--font-sans);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* ===== АЛЕРТЫ ===== */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-error {
            background: var(--danger-light);
            color: var(--danger);
        }

        @media (prefers-color-scheme: dark) {
            .alert-error {
                background: rgba(239, 68, 68, 0.1);
                color: var(--danger);
            }
        }

        .alert svg {
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        /* ===== ФУТЕР ===== */
        .login-footer {
            padding: 0 2rem 2rem;
            text-align: center;
        }

        .login-footer p {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (prefers-color-scheme: dark) {
            .login-footer p {
                color: var(--gray-400);
            }
        }

        /* ===== АНИМАЦИИ ПОЯВЛЕНИЯ ===== */
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

        .login-card {
            animation: fadeInUp 0.6s ease-out;
        }

        /* ===== АДАПТИВНОСТЬ ===== */
        @media (max-width: 480px) {
            .login-header {
                padding: 2rem 1.5rem 1rem;
            }

            .login-form, .login-footer {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .logo {
                width: 56px;
                height: 56px;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
@yield('content')

<script>
    // Добавляем плавные анимации при фокусе
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    });
</script>
</body>
</html>