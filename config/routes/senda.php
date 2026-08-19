<?php

declare(strict_types=1);

use App\Controllers\Senda\AttentionController;
use App\Controllers\Senda\DashboardController;
use App\Controllers\Senda\EntryTypeController;
use App\Controllers\Senda\FollowUpController;
use App\Controllers\Senda\MeetingController;
use App\Controllers\Senda\PeopleController;
use App\Controllers\Senda\ReferralController;
use App\Controllers\Senda\StatisticsController;
use Core\Router;

/** @var Router $router */

$router->get('/', [EntryTypeController::class, 'index'], 'can:senda.dashboard.view', 'senda.entry');
$router->post('/lookup', [EntryTypeController::class, 'lookup'], 'can:senda.people.create,senda.attentions.create', 'senda.entry.lookup');
$router->post('/referral-decision', [EntryTypeController::class, 'referralDecision'], 'can:senda.attentions.create', 'senda.entry.referral-decision');
$router->post('/ingreso', [EntryTypeController::class, 'store'], 'can:senda.dashboard.view', 'senda.entry.store');
$router->get('/dashboard', [DashboardController::class, 'index'], 'can:senda.dashboard.view', 'senda.dashboard');

$router->get('/people', [PeopleController::class, 'index'], 'can:senda.people.view', 'senda.people.index');
$router->get('/people/create', [PeopleController::class, 'create'], 'can:senda.people.create,senda.attentions.create', 'senda.people.create');
$router->post('/people/lookup', [PeopleController::class, 'lookup'], 'can:senda.people.create,senda.attentions.create', 'senda.people.lookup');
$router->get('/people/create/form', [PeopleController::class, 'form'], 'can:senda.people.create', 'senda.people.form');
$router->post('/people', [PeopleController::class, 'store'], 'can:senda.people.create', 'senda.people.store');
$router->get('/people/{id}/history', [FollowUpController::class, 'person'], 'can:senda.followups.view', 'senda.people.history');
$router->get('/people/{id}', [PeopleController::class, 'show'], 'can:senda.people.view', 'senda.people.show');
$router->post('/people/{id}/use', [PeopleController::class, 'usePerson'], 'can:senda.people.create,senda.attentions.create', 'senda.people.use');
$router->get('/people/{id}/edit', [PeopleController::class, 'edit'], 'can:senda.people.edit', 'senda.people.edit');
$router->put('/people/{id}', [PeopleController::class, 'update'], 'can:senda.people.edit', 'senda.people.update');

$router->get('/attentions', [AttentionController::class, 'index'], 'can:senda.attentions.view', 'senda.attentions.index');
$router->get('/attentions/create', [AttentionController::class, 'create'], 'can:senda.attentions.create', 'senda.attentions.create');
$router->post('/attentions', [AttentionController::class, 'store'], 'can:senda.attentions.create', 'senda.attentions.store');
$router->get('/attentions/{id}/follow-ups/create', [FollowUpController::class, 'createFromAttention'], 'can:senda.followups.create', 'senda.attentions.followups.create');
$router->get('/attentions/{id}', [AttentionController::class, 'show'], 'can:senda.attentions.view', 'senda.attentions.show');
$router->get('/attentions/{id}/edit', [AttentionController::class, 'edit'], 'can:senda.attentions.edit', 'senda.attentions.edit');
$router->put('/attentions/{id}', [AttentionController::class, 'update'], 'can:senda.attentions.edit', 'senda.attentions.update');

$router->get('/referrals', [ReferralController::class, 'index'], 'can:senda.referrals.view', 'senda.referrals.index');
$router->get('/referrals/create', [ReferralController::class, 'create'], 'can:senda.referrals.create', 'senda.referrals.create');
$router->post('/referrals', [ReferralController::class, 'store'], 'can:senda.referrals.create', 'senda.referrals.store');
$router->get('/referrals/{id}', [ReferralController::class, 'show'], 'can:senda.referrals.view', 'senda.referrals.show');
$router->get('/referrals/{id}/edit', [ReferralController::class, 'edit'], 'can:senda.referrals.edit', 'senda.referrals.edit');
$router->put('/referrals/{id}', [ReferralController::class, 'update'], 'can:senda.referrals.edit', 'senda.referrals.update');

$router->get('/follow-ups', [FollowUpController::class, 'index'], 'can:senda.followups.view', 'senda.followups.index');
$router->post('/follow-ups/search', [FollowUpController::class, 'search'], 'can:senda.followups.view', 'senda.followups.search');
$router->get('/follow-ups/person/{id}', [FollowUpController::class, 'person'], 'can:senda.followups.view', 'senda.followups.person');
$router->get('/follow-ups/create', [FollowUpController::class, 'create'], 'can:senda.followups.create', 'senda.followups.create');
$router->post('/follow-ups', [FollowUpController::class, 'store'], 'can:senda.followups.create', 'senda.followups.store');
$router->get('/follow-ups/{id}', [FollowUpController::class, 'show'], 'can:senda.followups.view', 'senda.followups.show');
$router->get('/follow-ups/{id}/edit', [FollowUpController::class, 'edit'], 'can:senda.followups.edit', 'senda.followups.edit');
$router->put('/follow-ups/{id}', [FollowUpController::class, 'update'], 'can:senda.followups.edit', 'senda.followups.update');
$router->delete('/follow-ups/{id}', [FollowUpController::class, 'destroy'], 'can:senda.followups.delete', 'senda.followups.destroy');

$router->get('/followups', [FollowUpController::class, 'index'], 'can:senda.followups.view');
$router->post('/followups/search', [FollowUpController::class, 'search'], 'can:senda.followups.view');
$router->get('/followups/person/{id}', [FollowUpController::class, 'person'], 'can:senda.followups.view');
$router->get('/followups/create', [FollowUpController::class, 'create'], 'can:senda.followups.create');
$router->post('/followups', [FollowUpController::class, 'store'], 'can:senda.followups.create');
$router->get('/followups/{id}', [FollowUpController::class, 'show'], 'can:senda.followups.view');
$router->get('/followups/{id}/edit', [FollowUpController::class, 'edit'], 'can:senda.followups.edit');
$router->put('/followups/{id}', [FollowUpController::class, 'update'], 'can:senda.followups.edit');
$router->delete('/followups/{id}', [FollowUpController::class, 'destroy'], 'can:senda.followups.delete');

$router->get('/statistics', [StatisticsController::class, 'index'], 'can:senda.statistics.view', 'senda.statistics');

$router->get('/meetings', [MeetingController::class, 'index'], 'can:senda.meetings.view', 'senda.meetings.index');
$router->get('/meetings/create', [MeetingController::class, 'create'], 'can:senda.meetings.create', 'senda.meetings.create');
$router->post('/meetings', [MeetingController::class, 'store'], 'can:senda.meetings.create', 'senda.meetings.store');
$router->get('/meetings/{id}', [MeetingController::class, 'show'], 'can:senda.meetings.view', 'senda.meetings.show');
$router->get('/meetings/{id}/edit', [MeetingController::class, 'edit'], 'can:meetings.edit', 'senda.meetings.edit');
$router->post('/meetings/{id}', [MeetingController::class, 'update'], 'can:meetings.edit', 'senda.meetings.update');
$router->post('/meetings/{id}/finalize', [MeetingController::class, 'finalize'], 'can:meetings.edit', 'senda.meetings.finalize');
$router->get('/meetings/{id}/sign', [MeetingController::class, 'signReview'], 'can:meetings.sign', 'senda.meetings.sign.review');
$router->post('/meetings/{id}/sign', [MeetingController::class, 'sign'], 'can:meetings.sign', 'senda.meetings.sign');
$router->post('/meetings/{id}/request-correction', [MeetingController::class, 'requestCorrection'], 'can:meetings.sign', 'senda.meetings.request_correction');
$router->post('/meetings/{id}/cancel', [MeetingController::class, 'cancel'], 'can:meetings.cancel', 'senda.meetings.cancel');
$router->post('/meetings/{id}/reopen', [MeetingController::class, 'reopen'], 'can:meetings.reopen', 'senda.meetings.reopen');
