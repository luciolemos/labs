# CHANGELOG - Labs

Historico tecnico de mudancas do painel Labs e infraestrutura de provisionamento.

## 2026-02-18
- Migracao para publicacao dinamica no Apache via `labs.conf` (`LABS_DYNAMIC_SITES`).
- Correcao da regex de `AliasMatch` para nao capturar rotas internas do Labs (`/assets`, `/admin`, `/api`, etc.).
- Ajuste de validacao no painel para bloquear criacao de slug quando o Apache dinamico nao estiver pronto.
- Ajuste no fluxo de remocao para evitar reimportacao indevida de site removido.
- Padronizacao dos templates `tech-v4-*` e documentacao individual por variante.

## 2026-02-17
- Estruturacao do modelo multi-template no painel (`tech-v4-blue`, `green`, `yellow`, `red`, `dark`).
- Inclusao de preview de template no Admin.
- Melhorias de UX no Admin: confirmacoes de salvar/remover e mensagens de status.
