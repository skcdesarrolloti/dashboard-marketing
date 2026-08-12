<section class="login-card">
  <div class="login-mark"><img src="<?= e(system_image('portal_logo_url')) ?>" alt="Su Casa Inmobiliaria"></div>
  <h1>Dashboard Marketing</h1>
  <p>Ingreso para funcionarios de promoción.</p>
  <?php if (!empty($error)) : ?>
    <div class="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="login">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label>Usuario</label>
    <input name="usuario" autocomplete="username" required>
    <label>Contraseña</label>
    <input name="password" type="password" autocomplete="current-password" required>
    <button class="primary" type="submit">Ingresar</button>
  </form>
</section>