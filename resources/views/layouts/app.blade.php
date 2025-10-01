<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Project Consultant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/sidebar.css',
        'resources/css/chatbot.css'
    ])
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="app-layout">

    @include('sidebar')

    <!-- New main content wrapper -->
    <div class="main-content-wrapper">
        
        @include('main-header')

        <!-- Main content area for chat/welcome message -->
        <main class="content-area">
            @yield('content')
        </main>

        <!-- Include the prompt bar at the bottom -->
        @include('prompt-bar')

    </div>

    <script>
        document.querySelectorAll('.nav-section-header').forEach(header => {
            header.addEventListener('click', () => {
                const expanded = header.getAttribute('aria-expanded') === 'true';
                header.setAttribute('aria-expanded', !expanded);
                const submenu = header.nextElementSibling;
                if (!expanded) {
                    submenu.style.display = 'block';
                } else {
                    submenu.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>