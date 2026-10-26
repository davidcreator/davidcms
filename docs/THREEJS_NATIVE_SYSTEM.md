# Three.js nativo — verificação read-only Admin/Catalog

**Snapshot de referência consultado:** `davidcreator/davidcms` `main`, commit `c1e2b3d52525513c08628fa2d972dbf408be897b`.

Este pacote recompõe e atualiza o pacote `ThreeJS-Native-ReadOnly-E2E.zip`. Contém o runner read-only, checker estático e esta documentação; **não é um clone do DavidCMS nem contém uma cópia integral dos arquivos do projeto**. Os caminhos `dev/...` mencionados são relativos à raiz real do DavidCMS.

## Escopo e segurança

- O runner Playwright consulta por GET as páginas configuradas, lê os valores e verifica os assets/fallbacks, sem submeter formulários nem salvar configurações.
- Não requer a confirmação usada pelo runner mutável. Ainda assim, confira as URLs antes de executar e forneça somente um storage state local privado já existente; não compartilhe cookies ou credenciais.
- Não execute o runner mutável em `localhost/davidcms` ou em qualquer ambiente persistente/compartilhado.
- O checker estático verifica os contratos locais do snapshot; não substitui teste de navegador nem prova que uma instalação foi atualizada.

## Execução

Na raiz do DavidCMS, coloque os dois arquivos `.cjs` nos caminhos `tools/e2e/` indicados e execute:

```sh
node tools/e2e/check_threejs_native_admin.cjs
node tools/e2e/threejs_native_readonly_playwright.cjs
```

O runner de navegador depende de Playwright no ambiente de QA e das variáveis `ADMIN_SETTINGS_URL`, `PUBLIC_HOME_URL`, `PUBLIC_404_URL` e `PLAYWRIGHT_STORAGE_STATE`. O estado autenticado deve permanecer privado. Não use dados de autenticação enviados por terceiros.

## Resultados registrados

- Checker nativo executado nesta sessão contra o snapshot seletivo: **9/9 PASS**.
- O usuário informou que a execução anterior do runner read-only retornou **PASS**. Esse PASS é do Admin/Catalog nativo; não valida página pública de Blog/CMS.
- Não há atualmente posts de Blog nem páginas CMS publicados para validar as thumbs públicas. A ausência de conteúdo elegível não representa, por si só, uma falha do runtime.
- PHP CLI não estava disponível nesta sessão; `php -l` não foi executado.

## Limite desta entrega

Nenhuma alteração foi enviada ao GitHub, aplicada ao ambiente compartilhado ou implantada. A edição experimental do atributo de preview (`data-custom-js`) está fora deste pacote: não foi integrada à instalação nem validada como execução sandbox no editor.
