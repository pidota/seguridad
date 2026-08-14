<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\AttentionService;
use App\Services\Senda\FollowUpCatalog;
use App\Services\Senda\FollowUpService;
use App\Services\Senda\FollowUpStatus;
use App\Services\Senda\HistoryService;
use App\Services\Senda\PersonContext;
use App\Services\Senda\PersonService;
use App\Validators\Senda\FollowUpSearchValidator;
use App\Validators\Senda\FollowUpStoreValidator;
use App\Validators\Senda\FollowUpUpdateValidator;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class FollowUpController extends SendaController
{
    public function __construct(
        private readonly FollowUpService $followUps = new FollowUpService(),
        private readonly AttentionService $attentions = new AttentionService(),
        private readonly PersonService $people = new PersonService(),
        private readonly HistoryService $history = new HistoryService()
    ) {
    }

    public function index(Request $request): void
    {
        $filters = $this->listFilters($request);
        $agenda = $this->isAgendaRequest($filters);

        $payload = [
            'title' => 'Seguimiento SENDA',
            'rut' => (string) old('rut', (string) $request->query('rut', '')),
            'name' => (string) old('name', (string) ($filters['name'] ?? '')),
            'notFound' => $request->query('not_found', '') === '1',
            'matches' => [],
            'agenda' => $agenda,
            'filters' => $filters,
            'contactTypes' => FollowUpCatalog::contactTypes(),
            'results' => FollowUpCatalog::results(),
            'staff' => $agenda ? $this->followUps->staffOptions() : [],
            'followups' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
        ];

        if ($agenda) {
            $page = max(1, (int) $request->query('page', 1));
            $result = $this->followUps->search($filters, $page);
            $payload['followups'] = $result['data'];
            $payload['total'] = $result['total'];
            $payload['page'] = $result['page'];
            $payload['pages'] = $result['pages'];
        }

        $this->sendaView('followups/search', $payload);
    }

    public function search(Request $request): void
    {
        $payload = [
            'rut' => trim((string) $request->input('rut', '')),
            'name' => trim((string) $request->input('name', '')),
        ];
        $errors = (new FollowUpSearchValidator())->validate($payload);

        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            $this->redirect(url('/senda/follow-ups'));
        }

        if ($payload['rut'] !== '') {
            $this->searchByRut($payload);
        }

        $this->searchByName($payload);
    }

    public function person(Request $request, string $id): void
    {
        $order = trim((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $tab = trim((string) $request->query('tab', 'historial'));
        $allowedTabs = ['historial', 'atenciones', 'fichas', 'seguimientos'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'historial';
        }

        try {
            $dossier = $this->history->dossier((int) $id, $order);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        PersonContext::remember((int) $dossier['person']['id']);
        $this->history->auditConsultation(
            (int) $dossier['person']['id'],
            (string) ($dossier['person']['rut'] ?? '')
        );

        $this->sendaView('followups/dossier', [
            'title' => 'Seguimiento de ' . $dossier['person']['full_name'] . ' — SENDA',
            'dossier' => $dossier,
            'tab' => $tab,
            'order' => $order,
        ]);
    }

    public function createFromAttention(Request $request, string $id): void
    {
        $query = http_build_query(array_filter([
            'attention' => (int) $id,
            'return' => $this->returnTo($request) ?: 'history',
        ]));
        $this->redirect(url('/senda/follow-ups/create') . '?' . $query);
    }

    public function create(Request $request): void
    {
        $attentionId = (int) $request->query('attention', 0);
        $personId = (int) $request->query('person', 0);
        $returnTo = $this->returnTo($request);

        if ($attentionId < 1 && $personId > 0) {
            $this->selectPersonAttention($request, $personId, $returnTo !== '' ? $returnTo : 'history');
            return;
        }

        if ($attentionId < 1) {
            $this->selectAttention($request);
            return;
        }

        [$attention, $person] = $this->attentionAndPerson($attentionId);

        $this->sendaView('followups/form', $this->formData(
            $this->followUps->defaults($attention),
            $attention,
            $person,
            false,
            $returnTo
        ));
    }

    public function store(Request $request): void
    {
        $payload = $request->all();
        $attentionId = (int) ($payload['senda_attention_id'] ?? 0);
        $returnTo = $this->returnTo($request);
        $createUrl = $this->withReturn(
            url('/senda/follow-ups/create') . ($attentionId > 0 ? '?attention=' . $attentionId : ''),
            $returnTo
        );

        $errors = (new FollowUpStoreValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($createUrl);
        }

        try {
            $this->followUps->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $createUrl);
        }

        Session::flashAlert('success', 'Seguimiento registrado', 'El seguimiento quedó asociado a la atención.');
        $this->redirect($this->afterSaveUrl($attentionId, $returnTo));
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $record = $this->followUps->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        [$attention, $person] = $this->attentionAndPerson((int) $record['senda_attention_id']);

        $this->sendaView('followups/form', $this->formData($record, $attention, $person, true, $this->returnTo($request)));
    }

    public function show(Request $request, string $id): void
    {
        try {
            $record = $this->followUps->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        [$attention, $person] = $this->attentionAndPerson((int) $record['senda_attention_id']);

        $this->sendaView('followups/show', [
            'title' => 'Ver seguimiento — SENDA',
            'record' => $record,
            'attention' => $attention,
            'person' => $person,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        try {
            $current = $this->followUps->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        $payload = $request->all();
        $payload['senda_attention_id'] = $current['senda_attention_id'];
        $returnTo = $this->returnTo($request);
        $editUrl = $this->withReturn(url('/senda/follow-ups/' . $id . '/edit'), $returnTo);

        $errors = (new FollowUpUpdateValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($editUrl);
        }

        try {
            $this->followUps->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $editUrl);
        }

        Session::flashAlert('success', 'Seguimiento actualizado', 'Los cambios se guardaron correctamente.');
        $this->redirect($this->afterSaveUrl((int) $current['senda_attention_id'], $returnTo));
    }

    public function destroy(Request $request, string $id): void
    {
        try {
            $current = $this->followUps->find((int) $id);
            $this->followUps->delete((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        Session::flashAlert('success', 'Seguimiento eliminado', 'El registro quedó anulado.');
        $this->redirect($this->afterSaveUrl((int) $current['senda_attention_id'], $this->returnTo($request)));
    }

    private function selectAttention(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'q' => trim((string) $request->query('q', '')),
        ];
        $result = $this->attentions->search($filters, $page);

        $this->sendaView('followups/select-attention', [
            'title' => 'Seleccionar atención — Seguimiento SENDA',
            'attentions' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'person' => null,
            'scoped' => false,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    /**
     * @param array{rut: string, name: string} $payload
     */
    private function searchByRut(array $payload): never
    {
        try {
            $result = $this->people->lookup($payload['rut']);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        if (!empty($result['exists']) && !empty($result['person']['id'])) {
            $this->redirect(url('/senda/follow-ups/person/' . (int) $result['person']['id']));
        }

        Session::flashInput($payload);
        Session::flashAlert(
            'info',
            'Persona no encontrada',
            'No existen registros SENDA asociados a este RUT.'
        );
        $this->redirect(url('/senda/follow-ups') . '?' . http_build_query([
            'not_found' => 1,
            'rut' => $result['rut'] ?? $payload['rut'],
        ]));
    }

    /**
     * @param array{rut: string, name: string} $payload
     */
    private function searchByName(array $payload): void
    {
        $matches = $this->people->all($payload['name']);

        if ($matches === []) {
            Session::flashInput($payload);
            Session::flashAlert(
                'info',
                'Persona no encontrada',
                'No existen registros SENDA asociados a este nombre.'
            );
            $this->redirect(url('/senda/follow-ups'));
        }

        if (count($matches) === 1) {
            $this->redirect(url('/senda/follow-ups/person/' . (int) $matches[0]['id']));
        }

        $this->sendaView('followups/search', [
            'title' => 'Seguimiento SENDA',
            'rut' => $payload['rut'],
            'name' => $payload['name'],
            'notFound' => false,
            'matches' => $matches,
            'agenda' => false,
            'filters' => [],
            'contactTypes' => FollowUpCatalog::contactTypes(),
            'results' => FollowUpCatalog::results(),
            'staff' => [],
            'followups' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
        ]);
    }

    private function selectPersonAttention(Request $request, int $personId, string $returnTo): void
    {
        try {
            $person = $this->people->find($personId);
            $rows = $this->people->attentions($personId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups'));
        }

        if ($rows === []) {
            Session::flashAlert(
                'info',
                'Sin atenciones',
                'Registre una atención para esta persona antes de crear un seguimiento.'
            );
            $this->redirect(url('/senda/follow-ups/person/' . $personId));
        }

        if (count($rows) === 1) {
            $this->redirect($this->withReturn(
                url('/senda/follow-ups/create') . '?attention=' . (int) $rows[0]['id'],
                $returnTo
            ));
        }

        $attentions = array_map(static function (array $row) use ($person): array {
            $row['person_full_name'] = (string) ($person['full_name'] ?? '');
            $row['person_rut'] = (string) ($person['rut'] ?? '');

            return $row;
        }, $rows);

        $this->sendaView('followups/select-attention', [
            'title' => '¿A qué atención corresponde este seguimiento?',
            'attentions' => $attentions,
            'total' => count($attentions),
            'page' => 1,
            'pages' => 1,
            'filters' => [],
            'person' => $person,
            'scoped' => true,
            'returnTo' => $returnTo,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function attentionAndPerson(int $attentionId): array
    {
        try {
            $attention = $this->attentions->find($attentionId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/follow-ups/create'));
        }

        $person = null;
        if (!empty($attention['senda_person_id'])) {
            try {
                $person = $this->people->find((int) $attention['senda_person_id']);
            } catch (\Throwable) {
                $person = null;
            }
        }

        return [$attention, $person];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $attention
     * @param array<string, mixed>|null $person
     * @return array<string, mixed>
     */
    private function formData(array $record, array $attention, ?array $person, bool $isEdit, string $returnTo = ''): array
    {
        return [
            'title' => $isEdit ? 'Editar seguimiento — SENDA' : 'Nuevo seguimiento — SENDA',
            'record' => $record,
            'attention' => $attention,
            'person' => $person,
            'returnTo' => $returnTo,
            'contactTypes' => FollowUpCatalog::contactTypes(),
            'results' => FollowUpCatalog::results(),
            'yesNo' => FollowUpCatalog::yesNo(),
        ];
    }

    private function returnTo(Request $request): string
    {
        $value = trim((string) $request->input('return', $request->query('return', '')));

        return in_array($value, ['attention', 'history'], true) ? $value : '';
    }

    private function withReturn(string $url, string $returnTo): string
    {
        if (!in_array($returnTo, ['attention', 'history'], true)) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'return=' . $returnTo;
    }

    private function afterSaveUrl(int $attentionId, string $returnTo): string
    {
        if ($returnTo === 'history' && $attentionId > 0) {
            $personId = $this->personIdForAttention($attentionId);
            if ($personId > 0) {
                return url('/senda/follow-ups/person/' . $personId);
            }
        }

        if ($returnTo === 'attention' && $attentionId > 0 && hasPermission('senda.attentions.edit')) {
            return url('/senda/attentions/' . $attentionId . '/edit');
        }

        return url('/senda/follow-ups') . ($attentionId > 0 ? '?attention=' . $attentionId : '');
    }

    private function personIdForAttention(int $attentionId): int
    {
        try {
            $attention = $this->attentions->find($attentionId);
        } catch (\Throwable) {
            return 0;
        }

        return (int) ($attention['senda_person_id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function isAgendaRequest(array $filters): bool
    {
        foreach (['status', 'pending', 'attention', 'date_from', 'date_to', 'contact_type', 'result', 'created_by'] as $key) {
            if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? 0) !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request): array
    {
        $contactType = trim((string) $request->query('contact_type', ''));
        $result = trim((string) $request->query('result', ''));
        $pending = trim((string) $request->query('pending', ''));
        $status = trim((string) $request->query('status', ''));
        $contacts = array_column(FollowUpCatalog::contactTypes(), 'value');
        $results = array_column(FollowUpCatalog::results(), 'value');

        return [
            'attention' => (int) $request->query('attention', 0) > 0
                ? (int) $request->query('attention', 0)
                : '',
            'name' => trim((string) $request->query('name', '')),
            'rut' => trim((string) $request->query('rut', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'contact_type' => in_array($contactType, $contacts, true) ? $contactType : '',
            'result' => in_array($result, $results, true) ? $result : '',
            'created_by' => trim((string) $request->query('created_by', '')),
            'pending' => in_array($pending, ['si', 'no'], true) ? $pending : '',
            'status' => FollowUpStatus::isValid($status) ? $status : '',
        ];
    }

    private function failAndRedirect(\Throwable $e, string $to): never
    {
        if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 422], true)) {
            Session::flashAlert(
                $e->getStatusCode() === 403 ? 'warning' : 'error',
                'No se pudo completar la acción',
                $e->getMessage()
            );
            $this->redirect($to);
        }

        throw $e;
    }
}
