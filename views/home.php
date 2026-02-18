<?php
$basePath = rtrim((string)($basePath ?? ''), '/');
$url = static fn(string $path): string => $basePath . $path;
?>
<main class="panel panel-home">
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
        $filteredTotal = (int)($meta['filteredTotal'] ?? $total);
        $perPage = (int)($meta['perPage'] ?? 0);
        $filters = $filters ?? ['q' => '', 'visibility' => 'all'];
        $searchQuery = (string)($filters['q'] ?? '');
        $visibility = (string)($filters['visibility'] ?? 'all');
        $templateFilter = (string)($filters['template'] ?? 'all');
        $templateOptions = $templateOptions ?? [];
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
        <span class="meta-item meta-success">Sites: <?= $filteredTotal ?><?= $filteredTotal !== $total ? ' de ' . $total : '' ?></span>
        <?php if ($lastProvision !== ''): ?>
          <span class="meta-item meta-warning">Ultimo provisionamento: <?= htmlspecialchars($lastProvision) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <form class="filter-form" method="get" action="<?= htmlspecialchars($url('/')) ?>">
    <div class="filter-row">
      <div class="filter-field">
        <label class="sr-only" for="home-filter-q">Buscar site</label>
        <input
          id="home-filter-q"
          class="input"
          type="search"
          name="q"
          placeholder="Buscar por nome, descricao ou URL"
          value="<?= htmlspecialchars($searchQuery) ?>"
        />
      </div>
      <div class="filter-field filter-select">
        <label class="sr-only" for="home-filter-visibility">Visibilidade</label>
        <select id="home-filter-visibility" class="input" name="visibility">
          <option value="all" <?= $visibility === 'all' ? 'selected' : '' ?>>Todos</option>
          <option value="protected" <?= $visibility === 'protected' ? 'selected' : '' ?>>Somente protegidos</option>
          <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>Somente publicos</option>
        </select>
      </div>
      <div class="filter-field filter-select">
        <label class="sr-only" for="home-filter-template">Template</label>
        <select id="home-filter-template" class="input" name="template">
          <?php foreach ($templateOptions as $option): ?>
            <?php $value = (string)($option['value'] ?? ''); ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $templateFilter === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($option['label'] ?? $value)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button class="button" type="submit">Filtrar</button>
        <a class="button" href="<?= htmlspecialchars($url('/')) ?>">Limpar</a>
      </div>
    </div>
  </form>

  <div class="table">
    <table class="sites-table">
      <caption class="sr-only">Lista de sites provisionados</caption>
      <thead>
        <tr>
          <th scope="col">Nome</th>
          <th scope="col">Descricao</th>
          <th scope="col">URL</th>
          <th scope="col">Template</th>
          <th scope="col">Acoes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($sites)): ?>
          <?php foreach ($sites as $site): ?>
            <tr>
              <td class="table-name" data-label="Nome"><?= htmlspecialchars($site['name']) ?></td>
              <td data-label="Descricao"><?= htmlspecialchars($site['description'] ?? '') ?></td>
              <td data-label="URL">
                <?php if (!empty($site['local_managed']) && empty($site['local_available'])): ?>
                  <span class="table-link table-link-missing"><?= htmlspecialchars($site['url']) ?></span>
                <?php else: ?>
                  <a class="table-link" href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($site['url']) ?>
                  </a>
                <?php endif; ?>
              </td>
              <td data-label="Template">
                <div class="template-cell">
                  <span class="template-pill <?= (($site['template_kind'] ?? 'managed') === 'legacy') ? 'template-pill-legacy' : 'template-pill-managed' ?>">
                    <?= htmlspecialchars((string)($site['template_label'] ?? 'Template')) ?>
                  </span>
                  <?php if (!empty($site['template_hint'])): ?>
                    <small class="template-hint"><?= htmlspecialchars((string)$site['template_hint']) ?></small>
                  <?php endif; ?>
                </div>
              </td>
              <td class="table-actions" data-label="Acoes">
                <div class="table-actions-inner">
                  <?php if (!empty($site['local_managed']) && empty($site['local_available'])): ?>
                    <button class="button" type="button" disabled title="Site local indisponivel">
                      Indisponivel
                    </button>
                  <?php else: ?>
                    <a class="button" href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener noreferrer">
                      Abrir
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="table-empty" colspan="5">Nenhum site encontrado para este filtro.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
    $pagination = $pagination ?? [];
    $page = (int)($pagination['page'] ?? 1);
    $totalPages = (int)($pagination['totalPages'] ?? 1);
    $total = (int)($pagination['total'] ?? 0);
    $baseParams = [];
    if ($searchQuery !== '') {
      $baseParams['q'] = $searchQuery;
    }
    if ($visibility !== 'all') {
      $baseParams['visibility'] = $visibility;
    }
    if ($templateFilter !== 'all') {
      $baseParams['template'] = $templateFilter;
    }
    $buildPageUrl = function (int $targetPage) use ($baseParams, $url): string {
      $params = $baseParams;
      $params['page'] = $targetPage;
      return $url('/?') . http_build_query($params);
    };
  ?>
  <div class="pagination">
    <div class="pagination-info">
      Pagina <?= $page ?> de <?= $totalPages ?> • <?= $total ?> sites no total
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="pagination-actions">
        <?php if ($page > 1): ?>
          <a class="button" href="<?= htmlspecialchars($buildPageUrl(1)) ?>">Primeira</a>
        <?php endif; ?>
        <?php if ($page > 1): ?>
          <a class="button" href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="button" href="<?= htmlspecialchars($buildPageUrl($page + 1)) ?>">Proxima</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="button" href="<?= htmlspecialchars($buildPageUrl($totalPages)) ?>">Ultima</a>
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
    <a class="link" href="<?= htmlspecialchars($url('/about')) ?>">Ver detalhes</a>
  </div>
</main>
