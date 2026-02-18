<?php
$basePath = rtrim((string)($basePath ?? ''), '/');
$url = static fn(string $path): string => $basePath . $path;
?>
<main class="panel">
  <div class="header">
    <div>
      <h1>Admin</h1>
      <div class="subtitle">Edite os sites sem mexer em codigo</div>
    </div>
  </div>

  <?php if (!empty($saved)): ?>
    <div class="alert alert-success" role="status" aria-live="polite">
      <?= htmlspecialchars((string)($savedMessage ?? 'Sites atualizados com sucesso.')) ?>
    </div>
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
    <div class="alert" role="alert" aria-live="assertive">
      <div>Existem erros no formulario. Corrija e tente novamente.</div>
      <?php
        $errorMessages = [];
        foreach ($errors as $rowErrors) {
          if (!is_array($rowErrors)) {
            continue;
          }
          foreach ($rowErrors as $message) {
            $msg = trim((string)$message);
            if ($msg !== '') {
              $errorMessages[] = $msg;
            }
          }
        }
        $errorMessages = array_values(array_unique($errorMessages));
      ?>
      <?php if (!empty($errorMessages)): ?>
        <ul>
          <?php foreach ($errorMessages as $message): ?>
            <li><?= htmlspecialchars($message) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (!empty($permissionHelp) && is_array($permissionHelp)): ?>
        <div class="help" style="margin-top: 8px;">
          <div><strong><?= htmlspecialchars((string)($permissionHelp['title'] ?? '')) ?></strong></div>
          <?php if (!empty($permissionHelp['commands']) && is_array($permissionHelp['commands'])): ?>
            <?php foreach ($permissionHelp['commands'] as $command): ?>
              <code><?= htmlspecialchars((string)$command) ?></code><br />
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($permissionHelp['fallback'])): ?>
            <div style="margin-top: 6px;">Provisionamento manual:</div>
            <code><?= htmlspecialchars((string)$permissionHelp['fallback']) ?></code>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="<?= htmlspecialchars($url('/admin/save')) ?>" data-admin-save-form>
    <div class="help">Adicione ou remova linhas. As URLs precisam ser validas. Sites com URL preenchida podem ser removidos imediatamente.</div>
    <section class="template-preview" data-template-preview>
      <div class="template-preview-head">
        <strong>Preview do template</strong>
        <span class="help">Atualiza ao trocar o template em qualquer linha.</span>
      </div>
      <div class="template-preview-body">
        <img class="template-preview-image" data-template-preview-image alt="Preview do template" hidden />
        <div class="template-preview-copy">
          <div class="template-preview-label" data-template-preview-label>Template</div>
          <p class="template-preview-description" data-template-preview-description></p>
          <code class="template-preview-id" data-template-preview-id></code>
        </div>
      </div>
    </section>

    <div data-sites-list class="site-list">
      <div class="form-row form-row-header" aria-hidden="true">
        <div class="header-cell">Nome</div>
        <div class="header-cell">Descricao</div>
        <div class="header-cell">URL</div>
        <div class="header-cell">Template</div>
        <div class="header-cell">Em uso</div>
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
          <input class="input" id="site-description-<?= (int)$index ?>" name="sites[description][]" placeholder="Descricao" aria-label="Descricao do site" title="<?= htmlspecialchars((string)($site['description'] ?? '')) ?>" value="<?= htmlspecialchars($site['description'] ?? '') ?>" />
          <label class="sr-only" for="site-url-<?= (int)$index ?>">URL do site</label>
          <input class="input" id="site-url-<?= (int)$index ?>" name="sites[url][]" placeholder="URL" aria-label="URL do site" value="<?= htmlspecialchars($site['url'] ?? '') ?>" />
          <label class="sr-only" for="site-template-<?= (int)$index ?>">Template do site</label>
          <select class="input" id="site-template-<?= (int)$index ?>" name="sites[template][]" aria-label="Template do site">
            <?php foreach (($templates ?? []) as $tpl): ?>
              <?php $tplId = (string)($tpl['id'] ?? ''); ?>
              <option value="<?= htmlspecialchars($tplId) ?>" <?= ((string)($site['template'] ?? ($templateDefault ?? '')) === $tplId) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)($tpl['label'] ?? $tplId)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php
            $usageKind = (string)($site['template_usage_kind'] ?? 'managed');
            $usageClass = $usageKind === 'legacy' ? 'template-pill-legacy' : ($usageKind === 'pending' ? 'template-pill-pending' : 'template-pill-managed');
          ?>
          <div class="template-cell" data-label="Em uso">
            <span class="template-pill <?= $usageClass ?>">
              <?= htmlspecialchars((string)($site['template_usage_label'] ?? 'Template')) ?>
            </span>
            <?php if (!empty($site['template_usage_hint'])): ?>
              <small class="template-hint"><?= htmlspecialchars((string)$site['template_usage_hint']) ?></small>
            <?php endif; ?>
          </div>
          <?php $urlValue = trim((string)($site['url'] ?? '')); ?>
          <label class="checkbox" for="site-protected-<?= (int)$index ?>" aria-label="Protegido">
            <span class="sr-only">Site protegido contra reprovisionamento</span>
            <input id="site-protected-<?= (int)$index ?>" type="checkbox" name="sites[protected][]" value="1" <?= !empty($site['protected']) ? 'checked' : '' ?> />
          </label>
          <div class="actions">
            <a class="button icon-only"
               href="<?= htmlspecialchars($urlValue !== '' ? $urlValue : '#') ?>"
               target="_blank"
               rel="noopener noreferrer"
               title="Abrir site em nova aba"
               aria-label="Abrir site em nova aba"
               <?= $urlValue === '' ? 'aria-disabled="true"' : '' ?>>
              <span class="btn-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
              </span>
            </a>
            <button class="button icon-only" type="button" data-reprovision title="Reprovisionar site com template selecionado" aria-label="Reprovisionar site com template selecionado" <?= $urlValue === '' ? 'aria-disabled="true"' : '' ?>>
              <span class="btn-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 15-6"/><path d="M18 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6"/><path d="M6 21v-5h5"/></svg>
              </span>
            </button>
            <button
              class="button button-danger icon-only"
              type="submit"
              formmethod="post"
              formaction="<?= htmlspecialchars($url('/admin/remove')) ?>"
              formnovalidate
              name="remove_index"
              value="<?= (int)$index ?>"
              data-remove-submit
              title="Remover site"
              aria-label="Remover site">
              <span class="btn-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              </span>
            </button>
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
        <input class="input" name="sites[description][]" placeholder="Descricao" aria-label="Descricao do site" title="" value="" />
        <input class="input" name="sites[url][]" placeholder="URL" aria-label="URL do site" value="" />
        <select class="input" name="sites[template][]" aria-label="Template do site">
          <?php foreach (($templates ?? []) as $tpl): ?>
            <?php $tplId = (string)($tpl['id'] ?? ''); ?>
            <option value="<?= htmlspecialchars($tplId) ?>" <?= ((string)($templateDefault ?? '') === $tplId) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($tpl['label'] ?? $tplId)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="template-cell">
          <span class="template-pill template-pill-pending">Novo (ao salvar)</span>
          <small class="template-hint">-</small>
        </div>
        <label class="checkbox" aria-label="Protegido">
          <span class="sr-only">Site protegido contra reprovisionamento</span>
          <input type="checkbox" name="sites[protected][]" value="1" />
        </label>
        <div class="actions">
          <a class="button icon-only" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true" title="Abrir site em nova aba" aria-label="Abrir site em nova aba">
            <span class="btn-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
            </span>
          </a>
          <button class="button icon-only" type="button" data-reprovision aria-disabled="true" title="Reprovisionar site com template selecionado" aria-label="Reprovisionar site com template selecionado">
            <span class="btn-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 15-6"/><path d="M18 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6"/><path d="M6 21v-5h5"/></svg>
            </span>
          </button>
          <button class="button button-danger icon-only" type="button" data-remove-row title="Remover linha do formulario" aria-label="Remover linha do formulario">
            <span class="btn-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </span>
          </button>
        </div>
      </div>
    </template>

    <div class="form-actions">
      <button class="button icon-only" type="button" data-add-row title="Adicionar nova linha de site" aria-label="Adicionar nova linha de site">
        <span class="btn-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        </span>
      </button>
      <button class="button icon-only" type="submit" title="Salvar alteracoes de sites" aria-label="Salvar alteracoes de sites">
        <span class="btn-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M20 7v13H4V4h12z"/><path d="M8 4v6h8"/><path d="M8 20v-6h8v6"/></svg>
        </span>
      </button>
    </div>
  </form>

  <form id="reprovision-form" method="post" action="<?= htmlspecialchars($url('/admin/reprovision')) ?>" style="display:none;">
    <input type="hidden" name="name" value="" />
    <input type="hidden" name="url" value="" />
    <input type="hidden" name="template" value="" />
  </form>
  <form id="remove-site-form" method="post" action="<?= htmlspecialchars($url('/admin/remove')) ?>" style="display:none;">
    <input type="hidden" name="url" value="" />
  </form>
  <script>
    window.LABS_TEMPLATE_CATALOG = <?= json_encode($templates ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>
</main>
