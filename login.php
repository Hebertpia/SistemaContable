<?php
session_start();
require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (!empty($usuario) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, password FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario'] = $usuario;
                header("Location: index.php");
                exit;
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
    } else {
        $error = "Por favor complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - PWA Contable</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="padding-top: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh;">

    <div class="container" style="width: 100%; max-width: 380px;">
        <div class="card" style="text-align: center; padding: 30px 20px;">
            <h2 style="margin-top: 0; color: var(--primary);">🔒 Acceso</h2>
            <p style="color: var(--text-muted); font-size: 0.9em;">Ingrese sus credenciales de administrador</p>

            <?php if (!empty($error)): ?>
                <div style="background: rgba(220,53,69,0.15); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group" style="text-align: left;">
                    <label>Usuario</label>
                    <input type="text" name="usuario" class="form-control" required placeholder="Ej. admin">
                </div>

                <div class="form-group" style="text-align: left;">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 10px;">Ingresar al Sistema</button>
            </form>
        </div>
    </div>

</body>
</html>