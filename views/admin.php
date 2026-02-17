<main class="panel">
  <div class="header">
    <div>
      <h1>Admin</h1>
      <div class="subtitle">Edite os sites sem mexer em codigo</div>
    </div>
  </div>

  <?php if (!empty($saved)): ?>
    <div class="alert alert-success" role="status" aria-live="polite">Sites atualizados com sucesso.</div>
  <?php endif; ?>

  <?php if (!empty($provision)): ?>
    <div class="alert" role="status" aria-live="polite">
      <?php foreach ($provision as $item): ?>
        <div>
          <?= htmlspecialchars($item['slug']) ?>:
          <?= htmlspecialchars($item['status']) ?>
          <?= htmlspecialchars($item['message'] ?? '') ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert" role="alert" aria-live="assertive">Existem erros no formulario. Corrija e tente novamente.</div>
  <?php endif; ?>

  <form class="form" method="post" action="/admin/save">
    <div class="help">Adicione ou remova linhas. As URLs precisam ser validas.</div>

    <div data-sites-list class="site-list">
      <div class="form-row form-row-header" aria-hidden="true">
        <div class="header-cell">Nome</div>
        <div class="header-cell">Descricao</div>
        <div class="header-cell">URL</div>
        <div class="header-cell">Protegido</div>
        <div class="header-cell">Acoes</div>
      </div>
      <?php foreach (($sites ?? []) as $index => $site): ?>
        <?php
          $slug = '';
          if (!empty($site['url'])) {
            $path = parse_url($site['url'], PHP_URL_PATH);
            $slug = trim((string)$path, '/');
            if (strpos($slug, '/') !== false) {
              $slug = explode('/', $slug)[0];
            }
          }
          $provisionStatus = $provisionBySlug[$slug] ?? null;
        ?>
        <div class="form-row" data-site-row>
          <label class="sr-only" for="site-name-<?= (int)$index ?>">Nome do site</label>
          <input class="input" id="site-name-<?= (int)$index ?>" name="sites[name][]" placeholder="Nome" aria-label="Nome do site" value="<?= htmlspecialchars($site['name'] ?? '') ?>" />
          <label class="sr-only" for="site-description-<?= (int)$index ?>">Descricao do site</label>
          <input class="input" id="site-description-<?= (int)$index ?>" name="sites[description][]" placeholder="Descricao" aria-label="Descricao do site" value="<?= htmlspecialchars($site['description'] ?? '') ?>" />
          <label class="sr-only" for="site-url-<?= (int)$index ?>">URL do site</label>
          <input class="input" id="site-url-<?= (int)$index ?>" name="sites[url][]" placeholder="URL" aria-label="URL do site" value="<?= htmlspecialchars($site['url'] ?? '') ?>" />
          <?php $urlValue = trim((string)($site['url'] ?? '')); ?>
          <label class="checkbox" for="site-protected-<?= (int)$index ?>" aria-label="Protegido">
            <span class="sr-only">Site protegido contra reprovisionamento</span>
            <input id="site-protected-<?= (int)$index ?>" type="checkbox" name="sites[protected][]" value="1" <?= !empty($site['protected']) ? 'checked' : '' ?> />
          </label>
          <div class="actions">
            <a class="button"
               href="<?= htmlspecialchars($urlValue !== '' ? $urlValue : '#') ?>"
               target="_blank"
               rel="noopener noreferrer"
               <?= $urlValue === '' ? 'aria-disabled="true"' : '' ?>>
              Abrir
            </a>
            <button class="button" type="button" data-reprovision <?= $urlValue === '' ? 'aria-disabled="true"' : '' ?>>Reprovisionar</button>
            <button class="button button-danger" type="button" data-remove-row>Remover</button>
          </div>
        </div>
        <?php if (!empty($provisionStatus)): ?>
          <div class="help">
            Status: <?= htmlspecialchars($provisionStatus['status'] ?? '') ?>
            <?= htmlspecialchars($provisionStatus['message'] ?? '') ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($errors[$index])): ?>
          <div class="help" style="color: var(--danger);" role="alert">
            <?= htmlspecialchars(implode(' ', $errors[$index])) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <template data-site-template>
      <div class="form-row" data-site-row>
        <input class="input" name="sites[name][]" placeholder="Nome" aria-label="Nome do site" value="" />
        <input class="input" name="sites[description][]" placeholder="Descricao" aria-label="Descricao do site" value="" />
        <input class="input" name="sites[url][]" placeholder="URL" aria-label="URL do site" value="" />
        <label class="checkbox" aria-label="Protegido">
          <span class="sr-only">Site protegido contra reprovisionamento</span>
          <input type="checkbox" name="sites[protected][]" value="1" />
        </label>
        <div class="actions">
          <a class="button" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true">Abrir</a>
          <button class="button" type="button" data-reprovision aria-disabled="true">Reprovisionar</button>
          <button class="button button-danger" type="button" data-remove-row>Remover</button>
        </div>
      </div>
    </template>

    <div class="form-actions">
      <button class="button" type="button" data-add-row>Adicionar linha</button>
      <button class="button" type="submit">Salvar</button>
    </div>
  </form>

  <form id="reprovision-form" method="post" action="/admin/reprovision" style="display:none;">
    <input type="hidden" name="name" value="" />
    <input type="hidden" name="url" value="" />
  </form>
</main>
