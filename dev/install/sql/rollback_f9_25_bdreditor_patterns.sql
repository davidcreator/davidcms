-- F9-25 rollback seguro: remove somente o grant da rota e mantém a tabela/dados.
-- Não há DROP: padrões pessoais são dados de usuários. Excluir esses dados requer
-- backup e autorização explícita em operação separada.
UPDATE `rms_user_group`
SET `permission` = JSON_SET(
  `permission`, '$.access',
  JSON_REMOVE(
    JSON_EXTRACT(`permission`, '$.access'),
    JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'cms/block_pattern'))
  )
)
WHERE JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'cms/block_pattern') IS NOT NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_SET(
  `permission`, '$.modify',
  JSON_REMOVE(
    JSON_EXTRACT(`permission`, '$.modify'),
    JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'cms/block_pattern'))
  )
)
WHERE JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'cms/block_pattern') IS NOT NULL;
