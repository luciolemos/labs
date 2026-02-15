<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Painel') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css" />
  <script defer src="/assets/js/app.js"></script>
</head>
<body>
  <?php
    $auth = $auth ?? ['logged' => false];
    $meta = $meta ?? [];
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $links = [
      ['href' => '/', 'label' => 'Painel'],
      ['href' => '/admin', 'label' => 'Admin'],
      ['href' => '/about', 'label' => 'Sobre'],
    ];
  ?>
  <div class="page">
    <header class="topbar">
      <div class="brand">
        <img class="brand-logo" src="/assets/img/natalcode/natalcode_icon_symbol_white.png" alt="NatalCode">
        <div class="brand-text">
          <strong>Labs</strong>
          <span>Painel de Sites</span>
        </div>
      </div>
      <nav class="topnav" id="topnav">
        <?php foreach ($links as $link): ?>
          <?php $active = $current === $link['href']; ?>
          <a class="<?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars($link['href']) ?>">
            <?= htmlspecialchars($link['label']) ?>
          </a>
        <?php endforeach; ?>
        <?php if (empty($auth['logged'])): ?>
          <a class="cta" href="/login">Login</a>
        <?php else: ?>
          <a class="cta" href="/logout">Sair</a>
        <?php endif; ?>
      </nav>
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
