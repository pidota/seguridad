<?php

declare(strict_types=1);

return [
    ['slug' => 'dashboard.access', 'name' => 'Acceder al panel', 'module' => 'dashboard', 'description' => 'Ingreso al panel principal'],

    ['slug' => 'users.access', 'name' => 'Acceder a usuarios', 'module' => 'users', 'description' => 'Ver el menú de usuarios'],
    ['slug' => 'users.view', 'name' => 'Ver usuarios', 'module' => 'users', 'description' => 'Listar y consultar usuarios'],
    ['slug' => 'users.create', 'name' => 'Crear usuarios', 'module' => 'users', 'description' => 'Registrar nuevos usuarios'],
    ['slug' => 'users.update', 'name' => 'Editar usuarios', 'module' => 'users', 'description' => 'Modificar datos y roles'],
    ['slug' => 'users.delete', 'name' => 'Desactivar usuarios', 'module' => 'users', 'description' => 'Desactivar o reactivar cuentas'],

    ['slug' => 'roles.access', 'name' => 'Acceder a roles', 'module' => 'roles', 'description' => 'Ver el menú de roles'],
    ['slug' => 'roles.view', 'name' => 'Ver roles', 'module' => 'roles', 'description' => 'Listar roles y sus permisos'],
    ['slug' => 'roles.create', 'name' => 'Crear roles', 'module' => 'roles', 'description' => 'Registrar roles personalizados'],
    ['slug' => 'roles.update', 'name' => 'Editar roles', 'module' => 'roles', 'description' => 'Modificar roles y asignar permisos'],
    ['slug' => 'roles.delete', 'name' => 'Eliminar roles', 'module' => 'roles', 'description' => 'Eliminar roles no sistémicos'],

    ['slug' => 'permissions.view', 'name' => 'Ver catálogo de permisos', 'module' => 'permissions', 'description' => 'Consultar permisos disponibles'],

    ['slug' => 'audit.access', 'name' => 'Acceder a auditoría', 'module' => 'audit', 'description' => 'Ver el menú de auditoría'],
    ['slug' => 'audit.view', 'name' => 'Ver auditoría', 'module' => 'audit', 'description' => 'Consultar bitácora inmutable'],

    ['slug' => 'settings.access', 'name' => 'Acceder a configuración', 'module' => 'settings', 'description' => 'Ver la configuración institucional'],

    ['slug' => 'security.access', 'name' => 'Acceder a Seguridad Municipal', 'module' => 'security', 'description' => 'Ingreso al módulo de seguridad comunal'],

    ['slug' => 'senda.access', 'name' => 'Acceder a SENDA', 'module' => 'senda', 'description' => 'Ingreso al módulo SENDA'],
    ['slug' => 'senda.dashboard.view', 'name' => 'Ver panel SENDA', 'module' => 'senda', 'description' => 'Ver el dashboard del módulo'],

    ['slug' => 'senda.people.view', 'name' => 'Ver personas SENDA', 'module' => 'senda', 'description' => 'Consultar personas atendidas'],
    ['slug' => 'senda.people.create', 'name' => 'Crear personas SENDA', 'module' => 'senda', 'description' => 'Registrar personas'],
    ['slug' => 'senda.people.edit', 'name' => 'Editar personas SENDA', 'module' => 'senda', 'description' => 'Modificar personas'],

    ['slug' => 'senda.attentions.view', 'name' => 'Ver atenciones SENDA', 'module' => 'senda', 'description' => 'Consultar atenciones'],
    ['slug' => 'senda.attentions.create', 'name' => 'Crear atenciones SENDA', 'module' => 'senda', 'description' => 'Registrar atenciones'],
    ['slug' => 'senda.attentions.edit', 'name' => 'Editar atenciones SENDA', 'module' => 'senda', 'description' => 'Modificar atenciones'],

    ['slug' => 'senda.referrals.view', 'name' => 'Ver derivaciones SENDA', 'module' => 'senda', 'description' => 'Consultar derivaciones'],
    ['slug' => 'senda.referrals.create', 'name' => 'Crear derivaciones SENDA', 'module' => 'senda', 'description' => 'Registrar derivaciones'],
    ['slug' => 'senda.referrals.edit', 'name' => 'Editar derivaciones SENDA', 'module' => 'senda', 'description' => 'Modificar derivaciones abiertas'],
    ['slug' => 'senda.referrals.edit_completed', 'name' => 'Editar fichas SENDA finalizadas', 'module' => 'senda', 'description' => 'Modificar fichas de referencia ya finalizadas'],

    ['slug' => 'senda.followups.view', 'name' => 'Ver seguimientos SENDA', 'module' => 'senda', 'description' => 'Consultar seguimientos'],
    ['slug' => 'senda.followups.create', 'name' => 'Crear seguimientos SENDA', 'module' => 'senda', 'description' => 'Registrar seguimientos'],
    ['slug' => 'senda.followups.edit', 'name' => 'Editar seguimientos SENDA', 'module' => 'senda', 'description' => 'Modificar seguimientos'],
    ['slug' => 'senda.followups.delete', 'name' => 'Eliminar seguimientos SENDA', 'module' => 'senda', 'description' => 'Eliminar seguimientos'],

    ['slug' => 'senda.statistics.view', 'name' => 'Ver estadísticas SENDA', 'module' => 'senda', 'description' => 'Consultar indicadores del módulo'],

    ['slug' => 'cctv.access', 'name' => 'Acceder a Central CCTV', 'module' => 'cctv', 'description' => 'Ingreso al módulo de videovigilancia'],
    ['slug' => 'cctv.dashboard.view', 'name' => 'Ver panel CCTV', 'module' => 'cctv', 'description' => 'Consultar el dashboard de la central'],

    ['slug' => 'cctv.shifts.view', 'name' => 'Ver turnos CCTV', 'module' => 'cctv', 'description' => 'Consultar turnos propios o activos'],
    ['slug' => 'cctv.shifts.create', 'name' => 'Abrir turnos CCTV', 'module' => 'cctv', 'description' => 'Iniciar un turno operativo'],
    ['slug' => 'cctv.shifts.close', 'name' => 'Cerrar turnos CCTV', 'module' => 'cctv', 'description' => 'Finalizar un turno operativo'],
    ['slug' => 'cctv.shifts.view_all', 'name' => 'Ver todos los turnos CCTV', 'module' => 'cctv', 'description' => 'Consultar turnos de todos los operadores'],
    ['slug' => 'cctv.shifts.edit_closed', 'name' => 'Editar turnos CCTV cerrados', 'module' => 'cctv', 'description' => 'Modificar turnos ya finalizados'],

    ['slug' => 'cctv.log.view', 'name' => 'Ver bitácora CCTV', 'module' => 'cctv', 'description' => 'Consultar novedades registradas'],
    ['slug' => 'cctv.log.create', 'name' => 'Registrar novedades CCTV', 'module' => 'cctv', 'description' => 'Crear entradas en la bitácora'],
    ['slug' => 'cctv.log.edit', 'name' => 'Editar novedades CCTV', 'module' => 'cctv', 'description' => 'Modificar entradas abiertas de la bitácora'],
    ['slug' => 'cctv.log.delete', 'name' => 'Anular registros CCTV', 'module' => 'cctv', 'description' => 'Anular entradas de la bitácora operativa'],
    ['slug' => 'cctv.log.view_all', 'name' => 'Ver toda la bitácora CCTV', 'module' => 'cctv', 'description' => 'Consultar novedades de todos los operadores'],
    ['slug' => 'cctv.log.edit_closed', 'name' => 'Editar novedades CCTV cerradas', 'module' => 'cctv', 'description' => 'Modificar entradas asociadas a turnos cerrados'],

    ['slug' => 'cctv.cameras.view', 'name' => 'Ver cámaras CCTV', 'module' => 'cctv', 'description' => 'Consultar el inventario de cámaras'],
    ['slug' => 'cctv.cameras.manage', 'name' => 'Administrar cámaras CCTV', 'module' => 'cctv', 'description' => 'Crear, editar y dar de baja cámaras'],

    ['slug' => 'cctv.reports.view', 'name' => 'Ver reportes CCTV', 'module' => 'cctv', 'description' => 'Consultar reportes e indicadores'],
    ['slug' => 'cctv.reports.export', 'name' => 'Exportar reportes CCTV', 'module' => 'cctv', 'description' => 'Descargar reportes en formatos exportables'],

    ['slug' => 'women.access', 'name' => 'Acceder a Oficina de la Mujer', 'module' => 'women', 'description' => 'Ingreso al módulo'],
    ['slug' => 'women.cases.view', 'name' => 'Ver casos de la mujer', 'module' => 'women', 'description' => 'Consultar casos'],
    ['slug' => 'women.cases.create', 'name' => 'Crear casos de la mujer', 'module' => 'women', 'description' => 'Registrar casos'],
    ['slug' => 'women.cases.update', 'name' => 'Editar casos de la mujer', 'module' => 'women', 'description' => 'Modificar casos'],

    ['slug' => 'guards.access', 'name' => 'Acceder a Guardias Municipales', 'module' => 'guards', 'description' => 'Ingreso al módulo'],
    ['slug' => 'guards.shifts.view', 'name' => 'Ver turnos de guardias', 'module' => 'guards', 'description' => 'Consultar turnos y novedades'],
    ['slug' => 'guards.shifts.create', 'name' => 'Crear turnos de guardias', 'module' => 'guards', 'description' => 'Registrar turnos'],
    ['slug' => 'guards.shifts.update', 'name' => 'Editar turnos de guardias', 'module' => 'guards', 'description' => 'Modificar turnos'],
];
