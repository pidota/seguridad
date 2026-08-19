<?php

declare(strict_types=1);

use App\Controllers\WomenOffice\CaseController;
use App\Controllers\WomenOffice\DashboardController;
use App\Controllers\WomenOffice\FollowUpController;
use App\Controllers\WomenOffice\PeopleController;
use App\Controllers\WomenOffice\StatisticsController;
use Core\Router;

/** @var Router $router */

$router->get('/', [DashboardController::class, 'index'], 'can:women.dashboard.view', 'women.dashboard');

$router->get('/cases', [CaseController::class, 'index'], 'can:women.cases.view', 'women.cases.index');
$router->get('/cases/create', [CaseController::class, 'create'], 'can:women.cases.create', 'women.cases.create');
$router->get('/cases/create/person', [PeopleController::class, 'create'], 'can:women.cases.create', 'women.cases.create.person');
$router->get('/cases/create/register', [CaseController::class, 'register'], 'can:women.cases.create', 'women.cases.create.register');
$router->post('/cases', [CaseController::class, 'store'], 'can:women.cases.create', 'women.cases.store');
$router->get('/cases/{id}/facts', [CaseController::class, 'facts'], 'can:women.cases.view', 'women.cases.facts');
$router->post('/cases/{id}/facts', [CaseController::class, 'updateFacts'], 'can:women.cases.edit', 'women.cases.facts.update');
$router->get('/cases/{id}/aggressor', [CaseController::class, 'aggressor'], 'can:women.cases.view', 'women.cases.aggressor');
$router->post('/cases/{id}/aggressor', [CaseController::class, 'updateAggressor'], 'can:women.cases.edit', 'women.cases.aggressor.update');
$router->get('/cases/{id}/background', [CaseController::class, 'background'], 'can:women.cases.view', 'women.cases.background');
$router->post('/cases/{id}/background', [CaseController::class, 'updateBackground'], 'can:women.cases.edit', 'women.cases.background.update');
$router->get('/cases/{id}/risk-priority', [CaseController::class, 'riskPriority'], 'can:women.cases.view', 'women.cases.risk_priority');
$router->post('/cases/{id}/risk-priority', [CaseController::class, 'updateRiskPriority'], 'can:women.cases.edit', 'women.cases.risk_priority.update');
$router->get('/cases/{id}/support', [CaseController::class, 'support'], 'can:women.cases.view', 'women.cases.support');
$router->post('/cases/{id}/support', [CaseController::class, 'updateSupport'], 'can:women.cases.edit', 'women.cases.support.update');
$router->get('/cases/{id}/actions', [CaseController::class, 'actions'], 'can:women.cases.view', 'women.cases.actions');
$router->post('/cases/{id}/actions', [CaseController::class, 'updateActions'], 'can:women.cases.edit', 'women.cases.actions.update');
$router->get('/cases/{id}/referrals', [CaseController::class, 'referrals'], 'can:women.cases.view', 'women.cases.referrals');
$router->post('/cases/{id}/referrals', [CaseController::class, 'updateReferrals'], 'can:women.cases.edit', 'women.cases.referrals.update');
$router->get('/cases/{id}/follow-ups', [CaseController::class, 'followUps'], 'can:women.cases.view', 'women.cases.follow_ups');
$router->post('/cases/{id}/follow-ups', [CaseController::class, 'updateFollowUps'], 'can:women.cases.edit', 'women.cases.follow_ups.update');
$router->post('/cases/{id}/close', [CaseController::class, 'close'], 'can:women.cases.close', 'women.cases.close');
$router->post('/cases/{id}/cancel', [CaseController::class, 'cancel'], 'can:women.cases.close', 'women.cases.cancel');
$router->post('/cases/{id}/documents', [CaseController::class, 'uploadDocument'], 'can:women.documents.upload', 'women.cases.documents.upload');
$router->get('/cases/{id}/documents/{documentId}', [CaseController::class, 'downloadDocument'], 'can:women.documents.view', 'women.cases.documents.download');
$router->post('/cases/{id}/documents/{documentId}/delete', [CaseController::class, 'deleteDocument'], 'can:women.documents.upload', 'women.cases.documents.delete');
$router->get('/cases/{id}', [CaseController::class, 'show'], 'can:women.cases.view', 'women.cases.show');

$router->post('/people/lookup', [PeopleController::class, 'lookup'], 'can:women.cases.create', 'women.people.lookup');
$router->get('/people/create/form', [PeopleController::class, 'form'], 'can:women.people.create,women.cases.create', 'women.people.form');
$router->post('/people', [PeopleController::class, 'store'], 'can:women.people.create,women.cases.create', 'women.people.store');
$router->get('/people/{id}/edit', [PeopleController::class, 'edit'], 'can:women.people.edit', 'women.people.edit');
$router->post('/people/{id}', [PeopleController::class, 'update'], 'can:women.people.edit', 'women.people.update');
$router->post('/people/{id}/use', [PeopleController::class, 'usePerson'], 'can:women.cases.create', 'women.people.use');

$router->get('/follow-ups', [FollowUpController::class, 'index'], 'can:women.followups.view', 'women.followups.index');

$router->get('/statistics', [StatisticsController::class, 'index'], 'can:women.statistics.view', 'women.statistics.index');
