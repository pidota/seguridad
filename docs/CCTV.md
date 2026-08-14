# Módulo CCTV — Central de Cámaras

Documentación operativa y técnica del módulo de videovigilancia municipal.

---

## Objetivo

Registrar de forma trazable la operación diaria de la central de monitoreo CCTV:

- Turnos de operadores con recepción y entrega de equipos.
- Bitácora de novedades, incidentes, coordinaciones y fallas técnicas.
- Inventario y estado de cámaras.
- Indicadores de supervisión y auditoría de acciones críticas.

El módulo sustituye el registro en planillas Excel por un flujo estructurado, con permisos, validaciones y historial auditable.

---

## Flujo operativo

```
OPERADOR
    ↓
INICIA TURNO
    ↓
RECIBE EQUIPOS
    ↓
BITÁCORA ACTIVA
    │
    ├── Novedad
    ├── Incidente
    ├── Novedad técnica
    └── Coordinación
    ↓
ENTREGA EQUIPOS
    ↓
FINALIZA TURNO
```

### Descripción del flujo

| Paso | Qué ocurre | Permiso típico |
|------|------------|----------------|
| Inicia turno | Se crea un registro en `cctv_shifts` con estado `open`. Solo un turno abierto por operador. | `cctv.shifts.create` |
| Recibe equipos | Checklist de recepción (`opening`) sobre equipos activos del catálogo. | `cctv.shifts.create` |
| Bitácora activa | Entradas en `cctv_log_entries` asociadas al turno abierto. | `cctv.log.create` |
| Entrega equipos | Checklist de entrega (`closing`) al finalizar. | `cctv.shifts.close` |
| Finaliza turno | Turno pasa a `closed`; no se admiten nuevas entradas en ese turno. | `cctv.shifts.close` |

La recepción y entrega de equipos **no** generan filas en `cctv_log_entries`, pero sí registros en `cctv_shift_equipment_checks` y aparecen en la **línea de tiempo** del turno como eventos sintéticos (inicio/cierre).

---

## Arquitectura

Patrón **MVC + servicios + repositorios**, bajo el prefijo de rutas `/cctv`.

```
config/routes/cctv.php          → Rutas y permisos por endpoint
app/Controllers/
  Camera/DashboardController    → Panel principal
  Camera/EventController        → Listado y edición de bitácora
  Cctv/ShiftController          → Turnos
  Cctv/LogEntryController       → Alta de novedades/incidentes/técnicas
  Cctv/CameraController         → Inventario de cámaras
app/Services/Cctv/              → Lógica de negocio
app/Repositories/Cctv/          → Acceso a datos (SQL)
app/Models/Cctv/                → Constantes y entidades
app/Validators/Cctv/            → Validación de formularios
app/Views/camera/               → Vistas del módulo
resources/js/modules/cctv/      → Comportamiento front-end
resources/scss/modules/cctv/    → Estilos del módulo
tests/cctv_*_functional.php     → Pruebas funcionales
```

### Capas principales

| Servicio | Responsabilidad |
|----------|-----------------|
| `ShiftService` | Apertura/cierre de turno, recepción/entrega de equipos, historial |
| `LogEntryService` | CRUD de bitácora, timeline, presentación, contactos |
| `CameraService` | Inventario y estado operativo de cámaras |
| `StatisticsService` | Indicadores de supervisión y tiempo de respuesta Carabineros |
| `CctvAuditService` | Auditoría semántica del módulo |
| `ClosedShiftPolicy` | Reglas de edición/anulación en turnos cerrados e IDOR |

---

## Permisos

Acceso al módulo: middleware `can:cctv.access` en el grupo `/cctv`.

| Permiso | Descripción |
|---------|-------------|
| `cctv.access` | Ingreso al módulo |
| `cctv.dashboard.view` | Panel principal |
| `cctv.shifts.view` | Ver turnos propios |
| `cctv.shifts.create` | Abrir turno |
| `cctv.shifts.close` | Cerrar turno (con entrega de equipos) |
| `cctv.shifts.view_all` | Ver turnos de todos los operadores (supervisión) |
| `cctv.shifts.edit_closed` | Modificar turnos ya cerrados |
| `cctv.log.view` | Ver bitácora propia |
| `cctv.log.view_all` | Ver bitácora de todos los operadores |
| `cctv.log.create` | Registrar entradas |
| `cctv.log.edit` | Editar entradas en turno abierto |
| `cctv.log.edit_closed` | Editar entradas de turnos cerrados |
| `cctv.log.delete` | Anular entradas (soft delete) |
| `cctv.cameras.view` | Consultar cámaras |
| `cctv.cameras.manage` | Administrar cámaras |
| `cctv.reports.view` | Ver reportes e indicadores |
| `cctv.reports.export` | Exportar reportes |

### Roles predefinidos

| Rol | Alcance CCTV |
|-----|--------------|
| `superadministrador` | Todos los permisos (`*`) |
| `administrador_seguridad` | Operación completa + supervisión + anulación + cámaras |
| `operador_camaras` | Turno propio, bitácora propia, consulta de cámaras |
| `consulta` | Solo lectura: dashboard, todos los turnos, toda la bitácora, cámaras, reportes |

---

## Tablas

### Operación

| Tabla | Propósito |
|-------|-----------|
| `cctv_shifts` | Turnos de operador (`open` / `closed`) |
| `cctv_log_entries` | Entradas de bitácora |
| `cctv_log_contacts` | Avisos/coordinaciones asociados a una entrada |
| `cctv_shift_equipment_checks` | Recepción y entrega de equipos por turno |

### Catálogos

| Tabla | Propósito |
|-------|-----------|
| `cctv_log_types` | Tipos de registro (novedad, incidente, novedad técnica, etc.) |
| `cctv_incident_types` | Clasificación de incidentes |
| `cctv_technical_issue_types` | Clasificación de fallas técnicas |
| `cctv_equipment` | Equipos del puesto (celular, monitores, joystick, etc.) |

### Infraestructura

| Tabla | Propósito |
|-------|-----------|
| `cctv_cameras` | Inventario de cámaras y su estado operativo |
| `sectors` | Sectores territoriales (compartido con otros módulos) |
| `users` | Operadores y autores de registros |

### Auditoría

Las acciones CCTV se registran en la tabla global de auditoría (`audit_logs`) vía `CctvAuditService` → `AuditService`, con módulo `cctv`.

---

## Relaciones

```
users
  ├── cctv_shifts.operator_id
  ├── cctv_log_entries.created_by
  ├── cctv_log_entries.cancelled_by
  └── cctv_shift_equipment_checks.checked_by

cctv_shifts (1) ──< (N) cctv_log_entries
cctv_shifts (1) ──< (N) cctv_shift_equipment_checks

cctv_log_entries (1) ──< (N) cctv_log_contacts

cctv_log_entries ──> cctv_log_types
cctv_log_entries ──> cctv_incident_types        (nullable)
cctv_log_entries ──> cctv_technical_issue_types (nullable)
cctv_log_entries ──> cctv_cameras               (nullable)
cctv_log_entries ──> cctv_equipment             (nullable)
cctv_log_entries ──> sectors                    (nullable)

cctv_shift_equipment_checks ──> cctv_equipment

cctv_cameras ──> sectors (nullable)
```

Toda entrada de bitácora **debe** pertenecer a un turno (`cctv_shift_id` NOT NULL).

---

## Flujo de turno

### Apertura

1. El operador accede a `/cctv/shifts/create`.
2. `ShiftService::openWithReception()`:
   - Verifica que no exista otro turno abierto para el mismo operador.
   - Crea el turno con `status = open`, `started_at` y `shift_date`.
   - Persiste checks de equipos en fase `opening`.
   - Registra auditoría `shift_opened`.

### Operación

- Mientras el turno está `open`, el operador puede crear entradas de bitácora.
- `LogEntryService` valida que el turno del operador siga abierto antes de insertar.
- El dashboard (`/cctv`) muestra el turno activo, estadísticas del turno y últimas entradas.

### Cierre

1. El operador accede a `/cctv/shifts/close`.
2. `ShiftService::closeWithDelivery()`:
   - Verifica que el turno pertenezca al operador autenticado.
   - Persiste checks de equipos en fase `closing`.
   - Marca el turno como `closed` con `ended_at` y `closing_notes`.
   - Registra auditoría `shift_closed`.

### Restricciones

- Un operador solo puede tener **un turno abierto** a la vez.
- Tras el cierre, las entradas del turno son de solo lectura para el operador estándar.
- Supervisores con `cctv.log.edit_closed` / `cctv.shifts.edit_closed` pueden modificar registros históricos.

---

## Recepción y entrega de equipos

Equipos seed: celular, computador, monitores, joystick, sistema CCTV.

Cada check registra:

| Campo | Valores |
|-------|---------|
| `check_phase` | `opening` (recepción) / `closing` (entrega) |
| `status` | `operativo`, `con_observaciones`, `no_operativo` |
| `observations` | Texto libre opcional |
| `checked_at` | Momento del registro |
| `checked_by` | Usuario que confirma |

Restricción única: un equipo solo puede tener un check por fase y turno (`cctv_shift_id + cctv_equipment_id + check_phase`).

En la línea de tiempo del turno, la recepción resume el estado de los equipos recibidos; la entrega resume el estado al cierre.

---

## Bitácora

Tabla central: `cctv_log_entries`.

### Campos comunes

| Campo | Descripción |
|-------|-------------|
| `occurred_at` | Fecha y hora del suceso |
| `observations` | Descripción (obligatoria) |
| `cctv_shift_id` | Turno al que pertenece |
| `cctv_log_type_id` | Tipo de registro |
| `sector_id` | Sector (opcional) |
| `status` | Estado del registro |
| `created_by` | Operador que registró |
| `deleted_at` / `cancelled_by` | Anulación (soft delete) |

### Tipos de registro (`cctv_log_types`)

| Slug | Uso |
|------|-----|
| `novedad` | Registro general de observación |
| `incidente` | Hecho que requiere clasificación y seguimiento |
| `novedad_tecnica` | Falla o problema de equipos/cámaras |
| `comunicacion_coordinacion` | Tipo catalogado (uso reservado) |
| `recepcion_entrega` | Tipo catalogado (recepción/entrega real va en checks de turno) |
| `otro` | Registro no listado |

### Rutas de alta

| Tipo | Ruta |
|------|------|
| Novedad general | `GET/POST /cctv/log/create` |
| Incidente | `GET/POST /cctv/log/incident/create` |
| Novedad técnica | `GET/POST /cctv/log/technical/create` |
| Consulta / edición | `GET /cctv/log`, `GET/PUT /cctv/log/{id}` |
| Anulación | `DELETE /cctv/log/{id}` |

---

## Incidentes

Formulario dedicado con validador `IncidentStoreValidator`.

### Campos específicos

| Campo | Descripción |
|-------|-------------|
| `cctv_incident_type_id` | Tipo de incidente (catálogo) |
| `incident_type_other` | Obligatorio si el tipo es `otro` |
| `sector_id` | Sector del hecho |
| `cctv_camera_id` | Cámara relacionada (opcional) |
| `police_arrived` | `0` No / `1` Sí / `2` No aplica |
| `police_arrival_time` | Hora de llegada (obligatoria si `police_arrived = 1`) |
| `coordination_notified` | `1` si hubo aviso o coordinación |
| `status` | `registrado`, `en_desarrollo`, `finalizado` |

### Tipos de incidente (catálogo)

Consumo de alcohol en vía pública, riña, violencia, vehículo mal estacionado, situación sospechosa, daños, accidente, emergencia, otro.

---

## Coordinaciones

Las coordinaciones **no son un tipo de bitácora independiente** en la implementación actual: se registran **dentro de un incidente** cuando `coordination_notified = 1`.

### Contactos (`cctv_log_contacts`)

| Campo | Descripción |
|-------|-------------|
| `contact_type` | Institución contactada |
| `contact_name` | Nombre/referencia (obligatorio si tipo = `otro`) |
| `contacted_at` | Fecha-hora del aviso |
| `notes` | Observaciones del contacto |

### Tipos de contacto

`carabineros`, `seguridad_municipal`, `guardias_municipales`, `bomberos`, `samu`, `pdi`, `otro`.

Los contactos solo se persisten si `coordination_notified = 1`. Un incidente puede tener **varios contactos** (por ejemplo Carabineros y Guardias Municipales).

En estadísticas, las coordinaciones se cuentan por entradas con `coordination_notified = 1`. El tiempo de respuesta de Carabineros usa el primer aviso a Carabineros o, en su defecto, la hora del suceso.

---

## Novedades técnicas

Formulario dedicado con validador `TechnicalStoreValidator`.

### Campos específicos

| Campo | Descripción |
|-------|-------------|
| `cctv_technical_issue_type_id` | Tipo de falla |
| `technical_issue_other` | Detalle si el tipo es `otro` |
| `cctv_camera_id` | Cámara afectada (opcional) |
| `cctv_equipment_id` | Equipo del puesto afectado (opcional) |
| `camera_status_applied` | Estado aplicado a la cámara al registrar |
| `status` | `detectado`, `pendiente`, `operativo_nuevamente` |

### Tipos técnicos (catálogo)

Sin señal, imagen congelada, intermitencia, sin video, equipo sin respuesta, otro.

Al registrar una novedad técnica con cámara asociada, `CameraService::applyStatus()` puede actualizar el estado de la cámara en `cctv_cameras` (`operativa`, `con_problemas`, `fuera_de_servicio`).

---

## Cierre

Al finalizar el turno:

1. **Entrega de equipos** — checklist `closing` con el mismo catálogo de recepción.
2. **Notas de cierre** — campo `closing_notes` en el turno.
3. **Resumen** — el formulario de cierre muestra estadísticas del turno (entradas, incidentes, técnicas, coordinaciones).
4. **Timeline** — se agrega evento sintético «CIERRE DE TURNO» con resumen de entrega.

Tras el cierre:

- No se pueden crear entradas en ese turno.
- Edición/anulación requiere permisos `*_edit_closed`.
- El operador puede iniciar un nuevo turno.

---

## Auditoría

Servicio: `CctvAuditService` (delega en `AuditService` global).

### Eventos registrados

| Evento | Cuándo |
|--------|--------|
| `shift_opened` | Apertura de turno (incluye checks de recepción) |
| `shift_closed` | Cierre de turno (incluye checks de entrega) |
| `shift_updated` | Modificación de turno abierto |
| `closed_shift_updated` | Modificación de turno cerrado |
| `log_entry_created` | Nueva entrada de bitácora |
| `log_entry_updated` | Edición de entrada |
| `log_entry_cancelled` | Anulación (soft delete) |
| `incident_created` / `incident_updated` | Alta/edición de incidente |
| `coordination_registered` | Contactos en incidente con coordinación |
| `camera_status_changed` | Cambio de estado de cámara |

Los snapshots de auditoría **no almacenan texto completo** de observaciones; incluyen extractos truncados (~120 caracteres) y metadatos estructurados.

---

## Seguridad

### Autenticación y autorización

- Todas las rutas `/cctv/*` exigen sesión (`auth`) y permiso `cctv.access`.
- Cada endpoint aplica permisos granulares (`can:cctv.*`).
- Los roles se definen en `config/roles.php`; los permisos en `config/permissions.php`.

### Control de acceso a datos (IDOR)

`ClosedShiftPolicy` centraliza las reglas:

| Acción | Regla |
|--------|-------|
| Ver/editar entrada ajena | Requiere `cctv.log.view_all` o ser `created_by` |
| Editar en turno cerrado | Requiere `cctv.log.edit_closed` |
| Anular entrada | Requiere `cctv.log.delete` + autoría (o `view_all`) |
| Anular en turno cerrado | Además requiere `cctv.log.edit_closed` |
| Cerrar turno | Solo el operador dueño del turno |
| Ver historial de turnos | Operador ve los suyos; supervisión con `cctv.shifts.view_all` |

### Validación de entrada

- Validadores dedicados por tipo de formulario (`IncidentStoreValidator`, `TechnicalStoreValidator`, `LogEntryStoreValidator`, `ShiftReceptionValidator`).
- Fechas, horas y catálogos se validan contra listas permitidas.
- Coherencia policial: hora de llegada solo si Carabineros = Sí; contactos solo si coordinación = Sí.

### Integridad operativa

- Turno abierto obligatorio para registrar bitácora.
- Transacciones de base de datos en operaciones compuestas (apertura con recepción, cierre con entrega, alta con contactos).
- Soft delete en entradas (`deleted_at` + `cancelled_by`); no se eliminan filas físicamente.
- Foreign keys con `RESTRICT` / `CASCADE` según corresponda.

### Protección de formularios

- Scripts `unsaved.js` en formularios de novedad, incidente y cierre de turno advierten antes de salir con cambios sin guardar.

---

## Indicadores de supervisión

Panel accesible con `cctv.shifts.view_all` en `/cctv`:

- Incidentes hoy / del mes.
- Novedades técnicas, comunicaciones a Carabineros, cámaras con problemas.
- Desglose de incidentes por sector y por tipo (mes).
- Registros por turno (últimos turnos).
- Tiempo de respuesta de Carabineros (promedio, mínimo, máximo, casos calculables).

Servicios: `StatisticsService`, `PoliceResponseTimeCalculator`, `LogEntryRepository`.

---

## Pruebas

Suites funcionales en `tests/`:

| Archivo | Cobertura |
|---------|-----------|
| `cctv_shifts_functional.php` | Turnos, dashboard, supervisión |
| `cctv_shift_workflow_functional.php` | Flujo completo apertura → bitácora → cierre |
| `cctv_shift_reception_functional.php` | Recepción/entrega de equipos |
| `cctv_log_entries_functional.php` | Bitácora general |
| `cctv_incidents_functional.php` | Incidentes, Carabineros, contactos |
| `cctv_permissions_functional.php` | Permisos e IDOR |
| `cctv_audit_functional.php` | Auditoría |
| `cctv_statistics_functional.php` | Indicadores y tiempo de respuesta |
| `cctv_cameras_functional.php` | Inventario de cámaras |
| `cctv_catalogs_functional.php` | Catálogos |

Ejemplo de ejecución:

```bash
php tests/cctv_shift_workflow_functional.php
php tests/cctv_permissions_functional.php
```

---

## Rutas principales

| Método | Ruta | Nombre |
|--------|------|--------|
| GET | `/cctv/` | Dashboard |
| GET/POST | `/cctv/shifts`, `/cctv/shifts/create` | Turnos |
| GET/POST | `/cctv/shifts/close` | Cierre de turno |
| GET | `/cctv/shifts/{id}` | Detalle de turno |
| GET | `/cctv/log` | Bitácora |
| GET/POST | `/cctv/log/create` | Nueva novedad |
| GET/POST | `/cctv/log/incident/create` | Nuevo incidente |
| GET/POST | `/cctv/log/technical/create` | Nueva novedad técnica |
| GET | `/cctv/log/{id}` | Detalle de entrada |
| GET | `/cctv/cameras` | Inventario de cámaras |

---

## Migraciones

Las tablas del módulo se crean y evolucionan en `database/migrations/033` a `050` (prefijo `cctv_`). Ejecutar migraciones con el script habitual del proyecto antes de desplegar.
