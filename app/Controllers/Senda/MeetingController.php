<?php

declare(strict_types=1);

namespace App\Controllers\Senda;

use App\Controllers\Meetings\MeetingRecordController as BaseMeetingController;
use App\Services\Meetings\MeetingSourceContext;
use App\Services\Meetings\MeetingSourceModule;
use Core\Request;

/**
 * Puente SENDA → módulo transversal de reuniones.
 */
final class MeetingController extends SendaController
{
    public function __construct(
        private readonly BaseMeetingController $meetings = new BaseMeetingController()
    ) {
    }

    public function index(Request $request): void
    {
        $this->withSendaSource(fn () => $this->meetings->index($request));
    }

    public function create(Request $request): void
    {
        $this->withSendaSource(fn () => $this->meetings->create($request));
    }

    public function store(Request $request): void
    {
        $this->withSendaSource(fn () => $this->meetings->store($request));
    }

    public function show(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->show($request, $id));
    }

    public function edit(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->edit($request, $id));
    }

    public function update(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->update($request, $id));
    }

    public function finalize(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->finalize($request, $id));
    }

    public function signReview(Request $request, string $id): void
    {
        $this->withSendaSource(function () use ($request, $id): void {
            (new \App\Controllers\Meetings\MeetingSignatureController())->review($request, $id);
        });
    }

    public function sign(Request $request, string $id): void
    {
        $this->withSendaSource(function () use ($request, $id): void {
            (new \App\Controllers\Meetings\MeetingSignatureController())->sign($request, $id);
        });
    }

    public function requestCorrection(Request $request, string $id): void
    {
        $this->withSendaSource(function () use ($request, $id): void {
            (new \App\Controllers\Meetings\MeetingSignatureController())->requestCorrection($request, $id);
        });
    }

    public function cancel(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->cancel($request, $id));
    }

    public function reopen(Request $request, string $id): void
    {
        $this->withSendaSource(fn () => $this->meetings->reopen($request, $id));
    }

    private function withSendaSource(callable $callback): void
    {
        MeetingSourceContext::set(MeetingSourceModule::SENDA);

        try {
            $callback();
        } finally {
            MeetingSourceContext::forget();
        }
    }
}
