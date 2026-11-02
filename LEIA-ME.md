# Correcao: repo-health verde (layout + portabilidade Python)

Os 4 FAIL reportados tinham 2 causas:
  1. verify-deps-layout.sh exigia ZERO arquivos em node_modules/, mas a pasta
     agora e sustentada por um index.html versionado.
  2. No Windows, python3/python podem ser o alias da Microsoft Store:
     `command -v` encontra, mas executar sai com 49. Tres guardas confiavam
     apenas em `command -v` e reprovavam sem culpa.

Mudancas de codigo (5 arquivos):
  tools/verify-deps-layout.sh       -> aceita SOMENTE index.html; qualquer pacote reprova
  tools/verify-f11-01.sh            -> usa interpretador real; SKIP explicito se ausente
  tools/release-readiness-report.sh -> idem; sai 3 (SKIP), nunca finge PASS
  tools/repo-health.sh              -> resolve Python, SKIP nomeado, avisa guardas nao executados
  tools/lib/python-runtime.sh       -> NOVO helper compartilhado (python3/python/py -3)

Os 14 CHECKSUMS*.txt foram reemitidos em cascata; o helper novo entrou no
CHECKSUMS.txt. Nenhum contrato, matriz, schema.sql, hash de release gate ou
release_approved foi alterado. Nenhum NOT_RUN/SKIP virou PASS.

Validado em 3 cenarios:
  A Python real   -> 6 PASS / 0 FAIL / 0 SKIP
  B so stub Store -> 4 PASS / 0 FAIL / 2 SKIP + aviso explicito
  C pacote injetado no indice -> FAIL (fail-closed)

Aplicar: bash tools/repo-health.sh
Se o Windows nao tiver Python, instale Python 3 (ou use `py -3`); os 2 guardas
dependentes aparecerao como SKIP nomeado ate la. SKIP nao e aprovacao.
Nenhuma escrita remota foi feita nesta entrega.
