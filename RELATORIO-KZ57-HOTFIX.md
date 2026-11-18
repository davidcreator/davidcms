# RELATÓRIO — Hotfix KZ-57: rótulos de idioma vazios no painel

## Sintoma
Dashboard do Dracaunos renderizava ícones/números/tabelas, mas **todos os textos vindos de idioma ficavam em branco** (títulos dos cards, labels dos stats, abas, cabeçalhos de tabela, labels de formulário).

## Causa
Os controllers chamavam `$this->load->language('security/dracaunos')` — que só preenche o serviço de idioma — mas **não mesclavam as chaves no `$data`** enviado à view. O Twig renderiza `{{ chave }}` como vazio quando a chave não está no contexto. (O dashboard funciona porque copia as chaves manualmente.)

## Correção
`array_merge($this->load->language(...), $data ?? [])` no início do `index()` de:
- `security/dracaunos.php`, `payments/jow.php`, `common/login.php`, `common/forgotten.php`, `common/reset.php`

## Regressão
Checker exige o merge de idioma nesses 5 controllers → **155 verificações PASS**.

## Governança
- decision-matrix KZ-57 DECIDED; CHANGELOG + CHECKSUMS recalculado (3 PASS / 0 FAIL).
