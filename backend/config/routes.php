<?php
declare(strict_types=1);

use TaskBuddy\Controllers\{AuthController, ProfileController, RequestController, InvitationController, TaskController, MessageController, PaymentController, NotificationController, ModerationController};
use TaskBuddy\Support\Router;
use TaskBuddy\Support\Response;

/** The logical API contract documented in docs/api.md. Roles: client | provider (admin has no mobile API access). */
return static function (Router $r): void {
    $r->add('GET', '/api/health', [AuthController::class, 'health'], false);

    $r->add('POST', '/api/auth/register', [AuthController::class, 'register'], false);
    $r->add('POST', '/api/auth/login', [AuthController::class, 'login'], false);
    $r->add('POST', '/api/auth/logout', [AuthController::class, 'logout']);
    $r->add('GET', '/api/auth/me', [AuthController::class, 'me']);
    $r->add('POST', '/api/auth/refresh', [AuthController::class, 'refresh']);

    $r->add('GET', '/api/profile', [ProfileController::class, 'show']);
    $r->add('PUT', '/api/profile', [ProfileController::class, 'update']);
    $r->add('GET', '/api/providers/{id}', [ProfileController::class, 'provider']);
    $r->add('PUT', '/api/provider/skills', [ProfileController::class, 'skills'], true, ['provider']);
    $r->add('PUT', '/api/provider/categories', [ProfileController::class, 'categories'], true, ['provider']);
    $r->add('PUT', '/api/provider/availability', [ProfileController::class, 'availability'], true, ['provider']);

    $r->add('GET', '/api/categories', [ProfileController::class, 'listCategories'], false);
    $r->add('GET', '/api/skills', [ProfileController::class, 'listSkills'], false);
    $r->add('GET', '/api/categories/{id}/skills', [ProfileController::class, 'categorySkills'], false);

    $r->add('POST', '/api/requests', [RequestController::class, 'create'], true, ['client']);
    $r->add('GET', '/api/requests', [RequestController::class, 'index']);
    $r->add('GET', '/api/requests/{id}', [RequestController::class, 'show']);
    $r->add('PUT', '/api/requests/{id}', [RequestController::class, 'update'], true, ['client']);
    $r->add('POST', '/api/requests/{id}/cancel', [RequestController::class, 'cancel'], true, ['client']);
    $r->add('GET', '/api/requests/{id}/matches', [RequestController::class, 'matches'], true, ['client']);
    $r->add('POST', '/api/requests/{id}/matches', [RequestController::class, 'rematch'], true, ['client']);
    $r->add('POST', '/api/requests/{id}/attachments', [RequestController::class, 'attach'], true, ['client']);
    $r->add('GET', '/api/attachments/{id}/download', [RequestController::class, 'downloadAttachment']);

    $r->add('POST', '/api/requests/{id}/invite', [RequestController::class, 'invite'], true, ['client']);
    $r->add('GET', '/api/invitations', [InvitationController::class, 'index']);
    $r->add('POST', '/api/invitations/{id}/accept', [InvitationController::class, 'accept'], true, ['provider']);
    $r->add('POST', '/api/invitations/{id}/decline', [InvitationController::class, 'decline'], true, ['provider']);

    $r->add('GET', '/api/tasks', [TaskController::class, 'index']);
    $r->add('GET', '/api/tasks/{id}', [TaskController::class, 'show']);
    $r->add('POST', '/api/tasks/{id}/submit', [TaskController::class, 'submit'], true, ['provider']);
    $r->add('POST', '/api/tasks/{id}/start-review', [TaskController::class, 'startReview'], true, ['client']);
    $r->add('POST', '/api/tasks/{id}/approve', [TaskController::class, 'approve'], true, ['client']);
    $r->add('POST', '/api/tasks/{id}/revision', [TaskController::class, 'revision'], true, ['client']);
    $r->add('POST', '/api/tasks/{id}/dispute', [TaskController::class, 'dispute']);
    $r->add('GET', '/api/submission-files/{id}/download', [TaskController::class, 'downloadSubmissionFile']);

    $r->add('GET', '/api/conversations', [MessageController::class, 'conversations']);
    $r->add('GET', '/api/conversations/{id}/messages', [MessageController::class, 'messages']);
    $r->add('POST', '/api/conversations/{id}/messages', [MessageController::class, 'send']);
    $r->add('POST', '/api/conversations/{id}/read', [MessageController::class, 'read']);

    $r->add('GET', '/api/payments', [PaymentController::class, 'index']);
    $r->add('GET', '/api/payments/{id}', [PaymentController::class, 'show']);

    $r->add('GET', '/api/notifications', [NotificationController::class, 'index']);
    $r->add('POST', '/api/notifications/read-all', [NotificationController::class, 'markAllRead']);
    $r->add('POST', '/api/notifications/{id}/read', [NotificationController::class, 'markRead']);

    $r->add('POST', '/api/providers/{id}/block', [ModerationController::class, 'block'], true, ['client']);
    $r->add('DELETE', '/api/providers/{id}/block', [ModerationController::class, 'unblock'], true, ['client']);
    $r->add('POST', '/api/reports', [ModerationController::class, 'report']);
};
