# Labs (Painel de Sites) - README

Este projeto `labs` e o painel escolar que gera sites estaticos/semidinamicos em `/var/www/<slug>` a partir de um template predefinido. Ele roda em `http://88.198.104.148/` e organiza os sites por alias do Apache (sem vhost individual, exceto `site1`).

**Sumario**
- [Estrutura do projeto labs](#estrutura-do-projeto-labs)
- [Template do site gerado (estrutura alvo)](#template-do-site-gerado-estrutura-alvo)
- [Como o painel (labs) funciona](#como-o-painel-labs-funciona)
- [Uso do .htaccess no labs](#uso-do-htaccess-no-labs)
- [Env do painel](#env-do-painel)
- [Composer do painel](#composer-do-painel)
- [Script de provisionamento](#script-de-provisionamento)
- [Apache aliases](#apache-aliases)
- [Passo a passo (resumo)](#passo-a-passo-resumo)
- [Ferramentas usadas](#ferramentas-usadas)
- [Notas didaticas](#notas-didaticas)

**Finalidade (escolar)**
- Demonstrar fluxo completo: painel -> provisionamento -> Apache alias -> site pronto.
- Exercitar Slim (backend do painel), Twig (template do site gerado), e boas praticas de estrutura.

**Tecnologias**
- Painel: PHP 8.2+, Slim 4, PSR-7
- Sites gerados: PHP 8.3+, Twig 3, Router basico, Bootstrap 5.3, AOS
- Servidor: Apache + alias em `site-paths.conf`

---

## Estrutura do projeto `labs`

```
/var/www/labs
├─ bin/
│  └─ provision-site
├─ composer.json
├─ composer.lock
├─ public/
├─ src/
├─ storage/
├─ vendor/
├─ views/
└─ .env
```

---

## Template do site gerado (estrutura alvo)

Quando o painel chama o script `bin/provision-site`, ele cria um site com a seguinte estrutura (exemplo `/var/www/siteX`):

```
/var/www/<slug>
├─ composer.json
├─ .env
├─ vendor/
├─ routes/
│  └─ web.php
├─ src/
│  ├─ Core/
│  │  ├─ Env.php
│  │  └─ Router.php
│  └─ Controllers/
│     └─ HomeController.php
├─ views/
│  ├─ base.twig
│  ├─ pages/
│  │  └─ home.twig
│  └─ partials/
│     ├─ navbar.twig
│     └─ footer.twig
├─ storage/
│  ├─ cache/twig/
│  └─ logs/
└─ public/
   ├─ index.php
   ├─ .htaccess
   └─ assets/
      ├─ css/
      │  ├─ app.css
      │  └─ landing.css
      ├─ js/
      │  └─ landing.js
      └─ img/
```

## Resumo do template
- **Twig** renderiza `views/pages/home.twig` usando `views/base.twig` e partials (`navbar.twig`, `footer.twig`).
- **Router basico** em `src/Core/Router.php` com uma rota `GET /`.
- **Front controller** em `public/index.php` carrega `.env`, Twig, e despacha a rota.
- **Assets** em `public/assets/...` para CSS/JS da landing.
- **Git automático** no primeiro provisionamento: cria repositório, branch `main`, commit inicial e (quando `gh` estiver autenticado) publica no GitHub.

---

## Como o painel (`labs`) funciona

1. O painel (Slim) recebe o `slug` do site a ser criado (ex.: `agencia`).
2. O script `/var/www/labs/bin/provision-site` cria o site em `/var/www/<slug>`.
3. O script grava o alias no Apache em `site-paths.conf`.
4. Apache serve o site em `http://88.198.104.148/<slug>/`.

---

## Uso do `.htaccess` no `labs`

No ambiente atual, o arquivo `/var/www/labs/public/.htaccess` esta ativo e e aplicado para o painel em `http://88.198.104.148/`.

Motivos tecnicos:
- O VirtualHost de `labs` usa `DocumentRoot /var/www/labs/public`.
- O bloco `<Directory /var/www/labs/public>` esta com `AllowOverride All`, permitindo leitura de `.htaccess`.
- O modulo `mod_rewrite` esta habilitado (`rewrite.load` em `mods-enabled`).

Com isso, as regras de front controller do Slim em `public/.htaccess` sao usadas normalmente.

---

## Env do painel

```ini
APP_ENV=local
APP_NAME=Painel de Sites
APP_DEBUG=true
APP_URL=http://88.198.104.148
ADMIN_USER=admin
ADMIN_PASS_HASH=$2y$12$v9xsh7TTMgmSR3j0r2M0muCuW0fJTmeUjaov.vtKY7UlLFcuxCnvC
ADMIN_SESSION_MINUTES=60
ADMIN_PROVISION=true
ADMIN_PROVISION_HOST=88.198.104.148
ADMIN_PROVISION_BASE=/var/www
ADMIN_APACHE_CONF=/etc/apache2/conf-available/site-paths.conf
ADMIN_APACHE_CONF_NAME=site-paths
```

Pontos importantes:
- `ADMIN_APACHE_CONF` aponta para `site-paths.conf`.
- `ADMIN_PROVISION=true` habilita a criacao automatica.

---

## Composer do painel

```json
{
  "name": "labs/painel",
  "description": "Painel simples em Slim MVC",
  "type": "project",
  "require": {
    "php": "^8.2",
    "slim/slim": "^4.12",
    "slim/psr7": "^1.7"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

Isso confirma que o painel e um app Slim 4.

---

## Script de provisionamento

Este e o template real. Ele **cria os arquivos** do site, injeta Twig, assets, `.env`, `.htaccess` e registra o alias no Apache.

### Versionamento Git no provisionamento
- No primeiro provisionamento (quando o site ainda não tem `.git`), o script:
- Inicializa um repositório Git local.
- Define branch padrão `main`.
- Aplica `git add -A`.
- Cria commit inicial: `chore: initial scaffold`.
- Em reprovisionamento de site já versionado, o script **não recria** o repositório.
- Publicação automática no GitHub (ao fim da criação):
- Pré-requisito: `gh` instalado e autenticado (`gh auth status`).
- Se não houver `origin`, o script cria/conecta `github.com/<owner>/<slug>` e executa `git push -u origin main`.
- Variáveis de controle:
- `GITHUB_AUTO_PUBLISH=true|false` (padrão `true`)
- `GITHUB_OWNER=<usuario>` (opcional; quando vazio usa o login autenticado no `gh`)
- `GITHUB_VISIBILITY=private|public|internal` (padrão `private`)

```bash
#!/usr/bin/env bash
set -euo pipefail

slug="${1:-}"
title="${2:-}"
force="${3:-}"
if [[ -z "$slug" ]]; then
  echo "missing slug" >&2
  exit 1
fi

base="/var/www"
conf="/etc/apache2/conf-available/site-paths.conf"
conf_name="site-paths"

if [[ ! "$slug" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo "invalid slug" >&2
  exit 1
fi

dir="$base/$slug"
mkdir -p "$dir"

if ! command -v apachectl >/dev/null 2>&1; then
  echo "apachectl not found" >&2
  exit 1
fi

if ! apachectl -M 2>/dev/null | grep -q "alias_module"; then
  echo "mod_alias not enabled" >&2
  exit 1
fi

if [[ -z "$title" ]]; then
  title="$slug"
fi

if [[ "$force" == "--force" || ! -f "$dir/.site-template" ]]; then
  mkdir -p "$dir/public/assets/css" \
           "$dir/public/assets/js" \
           "$dir/public/assets/img" \
           "$dir/routes" \
           "$dir/src/Core" \
           "$dir/src/Controllers" \
           "$dir/views/pages" \
           "$dir/views/partials" \
           "$dir/storage/cache/twig" \
           "$dir/storage/logs"

  chown -R www-data:www-data "$dir/storage" || true
  chmod -R 775 "$dir/storage" || true

  cat > "$dir/composer.json" <<JSON
{
  "name": "agency/$slug",
  "type": "project",
  "require": {
    "twig/twig": "^3.0"
  },
  "autoload": {
    "psr-4": {
      "App\\\\": "src/"
    }
  }
}
JSON

  cat > "$dir/.env" <<ENV
APP_NAME="$title"
APP_MARK="A"
APP_BADGE="PHP 8.3+"
APP_PAGE_TITLE="System — PHP 8.3+ moderno, didático e profissional"
APP_BASE="/$slug"
APP_ENV="production"

ENV="production"
ENV

  cat > "$dir/routes/web.php" <<'PHP'
<?php

return [
    ['GET', '/', 'home'],
];
PHP

  cat > "$dir/src/Core/Env.php" <<'PHP'
<?php

namespace App\Core;

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"");

            if ($key === '' || isset($_ENV[$key])) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
PHP

  cat > "$dir/src/Core/Router.php" <<'PHP'
<?php

namespace App\Core;

final class Router
{
    private string $basePath;
    private array $routes = [];

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            return 'Not found';
        }

        return (string)call_user_func($handler);
    }
}
PHP

  cat > "$dir/src/Controllers/HomeController.php" <<'PHP'
<?php

namespace App\Controllers;

use Twig\Environment;

final class HomeController
{
    public function __construct(private Environment $twig, private array $config)
    {
    }

    public function index(): string
    {
        return $this->home();
    }

    public function home(): string
    {
        return $this->twig->render('pages/home.twig', [
            'app_name' => $this->config['app_name'] ?? 'Agência',
            'app_mark' => $this->config['app_mark'] ?? 'A',
            'page_title' => $this->config['page_title'] ?? null,
        ]);
    }
}
PHP

  cat > "$dir/views/base.twig" <<'TWIG'
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
  <meta charset="utf-8">
  <title>{% block title %}{{ app_name ?? 'Agência' }}{% endblock %}</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="{% block meta_description %}Landing profissional em PHP + Twig{% endblock %}">
  <meta name="theme-color" content="#0b0f19">

  {# ==============================
     FAVICON / PWA (opcional)
     ============================== #}
  <link rel="icon" href="{{ base_url }}/assets/img/favicon.png">

  {# ==============================
     CSS — LIBS
     ============================== #}

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- AOS Animate On Scroll -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  {# ==============================
     CSS — APP
     ============================== #}

  <link href="{{ base_url }}/assets/css/app.css" rel="stylesheet">
  <link href="{{ base_url }}/assets/css/landing.css" rel="stylesheet">

  {% block head_extra %}{% endblock %}
</head>


<body class="bg-body text-body antialiased">

{# ==============================
   LOADER (opcional)
   ============================== #}

<div id="pageLoader" class="page-loader">
  <div class="spinner-border text-primary"></div>
</div>


{# ==============================
   CONTEÚDO
   ============================== #}

{% block content %}{% endblock %}



{# ==============================
   SCRIPTS — LIBS
   ============================== #}

<!-- Bootstrap bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- AOS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>


{# ==============================
   CONFIG GLOBAL JS
   ============================== #}

<script>
window.APP = {
  baseUrl: "{{ base_url }}",
  env: "{{ app_env ?? 'prod' }}"
};
</script>


{# ==============================
   JS — APP CORE
   ============================== #}

<script src="{{ base_url }}/assets/js/landing.js"></script>


{% block body_extra %}{% endblock %}

</body>
</html>
TWIG

  cat > "$dir/views/partials/navbar.twig" <<'TWIG'
<nav class="navbar navbar-expand-lg navbar-dark sticky-top py-3 nav-glass">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ base_url ?? '/' }}">
      <span class="brand-mark">{{ app_mark ?? 'S' }}</span>
      <span class="fw-semibold">{{ app_name ?? 'System' }}</span>

      <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle ms-2 d-none d-sm-inline">
        {{ app_badge ?? 'PHP 8.3+' }}
      </span>
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#topNav"
            aria-controls="topNav" aria-expanded="false" aria-label="Abrir menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="#features">Recursos</a></li>
        <li class="nav-item"><a class="nav-link" href="#how">Como funciona</a></li>
        <li class="nav-item"><a class="nav-link" href="#docs">Docs</a></li>
        <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
        <li class="nav-item ms-lg-2">
          <a class="btn btn-outline-light btn-sm px-3" href="#cta">
            Ver demo <i class="bi bi-arrow-right-short"></i>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
TWIG

  cat > "$dir/views/partials/footer.twig" <<'TWIG'
<footer class="py-4 border-top border-light-subtle">
  <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
    <div class="text-secondary-emphasis small">
      © <span id="year"></span> {{ app_name ?? 'System' }}. Feito com PHP 8.3+, Twig e Bootstrap.
    </div>

    <div class="d-flex gap-3">
      {# Ajuste links conforme seu router #}
      <a class="link-light" href="{{ github_url ?? '#' }}" aria-label="GitHub">
        <i class="bi bi-github"></i>
      </a>
      <a class="link-light" href="#docs" aria-label="Docs">
        <i class="bi bi-journal-text"></i>
      </a>
      <a class="link-light" href="#cta" aria-label="Começar">
        <i class="bi bi-rocket-takeoff"></i>
      </a>
    </div>
  </div>
</footer>

<button class="back-to-top btn btn-primary shadow" id="backToTop"
        aria-label="Voltar ao topo" title="Voltar ao topo">
  <i class="bi bi-chevron-up"></i>
</button>
TWIG

  cat > "$dir/views/pages/home.twig" <<'TWIG'
{% extends 'base.twig' %}

{% block title %}{{ page_title ?? 'System — PHP 8.3+ moderno, didático e profissional' }}{% endblock %}

{% block content %}

{# Background blobs #}
<div class="bg-blobs" aria-hidden="true">
  <span class="blob blob-1"></span>
  <span class="blob blob-2"></span>
  <span class="blob blob-3"></span>
</div>

{% include 'partials/navbar.twig' %}

<header class="hero py-5 py-lg-6">
  <div class="container position-relative">
    <div class="row align-items-center g-4 g-lg-5">
      <div class="col-lg-6">
        <div class="hero-badge mb-3" data-aos="fade-up" data-aos-delay="0">
          <i class="bi bi-stars"></i>
          <span>{{ hero_badge ?? 'Open-source, didático e pronto para produção' }}</span>
        </div>

        <h1 class="display-5 fw-bold lh-sm mb-3" data-aos="fade-up" data-aos-delay="80">
          {{ hero_title_prefix ?? 'Uma home' }}
          <span class="text-gradient">{{ hero_title_highlight ?? 'profissional' }}</span>
          {{ hero_title_suffix ?? 'para seu projeto PHP — com foco em conversão.' }}
        </h1>

        <p class="lead text-secondary-emphasis mb-4" data-aos="fade-up" data-aos-delay="140">
          {{ hero_lead ?? 'Stack moderna (PHP 8.3+, Twig, Router dinâmico, Bootstrap), documentação técnica integrada e uma experiência visual fluida com animações suaves.' }}
        </p>

        <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-3" data-aos="fade-up" data-aos-delay="220">
          <a class="btn btn-primary btn-lg px-4" href="#cta">
            {{ cta_primary ?? 'Começar agora' }} <i class="bi bi-arrow-right-short"></i>
          </a>
          <a class="btn btn-outline-light btn-lg px-4" href="#docs">
            {{ cta_secondary ?? 'Ler a documentação' }} <i class="bi bi-journal-text ms-1"></i>
          </a>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-4 small text-secondary-emphasis" data-aos="fade-up" data-aos-delay="300">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-check"></i><span>Segurança por padrão</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-lightning-charge"></i><span>Performance & observabilidade</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-braces"></i><span>Composer + PSR-4</span>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="hero-card glass p-3 p-sm-4" data-aos="zoom-in" data-aos-delay="200">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
              <span class="dot dot-red"></span>
              <span class="dot dot-yellow"></span>
              <span class="dot dot-green"></span>
            </div>
            <button class="btn btn-sm btn-outline-light" id="themeToggle" type="button" aria-label="Alternar tema">
              <i class="bi bi-moon-stars"></i>
            </button>
          </div>

          <div class="codebox">
            <div class="code-header">
              <span class="badge text-bg-dark border border-light-subtle">routes/web.php</span>
              <span class="text-secondary-emphasis small">Router dinâmico + handlers</span>
            </div>
            <pre class="mb-0"><code>[
  ['path' =&gt; '/', 'method' =&gt; 'GET', 'handler' =&gt; 'HomeController@home'],
  ['path' =&gt; '/blog/{slug}', 'method' =&gt; 'GET', 'handler' =&gt; 'HomeController@post'],
  ['path' =&gt; '/dashboard', 'method' =&gt; 'GET', 'handler' =&gt; 'DashboardController@index',
    'middlewares' =&gt; ['auth']
  ]
]</code></pre>
          </div>

          <div class="row g-2 mt-3">
            <div class="col-6">
              <div class="mini-card">
                <div class="mini-icon"><i class="bi bi-diagram-3"></i></div>
                <div>
                  <div class="fw-semibold">MVC leve</div>
                  <div class="small text-secondary-emphasis">Organização limpa</div>
                </div>
              </div>
            </div>
            <div class="col-6">
              <div class="mini-card">
                <div class="mini-icon"><i class="bi bi-eye"></i></div>
                <div>
                  <div class="fw-semibold">Perf Monitor</div>
                  <div class="small text-secondary-emphasis">Logs em JSONL</div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="mini-card">
                <div class="mini-icon"><i class="bi bi-layout-text-window"></i></div>
                <div>
                  <div class="fw-semibold">Twig + Partials</div>
                  <div class="small text-secondary-emphasis">Templates reutilizáveis</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="hero-metrics mt-3">
          <div class="metric glass" data-aos="fade-up" data-aos-delay="350">
            <div class="metric-kpi">290px+</div>
            <div class="metric-label">Responsivo real</div>
          </div>
          <div class="metric glass" data-aos="fade-up" data-aos-delay="420">
            <div class="metric-kpi">&lt; 2s</div>
            <div class="metric-label">LCP otimizado*</div>
          </div>
          <div class="metric glass" data-aos="fade-up" data-aos-delay="490">
            <div class="metric-kpi">AOS</div>
            <div class="metric-label">Animações suaves</div>
          </div>
        </div>

        <p class="small text-secondary-emphasis mt-2 mb-0">
          *Dependente de servidor/CDN/imagens. A base já ajuda com estrutura e boas práticas.
        </p>
      </div>
    </div>
  </div>
</header>

<section class="py-4">
  <div class="container">
    <div class="row g-3 align-items-center justify-content-between">
      <div class="col-12 col-lg-3 text-secondary-emphasis small" data-aos="fade-up">
        Construído com tecnologias confiáveis:
      </div>
      <div class="col-12 col-lg-9" data-aos="fade-up" data-aos-delay="80">
        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
          <span class="pill"><i class="bi bi-filetype-php"></i> PHP 8.3+</span>
          <span class="pill"><i class="bi bi-box"></i> Composer</span>
          <span class="pill"><i class="bi bi-braces-asterisk"></i> Twig</span>
          <span class="pill"><i class="bi bi-database"></i> MySQL</span>
          <span class="pill"><i class="bi bi-bootstrap"></i> Bootstrap 5.3</span>
          <span class="pill"><i class="bi bi-git"></i> Git</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="features" class="py-5 py-lg-6">
  <div class="container">
    <div class="text-center mb-4 mb-lg-5">
      <h2 class="fw-bold" data-aos="fade-up">O que você ganha com essa base</h2>
      <p class="text-secondary-emphasis mb-0" data-aos="fade-up" data-aos-delay="80">
        Um layout limpo, profissional e pronto para evoluir — sem peso desnecessário.
      </p>
    </div>

    <div class="row g-3 g-lg-4">
      {% set features = features ?? [
        {'icon':'rocket-takeoff','title':'Alta conversão','text':'Hero direto ao ponto, CTAs claros e seções objetivas — sem poluição.'},
        {'icon':'shield-lock','title':'Segurança como padrão','text':'Base pronta para CSRF, escape em templates e validações coerentes.'},
        {'icon':'diagram-3','title':'Arquitetura clara','text':'Perfeita pra explicar MVC/Router/Twig na prática, com docs e exemplos.'},
        {'icon':'phone','title':'Responsivo de verdade','text':'Ajustes para telas pequenas, inclusive abaixo de 320px.'},
        {'icon':'speedometer2','title':'Performance','text':'CSS moderno, animações suaves e JS enxuto (sem framework no front).'},
        {'icon':'journal-text','title':'Docs integradas','text':'Stack, request lifecycle, router, segurança e boas práticas em /docs/tech.'},
      ] %}

      {% for f in features %}
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ loop.index0 * 80 }}">
        <div class="feature-card glass h-100 p-4">
          <div class="feature-icon"><i class="bi bi-{{ f.icon }}"></i></div>
          <h3 class="h5 fw-semibold mt-3">{{ f.title }}</h3>
          <p class="text-secondary-emphasis mb-0">{{ f.text }}</p>
        </div>
      </div>
      {% endfor %}
    </div>
  </div>
</section>

<section id="projects" class="py-5 py-lg-6">
  <div class="container">
    <div class="text-center mb-4 mb-lg-5">
      <h2 class="fw-bold" data-aos="fade-up">Projetos recentes</h2>
      <p class="text-secondary-emphasis mb-0" data-aos="fade-up" data-aos-delay="80">
        Exemplos de landing pages publicadas com foco em performance, clareza e conversão.
      </p>
    </div>

    {% set projects = projects ?? [
      {
        'image':'/assets/img/projetos/note1.png',
        'alt':'Projeto de landing page para SaaS',
        'icon':'lightning-charge',
        'title':'Landing page para SaaS B2B',
        'text':'Estrutura orientada a conversão com CTA claro, prova social e formulário enxuto.'
      },
      {
        'image':'/assets/img/projetos/note2.png',
        'alt':'Projeto de página institucional de tecnologia',
        'icon':'house-gear',
        'title':'Página institucional de tecnologia',
        'text':'Narrativa técnica da stack, diferenciais do produto e benefícios para decisão rápida.'
      },
      {
        'image':'/assets/img/projetos/note3.png',
        'alt':'Projeto de landing para lançamento de MVP',
        'icon':'tools',
        'title':'Landing para lançamento de MVP',
        'text':'Template base com sections modulares, FAQ, métricas e integração simples com deploy em VPS.'
      },
    ] %}

    <div class="row g-3 g-lg-4 projects-grid">
      {% for p in projects %}
      <div class="col-12 col-lg-4" data-aos="fade-up" data-aos-delay="{{ loop.index0 * 80 }}">
        <div class="glass p-4 h-100">
          <img
            src="{{ base_url }}{{ p.image }}"
            alt="{{ p.alt }}"
            class="img-fluid project-thumb mb-3"
            loading="lazy"
            decoding="async"
            width="1200"
            height="900"
          >
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-{{ p.icon }}"></i>
            <span class="fw-semibold">{{ p.title }}</span>
          </div>
          <p class="text-secondary-emphasis mb-0">{{ p.text }}</p>
        </div>
      </div>
      {% endfor %}
    </div>
  </div>
</section>

<section id="depoimentos" class="py-5 py-lg-6">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold" data-aos="fade-up">Clientes que confiam</h2>
    </div>

    <div class="row g-3 g-lg-4 testimonials-grid">
      {% set testimonials = testimonials ?? [
        {'name':'Camila Rocha','region':'São Paulo/SP','service':'Landing para SaaS','text':'A página ficou pronta rápido e com copy muito clara. A taxa de clique no CTA principal subiu nas primeiras semanas.'},
        {'name':'Bruno Martins','region':'Belo Horizonte/MG','service':'Institucional tech','text':'Conseguimos apresentar stack, arquitetura e proposta comercial sem poluir a leitura. Ótimo equilíbrio entre design e conteúdo.'},
        {'name':'Marina Duarte','region':'Curitiba/PR','service':'Lançamento de MVP','text':'Template fácil de adaptar, estrutura organizada e deploy simples no servidor. Economizou bastante tempo de implementação.'},
      ] %}
      {% set testimonial_avatars = [
        '/assets/img/avatars/face2_620_620.png',
        '/assets/img/avatars/face9_620_620.png',
        '/assets/img/avatars/face4_620_620.png',
      ] %}

      {% for t in testimonials %}
      {% set avatar_src = testimonial_avatars|length > 0 ? testimonial_avatars[loop.index0 % (testimonial_avatars|length)] : '/assets/img/avatars/face2_620_620.png' %}
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ loop.index0 * 90 }}">
        <div class="quote-card glass h-100 p-4">
          <div class="d-flex justify-content-center mb-3">
            <img
              src="{{ base_url }}{{ avatar_src }}"
              alt="Foto de {{ t.name }}"
              class="avatar"
              loading="lazy"
              decoding="async"
              width="620"
              height="620"
            >
          </div>
          <div class="quote-stars mb-2" aria-label="Avaliação 5 de 5 estrelas">★★★★★</div>
          <div class="quote-mark">“</div>
          <p class="text-secondary-emphasis mb-3">{{ t.text }}</p>
          <div class="fw-semibold">{{ t.name }}</div>
          <div class="small text-secondary-emphasis">{{ t.region }} • {{ t.service }}</div>
        </div>
      </div>
      {% endfor %}
    </div>
  </div>
</section>

<section id="how" class="py-5 py-lg-6">
  <div class="container">
    <div class="row align-items-center g-4 g-lg-5">
      <div class="col-lg-6" data-aos="fade-up">
        <h2 class="fw-bold">Como funciona (visão rápida)</h2>
        <p class="text-secondary-emphasis">
          Do rewrite até o Twig renderizado — um ciclo simples e robusto.
        </p>

        <ol class="steps">
          <li><span class="step-num">1</span> Apache recebe <code>GET /blog</code></li>
          <li><span class="step-num">2</span> <code>public/index.php</code> (front controller)</li>
          <li><span class="step-num">3</span> Router encontra rota em <code>routes/web.php</code></li>
          <li><span class="step-num">4</span> Controller executa lógica e chama Model</li>
          <li><span class="step-num">5</span> Twig renderiza e devolve o HTML final</li>
        </ol>
      </div>

      <div class="col-lg-6" data-aos="fade-up" data-aos-delay="120">
        <div class="glass p-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold">Checklist “Home renovada”</div>
            <span class="badge text-bg-success-subtle border border-success-subtle">Pronto</span>
          </div>
          <ul class="checklist mb-0">
            <li><i class="bi bi-check2-circle"></i> Hero com gradientes animados</li>
            <li><i class="bi bi-check2-circle"></i> AOS progressivo no scroll</li>
            <li><i class="bi bi-check2-circle"></i> Cards minimalistas + CTAs</li>
            <li><i class="bi bi-check2-circle"></i> Responsivo até 290px</li>
            <li><i class="bi bi-check2-circle"></i> Glassmorphism com bom contraste</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</section>

<section id="docs" class="py-5 py-lg-6">
  <div class="container">
    <div class="row align-items-end justify-content-between g-3 mb-4">
      <div class="col-lg-7" data-aos="fade-up">
        <h2 class="fw-bold mb-2">Documentação técnica</h2>
        <p class="text-secondary-emphasis mb-0">
          Centralize tudo em <code>/docs/tech</code>: arquitetura, segurança, router, observabilidade e boas práticas.
        </p>
      </div>
      <div class="col-lg-5 text-lg-end" data-aos="fade-up" data-aos-delay="80">
        <a class="btn btn-outline-light" href="#cta">
          Ver estrutura recomendada <i class="bi bi-arrow-right-short"></i>
        </a>
      </div>
    </div>

    <div class="row g-3 g-lg-4">
      <div class="col-md-6 col-lg-4" data-aos="fade-up">
        <div class="glass p-4 h-100">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-diagram-3"></i><span class="fw-semibold">Arquitetura</span>
          </div>
          <p class="text-secondary-emphasis mt-2 mb-0">
            Request lifecycle, MVC e organização do código.
          </p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="80">
        <div class="glass p-4 h-100">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock"></i><span class="fw-semibold">Segurança</span>
          </div>
          <p class="text-secondary-emphasis mt-2 mb-0">
            CSRF, uploads hardening, sessões e PDO seguro.
          </p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="160">
        <div class="glass p-4 h-100">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-eye"></i><span class="fw-semibold">Observabilidade</span>
          </div>
          <p class="text-secondary-emphasis mt-2 mb-0">
            Perf monitor, logs JSONL e queries sample.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="cta" class="py-5 py-lg-6">
  <div class="container">
    <div class="cta-box glass p-4 p-lg-5 text-center" data-aos="zoom-in">
      <h2 class="fw-bold mb-2">Quer colocar isso no ar hoje?</h2>
      <p class="text-secondary-emphasis mb-4">
        Copie os arquivos, suba no Apache e você já tem uma home profissional — pronta para evoluir.
      </p>

      <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 gap-sm-3">
        <a class="btn btn-primary btn-lg px-4" href="{{ primary_download_url ?? '#' }}">
          Baixar projeto <i class="bi bi-download ms-1"></i>
        </a>
        <a class="btn btn-outline-light btn-lg px-4" href="#features">
          Ver recursos <i class="bi bi-arrow-up-right ms-1"></i>
        </a>
      </div>

      <div class="small text-secondary-emphasis mt-3">
        Dica: se estiver em <code>/site1/</code>, sirva via DocumentRoot apontando para <code>/public</code>.
      </div>
    </div>
  </div>
</section>

<section id="faq" class="py-5 py-lg-6">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold" data-aos="fade-up">Perguntas rápidas</h2>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="accordion accordion-flush glass px-3" id="faqAcc" data-aos="fade-up" data-aos-delay="80">
          <div class="accordion-item bg-transparent">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed bg-transparent text-white" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                Dá pra usar isso dentro do meu MVC/Twig?
              </button>
            </h3>
            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
              <div class="accordion-body text-secondary-emphasis">
                Sim. Essa página já está em Twig. Basta manter os assets em <code>/public/assets</code> e pronto.
              </div>
            </div>
          </div>

          <div class="accordion-item bg-transparent">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed bg-transparent text-white" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                Isso fica leve?
              </button>
            </h3>
            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
              <div class="accordion-body text-secondary-emphasis">
                Sim: Bootstrap + AOS + JS pequeno. Se quiser ultra-leve, dá pra remover AOS e manter só CSS.
              </div>
            </div>
          </div>

          <div class="accordion-item bg-transparent">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed bg-transparent text-white" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                E se eu estiver servindo em /site1/?
              </button>
            </h3>
            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
              <div class="accordion-body text-secondary-emphasis">
                Se seus assets forem servidos por <code>/site1/public</code>, use paths relativos ou um helper de assets (ex.: <code>asset()</code>).
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

{% include 'partials/footer.twig' %}

{% endblock %}
TWIG

  cat > "$dir/public/index.php" <<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/../src/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/../.env');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo '<h1>Dependencias ausentes</h1><p>Rode <code>composer install</code> neste projeto.</p>';
    exit;
}

require $autoload;

use App\Core\Router;
use App\Controllers\HomeController;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$base = $_ENV['APP_BASE'] ?? '';
$loader = new FilesystemLoader(__DIR__ . '/../views');
$twig = new Environment($loader, [
    'cache' => false,
    'auto_reload' => true,
]);
$twig->addGlobal('base_url', $base);
$twig->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'prod');
$twig->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'Agência');
$twig->addGlobal('app_mark', $_ENV['APP_MARK'] ?? 'A');
$twig->addGlobal('app_badge', $_ENV['APP_BADGE'] ?? 'PHP 8.3+');

$controller = new HomeController($twig, [
    'app_name' => $_ENV['APP_NAME'] ?? 'Agência',
    'app_mark' => $_ENV['APP_MARK'] ?? 'A',
    'page_title' => $_ENV['APP_PAGE_TITLE'] ?? null,
]);

$router = new Router($base);
$router->get('/', [$controller, 'index']);

echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

PHP

  cat > "$dir/public/.htaccess" <<'HT'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
HT

  cat > "$dir/.htaccess" <<'HT'
RewriteEngine On
RewriteRule ^assets/(.*)$ public/assets/$1 [L]
RewriteRule ^(.*)$ public/index.php [L]
HT

  cat > "$dir/public/assets/js/landing.js" <<'JS'
(function () {
  // AOS
  if (window.AOS) {
    AOS.init({
      duration: 650,
      once: true,
      offset: 90,
      easing: "ease-out"
    });
  }

  // Footer year
  const y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();

  // Page loader (fail-safe)
  const hideLoader = () => {
    const loader = document.getElementById("pageLoader");
    if (loader) loader.classList.add("loaded");
  };
  window.addEventListener("load", hideLoader);
  document.addEventListener("DOMContentLoaded", hideLoader);
  setTimeout(hideLoader, 2000);

  // Floating buttons (back-to-top + WhatsApp)
  const btn = document.getElementById("backToTop");
  const whatsappFloat = document.querySelector(".whatsapp-float");
  const toggleFloatingButtons = () => {
    const show = window.scrollY > 120;
    if (btn) {
      btn.style.opacity = show ? "1" : "0";
      btn.style.visibility = show ? "visible" : "hidden";
    }
    if (whatsappFloat) {
      whatsappFloat.style.opacity = show ? "1" : "0";
      whatsappFloat.style.visibility = show ? "visible" : "hidden";
    }
  };
  window.addEventListener("scroll", toggleFloatingButtons, { passive: true });
  toggleFloatingButtons();

  if (btn) {
    btn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Theme toggle
  const themeBtns = [document.getElementById("themeToggle"), document.getElementById("themeToggleMobile")]
    .filter(Boolean);
  const root = document.documentElement;

  const getTheme = () => root.getAttribute("data-theme") || "dark";
  const setTheme = (t) => {
    root.setAttribute("data-theme", t);
    try { localStorage.setItem("theme", t); } catch (e) {}
  };

  try {
    const saved = localStorage.getItem("theme");
    if (saved) setTheme(saved);
  } catch (e) {}

  const updateIcon = (t) => {
    themeBtns.forEach((btnEl) => {
      const icon = btnEl.querySelector("i");
      if (icon) icon.className = t === "dark" ? "bi bi-moon-stars" : "bi bi-sun";
    });
  };
  updateIcon(getTheme());

  themeBtns.forEach((btnEl) => {
    btnEl.addEventListener("click", () => {
      const next = getTheme() === "dark" ? "light" : "dark";
      setTheme(next);
      updateIcon(next);
    });
  });

  // Collapse mobile menu after clicking a nav link
  const nav = document.getElementById("topNav");
  const toggler = document.querySelector('.navbar-toggler[data-bs-target="#topNav"]');
  const isNavOpen = () => nav && nav.classList.contains("show");
  const closeNav = () => {
    if (!nav) return;
    nav.classList.remove("show", "collapsing");
    nav.style.height = "";
    if (toggler) toggler.classList.add("collapsed");
    if (toggler) toggler.setAttribute("aria-expanded", "false");
  };

  if (nav) {
    document.addEventListener("click", (event) => {
      const link = event.target.closest("a.nav-link, a.btn");
      if (!link) return;
      if (!nav.contains(link)) return;
      setTimeout(closeNav, 0);
    });
  }

  document.addEventListener("click", (event) => {
    if (!isNavOpen()) return;
    const isToggler = toggler && (event.target === toggler || toggler.contains(event.target));
    if (nav.contains(event.target) || isToggler) return;
    setTimeout(closeNav, 0);
  });

  window.addEventListener("scroll", () => {
    if (!isNavOpen()) return;
    closeNav();
  }, { passive: true });

  window.addEventListener("hashchange", () => {
    setTimeout(closeNav, 0);
  });

  // Active nav link on scroll (single page)
  const navLinks = Array.from(document.querySelectorAll('.navbar .nav-link[href^="#"]'));
  const sections = navLinks
    .map((link) => {
      const id = link.getAttribute("href").slice(1);
      const el = document.getElementById(id);
      return el ? { link, el } : null;
    })
    .filter(Boolean);

  const setActiveLink = () => {
    if (!sections.length) return;
    const offset = 140;
    const scrollPos = window.scrollY + offset;
    let current = sections[0].link;
    for (const s of sections) {
      if (s.el.offsetTop <= scrollPos) current = s.link;
    }
    navLinks.forEach((l) => l.classList.remove("active"));
    current.classList.add("active");
  };

  window.addEventListener("scroll", setActiveLink, { passive: true });
  window.addEventListener("load", setActiveLink);

  // Tablet carousels (projects + testimonials): arrows + pagination dots
  const tabletRange = window.matchMedia("(min-width: 768px) and (max-width: 1366px)");
  const carouselTargets = [
    { sectionId: "projects", gridSelector: ".projects-grid", label: "projetos" },
    { sectionId: "depoimentos", gridSelector: ".testimonials-grid", label: "depoimentos" }
  ];

  const createTabletCarousel = ({ sectionId, gridSelector, label }) => {
    const section = document.getElementById(sectionId);
    if (!section) return null;
    const grid = section.querySelector(gridSelector);
    if (!grid) return null;
    const slides = Array.from(grid.children);
    if (!slides.length) return null;

    const controls = document.createElement("div");
    controls.className = "tablet-carousel-controls";
    controls.setAttribute("role", "group");
    controls.setAttribute("aria-label", `Navegacao de ${label}`);

    const prevBtn = document.createElement("button");
    prevBtn.type = "button";
    prevBtn.className = "tablet-carousel-arrow";
    prevBtn.setAttribute("aria-label", "Slide anterior");
    prevBtn.innerHTML = '<i class="bi bi-chevron-left" aria-hidden="true"></i>';

    const nextBtn = document.createElement("button");
    nextBtn.type = "button";
    nextBtn.className = "tablet-carousel-arrow";
    nextBtn.setAttribute("aria-label", "Proximo slide");
    nextBtn.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';

    const dots = document.createElement("div");
    dots.className = "tablet-carousel-dots";
    dots.setAttribute("aria-label", `Paginacao de ${label}`);

    const dotButtons = slides.map((_, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "tablet-carousel-dot";
      dot.setAttribute("aria-label", `Ir para slide ${index + 1}`);
      dot.addEventListener("click", () => scrollToIndex(index));
      dots.appendChild(dot);
      return dot;
    });

    controls.appendChild(prevBtn);
    controls.appendChild(dots);
    controls.appendChild(nextBtn);
    grid.insertAdjacentElement("afterend", controls);

    let activeIndex = 0;
    let scrollTicking = false;

    const getNearestIndex = () => {
      const viewportCenter = grid.scrollLeft + (grid.clientWidth / 2);
      let nearest = 0;
      let nearestDiff = Number.POSITIVE_INFINITY;
      slides.forEach((slide, idx) => {
        const slideCenter = slide.offsetLeft + (slide.clientWidth / 2);
        const diff = Math.abs(slideCenter - viewportCenter);
        if (diff < nearestDiff) {
          nearestDiff = diff;
          nearest = idx;
        }
      });
      return nearest;
    };

    const syncUi = () => {
      activeIndex = getNearestIndex();
      prevBtn.disabled = activeIndex <= 0;
      nextBtn.disabled = activeIndex >= slides.length - 1;
      dotButtons.forEach((dot, idx) => {
        dot.classList.toggle("active", idx === activeIndex);
        if (idx === activeIndex) {
          dot.setAttribute("aria-current", "true");
        } else {
          dot.removeAttribute("aria-current");
        }
      });
    };

    const scrollToIndex = (index) => {
      const clamped = Math.max(0, Math.min(index, slides.length - 1));
      const target = slides[clamped];
      if (!target) return;
      grid.scrollTo({
        left: target.offsetLeft,
        behavior: "smooth"
      });
    };

    prevBtn.addEventListener("click", () => scrollToIndex(activeIndex - 1));
    nextBtn.addEventListener("click", () => scrollToIndex(activeIndex + 1));

    grid.addEventListener("scroll", () => {
      if (scrollTicking) return;
      scrollTicking = true;
      window.requestAnimationFrame(() => {
        syncUi();
        scrollTicking = false;
      });
    }, { passive: true });

    const onViewportChange = () => {
      controls.hidden = !tabletRange.matches;
      if (tabletRange.matches) syncUi();
    };
    onViewportChange();

    return { syncUi, onViewportChange };
  };

  const carouselInstances = carouselTargets
    .map(createTabletCarousel)
    .filter(Boolean);

  const refreshCarousels = () => {
    carouselInstances.forEach((instance) => {
      instance.onViewportChange();
      instance.syncUi();
    });
  };

  if (tabletRange.addEventListener) {
    tabletRange.addEventListener("change", refreshCarousels);
  } else if (tabletRange.addListener) {
    tabletRange.addListener(refreshCarousels);
  }
  window.addEventListener("resize", refreshCarousels);
  window.addEventListener("load", refreshCarousels);
})();
JS

  cat > "$dir/public/assets/css/app.css" <<'CSS'
:root {
  --bg: #0b0f19;
  --text: #eef2ff;
  --muted: #9aa4b2;
}

[data-theme="light"] {
  --bg: #ffffff;
  --text: #0b1220;
  --muted: #3b4a62;
}

body {
  min-height: 100vh;
}

.bg-body {
  background-color: var(--bg) !important;
}

.text-body {
  color: var(--text) !important;
}

.text-secondary-emphasis {
  color: var(--muted) !important;
}

/* loader */
.page-loader {
  position: fixed;
  inset: 0;
  background: #0b0f19;
  display: grid;
  place-items: center;
  z-index: 9999;
  transition: opacity .3s;
}

.page-loader.loaded {
  opacity: 0;
  pointer-events: none;
}
CSS

  cat > "$dir/public/assets/css/landing.css" <<'CSS'
:root{
  --bg0:#070b16;
  --bg1:#0b1220;
  --card: rgba(255,255,255,.07);
  --stroke: rgba(255,255,255,.14);
  --text: rgba(240,244,255,.96);
  --muted: rgba(170,180,195,.88);

  --r1: 18px;
  --shadow: 0 18px 50px rgba(0,0,0,.35);

  --gradA: 255, 79, 129;
  --gradB: 122, 92, 255;
  --gradC: 72, 209, 204;
}

/* Base */
html,body{
  height:100%;
}
body{
  background: radial-gradient(1200px 800px at 25% 10%, rgba(var(--gradB), .20), transparent 55%),
              radial-gradient(900px 700px at 80% 15%, rgba(var(--gradA), .18), transparent 55%),
              radial-gradient(900px 800px at 60% 80%, rgba(var(--gradC), .14), transparent 55%),
              linear-gradient(180deg, var(--bg0), var(--bg1));
  color: var(--text);
  overflow-x:hidden;
}

/* Contrast helpers */
.text-secondary-emphasis{ color: var(--muted) !important; }
.nav-link{ color: var(--muted) !important; }
.nav-link:hover{ color: var(--text) !important; }
.btn-outline-light{
  color: var(--text);
  border-color: var(--stroke);
}
.btn-outline-light:hover{
  background: rgba(255,255,255,.08);
}
.accordion-button{ color: var(--text) !important; }
.accordion-button:focus{ box-shadow: none; }

/* Smooth */
a, button{
  transition: transform .12s ease, opacity .12s ease;
}
a:active, button:active{
  transform: translateY(1px);
}

.text-gradient{
  background: linear-gradient(90deg, rgba(var(--gradA),1), rgba(var(--gradB),1), rgba(var(--gradC),1));
  -webkit-background-clip: text;
  background-clip:text;
  color:transparent;
}

.nav-glass{
  background: rgba(10, 14, 28, .55);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--stroke);
}

.brand-mark{
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display:grid;
  place-items:center;
  background: linear-gradient(135deg, rgba(var(--gradB),1), rgba(var(--gradA),1));
  box-shadow: 0 12px 30px rgba(0,0,0,.35);
  font-weight: 800;
}

.hero{
  position: relative;
}
.py-lg-6{
  padding-top: 4.5rem!important;
  padding-bottom: 4.5rem!important;
}

.hero-badge{
  display:inline-flex;
  align-items:center;
  gap:.5rem;
  padding:.45rem .75rem;
  border-radius: 999px;
  background: rgba(255,255,255,.06);
  border: 1px solid var(--stroke);
}

.glass{
  background: var(--card);
  border: 1px solid var(--stroke);
  border-radius: var(--r1);
  box-shadow: var(--shadow);
  backdrop-filter: blur(16px);
}

.hero-card{
  position: relative;
}

.dot{
  width: 10px; height:10px; border-radius:99px; display:inline-block;
  opacity:.9;
}
.dot-red{ background: #ff5f57; }
.dot-yellow{ background: #febc2e; }
.dot-green{ background: #28c840; }

.codebox{
  background: rgba(0,0,0,.35);
  border: 1px solid rgba(255,255,255,.10);
  border-radius: 14px;
  overflow:hidden;
}
.code-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:.75rem .85rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
pre{
  padding: .85rem;
  margin:0;
  font-size: .85rem;
  color: rgba(255,255,255,.9);
}
code{
  color: rgba(255,255,255,.92);
}

.mini-card{
  display:flex;
  align-items:center;
  gap:.75rem;
  padding: .75rem .85rem;
  border-radius: 14px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.10);
}
.mini-icon{
  width: 38px; height:38px;
  border-radius: 12px;
  display:grid;
  place-items:center;
  background: linear-gradient(135deg, rgba(var(--gradC),.25), rgba(var(--gradB),.25));
  border: 1px solid rgba(255,255,255,.12);
}

.hero-metrics{
  display:grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .75rem;
}
.metric{
  padding: .9rem 1rem;
  text-align:center;
}
.metric-kpi{
  font-weight: 800;
  font-size: 1.1rem;
}
.metric-label{
  font-size: .85rem;
  color: var(--muted);
}

.pill{
  display:inline-flex;
  align-items:center;
  gap:.5rem;
  padding:.45rem .75rem;
  border-radius:999px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.10);
  color: var(--text);
  font-size: .9rem;
}

.feature-card{
  position: relative;
}
.feature-card:hover{
  transform: translateY(-3px);
}
.project-thumb{
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: var(--r2);
  border: 1px solid rgba(255,255,255,.14);
}
.quote-card{
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}
.quote-card:hover{
  transform: translateY(-3px);
  border-color: rgba(var(--gradB), .34);
  box-shadow: 0 14px 30px rgba(0,0,0,.32);
}
.feature-icon{
  width: 44px; height:44px;
  border-radius: 14px;
  display:grid;
  place-items:center;
  background: linear-gradient(135deg, rgba(var(--gradA),.20), rgba(var(--gradB),.20));
  border: 1px solid rgba(255,255,255,.12);
}

.steps{
  list-style:none;
  padding:0;
  margin: 1.25rem 0 0;
  display:grid;
  gap: .7rem;
}
.steps li{
  display:flex;
  align-items:center;
  gap: .75rem;
  padding:.7rem .85rem;
  border-radius: 14px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.10);
}
.step-num{
  width: 28px; height:28px;
  display:grid;
  place-items:center;
  border-radius: 10px;
  background: rgba(var(--gradB), .25);
  border: 1px solid rgba(255,255,255,.12);
  font-weight: 800;
}

.checklist{
  list-style:none;
  padding:0;
  margin: .85rem 0 0;
  display:grid;
  gap: .55rem;
}
.checklist li{
  display:flex;
  align-items:center;
  gap: .6rem;
}
.checklist i{
  color: rgba(140, 255, 193, .95);
}
.avatar{
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid rgba(255,255,255,.25);
  box-shadow: 0 6px 18px rgba(0,0,0,.25);
}
.quote-stars{
  text-align: center;
  letter-spacing: .14em;
  color: rgba(var(--gradA), 1);
  font-size: .82rem;
}
.quote-mark{
  font-size: 2.2rem;
  line-height: 1;
  color: rgba(var(--gradA), .9);
  margin-bottom: .25rem;
}

.cta-box{
  position: relative;
  overflow:hidden;
}
.cta-box::before{
  content:"";
  position:absolute;
  inset:-2px;
  background: linear-gradient(120deg, rgba(var(--gradA),.25), rgba(var(--gradB),.18), rgba(var(--gradC),.16));
  filter: blur(22px);
  z-index:0;
}
.cta-box > *{
  position: relative;
  z-index:1;
}

.bg-blobs{
  position: fixed;
  inset: 0;
  pointer-events:none;
  z-index:-1;
}
.blob{
  position:absolute;
  width: 520px;
  height: 520px;
  border-radius: 50%;
  filter: blur(40px);
  opacity: .55;
  animation: floaty 10s ease-in-out infinite;
}
.blob-1{
  left: -180px;
  top: -160px;
  background: radial-gradient(circle at 30% 30%, rgba(var(--gradB), .55), transparent 60%);
}
.blob-2{
  right: -220px;
  top: 60px;
  background: radial-gradient(circle at 30% 30%, rgba(var(--gradA), .55), transparent 60%);
  animation-delay: -2.5s;
}
.blob-3{
  left: 35%;
  bottom: -260px;
  background: radial-gradient(circle at 30% 30%, rgba(var(--gradC), .50), transparent 60%);
  animation-delay: -4.5s;
}

@keyframes floaty{
  0%,100% { transform: translate(0,0) scale(1); }
  50%     { transform: translate(20px, -18px) scale(1.03); }
}

.back-to-top{
  position: fixed;
  right: 1.25rem;
  bottom: 1.25rem;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: .8rem;
  opacity:0;
  visibility:hidden;
  transition: opacity .2s ease, visibility .2s ease;
  z-index: 999;
}

/* Pequenas telas (ate 290px) */
@media (max-width: 300px){
  .display-5{ font-size: 1.55rem; }
  .lead{ font-size: 1rem; }
  .btn-lg{ padding: .65rem .9rem; font-size: 1rem; }
  .hero-metrics{ grid-template-columns: 1fr; }
  pre{ font-size: .78rem; }
  .pill{ font-size: .85rem; }
}

/* Ajuste normal mobile */
@media (max-width: 576px){
  .hero-metrics{ grid-template-columns: 1fr; }
}

@media (min-width: 768px) and (max-width: 1366px){
  #projects,
  #projects .container,
  #projects .projects-grid{
    background: transparent !important;
  }
  #projects .projects-grid,
  #depoimentos .testimonials-grid{
    flex-wrap: nowrap !important;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
    -ms-overflow-style: none;
    scrollbar-width: none;
    scrollbar-color: transparent transparent;
    --bs-gutter-x: 0;
    margin-left: 0;
    margin-right: 0;
    border: 0;
    box-shadow: none;
  }
  #projects .projects-grid::-webkit-scrollbar,
  #depoimentos .testimonials-grid::-webkit-scrollbar{
    width: 0;
    height: 0;
    display: none;
  }
  #projects .projects-grid > *,
  #depoimentos .testimonials-grid > *{
    flex: 0 0 100% !important;
    max-width: 100% !important;
    scroll-snap-align: start;
    background: transparent !important;
    padding-left: 0;
    padding-right: 0;
  }
  #projects .projects-grid .glass,
  #depoimentos .testimonials-grid .glass{
    background: transparent;
    backdrop-filter: none;
    border: 1px solid rgba(var(--gradB), .58);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.2);
  }
  #projects .projects-grid .project-thumb{
    border: 0;
    background: transparent;
  }
}

.tablet-carousel-controls{
  display: none;
}
@media (min-width: 768px) and (max-width: 1366px){
  .tablet-carousel-controls{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    margin-top: 1rem;
  }
  .tablet-carousel-arrow{
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 999px;
    border: 1px solid var(--stroke);
    background: transparent;
    color: var(--text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .tablet-carousel-arrow[disabled]{
    opacity: .4;
    cursor: not-allowed;
  }
  .tablet-carousel-dots{
    display: flex;
    align-items: center;
    gap: .45rem;
  }
  .tablet-carousel-dot{
    width: .55rem;
    height: .55rem;
    border-radius: 999px;
    border: 1px solid var(--stroke);
    background: transparent;
    padding: 0;
  }
  .tablet-carousel-dot.active{
    width: 1.3rem;
    background: transparent;
  }
}

/* Tema claro opcional */
html[data-theme="light"] body{
  --bg0:#f8fafc;
  --bg1:#eef2ff;
  --card: rgba(255,255,255,.85);
  --stroke: rgba(15, 23, 42, .12);
  --text: rgba(11, 18, 32, .96);
  --muted: rgba(59, 74, 98, .92);
  background: radial-gradient(1200px 800px at 25% 10%, rgba(var(--gradB), .22), transparent 55%),
              radial-gradient(900px 700px at 80% 15%, rgba(var(--gradA), .20), transparent 55%),
              radial-gradient(900px 800px at 60% 80%, rgba(var(--gradC), .16), transparent 55%),
              linear-gradient(180deg, var(--bg0), var(--bg1));
}

/* Tema claro: navbar com melhor contraste */
html[data-theme="light"] .nav-glass{
  background: rgba(255,255,255,.85);
  border-bottom: 1px solid rgba(15, 23, 42, .12);
}
html[data-theme="light"] .nav-link{
  color: rgba(15, 23, 42, .75) !important;
}
html[data-theme="light"] .nav-link:hover{
  color: rgba(15, 23, 42, 1) !important;
}
html[data-theme="light"] .navbar-brand .fw-semibold{
  color: rgba(15, 23, 42, 1);
}

/* Terminal/codebox: garantir contraste em tema claro */
html[data-theme="light"] .codebox {
  background: rgba(8, 12, 20, 0.88);
  border-color: rgba(8, 12, 20, 0.35);
}
html[data-theme="light"] .codebox pre,
html[data-theme="light"] .codebox code {
  color: rgba(255,255,255,0.95);
}
html[data-theme="light"] .code-header {
  border-bottom-color: rgba(255,255,255,0.12);
}

/* Tema claro: badge visível */
html[data-theme="light"] .navbar .badge{
  background: rgba(15, 23, 42, 0.12) !important;
  border-color: rgba(15, 23, 42, 0.18) !important;
  color: rgba(15, 23, 42, 0.95) !important;
}

/* Tema claro: pills com contraste */
html[data-theme="light"] .pill{
  background: rgba(15, 23, 42, 0.06);
  border-color: rgba(15, 23, 42, 0.18);
  color: rgba(15, 23, 42, 0.9);
}

/* Seção "Como funciona" e checklist: garantir contraste */
.steps li,
.checklist li{
  color: var(--text);
}
.steps li code,
.checklist li code{
  color: var(--text);
}

/* Hover do botão outline no tema escuro */
html[data-theme="dark"] .btn-outline-light:hover{
  color: #fff !important;
  background: rgba(255,255,255,0.16);
  border-color: rgba(255,255,255,0.35);
}

/* Code inline com contraste em tema claro */
html[data-theme="light"] code{
  color: rgba(15, 23, 42, 0.95);
  background: rgba(15, 23, 42, 0.08);
  border-radius: 6px;
  padding: 0 .25rem;
}
CSS

  echo "template-v3" > "$dir/.site-template"

  if command -v composer >/dev/null 2>&1; then
    (cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist)
  fi
fi

if [[ ! -f "$conf" ]]; then
  touch "$conf"
fi

if ! grep -q "^Alias /$slug " "$conf"; then
  cat >> "$conf" <<CONF

Alias /$slug $dir
<Directory $dir>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
CONF
fi

if command -v a2enconf >/dev/null 2>&1; then
  a2enconf "$conf_name" >/dev/null 2>&1 || true
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload apache2
fi

printf "ok\n"
```

Observacoes:
- Atualmente o bloco final adiciona alias para `/$slug` apontando para `/var/www/<slug>` (nao `/public`).
- O arquivo `site-paths.conf` pode ser ajustado manualmente para apontar para `/public` quando desejado.

---

## Apache aliases

```apache
Alias /site1 /var/www/site1
<Directory /var/www/site1>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /site2 /var/www/site2/public
<Directory /var/www/site2/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /site3 /var/www/site3/public
<Directory /var/www/site3/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /site4 /var/www/site4/public
<Directory /var/www/site4/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /agencia /var/www/agencia/public
<Directory /var/www/agencia/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /portifolio /var/www/portifolio/public
<Directory /var/www/portifolio/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /cbmrn /var/www/cbmrn/public
<Directory /var/www/cbmrn/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /site6 /var/www/site6/public
<Directory /var/www/site6/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /site7 /var/www/site7/public
<Directory /var/www/site7/public>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>

Alias /nathan /var/www/nathan
<Directory /var/www/nathan>
  Options Indexes FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
```

---

## Passo a passo (resumo)

1. No painel `labs`, crie um novo site com um `slug` (ex.: `agencia`).
2. O painel executa `bin/provision-site`.
3. O script cria a estrutura do site em `/var/www/<slug>`.
4. O script inclui o alias no `site-paths.conf`.
5. Acesse: `http://88.198.104.148/<slug>/`.

---

## Ferramentas usadas
- PHP 8.2+ (painel), PHP 8.3+ (sites gerados)
- Slim 4 (painel)
- Twig 3 (templates)
- Apache (aliases + `.htaccess`)
- Composer (autoload + dependencias)

---

## Notas didaticas
- Este projeto serve para demonstrar **provisionamento automatico de sites**.
- O template foi criado para ser um exemplo completo de landing page moderna, com dark/light theme.
- A estrutura e intencionalmente simples para fins educativos (router simples, MVC leve, sem banco).

## Observacao: permissoes do storage

O painel roda como `www-data`, mas a pasta `storage/` (onde ficam `sites.json` e `provisioned.json`) esta com dono `luciolemos`. Ou seja: o painel nao consegue gravar nesses JSONs, entao ele cria a pasta do site (via `sudo`) mas nao registra no painel.

**Prova**
- `/var/www/labs/storage/*` esta `luciolemos:luciolemos` com `775`.
- `www-data` nao tem permissao de escrita.

**Como corrigir (recomendado)**

```bash
sudo chown -R www-data:www-data /var/www/labs/storage
sudo chmod -R 775 /var/www/labs/storage
```

```bash
sudo chown -R luciolemos:www-data /var/www/agencia
ssudo chmod -R 775 /var/www/agencia
```
