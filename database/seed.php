<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse por consola.\n");
    exit(1);
}

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$pdo = Core\Database::connection();

$roleCatalog = [
    'superadministrador' => ['Superadministrador', 'Acceso total a la plataforma', 1],
    'administrador_seguridad' => ['Administrador Seguridad', 'Administración de usuarios, roles y módulos de seguridad', 1],
    'operador_camaras' => ['Operador Cámaras', 'Operación de la central de videovigilancia', 1],
    'senda' => ['SENDA', 'Atenciones y seguimiento comunitario', 1],
    'oficina_mujer' => ['Oficina de la Mujer', 'Atención y seguimiento de casos', 1],
    'guardias' => ['Guardias', 'Turnos, rondas y novedades', 1],
    'consulta' => ['Consulta', 'Acceso de solo lectura a los módulos operativos', 1],
];

$renames = [
    'administrador' => 'superadministrador',
    'seguridad_municipal' => 'administrador_seguridad',
    'central_camaras' => 'operador_camaras',
    'guardia_municipal' => 'guardias',
];

$renameStmt = $pdo->prepare('UPDATE roles SET slug = :new_slug WHERE slug = :old_slug');
foreach ($renames as $old => $new) {
    $existsNew = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
    $existsNew->execute(['slug' => $new]);
    if ($existsNew->fetch()) {
        continue;
    }
    $renameStmt->execute(['new_slug' => $new, 'old_slug' => $old]);
}

$upsertRole = $pdo->prepare(
    'INSERT INTO roles (slug, name, description, is_system)
     VALUES (:slug, :name, :description, :is_system)
     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_system = VALUES(is_system)'
);

foreach ($roleCatalog as $slug => [$name, $description, $isSystem]) {
    $upsertRole->execute([
        'slug' => $slug,
        'name' => $name,
        'description' => $description,
        'is_system' => $isSystem,
    ]);
}

$permissions = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'permissions.php';
$upsertPermission = $pdo->prepare(
    'INSERT INTO permissions (slug, name, module, description)
     VALUES (:slug, :name, :module, :description)
     ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description)'
);

foreach ($permissions as $permission) {
    $upsertPermission->execute($permission);
}

$permissionIds = [];
foreach ($pdo->query('SELECT id, slug FROM permissions')->fetchAll() as $row) {
    $permissionIds[$row['slug']] = (int) $row['id'];
}

$roleIds = [];
foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll() as $row) {
    $roleIds[$row['slug']] = (int) $row['id'];
}

foreach ($renames as $oldSlug => $newSlug) {
    if (!isset($roleIds[$oldSlug], $roleIds[$newSlug])) {
        continue;
    }

    $oldId = $roleIds[$oldSlug];
    $newId = $roleIds[$newSlug];

    $move = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id)
         SELECT user_id, :new_id FROM user_roles WHERE role_id = :old_id'
    );
    $move->execute(['new_id' => $newId, 'old_id' => $oldId]);
    $pdo->prepare('DELETE FROM user_roles WHERE role_id = :old_id')->execute(['old_id' => $oldId]);
    $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :old_id')->execute(['old_id' => $oldId]);
    $pdo->prepare('DELETE FROM roles WHERE id = :old_id')->execute(['old_id' => $oldId]);
}

$roleIds = [];
foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll() as $row) {
    $roleIds[$row['slug']] = (int) $row['id'];
}

$rolePermissions = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'roles.php';
$insertRp = $pdo->prepare(
    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
);
$deleteRp = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');

foreach ($rolePermissions as $slug => $slugs) {
    if (!isset($roleIds[$slug])) {
        continue;
    }

    $roleId = $roleIds[$slug];
    $deleteRp->execute(['role_id' => $roleId]);

    $assigned = $slugs === ['*'] ? array_keys($permissionIds) : $slugs;

    foreach ($assigned as $permissionSlug) {
        if (!isset($permissionIds[$permissionSlug])) {
            continue;
        }
        $insertRp->execute([
            'role_id' => $roleId,
            'permission_id' => $permissionIds[$permissionSlug],
        ]);
    }
}

$adminEmail = 'admin@municipalidad.local';
$exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$exists->execute(['email' => $adminEmail]);
$adminId = $exists->fetchColumn();

if (!$adminId) {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password, is_active, must_change_password)
         VALUES (:name, :email, :password, 1, 0)'
    );
    $insertUser->execute([
        'name' => 'Administrador del Sistema',
        'email' => $adminEmail,
        'password' => password_hash('Admin123!', PASSWORD_DEFAULT),
    ]);
    $adminId = (int) $pdo->lastInsertId();
    echo "Usuario inicial creado: {$adminEmail} / Admin123!\n";
} else {
    $adminId = (int) $adminId;
    echo "El usuario administrador ya existe.\n";
}

if (isset($roleIds['superadministrador'])) {
    $assign = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $adminId,
        'role_id' => $roleIds['superadministrador'],
    ]);
}

$consultaEmail = 'consulta@municipalidad.local';
$exists->execute(['email' => $consultaEmail]);
$consultaId = $exists->fetchColumn();

if (!$consultaId) {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password, is_active, must_change_password)
         VALUES (:name, :email, :password, 1, 0)'
    );
    $insertUser->execute([
        'name' => 'Usuario Consulta',
        'email' => $consultaEmail,
        'password' => password_hash('Consulta123!', PASSWORD_DEFAULT),
    ]);
    $consultaId = (int) $pdo->lastInsertId();
    echo "Usuario de consulta creado: {$consultaEmail} / Consulta123!\n";
} else {
    $consultaId = (int) $consultaId;
}

if (isset($roleIds['consulta'])) {
    $assign = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $consultaId,
        'role_id' => $roleIds['consulta'],
    ]);
}

echo "Roles, permisos y asignaciones sincronizados.\n";
