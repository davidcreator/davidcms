<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Serviço de usuário autenticado (ADR 0005 — F2-2).
 * Montado preguiçosamente pela pré-ação startup/login do admin (o front
 * controller é imutável por app — Contrato §2). Estado persistido na sessão
 * da aplicação e hidratado do banco a cada requisição; CRLF: login() exige
 * status=1, verifica password_hash/password_verify e re-hasheia quando o
 * algoritmo do PHP evoluir (higiene transparente de hash).
 */
namespace Reamur\System\Library;

final class User {
    private bool $logged = false;

    private int $user_id = 0;

    private string $username = '';

    private string $email = '';

    private string $firstname = '';

    private string $lastname = '';

    private int $user_group_id = 0;

    /** @var array{access: array<int, string>, modify: array<int, string>} */
    private array $permissions = ['access' => [], 'modify' => []];

    public function __construct(private \Reamur\System\Engine\Registry $registry) {
        $session = $this->session();

        if ($session instanceof Session && $this->db() instanceof Db) {
            $this->hydrate((int)($session->data['user_id'] ?? 0));
        }
    }

    /**
     * Autentica username+senha; em sucesso regenera a identidade da sessão
     * (mitigação de fixation) e hidrata o estado.
     */
    public function login(string $username, string $password): bool {
        $db = $this->db();

        if (!$db instanceof Db) {
            return false;
        }

        $query = $db->query(
            "SELECT u.user_id, u.password FROM `" . $this->prefix() . "user` AS u "
            . "WHERE u.username = '" . $db->escape($username) . "' AND u.status = '1' LIMIT 1"
        );

        if (!$query instanceof \stdClass || empty($query->row)) {
            return false;
        }

        $row = (array)$query->row;

        if (!password_verify($password, (string)$row['password'])) {
            return false;
        }

        // Higiene silenciosa: se o algoritmo padrão evoluir, re-hasheia no próximo login
        if (password_needs_rehash((string)$row['password'], PASSWORD_DEFAULT)) {
            $db->query(
                "UPDATE `" . $this->prefix() . "user` SET `password` = '"
                . $db->escape(password_hash($password, PASSWORD_DEFAULT))
                . "' WHERE user_id = '" . (int)$row['user_id'] . "'"
            );
        }

        $session = $this->session();

        if ($session instanceof Session) {
            $session->data['user_id'] = (int)$row['user_id'];
            $session->regenerateId();
        }

        $this->hydrate((int)$row['user_id']);

        return $this->isLogged();
    }

    /** Encerra a sessão e zera o estado local */
    public function logout(): void {
        $session = $this->session();

        if ($session instanceof Session) {
            $session->destroy();
        }

        $this->logged     = false;
        $this->user_id    = 0;
        $this->username   = '';
        $this->email      = '';
        $this->firstname  = '';
        $this->lastname   = '';
        $this->user_group_id = 0;
        $this->permissions   = ['access' => [], 'modify' => []];
    }

    /** ACL: a rota pedida consta na lista {type} do grupo do usuário? */
    public function hasPermission(string $type, string $route): bool {
        return in_array($route, $this->permissions[$type] ?? [], true);
    }

    /** Quantidade concedida no tipo (diagnóstico do dashboard) */
    public function countPermissions(string $type): int {
        return count($this->permissions[$type] ?? []);
    }

    public function isLogged(): bool {
        return $this->logged;
    }

    public function getUserId(): int {
        return $this->user_id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getFirstname(): string {
        return $this->firstname;
    }

    public function getLastname(): string {
        return $this->lastname;
    }

    public function getFullname(): string {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    public function getUserGroupId(): int {
        return $this->user_group_id;
    }

    /**
     * Hidrata o estado a partir do banco; usuário desativado perde a
     * sessão efetiva (logged=false) sem derrubar a aplicação.
     */
    private function hydrate(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        $db = $this->db();

        if (!$db instanceof Db) {
            return;
        }

        $query = $db->query(
            "SELECT u.user_id, u.username, u.email, u.firstname, u.lastname, u.user_group_id, u.status, ug.permission "
            . "FROM `" . $this->prefix() . "user` AS u "
            . "LEFT JOIN `" . $this->prefix() . "user_group` AS ug ON (u.user_group_id = ug.user_group_id) "
            . "WHERE u.user_id = '" . $user_id . "' AND u.status = '1' LIMIT 1"
        );

        if (!$query instanceof \stdClass || empty($query->row)) {
            $this->logout();

            return;
        }

        $row = (array)$query->row;

        $this->logged        = true;
        $this->user_id       = (int)$row['user_id'];
        $this->username      = (string)$row['username'];
        $this->email         = (string)$row['email'];
        $this->firstname     = (string)$row['firstname'];
        $this->lastname      = (string)$row['lastname'];
        $this->user_group_id = (int)$row['user_group_id'];
        $this->permissions   = $this->decodePermissions((string)($row['permission'] ?? ''));
    }

    /**
     * @return array{access: array<int, string>, modify: array<int, string>}
     */
    private function decodePermissions(string $json): array {
        $decoded = (array)json_decode($json, true);

        if (!is_array($decoded)) {
            return ['access' => [], 'modify' => []];
        }

        // F8-01 (ADR 0045 §bugfix-escape-ACL): instalações pré-fix podem ter
        // gravado valores "user\/user" (json_encode + real_escape_string no
        // wizard mysqli). Normalizamos no decode — inocuo e retro-compatível.
        $canon = static function (array $routes): array {
            return array_values(array_map(
                static fn($r): string => str_replace('\\/', '/', (string)$r),
                $routes
            ));
        };

        return [
            'access' => $canon((array)($decoded['access'] ?? [])),
            'modify' => $canon((array)($decoded['modify'] ?? [])),
        ];
    }

    private function prefix(): string {
        return defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
    }

    private function db(): ?Db {
        $db = $this->registry->get('db');

        return $db instanceof Db ? $db : null;
    }

    private function session(): ?Session {
        $session = $this->registry->get('session');

        return $session instanceof Session ? $session : null;
    }
}
