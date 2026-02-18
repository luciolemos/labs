# Labs (Painel de Sites)

Painel para gerenciar sites em `/var/www/<slug>` com Slim 4 + Twig.

## Objetivo
- Criar sites novos por template
- Editar metadados e template no painel
- Reprovisionar e remover sites
- Publicar cada site em `http://88.198.104.148/<slug>/`

## Arquitetura atual
- Painel: `/var/www/labs`
- Templates: `/var/www/labs/templates/*`
- Sites provisionados: `/var/www/<slug>`
- Banco do painel (JSON):
  - `storage/data/sites.json`
  - `storage/data/provisioned.json`

## Publicação Apache (modo dinâmico atual)
O ambiente atual usa um único vhost (`labs.conf`) para:
- raiz `/` apontando para o site institucional (`/var/www/natalcode/public`)
- painel em `/labs/`
- sites provisionados em `/<slug>/...` via regra dinâmica

Vhost ativo:
- `/etc/apache2/sites-available/labs.conf`

Configuração recomendada:

```apache
<VirtualHost *:80>
    ServerName 88.198.104.148

    DocumentRoot /var/www/natalcode/public
    <Directory /var/www/natalcode/public>
        AllowOverride All
        Require all granted
    </Directory>

    Alias /labs /var/www/labs/public
    <Directory /var/www/labs/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Fallback para subrotas de sites provisionados:
    # /site1/login, /site1/about, /site2/blog, etc.
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/labs(?:/|$)
    RewriteCond %{REQUEST_URI} !^/assets(?:/|$)
    RewriteCond %{REQUEST_URI} !^/favicon\.ico$
    RewriteCond %{REQUEST_URI} !^/robots\.txt$
    RewriteCond %{REQUEST_URI} ^/([A-Za-z0-9_-]+)/(.*)$
    RewriteCond /var/www/%1/public -d
    RewriteCond /var/www/%1/public/%2 !-f
    RewriteCond /var/www/%1/public/%2 !-d
    RewriteRule ^ /%1/index.php [QSA,L,PT]

    # Mapeamento dinâmico /<slug> -> /var/www/<slug>/public
    AliasMatch ^/(?!labs(?:/|$)|assets(?:/|$)|favicon\.ico$|robots\.txt$)([A-Za-z0-9_-]+)(/.*)?$ /var/www/$1/public$2
    <Directory /var/www>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/labs_error.log
    CustomLog ${APACHE_LOG_DIR}/labs_access.log combined
</VirtualHost>
```

Aplicar:

```bash
sudo apache2ctl -t
sudo systemctl reload apache2
```

## site-paths.conf (estado atual)
`site-paths.conf` é legado e deve ficar desabilitado neste modo dinâmico.

Verificar:
```bash
ls -l /etc/apache2/conf-enabled/site-paths.conf
```

Desabilitar (se existir):
```bash
sudo a2disconf site-paths
sudo apache2ctl -t
sudo systemctl reload apache2
```

Observação:
- manter `site-paths.conf` habilitado junto com `labs.conf` dinâmico pode gerar comportamento inconsistente por sobreposição de regras/aliases.

## Fluxo operacional
1. Usuário cria/edita/remove em `/admin`.
2. `ProvisionService` aplica template em `/var/www/<slug>`.
3. O site entra em `sites.json` e `provisioned.json`.
4. Apache publica automaticamente pela regra dinâmica.

## Configuração `.env` relevante
```env
ADMIN_PROVISION=true
ADMIN_PROVISION_HOST=88.198.104.148
ADMIN_PROVISION_BASE=/var/www

ADMIN_APACHE_DYNAMIC=true
ADMIN_APACHE_DYNAMIC_VHOST=/etc/apache2/sites-available/labs.conf
ADMIN_APACHE_DYNAMIC_MARKER=LABS_DYNAMIC_SITES

ADMIN_TEMPLATES_DIR=/var/www/labs/templates
ADMIN_TEMPLATE_DEFAULT=tech-v4-blue
```

## Templates disponíveis
- `tech-v4-blue`
- `tech-v4-green`
- `tech-v4-yellow`
- `tech-v4-red`
- `tech-v4-dark`

Cada template possui `template.json` e `README.md` próprio.

## Comandos úteis
Validar Apache:
```bash
sudo apache2ctl -t
```

Recarregar Apache:
```bash
sudo systemctl reload apache2
```

Teste rápido:
```bash
curl -I http://88.198.104.148/
curl -I http://88.198.104.148/labs/
curl -I http://88.198.104.148/site1/
curl -I http://88.198.104.148/site1/login
```

## Arquivos-chave do Labs
- `src/Controllers/AdminController.php`
- `src/Services/ProvisionService.php`
- `src/Services/SiteService.php`
- `src/Config/app.php`
- `bin/provision-site`
- `bin/deprovision-site`

## Historico de Mudancas
- Labs (infra e painel): `CHANGELOG.md`
- Templates de landing page: `templates/tech-v4-*/TEMPLATE_CHANGELOG.md`
