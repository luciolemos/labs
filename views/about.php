<main class="panel">
  <div class="header">
    <div>
      <h1>Sobre</h1>
      <div class="subtitle">Informacoes do ambiente</div>
    </div>
  </div>
  <div class="grid">
    <section class="card">
      <div class="badge">APP</div>
      <div class="name"><?= htmlspecialchars($app['name'] ?? 'Painel') ?></div>
      <div class="url">Ambiente: <?= htmlspecialchars($app['env'] ?? 'production') ?></div>
      <div class="url">Debug: <?= htmlspecialchars(($app['debug'] ?? false) ? 'true' : 'false') ?></div>
    </section>
    <section class="card">
      <div class="badge">ROTAS</div>
      <div class="name">Extras</div>
      <div class="url">/about</div>
      <div class="url">/api/sites</div>
      <div class="url">/api/validate</div>
      <div class="url">/health</div>
    </section>
    <section class="card">
      <div class="badge">DEPLOY</div>
      <div class="name">Como recriar</div>
      <div class="url">1. Clone o repositorio e rode composer install.</div>
      <div class="url">2. Configure o .env (APP_*, ADMIN_*, SITE_PER_PAGE).</div>
      <div class="url">3. Apache + mod_rewrite, vhost apontando para /public.</div>
      <div class="url">4. storage/ gravavel pelo usuario do Apache.</div>
      <div class="url">5. (Opcional) sudo para bin/provision-site e escrita no conf do Apache.</div>
    </section>
    <section class="card">
      <div class="badge">ENV</div>
      <div class="name">Variaveis chave</div>
      <div class="url">ADMIN_USER / ADMIN_PASS / ADMIN_PASS_HASH</div>
      <div class="url">ADMIN_PROVISION / ADMIN_PROVISION_HOST / ADMIN_PROVISION_BASE</div>
      <div class="url">ADMIN_APACHE_CONF / ADMIN_APACHE_CONF_NAME</div>
      <div class="url">SITE_PER_PAGE</div>
    </section>
  </div>
</main>
