<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Services\Senda\AttentionService;
use App\Services\Senda\EntryFlowContext;
use App\Services\Senda\EntryType;
use App\Services\Senda\EntryTypeContext;
use App\Services\Senda\PersonContext;
use App\Services\Senda\PersonService;
use App\Validators\Senda\PersonLookupValidator;
use Core\Request;
use Core\Session;

final class EntryTypeController extends SendaController
{
    public function __construct(
        private readonly PersonService $people = new PersonService(),
        private readonly AttentionService $attentions = new AttentionService()
    ) {
    }

    public function index(Request $request): void
    {
        $next = $this->nextTarget($request);
        $step = trim((string) $request->query('step', ''));

        if ($step === 'tipo') {
            $this->showTypeSelection($request, $next);

            return;
        }

        if ($next === 'attention') {
            $person = $this->people->current();

            if ($person === null) {
                Session::flashAlert(
                    'info',
                    'Identificar persona',
                    'Ingrese el RUT de la persona antes de continuar.'
                );
                $this->showRutForm($request);

                return;
            }

            if ($step === 'referral') {
                if (EntryTypeContext::current() === null) {
                    Session::flashAlert(
                        'info',
                        'Tipo de atención',
                        'Seleccione el tipo de atención antes de continuar.'
                    );
                    $this->redirect(EntryFlowContext::attentionTypesUrl());
                }

                if ($this->shouldCompleteReferralFirst()) {
                    $this->redirect($this->referralCreateUrl((int) EntryFlowContext::draftAttentionId()));

                    return;
                }

                $this->showReferralQuestion($person);

                return;
            }

            if ($this->shouldCompleteReferralFirst()) {
                $this->redirect($this->referralCreateUrl((int) EntryFlowContext::draftAttentionId()));

                return;
            }

            $this->showTypeSelection($request, 'attention', $person);

            return;
        }

        PersonContext::forget();
        $this->showRutForm($request);
    }

    public function lookup(Request $request): void
    {
        $payload = [
            'rut' => trim((string) $request->input('rut', '')),
        ];

        $errors = (new PersonLookupValidator())->validate($payload);
        if ($errors !== []) {
            Session::flashInput($payload);
            Session::flashErrors($errors);
            $this->redirect(url('/senda'));
        }

        try {
            $result = $this->people->lookup($payload['rut']);
        } catch (\Throwable $e) {
            Session::flashInput($payload);
            $this->failAndBack($e);
        }

        if ($result['exists'] && !empty($result['person']['id'])) {
            try {
                $this->people->use((int) $result['person']['id']);
            } catch (\Throwable $e) {
                $this->failAndBack($e);
            }

            EntryFlowContext::forget();
            $this->redirect(EntryFlowContext::attentionTypesUrl());
        }

        if (!hasPermission('senda.people.create')) {
            Session::flashAlert(
                'warning',
                'Persona no registrada',
                'No hay una persona con ese RUT y su perfil no puede registrar nuevas personas.'
            );
            $this->showRutForm($request, $result['rut'], false);

            return;
        }

        $this->showRutForm($request, $result['rut'], false);
    }

    public function referralDecision(Request $request): void
    {
        $person = $this->people->current();

        if ($person === null) {
            Session::flashAlert(
                'info',
                'Identificar persona',
                'Ingrese el RUT de la persona antes de continuar.'
            );
            $this->redirect(url('/senda'));
        }

        $requires = trim((string) $request->input('requires_referral', ''));

        if ($requires === '0') {
            EntryFlowContext::markReferralSkipped();
            $this->redirect(EntryFlowContext::attentionCreateUrl());
        }

        if ($requires !== '1') {
            Session::flashAlert('error', 'Selección requerida', 'Indique si la persona requiere ficha de referencia.');
            $this->redirect(EntryFlowContext::referralQuestionUrl());
        }

        if (!hasPermission('senda.referrals.create')) {
            EntryFlowContext::markReferralSkipped();
            Session::flashAlert(
                'warning',
                'Sin permiso para ficha',
                'Su perfil no puede registrar fichas de referencia. Continúe con el registro de la atención.'
            );
            $this->redirect(EntryFlowContext::attentionCreateUrl());
        }

        if (EntryTypeContext::current() === null) {
            Session::flashAlert(
                'info',
                'Tipo de atención',
                'Seleccione el tipo de atención antes de continuar.'
            );
            $this->redirect(EntryFlowContext::attentionTypesUrl());
        }

        try {
            $draftId = $this->attentions->createDraftForEntryFlow((int) $person['id']);
        } catch (\Throwable $e) {
            $this->failAndBack($e);
        }

        EntryFlowContext::markReferralRequired($draftId);
        $this->redirect($this->referralCreateUrl($draftId));
    }

    public function store(Request $request): void
    {
        $type = trim((string) $request->input('tipo_ingreso', ''));
        $next = $this->nextTarget($request);
        $personId = PersonContext::id();

        if ($next === 'attention' && $personId === null) {
            Session::flashAlert(
                'info',
                'Identificar persona',
                'Ingrese el RUT de la persona antes de seleccionar el tipo de atención.'
            );
            $this->redirect(url('/senda'));
        }

        if ($next === 'attention' && $this->shouldCompleteReferralFirst()) {
            $this->redirect($this->referralCreateUrl((int) EntryFlowContext::draftAttentionId()));
        }

        if (EntryType::isFollowUpMenuOption($type)) {
            if (!hasPermission('senda.followups.view')) {
                Session::flashAlert('warning', 'Acceso denegado', 'No puede acceder al módulo de seguimiento.');
                $this->redirect(url('/senda') . ($next === 'attention' ? '?next=attention' : '?step=tipo'));
            }

            if ($personId !== null) {
                $this->redirect(url('/senda/follow-ups/person/' . $personId));
            }

            $this->redirect(url('/senda/follow-ups'));
        }

        if (!EntryType::isValid($type)) {
            Session::flashAlert(
                'error',
                'Atención',
                'Seleccione una opción válida para continuar.'
            );
            $this->redirect($this->typeSelectionUrl($next));
        }

        EntryTypeContext::remember($type);

        if ($next === 'attention' && hasPermission('senda.attentions.create')) {
            if ($this->shouldCompleteReferralFirst()) {
                $this->redirect($this->referralCreateUrl((int) EntryFlowContext::draftAttentionId()));
            }

            if (EntryFlowContext::needsReferralQuestion()) {
                $this->redirect(EntryFlowContext::referralQuestionUrl());
            }

            $this->redirect(EntryFlowContext::attentionCreateUrl());
        }

        $this->redirect(url('/senda/dashboard') . '?' . http_build_query([
            EntryTypeContext::QUERY_KEY => $type,
        ]));
    }

    private function showRutForm(Request $request, ?string $rut = null, ?bool $exists = null): void
    {
        $this->sendaView('entry/rut', [
            'title' => 'Atención — SENDA',
            'rut' => $rut ?? (string) old('rut', PersonContext::lookupRut() ?? ''),
            'exists' => $exists,
            'showSendaEntryBanner' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $person
     */
    private function showReferralQuestion(array $person): void
    {
        $this->sendaView('entry/referral-question', [
            'title' => 'Atención — SENDA',
            'person' => $person,
            'entryType' => EntryTypeContext::meta(),
            'showSendaEntryBanner' => false,
        ]);
    }

    /**
     * @param array<string, mixed>|null $person
     */
    private function showTypeSelection(Request $request, string $next = '', ?array $person = null): void
    {
        $this->sendaView('entry/index', [
            'title' => 'Atención — SENDA',
            'options' => EntryType::attentionMenuOptions(hasPermission('senda.followups.view')),
            'currentEntryType' => EntryTypeContext::current(),
            'next' => $next,
            'person' => $person,
            'showSendaEntryBanner' => false,
        ]);
    }

    private function shouldCompleteReferralFirst(): bool
    {
        return EntryFlowContext::referralRequired() && !EntryFlowContext::referralCompleted();
    }

    private function referralCreateUrl(int $attentionId): string
    {
        return url('/senda/referrals/create') . '?' . http_build_query([
            'attention' => $attentionId,
            'flow' => 'entry',
        ]);
    }

    private function typeSelectionUrl(string $next): string
    {
        if ($next === 'attention') {
            return EntryFlowContext::attentionTypesUrl();
        }

        return url('/senda') . '?step=tipo';
    }

    private function nextTarget(Request $request): string
    {
        $next = trim((string) $request->input('next', $request->query('next', '')));

        return $next === 'attention' ? 'attention' : '';
    }
}
