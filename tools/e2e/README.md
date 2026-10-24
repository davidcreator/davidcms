# F9-20 — verificação estática

`e2e_f920_static.sh` executa o checker estático F9-19 contra os arquivos pontuais capturados do HEAD em `reference/`. O checker só lê fontes e JSON; não inicia produto, PHP, SQL, browser, servidor ou banco.

Resultado obtido nesta entrega: PASS para cinco arquivos de runtime e oito fixtures sintéticas. O PASS confirma somente os marcadores verificados no código; não substitui testes E2E em ambiente controlado.
