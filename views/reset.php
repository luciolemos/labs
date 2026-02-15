<main class="panel">
  <div class="header">
    <div>
      <h1>Reset de senha</h1>
      <div class="subtitle">Reset manual via .env</div>
    </div>
  </div>

  <div class="alert">
    Para resetar a senha, edite o arquivo <code>.env</code> e defina
    <code>ADMIN_PASS_HASH</code> com o hash da nova senha.
  </div>

  <div class="help">Exemplo para gerar o hash:</div>
  <pre class="alert" style="white-space: pre-wrap;">php -r "echo password_hash('SUA_SENHA', PASSWORD_DEFAULT), PHP_EOL;"</pre>

  <div class="help">Depois disso, atualize o <code>ADMIN_PASS_HASH</code> no <code>.env</code>.</div>
</main>
