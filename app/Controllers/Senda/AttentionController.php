<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\AttentionService;
use App\Services\Senda\EntryType;
use App\Services\Senda\EntryTypeContext;
use App\Services\Senda\FollowUpService;
use App\Services\Senda\PersonService;
use App\Services\Senda\ReferralInstitutionType;
use App\Validators\Senda\AttentionStoreValidator;
use App\Validators\Senda\AttentionUpdateValidator;
use Core\Request;
use Core\Session;

final class AttentionController extends SendaController
{
    public function __construct(
        private readonly AttentionService $attentions = new AttentionService(),
        private readonly PersonService $people = new PersonService(),
        private readonly FollowUpService $followUps = new FollowUpService()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->listFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->attentions->search($filters, $page);

        $this->sendaView('attentions/index', [
            'title' => 'Registro de Atención — SENDA',
            'attentions' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'entryTypes' => EntryType::options(),
            'staff' => $this->attentions->staffOptions(),
        ]);
    }

    public function create(): void
    {
        $type = $this->requireEntryType('attention');
        $person = $this->requirePersonForAttention();

        $this->sendaView('attentions/form', [
            'title' => 'Nueva atención — SENDA',
            'record' => null,
            'entryType' => EntryType::meta($type),
            'person' => $person,
            'isReferral' => $type === EntryType::DERIVACION,
            'institutionTypes' => ReferralInstitutionType::options(),
            'followups' => [],
            'defaults' => [
                'attention_date' => date('Y-m-d'),
                'attention_time' => date('H:i'),
            ],
        ]);
    }

    public function store(Request $request): void
    {
        $this->requireEntryType('attention');
        $person = $this->requirePersonForAttention();

        $payload = $request->all();
        $payload['entry_type'] = EntryTypeContext::resolveForStore($request->input('entry_type'));
        $payload['senda_person_id'] = $person['id'];

        $errors = (new AttentionStoreValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/senda/attentions/create'));
        }

        try {
            $this->attentions->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Atención registrada', 'Se asignó un correlativo único y quedó asociada a la persona.');
        if (!empty($person['id']) && hasPermission('senda.followups.view')) {
            $this->redirect(url('/senda/follow-ups/person/' . (int) $person['id']));
        }
        $this->redirect(url('/senda/attentions'));
    }

    public function show(Request $request, string $id): void
    {
        try {
            $record = $this->attentions->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $person = null;
        if (!empty($record['senda_person_id'])) {
            try {
                $person = $this->people->find((int) $record['senda_person_id'], true);
            } catch (\Throwable) {
                $person = null;
            }
        }

        $this->sendaView('attentions/show', [
            'title' => 'Ver atención — SENDA',
            'record' => $record,
            'entryType' => EntryType::meta((string) $record['entry_type']),
            'person' => $person,
            'isReferral' => $record['entry_type'] === EntryType::DERIVACION,
            'followups' => hasPermission('senda.followups.view')
                ? $this->followUps->all((int) $id)
                : [],
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $record = $this->attentions->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $person = null;
        if (!empty($record['senda_person_id'])) {
            try {
                $person = $this->people->find((int) $record['senda_person_id'], true);
            } catch (\Throwable) {
                $person = null;
            }
        }

        $this->sendaView('attentions/form', [
            'title' => 'Editar atención — SENDA',
            'record' => $record,
            'entryType' => EntryType::meta((string) $record['entry_type']),
            'person' => $person,
            'isReferral' => $record['entry_type'] === EntryType::DERIVACION,
            'institutionTypes' => ReferralInstitutionType::options(),
            'followups' => hasPermission('senda.followups.view')
                ? $this->followUps->all((int) $id)
                : [],
            'defaults' => [
                'attention_date' => (string) $record['attention_date'],
                'attention_time' => (string) ($record['attention_time_short'] ?? ''),
            ],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        try {
            $current = $this->attentions->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        $payload = $request->all();
        $payload['senda_person_id'] = $current['senda_person_id'];
        $payload['entry_type'] = $current['entry_type'];

        $errors = (new AttentionUpdateValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect(url('/senda/attentions/' . $id . '/edit'));
        }

        try {
            $this->attentions->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        Session::flashAlert('success', 'Atención actualizada', 'Los cambios se guardaron correctamente.');
        $this->redirect(url('/senda/attentions'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePersonForAttention(): array
    {
        $person = $this->people->current();

        if ($person === null) {
            Session::flashAlert(
                'info',
                'Identificar persona',
                'Busque el RUT antes de registrar la atención. Si ya existe, utilice ese registro.'
            );
            $this->redirect(url('/senda/people/create') . '?next=attention');
        }

        return $person;
    }

    /**
     * @return array<string, string>
     */
    private function listFilters(Request $request): array
    {
        $entryType = trim((string) $request->query('entry_type', ''));
        $ficha = trim((string) $request->query('ficha', ''));

        return [
            'q' => trim((string) $request->query('q', '')),
            'rut' => trim((string) $request->query('rut', '')),
            'name' => trim((string) $request->query('name', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'entry_type' => EntryType::isValid($entryType) ? $entryType : '',
            'created_by' => trim((string) $request->query('created_by', '')),
            'ficha' => in_array($ficha, ['con', 'sin'], true) ? $ficha : '',
        ];
    }
}
