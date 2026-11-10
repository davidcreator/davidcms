<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model do wizard: conexão, importação do schema e criação do administrador.
 * REGRA MVCL: todo SQL da instalação vive aqui — nenhum SQL em controller.
 * NOTA: usa extensões nativas (mysqli/PDO) porque system/library/db/* ainda não
 * existe nesta fase; o wizard precisa operar ANTES do config.php existir.
 */
namespace Reamur\Install\Model\Install;

class Install extends \Reamur\System\Engine\Model {

    /** @var \mysqli|\PDO|null Conexão aberta pela última chamada a connect() */
    private $handle = null;

    /** Última mensagem de erro de connect() (via getError) */
    private string $error = '';

    /**
     * Testa/abre conexão com as credenciais do formulário.
     *
     * REGRA (motor Reamur): models rodam via Proxy/__call — argumentos &by-ref
     * NÃO atravessam. Em caso de false, leia a mensagem em getError().
     *
     * @param array{db_driver: string, db_hostname: string, db_username: string, db_password: string, db_database: string, db_port: int|string} $data
     */
    public function connect(array $data): bool {
        $this->handle = null;
        $this->error  = '';

        try {
            if ($data['db_driver'] === 'mysqli') {
                $mysqli = new \mysqli(
                    (string)$data['db_hostname'],
                    (string)$data['db_username'],
                    (string)$data['db_password'],
                    (string)$data['db_database'],
                    (int)($data['db_port'] ?: 3306)
                );

                $mysqli->set_charset('utf8mb4');

                $this->handle = $mysqli;
            } else { // pdo (pdo_mysql)
                $this->handle = new \PDO(
                    sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        $data['db_hostname'],
                        (int)($data['db_port'] ?: 3306),
                        $data['db_database']
                    ),
                    (string)$data['db_username'],
                    (string)$data['db_password'],
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
            }
        } catch (\mysqli_sql_exception|\PDOException $e) {
            $this->error = $e->getMessage();

            return false;
        }

        return true;
    }

    /** Mensagem da última falha de connect() ('' quando a conexão abriu) */
    public function getError(): string {
        return $this->error;
    }

    /**
     * Verifica se o banco já contém tabelas do prefixo (instalação deve partir de banco vazio).
     */
    private function assertValidPrefix(string $prefix): void {
        if (preg_match('/^[a-z][a-z0-9_]{0,19}$/', $prefix) !== 1) {
            throw new \InvalidArgumentException('Invalid database table prefix.');
        }
    }

    private function requireHandle(): \mysqli|\PDO {
        if (!$this->handle instanceof \mysqli && !$this->handle instanceof \PDO) {
            throw new \RuntimeException('Database connection is not initialized.');
        }

        return $this->handle;
    }

    public function hasTables(string $prefix): bool {
        $this->assertValidPrefix($prefix);
        $handle = $this->requireHandle();
        $like = str_replace(['%', '_'], ['\\%', '\\_'], $prefix) . '%';

        if ($handle instanceof \mysqli) {
            $result = $handle->query("SHOW TABLES LIKE '" . $handle->real_escape_string($like) . "'");

            return $result instanceof \mysqli_result && $result->num_rows > 0;
        }

        $stmt = $handle->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$like]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Importa sql/schema-complete.sql trocando o prefixo-padrão `rms_` pelo escolhido.
     * O arquivo usa identificadores cravados (`rms_tabela`), então a troca cirúrgica
     * atinge somente nomes de tabelas — inclusive nos INSERTs de seed.
     */
    public function importSchema(string $prefix): void {
        $this->assertValidPrefix($prefix);
        $handle = $this->requireHandle();
        $schemaPath = dirname(__DIR__, 2) . '/sql/schema-complete.sql';
        $sql = @file_get_contents($schemaPath); // dev/install/sql/schema-complete.sql
        if ($sql === false) {
            throw new \RuntimeException('Installation SQL schema file could not be read.');
        }

        // Linhas de comentário (cabeçalhos de seção do arquivo) saem ANTES do split —
        // um chunk começando com '--' não pode derrubar o CREATE/INSERT que o segue.
        $sql = (string)preg_replace('/^\s*--[^\n]*$/m', '', $sql);

        if ($prefix !== 'rms_') {
            $sql = str_replace('`rms_', '`' . $prefix, $sql);
        }

        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }

            $handle instanceof \mysqli
                ? $handle->query($statement)
                : $handle->exec($statement);
        }
    }

    /**
     * Cria o grupo 'Administrator' e o primeiro usuário admin. O grupo nasce
     * (F2-6) com access+modify nas rotas-base mínimas de autogestão —
     * 'user/user', 'user/user_group', 'catalog/project', 'catalog/category', 'cms/page', 'marketing/testimonial', 'marketing/client_logo', 'tool/tool', 'sale/order', 'communication/contact_message' e 'setting/webhook' (F2-16 — último do schema) — senão o único admin sairia do wizard
     * sem poder administrar as próprias contas. Colunas 1:1 com
     * docs/arquitetura/Dicionario-de-Dados.md.
     *
     * @param array{username: string, firstname: string, lastname: string, email: string, password: string} $data
     */
    public function createAdministrator(array $data, string $prefix): void {
        $this->assertValidPrefix($prefix);
        $handle = $this->requireHandle();
        $seedRoutes = [
            'user/user', 'user/user_group', 'catalog/project', 'catalog/category',
            'cms/page', 'cms/block_pattern', 'marketing/testimonial', 'marketing/client_logo',
            'tool/tool', 'sale/order', 'communication/contact_message', 'setting/webhook',
            'setting/setting', 'setting/site_info', 'setting/appearance', 'setting/marketplace',
            'localization/language', 'blog/blog', 'blog/import',
            'custom_field/definition', 'design/theme',
        ]; // Todas as rotas administrativas consolidadas em schema-complete.sql
        // F8-01 (ADR 0045 §bugfix-escape-ACL): UNESCAPED_SLASHES impede que
        // real_escape_string dobre o '\' produzido pelo json_encode padrão —
        // combinando, os valores ficavam "user\\/user" e nenhum hasPermission
        // batia nas rotas recém-semeadas (302 mudo em cascata no admin).
        $permission = json_encode(['access' => $seedRoutes, 'modify' => $seedRoutes], JSON_UNESCAPED_SLASHES)
            ?: '{"access":[],"modify":[]}';

        if ($handle instanceof \mysqli) {
            $e = fn(string $v): string => $handle->real_escape_string($v);

            $handle->query("INSERT INTO `" . $e($prefix) . "user_group`
                SET `name` = 'Administrator', `permission` = '" . $e($permission) . "'");

            $group_id = (int)$handle->insert_id;

            $handle->query("INSERT INTO `" . $e($prefix) . "user`
                SET `user_group_id` = '" . $group_id . "',
                    `username`  = '" . $e($data['username']) . "',
                    `password`  = '" . $e(password_hash($data['password'], PASSWORD_DEFAULT)) . "',
                    `email`     = '" . $e($data['email']) . "',
                    `firstname` = '" . $e($data['firstname']) . "',
                    `lastname`  = '" . $e($data['lastname']) . "',
                    `image`     = '',
                    `status`    = '1',
                    `date_added` = NOW()");
        } else {
            $stmt = $handle->prepare("INSERT INTO `" . $prefix . "user_group` SET `name` = 'Administrator', `permission` = ?");
            $stmt->execute([$permission]);

            $group_id = (int)$handle->lastInsertId();

            $stmt = $handle->prepare("INSERT INTO `" . $prefix . "user`
                SET `user_group_id` = ?, `username` = ?, `password` = ?, `email` = ?,
                    `firstname` = ?, `lastname` = ?, `image` = '', `status` = '1', `date_added` = NOW()");
            $stmt->execute([
                $group_id,
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['email'],
                $data['firstname'],
                $data['lastname'],
            ]);
        }
    }
}
