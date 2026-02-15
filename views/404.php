<main class="panel">
  <div class="header">
    <div>
      <h1>404</h1>
      <div class="subtitle">Pagina nao encontrada</div>
    </div>
  </div>

  <div class="alert">
    A rota <code><?= htmlspecialchars($path ?? '') ?></code> nao existe no Labs.
  </div>

  <div style="display:flex; gap:10px; flex-wrap: wrap;">
    <a class="button" href="/">Voltar para o painel</a>
    <a class="button" href="/about">Ver sobre o sistema</a>
  </div>
</main>
