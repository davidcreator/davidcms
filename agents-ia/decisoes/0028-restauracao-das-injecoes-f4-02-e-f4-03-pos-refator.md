# ADR 0028 — Restauração das injeções/settings e do módulo Site Info após refator manual

- **Status:** Aceita — 2026-10-19 (delta de reparo sobre HEAD `3cfe860`)
- **Contexto:** após o push de `a04a188` (F4-03), o mantenedor editou manualmente
  o repositório em dois commits: `61057c1` ("refactor: clean up dashboard and
  language code") removeu as chaves `config_language_*` e `config_contact_*` /
  `config_copyright_*` / `config_social_*` das allowlists de **ambos** os
  `startup/setting` (admin e catalog), removeu as chaves F4-03 do allowlist do
  `admin/model/setting/setting.php` e removeu o módulo Site Info do dashboard;
  `3cfe860` ("feat(language): add per-app language management and dashboard
  menu") recolocou apenas uma fração (módulo Languages, `config_language_*`
  somente no model, switcher no header, ordem das pré-ações invertida correta).
- **Fatos medidos (leitura via clone + grep — antes de escrever):**
  - telas F4-02 (`localization/language`) e F4-03 (`setting/site_info`) existiam
    inteiras no HEAD, assim como todo o consumo do catálogo (home/footer/header,
    13+4 referências F4-03, switcher gateado por rms_language);
  - mas sem as allowlists: (a) `editSettings` descartava silenciosamente as 15
    chaves do form Site Info (save-mudo: POST retornava 302 e o banco não mudava);
    (b) `startup/setting` não injetava `config_language_*` em Config, o que
    reabria exatamente o bug de round-trip documentado na ADR 0025 §4 e
    impedia `startup/language` de ler o default do banco.
- **Decisão (com sinal do mantenedor):** restaurar o mínimo cirúrgico,
  preservando os dois commits manuais no histórico:
  1. `admin/controller/startup/setting.php` — reintegra `config_language_*`
     (ADR 0025) + as 15 chaves `config_contact_/copyright_/social_*` (ADR 0026);
  2. `catalog/controller/startup/setting.php` — idem (consumo público);
  3. `admin/model/setting/setting.php` — reintegra as 15 chaves F4-03 a
     `editSettings` (`config_language_*` já estava de volta via `3cfe860`);
  4. `admin/controller/common/dashboard.php` + langs ×3 — reintegra o módulo
     "Site Information" gateado por `hasPermission('access','setting/site_info')`.
- **Fora de escopo deliberado:** o restante removido pelo `61057c1` já estava
  inteiro; os comentários de pré-ação e docs ADR 0024/0025/0026 permanecem a
  verdade normativa (não retrocarbinados).
- **Validação:** e2e_f401/e2e_f402/e2e_f403 = 30/30 no estado reparado +
  suíte completa; prova de encadeamento HEAD + zip = espelho corrigido
  (ver `tools/zips`/pacote da sessão).
