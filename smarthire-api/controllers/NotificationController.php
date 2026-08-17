<?php

require_once __DIR__ . '/../repositories/NotificationRepository.php';
require_once __DIR__ . '/../core/Response.php';

class NotificationController
{
    /** GET /api/notifications?page=1&per_page=20 — notifications de l'utilisateur connecté */
    public static function index(Request $request): void
    {
        $repo = new NotificationRepository();
        $pagination = $request->pagination();

        $result = $repo->paginateForUser((int) $request->user['sub'], $pagination['offset'], $pagination['perPage']);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** GET /api/notifications/unread-count — pour la pastille de la cloche dans la navbar */
    public static function unreadCount(Request $request): void
    {
        $repo = new NotificationRepository();
        $count = $repo->countUnread((int) $request->user['sub']);

        Response::json(['unread_count' => $count]);
    }

    /** PUT /api/notifications/{id}/read */
    public static function markRead(Request $request): void
    {
        $repo = new NotificationRepository();
        $id = (int) $request->params['id'];

        $notification = $repo->findById($id);
        if (!$notification) {
            Response::error('Notification introuvable', 404);
        }
        if ((int) $notification['user_id'] !== (int) $request->user['sub']) {
            Response::error('Forbidden', 403);
        }

        $repo->markRead($id, (int) $request->user['sub']);
        Response::json($repo->findById($id));
    }

    /** PUT /api/notifications/read-all */
    public static function markAllRead(Request $request): void
    {
        $repo = new NotificationRepository();
        $repo->markAllRead((int) $request->user['sub']);

        Response::json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }
}
