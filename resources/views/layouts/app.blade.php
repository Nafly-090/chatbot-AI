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

    <div class="main-content-wrapper">

        <div class="mobile-header">
            <button id="menu-toggle-btn" class="menu-toggle-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <div class="mobile-header-title">CodeCraft AI</div>
        </div>

        <!-- Main content area for chat/welcome message -->
        <main class="content-area">
            @yield('content')
        </main>

    </div>
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    
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