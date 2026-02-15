<main class="panel">
  <div class="header">
    <div>
      <h1><?= htmlspecialchars($title ?? 'Painel de Sites') ?></h1>
      <div class="subtitle">Atalhos rapidos para seus projetos</div>
      <?php
        $meta = $meta ?? [];
        $env = $meta['env'] ?? 'production';
        $php = $meta['php'] ?? '';
        $apache = $meta['apache'] ?? '';
        $host = $meta['host'] ?? '';
        $uptime = $meta['uptime'] ?? '';
        $lastProvision = $meta['lastProvision'] ?? '';
        $total = (int)($meta['total'] ?? 0);
        $perPage = (int)($meta['perPage'] ?? 0);
      ?>
      <div class="meta">
        <span class="meta-item meta-primary">Ambiente: <?= htmlspecialchars($env) ?></span>
        <?php if ($php !== ''): ?>
          <span class="meta-item meta-info">PHP: <?= htmlspecialchars($php) ?></span>
        <?php endif; ?>
        <?php if ($apache !== ''): ?>
          <span class="meta-item meta-info">Apache: <?= htmlspecialchars($apache) ?></span>
        <?php endif; ?>
        <?php if ($host !== ''): ?>
          <span class="meta-item meta-primary">Host: <?= htmlspecialchars($host) ?></span>
        <?php endif; ?>
        <span class="meta-item meta-success">Sites: <?= $total ?></span>
        <?php if ($lastProvision !== ''): ?>
          <span class="meta-item meta-warning">Ultimo provisionamento: <?= htmlspecialchars($lastProvision) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="table">
    <div class="table-row table-header" aria-hidden="true">
      <div class="table-cell">Nome</div>
      <div class="table-cell">Descricao</div>
      <div class="table-cell">URL</div>
      <div class="table-cell">Acoes</div>
    </div>
    <?php foreach ($sites ?? [] as $site): ?>
      <div class="table-row">
        <div class="table-cell table-name"><?= htmlspecialchars($site['name']) ?></div>
        <div class="table-cell">
          <?= htmlspecialchars($site['description'] ?? '') ?>
        </div>
        <div class="table-cell">
          <a class="table-link" href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener noreferrer">
            <?= htmlspecialchars($site['url']) ?>
          </a>
        </div>
        <div class="table-cell table-actions">
          <a class="button" href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener noreferrer">
            Abrir
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
    $pagination = $pagination ?? [];
    $page = (int)($pagination['page'] ?? 1);
    $totalPages = (int)($pagination['totalPages'] ?? 1);
    $total = (int)($pagination['total'] ?? 0);
  ?>
  <div class="pagination">
    <div class="pagination-info">
      Pagina <?= $page ?> de <?= $totalPages ?> • <?= $total ?> sites no total
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="pagination-actions">
        <?php if ($page > 1): ?>
          <a class="button" href="/?page=1">Primeira</a>
        <?php endif; ?>
        <?php if ($page > 1): ?>
          <a class="button" href="/?page=<?= $page - 1 ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="button" href="/?page=<?= $page + 1 ?>">Proxima</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="button" href="/?page=<?= $totalPages ?>">Ultima</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="info-strip">
    <div>
      <div class="badge">INFO</div>
      <div class="name">Sobre o painel</div>
      <div class="description">Rotas extras e ambiente</div>
    </div>
    <a class="link" href="/about">Ver detalhes</a>
  </div>
</main>
