# Slim Site Template

Template de site gerado pelo Labs com base em Slim 4 e Twig.

## Stack
- PHP 8.3+
- Slim 4 (`slim/slim`, `slim/psr7`)
- Twig (`slim/twig-view`, `twig/twig`)
- Apache 2.4 com `mod_rewrite`

## Estrutura
- `public/`: front controller (`index.php`), `.htaccess` e assets.
- `routes/web.php`: rotas Slim.
- `src/Controllers/HomeController.php`: controller HTTP (PSR-7).
- `src/Core/Env.php`: loader simples de `.env`.
- `views/`: templates Twig.
- `storage/`: cache/logs.

## Execucao local
```bash
composer install
```

Abra:
- `http://88.198.104.148/<slug>/`

## Como funciona
1. Apache aponta para `/var/www/<slug>/public`.
2. `public/index.php` sobe Slim, Twig e middlewares.
3. `routes/web.php` registra endpoints.
4. Controller renderiza Twig.

## Alias Apache (Labs)
Formato correto:

```apache
Alias /<slug> /var/www/<slug>/public
<Directory /var/www/<slug>/public>
  Options FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
```

## Variaveis de ambiente
- `APP_NAME`
- `APP_BASE` (ex.: `/site2`)
- `APP_ENV` (`production` ou `dev`)
- Links sociais e contato (`GITHUB_URL`, `X_URL`, `INSTAGRAM_URL`, `WHATSAPP_URL`)

## Deploy
1. Garantir permissao de escrita em `storage/`.
2. Rodar `composer install --no-dev --optimize-autoloader`.
3. Validar Apache:
```bash
sudo apache2ctl -t
sudo systemctl reload apache2
```

## Conversao de Imagens para WebP
Script utilitario para converter imagens com GD:
- `scripts/convert-webp.php`

Exemplos:
```bash
php scripts/convert-webp.php
php scripts/convert-webp.php --path=public/assets/img --quality=82
php scripts/convert-webp.php --path=public/assets/img --dry-run
php scripts/convert-webp.php --path=public/assets/img --force
```
