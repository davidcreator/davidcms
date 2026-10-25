# Agentes IA — F9-27

- Só executar o release gate em ambiente descartável depois de autorização explícita.
- Confirmar F9-12 com o responsável; não presumir schema aplicado e não rodar migration automaticamente.
- Preservar proibição de testes PHP/SQL/browser/app/E2E nesta entrega; checker atual é apenas estático.
- Sem produção, dumps, dados pessoais, credenciais em logs ou publicação automática.
- Se houver diferença de commit/schema, ambiente não isolado, dados inesperados ou qualquer falha, parar e registrar apenas síntese sem payload sensível.
- A aprovação/reprovação de release é decisão humana; nenhum PASS estático libera produção.
