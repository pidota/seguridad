<?php

declare(strict_types=1);

/**
 * Mapa de permisos por rol de sistema.
 * '*' otorga todos los permisos del catálogo.
 */
return [
    'superadministrador' => ['*'],

    'administrador_seguridad' => [
        'dashboard.access',
        'users.access', 'users.view', 'users.create', 'users.update', 'users.delete',
        'roles.access', 'roles.view', 'roles.create', 'roles.update',
        'permissions.view',
        'audit.access', 'audit.view',
        'settings.access',
        'security.access',
        'senda.access',
        'senda.dashboard.view',
        'senda.people.view',
        'senda.attentions.view',
        'senda.referrals.view',
        'senda.followups.view',
        'senda.statistics.view',
        'cctv.access',
        'cctv.dashboard.view',
        'cctv.shifts.view', 'cctv.shifts.create', 'cctv.shifts.close', 'cctv.shifts.view_all', 'cctv.shifts.edit_closed',
        'cctv.log.view', 'cctv.log.create', 'cctv.log.edit', 'cctv.log.delete', 'cctv.log.view_all', 'cctv.log.edit_closed',
        'cctv.cameras.view', 'cctv.cameras.manage',
        'cctv.reports.view', 'cctv.reports.export',
        'women.access', 'women.cases.view',
        'guards.access', 'guards.shifts.view', 'guards.shifts.create', 'guards.shifts.update',
    ],

    'operador_camaras' => [
        'dashboard.access',
        'cctv.access',
        'cctv.dashboard.view',
        'cctv.shifts.view',
        'cctv.shifts.create',
        'cctv.shifts.close',
        'cctv.log.view',
        'cctv.log.create',
        'cctv.log.edit',
        'cctv.cameras.view',
    ],

    'senda' => [
        'dashboard.access',
        'senda.access',
        'senda.dashboard.view',
        'senda.people.view', 'senda.people.create', 'senda.people.edit',
        'senda.attentions.view', 'senda.attentions.create', 'senda.attentions.edit',
        'senda.referrals.view', 'senda.referrals.create', 'senda.referrals.edit',
        'senda.followups.view', 'senda.followups.create', 'senda.followups.edit', 'senda.followups.delete',
        'senda.statistics.view',
    ],

    'oficina_mujer' => [
        'dashboard.access',
        'women.access',
        'women.cases.view',
        'women.cases.create',
        'women.cases.update',
    ],

    'guardias' => [
        'dashboard.access',
        'guards.access',
        'guards.shifts.view',
        'guards.shifts.create',
        'guards.shifts.update',
    ],

    'consulta' => [
        'dashboard.access',
        'security.access',
        'senda.access',
        'senda.dashboard.view',
        'senda.people.view',
        'senda.attentions.view',
        'senda.referrals.view',
        'senda.followups.view',
        'senda.statistics.view',
        'cctv.access',
        'cctv.dashboard.view',
        'cctv.shifts.view',
        'cctv.shifts.view_all',
        'cctv.log.view',
        'cctv.log.view_all',
        'cctv.cameras.view',
        'cctv.reports.view',
        'women.access', 'women.cases.view',
        'guards.access', 'guards.shifts.view',
    ],
];
