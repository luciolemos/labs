<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Painel') ?></title>
  <script>
    (function () {
      try {
        var saved = localStorage.getItem('labs-theme');
        var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
        var theme = (saved === 'light' || saved === 'dark') ? saved : (prefersLight ? 'light' : 'dark');
        document.documentElement.setAttribute('data-theme', theme);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php
    $basePath = rtrim((string)($basePath ?? ''), '/');
    if ($basePath === '/') {
      $basePath = '';
    }
    $url = static function (string $path) use ($basePath): string {
      return $basePath . $path;
    };
  ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($url('/assets/css/app.css')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/public/assets/css/app.css') ?: 1) ?>" />
  <script defer src="<?= htmlspecialchars($url('/assets/js/app.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/public/assets/js/app.js') ?: 1) ?>"></script>
</head>
<body>
  <?php
    $auth = $auth ?? ['logged' => false];
    $meta = $meta ?? [];
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($basePath !== '' && str_starts_with($current, $basePath)) {
      $current = substr($current, strlen($basePath)) ?: '/';
    }
    $links = [
      ['href' => '/', 'label' => 'Painel'],
      ['href' => '/admin', 'label' => 'Admin'],
      ['href' => '/readme', 'label' => 'README'],
      ['href' => '/about', 'label' => 'Sobre'],
    ];
  ?>
  <div class="page">
    <header class="topbar">
      <div class="brand">
        <img class="brand-logo brand-logo-dark" src="<?= htmlspecialchars($url('/assets/img/brands/white.png')) ?>" alt="Labs">
        <img class="brand-logo brand-logo-light" src="<?= htmlspecialchars($url('/assets/img/brands/black.png')) ?>" alt="Labs">
        <div class="brand-text">
          <strong>Labs</strong>
          <span>Painel de Sites</span>
        </div>
      </div>
      <nav class="topnav" id="topnav">
        <?php foreach ($links as $link): ?>
          <?php $active = $current === $link['href']; ?>
          <a class="<?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars($url($link['href'])) ?>">
            <?= htmlspecialchars($link['label']) ?>
          </a>
        <?php endforeach; ?>
        <?php if (empty($auth['logged'])): ?>
          <a class="cta" href="<?= htmlspecialchars($url('/login')) ?>">Login</a>
        <?php else: ?>
          <a class="cta" href="<?= htmlspecialchars($url('/logout')) ?>">Sair</a>
        <?php endif; ?>
      </nav>
      <button
        class="theme-toggle"
        type="button"
        aria-label="Alternar tema claro e escuro"
        title="Alternar tema"
        data-theme-toggle
      >
        <span class="theme-toggle-ico theme-toggle-ico-sun" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.5"/><path d="M12 19v2.5"/><path d="M4.9 4.9l1.8 1.8"/><path d="M17.3 17.3l1.8 1.8"/><path d="M2.5 12H5"/><path d="M19 12h2.5"/><path d="M4.9 19.1l1.8-1.8"/><path d="M17.3 6.7l1.8-1.8"/></svg>
        </span>
        <span class="theme-toggle-ico theme-toggle-ico-moon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M20.2 14.4a8.5 8.5 0 1 1-10.6-10.6 7 7 0 1 0 10.6 10.6z"/></svg>
        </span>
      </button>
      <button class="nav-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="topnav">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </header>

    <main class="content">
      <?= $content ?? '' ?>
    </main>

    <footer class="footer">
      <div>
        <strong>Labs</strong> • Painel de Sites
      </div>
      <div class="footer-meta">
        <span>PHP <?= htmlspecialchars(PHP_VERSION) ?></span>
        <span>Ambiente: <?= htmlspecialchars($meta['env'] ?? 'production') ?></span>
      </div>
    </footer>
  </div>
</body>
</html>
