# Fase 9 · Tarefa 05 — Model de definições + ACL dormente de campos personalizados

**Data:** 2026-10-23 · **Base verificada:** `davidcreator/davidcms` no `main`
**HEAD:** `2be7cc06c982e24faca1195b6cdd126f34960322`
**Estado:** model backend + contrato de rota/ACL; **nenhum DB, controller, UI ou valor executado**.

## 1. Objetivo

Transformar o schema da F9-02 em uma camada de domínio administrável, deixando pronta a base para a tela da F9-06. A entrega cobre:

1. Model administrativo das **definições** de grupos/campos;
2. ACL da rota futura `custom_field/definition` para instalações novas e existentes;
3. guardas de lifecycle para impedir exclusão insegura;
4. Tool E2E estático local.

Não cria valores reais, não liga CMS/page ao campo personalizado e não expõe nada ao público.

## 2. Model entregue

Arquivo novo: `dev/admin/model/custom_field/definition.php`

Classe: `Reamur\Admin\Model\Custom_field\Definition`  
Proxy esperado pelo padrão MVCL: `model_custom_field_definition`

### Cobertura do model

- CRUD de grupos de campo (`custom_field_group` + labels multilíngues);
- CRUD de definições de campo (`custom_field` + labels/ajuda multilíngues);
- idiomas ativos por `sort_order` para montar formulários futuros;
- filtros por entidade, chave, grupo, tipo, visibilidade, status de grupo;
- whitelist inicial: entidade única `cms/page`;
- tipos permitidos: `text`, `textarea`, `number`, `boolean`, `url`, `select`;
- visibilidade: `admin_only` ou `public`, com padrão contratual ainda privado;
- opções `select` serializadas como JSON estrito; demais tipos gravam `[]`.

### Regras de lifecycle

- `entity_type`, `key`, `type` e `localized` são imutáveis após criação;
- editar grupo não altera `entity_type/key`;
- group só pode ser excluído sem campos;
- field só pode ser excluído quando `custom_field_value` estiver vazio;
- nenhum valor é criado, alterado ou apagado por este model.

Isso preserva a regra aprovada da F9-01: mudança incompatível de key/type/localização pede migração dedicada, não edição silenciosa.

## 3. ACL dormente

Arquivos novos:

- `dev/install/sql/upgrade_f9_05_custom_field_definition_grant.sql`
- `dev/install/sql/rollback_f9_05_custom_field_definition_grant.sql`

E alteração pontual:

- `dev/install/model/install/install.php` — novas instalações nascem com `custom_field/definition` nas permissões do grupo Administrator.

A rota **ainda não existe** como controller/tela; o grant é propositalmente dormente e será ativado na próxima subtarefa. Isso evita misturar ACL, Model e UI em um pacote grande demais.

Rollback do grant remove somente a rota dos arrays JSON de permissão; não apaga tabelas nem valores.

## 4. Limites desta tarefa

- Não executa SQL nem banco.
- Não altera `schema.sql`.
- Não cria controller/template/menu/idioma de tela ainda.
- Não integra `cms/page` nem salva `custom_field_value`.
- Não aplica migration em ambiente real.
- Não lê dump `bd-davidalmeida.sql` nem reabre o ciclo legado.

## 5. Validação local

Ferramentas incluídas:

- `tools/e2e/e2e_f905.sh --check`
- `tools/e2e/e2e_f905.cjs`

O Tool confere:

- caminho, namespace, classe e proxy esperados do Model;
- presença dos métodos públicos de grupos/campos;
- whitelist exata de entidade/tipos/visibilidades;
- ausência de SQL de escrita em `custom_field_value`;
- guardas de exclusão por valores/campos filhos;
- imutabilidade de `entity_type`, `key`, `type` e `localized` no método `editField`;
- grant SQL idempotente e rollback separado;
- atualização dormente do seed de ACL para novas instalações;
- contrato JSON íntegro.

Resultado executado pelo agente: **PASS** — validação estática aprovada, sem PHP/banco/produção.

## 6. Arquivos da entrega

- `dev/admin/model/custom_field/definition.php`
- `dev/install/model/install/install.php`
- `dev/install/sql/upgrade_f9_05_custom_field_definition_grant.sql`
- `dev/install/sql/rollback_f9_05_custom_field_definition_grant.sql`
- `docs/F9-05-Modelo-Definicoes-Campos.md`
- `docs/F9-05-Contrato-Definicoes-Campos.json`
- `docs/README.md`
- `agents-ia/decisoes/0061-f9-05-modelo-definicoes-campos.md`
- `tools/e2e/e2e_f905.sh`
- `tools/e2e/e2e_f905.cjs`
- `manifest.json` e `sha256sum.txt`
