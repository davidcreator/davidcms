# F9-19 — guardrail estático de regressão

Execute `tools/e2e/e2e_f919_static.sh [checkout-ou-pacote-F9-19]` para verificar os contratos de fonte e oito fixtures sintéticas. A ferramenta apenas lê texto/JSON; não executa código PHP, SQL, Twig, browser ou aplicação, não abre banco e não aplica migration.

PASS indica apenas a presença dos guardrails no código-fonte; não valida comportamento em runtime. A F9-12 não é presumida aplicada. E2E funcional fica para um ambiente isolado e autorização futura.
