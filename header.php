<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sistema Contable PWA</title>
    
    <!-- Archivos CSS y PWA -->
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d1117">
    
    <!-- Configuración para iOS (Safari PWA) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Contable">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
</head>
<body>

 <!-- Top Bar -->
<div class="top-bar">
    <button class="top-bar-btn" onclick="toggleDrawer()">☰</button>
    <a href="index.php?v=<?php echo time(); ?>" class="top-bar-title" style="text-decoration: none; color: inherit;">Sistema Contable</a>
    <button class="top-bar-btn" id="themeToggleBtn" onclick="toggleTheme()">🌙</button>
</div>

    <!-- Drawer Overlay -->
    <div class="drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>

    <!-- Menú Lateral -->
    <div class="drawer" id="drawer">
        <div class="drawer-header">
            <h3 style="margin:0;">📍 Navegación</h3>
            <small style="color:var(--text-muted);">Usuario: <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Admin'); ?></small>
        </div>
        <ul class="drawer-menu">
            <li><a href="index.php?v=<?php echo time(); ?>">🏠 Menú Principal</a></li>
            <li><a href="clientes_deudores.php?v=<?php echo time(); ?>">📋 Cobros Pendientes</a></li>
            <li><a href="exportar_pdf_ciclos.php?v=<?php echo time(); ?>">📄 Exportar Estado de Cuenta</a></li>
            <li><a href="config_mensaje.php" class="drawer-item">⚙️ Config. Mensaje WhatsApp</a></li>
            <li><a href="eliminar_cliente.php?v=<?php echo time(); ?>">🚫 Archivar Cliente</a></li>
            
            <!-- Botón de instalación PWA integrado de forma nativa en el menú -->
            <li id="contenedorBtnInstalar" style="display: none; margin-top: 10px;">
                <button id="btnInstalarApp" style="width: 100%; background: var(--primary, #3b82f6); color: white; border: none; padding: 10px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    📱 Instalar Aplicación
                </button>
            </li>

            <li style="margin-top: 20px;"><a href="logout.php" style="color: var(--danger);">🚪 Cerrar Sesión</a></li>
        </ul>
    </div>

    <script>
        // Lógica de Menú Lateral
        function toggleDrawer() {
            document.getElementById('drawer').classList.toggle('active');
            document.getElementById('drawerOverlay').classList.toggle('active');
        }

        // Lógica Modo Oscuro
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            document.getElementById('themeToggleBtn').innerText = isDark ? '☀️' : '🌙';
        }

        // Cargar tema guardado
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            document.getElementById('themeToggleBtn').innerText = '☀️';
        }

        // Registro de Service Worker para PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                    .then(reg => console.log('SW registrado con éxito:', reg.scope))
                    .catch(err => console.log('Error al registrar SW:', err));
            });
        }

        // Lógica para mostrar el botón de instalación de la PWA
        let deferredPrompt;
        const contenedorBtnInstalar = document.getElementById('contenedorBtnInstalar');
        const btnInstalarApp = document.getElementById('btnInstalarApp');

        window.addEventListener('beforeinstallprompt', (e) => {
            // Previene que el navegador muestre su banner automático
            e.preventDefault();
            deferredPrompt = e;
            // Muestra el botón dentro del menú lateral
            contenedorBtnInstalar.style.display = 'block';
        });

        btnInstalarApp.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            // Muestra el aviso nativo de instalación
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                console.log('Usuario aceptó instalar la PWA');
            }
            deferredPrompt = null;
            contenedorBtnInstalar.style.display = 'none';
        });

        window.addEventListener('appinstalled', () => {
            contenedorBtnInstalar.style.display = 'none';
            deferredPrompt = null;
            console.log('PWA instalada correctamente');
        });
    </script>
    <div class="container">