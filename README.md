# Labs (Painel de Sites)

Painel para provisionar sites em `/var/www/<slug>` com base Slim 4 + Twig.

## Estado atual
- Painel (`labs`): Slim 4
- Sites gerados: Slim 4 + Twig
- Publicacao em Apache via alias em `site-paths.conf`
- Padrao de seguranca: alias sempre aponta para `.../public`

## Fluxo de provisionamento
1. O painel recebe `slug` e dados do site.
2. Executa `bin/provision-site`.
3. O script copia o template-base em `/var/www/labs/templates/site-template-v4` para `/var/www/<slug>`.
4. O script grava/atualiza alias no Apache.
5. Site fica acessivel em `http://88.198.104.148/<slug>/`.

## Template-base (novo fluxo)
- Template atual: `/var/www/labs/templates/site-template-v4`
- Script usa por padrao:
  - `SITE_TEMPLATE_DIR=/var/www/labs/templates/site-template-v4`
- Fallback:
  - se `SITE_TEMPLATE_DIR` nao existir, o `bin/provision-site` cai no modo legado (scaffold inline no proprio script).

### Como manter os novos sites iguais ao template
1. Ajuste arquivos no template-base (`views`, `public/assets`, `src`, etc).
2. Gere novo site normalmente.
3. O novo site sai com os ajustes automaticamente.

Observacao:
- Sites antigos nao mudam sozinhos. Para atualizar existentes, rode migração/reprovisionamento.

## Estrutura gerada
```text
/var/www/<slug>
├─ composer.json
├─ .env
├─ public/
│  ├─ index.php
│  ├─ .htaccess
│  └─ assets/
├─ routes/web.php
├─ src/
│  ├─ Controllers/HomeController.php
│  └─ Core/Env.php
├─ views/
├─ storage/
└─ vendor/
```

## Stack do site gerado
- `slim/slim`
- `slim/psr7`
- `slim/twig-view`
- `twig/twig`

## Padrao Apache (obrigatorio)
Use sempre `public` como destino.

```apache
Alias /<slug> /var/www/<slug>/public
<Directory /var/www/<slug>/public>
  Options FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
```

## Configuracao do painel (`.env`)
Campos principais:
- `ADMIN_PROVISION=true`
- `ADMIN_PROVISION_HOST=88.198.104.148`
- `ADMIN_PROVISION_BASE=/var/www`
- `ADMIN_APACHE_CONF=/etc/apache2/conf-available/site-paths.conf`
- `ADMIN_APACHE_CONF_NAME=site-paths`

## Comandos de operacao
Validar e recarregar Apache:

```bash
sudo apache2ctl -t
sudo systemctl reload apache2
```

Testes rapidos do site provisionado:

```bash
curl -I http://88.198.104.148/<slug>/
curl -s -o /dev/null -w "%{http_code}\n" http://88.198.104.148/<slug>/composer.json
```

Esperado:
- `/<slug>/` -> `200` ou `302`
- `/<slug>/composer.json` -> `404`

## Arquivos chave
- Script de provisionamento: `bin/provision-site`
- Script de remoção: `bin/deprovision-site`
- Serviço de provisionamento: `src/Services/ProvisionService.php`
- Config da app: `src/Config/app.php`
- Template-base: `templates/site-template-v4`
