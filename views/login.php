<main class="panel">
  <div class="header">
    <div>
      <h1>Login</h1>
      <div class="subtitle">Acesso restrito ao admin</div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert">Usuario ou senha invalidos.</div>
  <?php endif; ?>

  <form class="form" method="post" action="/login">
    <div class="form-row" style="grid-template-columns: 1fr;">
      <input class="input" name="user" placeholder="Usuario" />
      <input class="input" type="password" name="pass" placeholder="Senha" />
    </div>
    <div style="display:flex; gap:10px; flex-wrap: wrap;">
      <button class="button" type="submit">Entrar</button>
      <a class="button" href="/reset">Esqueci minha senha</a>
    </div>
  </form>
</main>
