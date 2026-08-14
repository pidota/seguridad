<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\Database;

final class UserRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower($email)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => mb_strtolower($email)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function paginate(int $page, int $perPage, ?string $search = null): array
    {
        $where = '';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where = 'WHERE u.name LIKE :q OR u.email LIKE :q2';
            $term = '%' . trim($search) . '%';
            $params['q'] = $term;
            $params['q2'] = $term;
        }

        $countSql = "SELECT COUNT(*) FROM users u {$where}";
        $count = $this->db()->prepare($countSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT u.* FROM users u {$where} ORDER BY u.name ASC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = $this->hydrate($row);
        }

        return ['data' => $rows, 'total' => $total];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO users (name, email, password, is_active, must_change_password)
                VALUES (:name, :email, :password, :is_active, :must_change_password)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active' => (int) $data['is_active'],
            'must_change_password' => (int) ($data['must_change_password'] ?? 0),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'name = :name',
            'email = :email',
            'is_active = :is_active',
        ];
        $params = [
            'id' => $id,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'is_active' => (int) $data['is_active'],
        ];

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $fields[] = 'must_change_password = 0';
            $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db()->prepare('UPDATE users SET is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function updatePassword(int $id, string $plainPassword): void
    {
        $sql = 'UPDATE users
                SET password = :password, must_change_password = 0, updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public function markLastLogin(int $id): void
    {
        $sql = 'UPDATE users SET last_login_at = NOW() WHERE id = :id';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function createPasswordReset(string $email, string $tokenHash, string $expiresAt): void
    {
        $sql = 'INSERT INTO password_resets (email, token, expires_at)
                VALUES (:email, :token, :expires_at)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'email' => mb_strtolower($email),
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db()->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);

        $insert = $this->db()->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );

        foreach ($roleIds as $roleId) {
            $insert->execute([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
            ]);
        }
    }

    public function countByRoleSlug(string $slug): int
    {
        $sql = 'SELECT COUNT(*) FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.slug = :slug';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['slug' => $slug]);

        return (int) $stmt->fetchColumn();
    }

    public function hasRole(int $userId, string $slug): bool
    {
        $sql = 'SELECT 1 FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :user_id AND r.slug = :slug
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'slug' => $slug]);

        return (bool) $stmt->fetchColumn();
    }

    private function hydrate(array $row): array
    {
        $roles = $this->rolesFor((int) $row['id']);
        $row['roles'] = $roles;
        $row['role_slugs'] = array_column($roles, 'slug');
        $row['role_names'] = implode(', ', array_column($roles, 'name'));
        $row['role_name'] = $row['role_names'];

        return $row;
    }

    private function rolesFor(int $userId): array
    {
        $sql = 'SELECT r.id, r.slug, r.name, r.is_system
                FROM roles r
                INNER JOIN user_roles ur ON ur.role_id = r.id
                WHERE ur.user_id = :id
                ORDER BY r.name ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll();
    }
}
