<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\AssistClassificationService;
use App\Services\Senda\AssistedReferralCatalog;
use App\Services\Senda\AttentionService;
use App\Services\Senda\DemandOrigin;
use App\Services\Senda\EntryFlowContext;
use App\Services\Senda\PersonContext;
use App\Services\Senda\PersonService;
use App\Services\Senda\ReferralService;
use App\Services\Senda\ReferralStatus;
use App\Validators\Senda\ReferralStoreValidator;
use App\Validators\Senda\ReferralUpdateValidator;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Session;

final class ReferralController extends SendaController
{
    public function __construct(
        private readonly ReferralService $referrals = new ReferralService(),
        private readonly AttentionService $attentions = new AttentionService(),
        private readonly PersonService $people = new PersonService()
    ) {
    }

    public function index(): void
    {
        $this->sendaView('referrals/index', [
            'title' => 'Ficha de Referencia Asistida a Tratamiento — SENDA',
            'referrals' => $this->referrals->all(),
        ]);
    }

    public function create(Request $request): void
    {
        $attentionId = (int) $request->query('attention', 0);
        $flow = trim((string) $request->query('flow', ''));

        if ($attentionId < 1) {
            $this->selectAttention($request);

            return;
        }

        $existing = $this->referrals->findByAttention($attentionId);

        if ($existing !== null) {
            if (EntryFlowContext::isEntryFlow($flow)) {
                EntryFlowContext::markReferralCompleted();
                Session::flashAlert(
                    'info',
                    'Ficha existente',
                    'Esta atención ya tiene una ficha de referencia registrada.'
                );
                $this->redirect(EntryFlowContext::attentionCreateUrl());
            }

            Session::flashAlert(
                'info',
                'Ficha existente',
                'Esta atención ya tiene una ficha de referencia. Puede continuar sobre el registro existente.'
            );
            $this->redirect(url('/senda/referrals/' . $existing['id'] . '/edit'));
        }

        [$attention, $person] = $this->attentionAndPerson($attentionId);

        if (EntryFlowContext::isEntryFlow($flow)) {
            $this->assertEntryFlowPerson($person);
        }

        $this->sendaView('referrals/form', $this->formData(
            $this->referrals->defaults($attention, $person),
            $attention,
            $person,
            false,
            $flow
        ));
    }

    public function show(Request $request, string $id): void
    {
        try {
            $record = $this->referrals->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/referrals'));
        }

        [$attention, $person] = $this->attentionAndPerson((int) $record['senda_attention_id']);

        $this->sendaView('referrals/show', array_merge(
            $this->formData($record, $attention, $person, true),
            ['title' => 'Ver ficha de referencia — SENDA']
        ));
    }

    public function store(Request $request): void
    {
        $payload = $request->all();
        $attentionId = (int) ($payload['senda_attention_id'] ?? 0);
        $returnFlow = trim((string) ($payload['return_flow'] ?? ''));
        $createUrl = url('/senda/referrals/create') . ($attentionId > 0 ? '?attention=' . $attentionId : '');
        if (EntryFlowContext::isEntryFlow($returnFlow) && $attentionId > 0) {
            $createUrl .= '&flow=entry';
        }

        if ($attentionId > 0) {
            try {
                $attention = $this->attentions->find($attentionId);
                $payload['demand_origin'] = DemandOrigin::resolve($attention, $payload['demand_origin'] ?? null);
            } catch (\Throwable) {
            }
        }

        $errors = (new ReferralStoreValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($createUrl);
        }

        try {
            $this->referrals->create($payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $createUrl);
        }

        if (EntryFlowContext::isEntryFlow($returnFlow)) {
            EntryFlowContext::markReferralCompleted();
            Session::flashAlert('success', 'Ficha registrada', 'Continúe completando la atención.');
            $this->redirect(EntryFlowContext::attentionCreateUrl());
        }

        if (!empty($payload['finalize_referral'])) {
            Session::flashAlert('success', 'Ficha finalizada', 'La ficha quedó registrada y finalizada.');
        } else {
            Session::flashAlert('success', 'Borrador guardado', 'Puede continuar editando esta ficha más adelante.');
        }
        $this->redirect(url('/senda/referrals'));
    }

    public function edit(Request $request, string $id): void
    {
        try {
            $record = $this->referrals->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/referrals'));
        }

        [$attention, $person] = $this->attentionAndPerson((int) $record['senda_attention_id']);

        $this->sendaView('referrals/form', $this->formData($record, $attention, $person, true));
    }

    public function update(Request $request, string $id): void
    {
        try {
            $current = $this->referrals->find((int) $id);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/referrals'));
        }

        if (ReferralStatus::isCompleted($current)) {
            requirePermission('senda.referrals.edit_completed');
        }

        $payload = $request->all();
        $payload['senda_attention_id'] = $current['senda_attention_id'];
        $payload['demand_origin'] = DemandOrigin::resolve(
            $this->attentions->find((int) $current['senda_attention_id']),
            $payload['demand_origin'] ?? $current['demand_origin'] ?? null
        );
        $editUrl = url('/senda/referrals/' . $id . '/edit');

        $errors = (new ReferralUpdateValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            Session::flashAlert('error', 'Revise el formulario', 'Hay campos pendientes o inválidos.');
            $this->redirect($editUrl);
        }

        try {
            $this->referrals->update((int) $id, $payload);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndRedirect($e, $editUrl);
        }

        if (ReferralStatus::isCompleted($current) || !empty($payload['finalize_referral'])) {
            Session::flashAlert('success', 'Ficha finalizada', 'Los cambios de la ficha finalizada quedaron registrados en auditoría.');
        } else {
            Session::flashAlert('success', 'Borrador guardado', 'Puede continuar editando esta ficha más adelante.');
        }
        $this->redirect(url('/senda/referrals'));
    }

    private function selectAttention(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'ficha' => 'sin',
        ];
        $result = $this->attentions->search($filters, $page);

        $this->sendaView('referrals/select-attention', [
            'title' => 'Seleccionar atención — SENDA',
            'attentions' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function attentionAndPerson(int $attentionId): array
    {
        try {
            $attention = $this->attentions->find($attentionId);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/referrals/create'));
        }

        if (empty($attention['senda_person_id'])) {
            Session::flashAlert('error', 'Atención incompleta', 'La atención no tiene una persona asociada.');
            $this->redirect(url('/senda/referrals/create'));
        }

        try {
            $person = $this->people->find((int) $attention['senda_person_id']);
        } catch (\Throwable $e) {
            $this->failAndRedirect($e, url('/senda/referrals/create'));
        }

        return [$attention, $person];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $attention
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    private function formData(array $record, array $attention, array $person, bool $isEdit, string $flow = ''): array
    {
        $locked = $isEdit && ReferralStatus::isCompleted($record) && !hasPermission('senda.referrals.edit_completed');
        $age = $person['age'] ?? PersonService::age($person['birth_date'] ?? null);
        $entryFlow = EntryFlowContext::isEntryFlow($flow);

        return [
            'title' => $isEdit
                ? 'Editar ficha de referencia — SENDA'
                : 'Ficha de Referencia Asistida a Tratamiento — SENDA',
            'record' => $record,
            'attention' => $attention,
            'person' => $person,
            'locked' => $locked,
            'suggestAssist' => $age !== null && (int) $age >= 18,
            'missingPersonFields' => $this->missingPersonFields($person),
            'demandOrigins' => DemandOrigin::optionsForEntryType((string) ($attention['entry_type'] ?? '')),
            'demandOriginLocked' => DemandOrigin::isLocked((string) ($attention['entry_type'] ?? '')),
            'requestTypes' => AssistedReferralCatalog::requestTypes(),
            'destinationCenters' => AssistedReferralCatalog::destinationCenters(),
            'applicantKinds' => AssistedReferralCatalog::applicantKinds(),
            'relationships' => AssistedReferralCatalog::applicantRelationships(),
            'genders' => AssistedReferralCatalog::genders(),
            'riskLevels' => AssistedReferralCatalog::riskLevels(),
            'frequencies' => AssistedReferralCatalog::frequencies(),
            'assistSubstances' => AssistedReferralCatalog::assistSubstances(),
            'assistClassifications' => (new AssistClassificationService())->options(),
            'assistClassificationRules' => (new AssistClassificationService())->clientRules(),
            'treatmentModalities' => AssistedReferralCatalog::treatmentModalities(),
            'treatmentStayPeriods' => AssistedReferralCatalog::treatmentStayPeriods(),
            'yesNo' => AssistedReferralCatalog::yesNo(),
            'yesNoUnknown' => AssistedReferralCatalog::yesNoUnknown(),
            'consumptionSubstances' => AssistedReferralCatalog::consumptionSubstances(),
            'returnFlow' => $entryFlow ? 'entry' : '',
            'cancelUrl' => $entryFlow
                ? EntryFlowContext::referralQuestionUrl()
                : url('/senda/referrals'),
            'showSendaEntryBanner' => !$entryFlow,
        ];
    }

    /**
     * @param array<string, mixed> $person
     */
    private function assertEntryFlowPerson(array $person): void
    {
        $currentId = PersonContext::id();

        if ($currentId === null || (int) ($person['id'] ?? 0) !== $currentId) {
            Session::flashAlert(
                'warning',
                'Persona no coincidente',
                'La ficha debe completarse para la persona identificada en la atención.'
            );
            $this->redirect(EntryFlowContext::referralQuestionUrl());
        }
    }

    /**
     * @param array<string, mixed> $person
     * @return list<string>
     */
    private function missingPersonFields(array $person): array
    {
        $labels = [
            'full_name' => 'Nombre',
            'rut' => 'RUT',
            'birth_date' => 'Fecha de nacimiento',
            'address' => 'Domicilio',
            'phone' => 'Teléfono',
            'education' => 'Educación',
            'occupation' => 'Ocupación',
        ];
        $missing = [];

        foreach ($labels as $field => $label) {
            if (trim((string) ($person[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
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
