(function () {
  const toggle = document.querySelector('.nav-toggle');
  const topbar = document.querySelector('.topbar');
  const topnav = document.getElementById('topnav');
  const closeNav = () => {
    if (!topbar || !toggle) return;
    topbar.classList.remove('nav-open');
    toggle.setAttribute('aria-expanded', 'false');
  };
  if (toggle && topbar) {
    toggle.addEventListener('click', () => {
      const open = topbar.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeNav();
      }
    });

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Node)) return;
      if (!topbar.contains(event.target)) {
        closeNav();
      }
    });

    if (topnav) {
      topnav.addEventListener('click', (event) => {
        const link = event.target;
        if (link instanceof HTMLElement && link.closest('a')) {
          closeNav();
        }
      });
    }
  }

  const root = document.documentElement;
  const themeToggle = document.querySelector('[data-theme-toggle]');
  const readTheme = () => {
    const attr = root.getAttribute('data-theme');
    if (attr === 'light' || attr === 'dark') {
      return attr;
    }
    return 'dark';
  };
  const applyTheme = (theme) => {
    const next = theme === 'light' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    if (themeToggle instanceof HTMLButtonElement) {
      themeToggle.setAttribute('aria-pressed', next === 'light' ? 'true' : 'false');
      themeToggle.setAttribute('title', next === 'light' ? 'Usando tema claro' : 'Usando tema escuro');
    }
    try {
      localStorage.setItem('labs-theme', next);
    } catch (e) {
      // ignore storage failures
    }
  };

  if (themeToggle instanceof HTMLButtonElement) {
    applyTheme(readTheme());
    themeToggle.addEventListener('click', () => {
      const current = readTheme();
      applyTheme(current === 'light' ? 'dark' : 'light');
    });
  }

  const addBtn = document.querySelector('[data-add-row]');
  const list = document.querySelector('[data-sites-list]');
  const template = document.querySelector('[data-site-template]');
  const adminForm = document.querySelector('form[data-admin-save-form]');
  const templateCatalog = Array.isArray(window.LABS_TEMPLATE_CATALOG) ? window.LABS_TEMPLATE_CATALOG : [];
  const templatePreviewRoot = document.querySelector('[data-template-preview]');
  const templatePreviewImage = document.querySelector('[data-template-preview-image]');
  const templatePreviewLabel = document.querySelector('[data-template-preview-label]');
  const templatePreviewDescription = document.querySelector('[data-template-preview-description]');
  const templatePreviewId = document.querySelector('[data-template-preview-id]');

  if (!addBtn || !list || !template) {
    return;
  }

  if (adminForm instanceof HTMLFormElement) {
    adminForm.addEventListener('submit', (event) => {
      const submitter = event.submitter;
      if (submitter instanceof HTMLElement) {
        const formAction = (submitter.getAttribute('formaction') || '').trim();
        if (formAction.endsWith('/admin/remove')) {
          const row = submitter.closest('[data-site-row]');
          const nameInput = row ? row.querySelector('input[name="sites[name][]"]') : null;
          const urlInput = row ? row.querySelector('input[name="sites[url][]"]') : null;
          const name = nameInput instanceof HTMLInputElement ? nameInput.value.trim() : '';
          const url = urlInput instanceof HTMLInputElement ? urlInput.value.trim() : '';
          let targetLabel = 'este site';
          if (name !== '' && url !== '') {
            targetLabel = `"${name}" (${url})`;
          } else if (name !== '') {
            targetLabel = `"${name}"`;
          } else if (url !== '') {
            targetLabel = url;
          }
          if (!confirm(`Deseja realmente remover ${targetLabel}?\n\nA remocao sera aplicada imediatamente.`)) {
            event.preventDefault();
          }
          return;
        }
      }

      if (!confirm('Deseja salvar as alteracoes do painel?')) {
        event.preventDefault();
      }
    });
  }

  const templateById = (id) => templateCatalog.find((item) => item && item.id === id) || null;

  const renderTemplatePreview = (id) => {
    if (!templatePreviewRoot || !templatePreviewLabel || !templatePreviewDescription || !templatePreviewId) {
      return;
    }
    const tpl = templateById(id) || templateCatalog[0] || null;
    if (!tpl) {
      templatePreviewLabel.textContent = 'Template';
      templatePreviewDescription.textContent = 'Nenhum template encontrado.';
      templatePreviewId.textContent = '-';
      if (templatePreviewImage) {
        templatePreviewImage.hidden = true;
        templatePreviewImage.removeAttribute('src');
      }
      return;
    }
    templatePreviewLabel.textContent = tpl.label || tpl.id || 'Template';
    templatePreviewDescription.textContent = tpl.description || 'Template base para provisionamento de sites.';
    templatePreviewId.textContent = tpl.id || '-';
    if (templatePreviewImage) {
      if (tpl.preview_url) {
        templatePreviewImage.src = tpl.preview_url;
        templatePreviewImage.hidden = false;
      } else {
        templatePreviewImage.hidden = true;
        templatePreviewImage.removeAttribute('src');
      }
    }
  };

  const syncDescriptionTitle = (row) => {
    const descriptionInput = row.querySelector('input[name="sites[description][]"]');
    if (!(descriptionInput instanceof HTMLInputElement)) {
      return;
    }
    const value = descriptionInput.value.trim();
    if (value === '') {
      descriptionInput.removeAttribute('title');
      return;
    }
    descriptionInput.setAttribute('title', value);
  };

  const addRow = () => {
    const clone = template.content.cloneNode(true);
    list.appendChild(clone);
    const rows = list.querySelectorAll('[data-site-row]');
    const newRow = rows[rows.length - 1];
    if (newRow instanceof HTMLElement) {
      syncLink(newRow);
      syncDescriptionTitle(newRow);
      const templateSelect = newRow.querySelector('select[name="sites[template][]"]');
      if (templateSelect instanceof HTMLSelectElement) {
        renderTemplatePreview(templateSelect.value);
      }
      const firstInput = newRow.querySelector('input[name="sites[name][]"]');
      if (firstInput instanceof HTMLInputElement) {
        firstInput.focus();
      }
    }
  };

  const markDirty = (row) => {
    if (!(row instanceof HTMLElement)) return;
    row.classList.add('row-dirty');
  };

  const syncLink = (row) => {
    const urlInput = row.querySelector('input[name="sites[url][]"]');
    const link = row.querySelector('a.button');
    const reprovision = row.querySelector('[data-reprovision]');
    if (!urlInput || !link) {
      return;
    }
    const value = urlInput.value.trim();
    if (value === '') {
      link.setAttribute('href', '#');
      link.setAttribute('aria-disabled', 'true');
      if (reprovision) {
        reprovision.setAttribute('aria-disabled', 'true');
      }
      return;
    }
    link.setAttribute('href', value);
    link.removeAttribute('aria-disabled');
    if (reprovision) {
      reprovision.removeAttribute('aria-disabled');
    }
  };

  addBtn.addEventListener('click', (event) => {
    event.preventDefault();
    addRow();
  });

  list.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const removeButton = target.closest('[data-remove-row]');
    const reprovisionButton = target.closest('[data-reprovision]');
    if (target.matches('input[name="sites[protected][]"]')) {
      return;
    }
    if (removeButton instanceof HTMLElement) {
      event.preventDefault();
      const row = removeButton.closest('[data-site-row]');
      if (row) {
        const nameInput = row.querySelector('input[name="sites[name][]"]');
        const urlInput = row.querySelector('input[name="sites[url][]"]');
        const name = nameInput instanceof HTMLInputElement ? nameInput.value.trim() : '';
        const url = urlInput instanceof HTMLInputElement ? urlInput.value.trim() : '';
        let targetLabel = 'este site';
        if (name !== '' && url !== '') {
          targetLabel = `"${name}" (${url})`;
        } else if (name !== '') {
          targetLabel = `"${name}"`;
        } else if (url !== '') {
          targetLabel = url;
        }
        if (!confirm(`Deseja realmente remover ${targetLabel}?\n\nA remocao so sera aplicada apos clicar em Salvar.`)) {
          return;
        }
        row.remove();
      }
    }
    if (reprovisionButton instanceof HTMLElement) {
      event.preventDefault();
      if (reprovisionButton.getAttribute('aria-disabled') === 'true') {
        return;
      }
      const row = reprovisionButton.closest('[data-site-row]');
      if (!row) {
        return;
      }
      const nameInput = row.querySelector('input[name="sites[name][]"]');
      const urlInput = row.querySelector('input[name="sites[url][]"]');
      const templateInput = row.querySelector('select[name="sites[template][]"]');
      const protectedInput = row.querySelector('input[name="sites[protected][]"]');
      const name = nameInput ? nameInput.value.trim() : '';
      const url = urlInput ? urlInput.value.trim() : '';
      const template = templateInput ? templateInput.value.trim() : '';
      const isProtected = protectedInput ? protectedInput.checked : false;
      if (isProtected) {
        alert('Este site esta protegido contra reprovisionamento.');
        return;
      }
      if (!url) {
        alert('Informe a URL para reprovisionar.');
        return;
      }
      if (!confirm('Reprovisionar e sobrescrever o template deste site?')) {
        return;
      }
      const form = document.getElementById('reprovision-form');
      if (!form) {
        return;
      }
      form.querySelector('input[name="name"]').value = name;
      form.querySelector('input[name="url"]').value = url;
      form.querySelector('input[name="template"]').value = template;
      form.submit();
    }
  });

  list.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.name === 'sites[url][]') {
      const row = target.closest('[data-site-row]');
      if (row) {
        syncLink(row);
        markDirty(row);
      }
    }
    if (target.name === 'sites[name][]' || target.name === 'sites[description][]') {
      const row = target.closest('[data-site-row]');
      if (row) {
        markDirty(row);
        if (target.name === 'sites[description][]') {
          syncDescriptionTitle(row);
        }
      }
    }
  });

  list.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) {
      return;
    }
    if (target.name === 'sites[protected][]' || target.name === 'sites[template][]') {
      const row = target.closest('[data-site-row]');
      if (row) {
        markDirty(row);
      }
      if (target.name === 'sites[template][]') {
        renderTemplatePreview(target.value);
      }
    }
  });

  list.addEventListener('focusin', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement)) {
      return;
    }
    if (target.name === 'sites[template][]') {
      renderTemplatePreview(target.value);
    }
  });

  list.querySelectorAll('[data-site-row]').forEach((row) => {
    syncLink(row);
    syncDescriptionTitle(row);
  });

  const initialTemplateSelect = list.querySelector('select[name="sites[template][]"]');
  renderTemplatePreview(initialTemplateSelect instanceof HTMLSelectElement ? initialTemplateSelect.value : '');
})();
