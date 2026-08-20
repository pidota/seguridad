<?php

declare(strict_types=1);

use App\Controllers\Meetings\MeetingRecordController;
use App\Controllers\Meetings\MeetingSignatureController;
use App\Controllers\Meetings\UserSignatureController;
use Core\Router;

/** @var Router $router */

$router->get('/', [MeetingRecordController::class, 'index'], 'can:meetings.view', 'meetings.index');
$router->get('/create', [MeetingRecordController::class, 'create'], 'can:meetings.create', 'meetings.create');
$router->post('/', [MeetingRecordController::class, 'store'], 'can:meetings.create', 'meetings.store');
$router->get('/users/search', [MeetingRecordController::class, 'searchUsers'], 'can:meetings.create,meetings.edit', 'meetings.users.search');
$router->get('/pending-signatures', [MeetingSignatureController::class, 'pending'], 'can:meetings.view_pending_signatures', 'meetings.pending_signatures');
$router->get('/profile/signature', [UserSignatureController::class, 'show'], 'can:meetings.signature.manage', 'meetings.signature.show');
$router->post('/profile/signature', [UserSignatureController::class, 'store'], 'can:meetings.signature.manage', 'meetings.signature.store');
$router->get('/profile/signature/image', [UserSignatureController::class, 'image'], 'can:meetings.signature.manage', 'meetings.signature.image');
$router->get('/signatures/{signatureId}/image', [MeetingSignatureController::class, 'snapshotImage'], 'can:meetings.view', 'meetings.signatures.image');
$router->get('/{id}/sign', [MeetingSignatureController::class, 'review'], 'can:meetings.sign', 'meetings.sign.review');
$router->get('/{id}', [MeetingRecordController::class, 'show'], 'can:meetings.view', 'meetings.show');
$router->get('/{id}/edit', [MeetingRecordController::class, 'edit'], 'can:meetings.edit', 'meetings.edit');
$router->post('/{id}', [MeetingRecordController::class, 'update'], 'can:meetings.edit', 'meetings.update');
$router->post('/{id}/finalize', [MeetingRecordController::class, 'finalize'], 'can:meetings.edit', 'meetings.finalize');
$router->post('/{id}/cancel', [MeetingRecordController::class, 'cancel'], 'can:meetings.cancel', 'meetings.cancel');
$router->post('/{id}/reopen', [MeetingRecordController::class, 'reopen'], 'can:meetings.reopen', 'meetings.reopen');
$router->post('/{id}/delete', [MeetingRecordController::class, 'destroy'], 'can:meetings.delete', 'meetings.delete');
$router->post('/{id}/sign', [MeetingSignatureController::class, 'sign'], 'can:meetings.sign', 'meetings.sign');
$router->post('/{id}/request-correction', [MeetingSignatureController::class, 'requestCorrection'], 'can:meetings.sign', 'meetings.request_correction');
