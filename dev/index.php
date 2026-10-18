<?php
/**
 * Raiz {projeto}/ — atalho: encaminha para a LOJA (catalog/). O painel é /admin/.
 * Útil em XAMPP/subpasta (http://localhost/davidcms/dev/): o usuário abre a
 * pasta do projeto e cai no lugar certo.
 */
header('Location: catalog/index.php');
exit();
