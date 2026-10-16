<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Fachada de acesso a dados (Arquitetura-MVCL §3): Registry expõe como $this->db.
 * O driver concreto vem do config (db_engine): library/db/{mysqli,pdo,...}.php.
 */
namespace Reamur\System\Library;

final class Db {
    private object $adaptor;

    public function __construct(
        string $adaptor,
        string $hostname,
        string $username,
        string $password,
        string $database,
        string $port = '3306'
    ) {
        $class = __NAMESPACE__ . '\\Db\\' . ucfirst($adaptor);

        if (!class_exists($class)) {
            throw new \Exception('Erro: driver de banco não encontrado: ' . $adaptor);
        }

        $this->adaptor = new $class($hostname, $username, $password, $database, $port);
    }

    /**
     * SELECT devolve \stdClass com ->row / ->rows / ->num_rows;
     * INSERT/UPDATE/DDL devolve bool.
     */
    public function query(string $sql): \stdClass|bool {
        return $this->adaptor->query($sql);
    }

    public function escape(string $value): string {
        return $this->adaptor->escape($value);
    }

    public function countAffected(): int {
        return $this->adaptor->countAffected();
    }

    public function getLastId(): int {
        return $this->adaptor->getLastId();
    }

    public function getDriver(): string {
        return $this->adaptor::class;
    }
}
