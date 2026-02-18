<?php
$basePath = rtrim((string)($basePath ?? ''), '/');
$url = static fn(string $path): string => $basePath . $path;
?>
<main class="panel panel-auth">
  <div class="header">
    <div>
      <h1>Login</h1>
      <div class="subtitle">Acesso restrito ao admin</div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert" role="alert" aria-live="assertive">Usuario ou senha invalidos.</div>
  <?php endif; ?>

  <form class="form" method="post" action="<?= htmlspecialchars($url('/login')) ?>">
    <div class="form-row form-row-single">
      <label class="sr-only" for="login-user">Usuario</label>
      <input class="input" id="login-user" name="user" placeholder="Usuario" autocomplete="username" required />
      <label class="sr-only" for="login-pass">Senha</label>
      <input class="input" id="login-pass" type="password" name="pass" placeholder="Senha" autocomplete="current-password" required />
    </div>
    <div class="form-actions">
      <button class="button" type="submit">Entrar</button>
      <a class="button" href="<?= htmlspecialchars($url('/reset')) ?>">Esqueci minha senha</a>
    </div>
  </form>
</main>
