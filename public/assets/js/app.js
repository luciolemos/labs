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

  const addBtn = document.querySelector('[data-add-row]');
  const list = document.querySelector('[data-sites-list]');
  const template = document.querySelector('[data-site-template]');

  if (!addBtn || !list || !template) {
    return;
  }

  const addRow = () => {
    const clone = template.content.cloneNode(true);
    list.appendChild(clone);
    const rows = list.querySelectorAll('[data-site-row]');
    const newRow = rows[rows.length - 1];
    if (newRow instanceof HTMLElement) {
      syncLink(newRow);
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
    if (target.matches('input[name="sites[protected][]"]')) {
      return;
    }
    if (target.matches('[data-remove-row]')) {
      event.preventDefault();
      const row = target.closest('[data-site-row]');
      if (row) {
        if (!confirm('Remover esta linha do formulario?')) {
          return;
        }
        row.remove();
      }
    }
    if (target.matches('[data-reprovision]')) {
      event.preventDefault();
      if (target.getAttribute('aria-disabled') === 'true') {
        return;
      }
      const row = target.closest('[data-site-row]');
      if (!row) {
        return;
      }
      const nameInput = row.querySelector('input[name="sites[name][]"]');
      const urlInput = row.querySelector('input[name="sites[url][]"]');
      const protectedInput = row.querySelector('input[name="sites[protected][]"]');
      const name = nameInput ? nameInput.value.trim() : '';
      const url = urlInput ? urlInput.value.trim() : '';
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
      }
    }
  });

  list.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.name === 'sites[protected][]') {
      const row = target.closest('[data-site-row]');
      if (row) {
        markDirty(row);
      }
    }
  });

  list.querySelectorAll('[data-site-row]').forEach((row) => {
    syncLink(row);
  });
})();
