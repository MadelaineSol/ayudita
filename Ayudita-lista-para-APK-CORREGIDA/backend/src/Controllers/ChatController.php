<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Text;
use App\Repositories\ChatRepository;
use App\Services\UploadService;
use App\Validation\Validator;

/**
 * Chat interno: texto, fotos, ubicación y archivos.
 * El frontend consulta mensajes nuevos con ?after_id= (polling liviano).
 */
final class ChatController
{
    private ChatRepository $chat;

    public function __construct()
    {
        $this->chat = new ChatRepository();
    }

    public function conversations(Request $request): void
    {
        Response::json($this->chat->listForUser($request->userId()));
    }

    public function open(Request $request): void
    {
        $data = Validator::validate($request->body, [
            'user_id'    => 'required|integer',
            'booking_id' => 'integer',
        ]);
        if ((int) $data['user_id'] === $request->userId()) {
            throw new HttpException('No podés chatear con vos mismo', 400);
        }
        $id = $this->chat->findOrCreateConversation(
            $request->userId(),
            (int) $data['user_id'],
            isset($data['booking_id']) ? (int) $data['booking_id'] : null
        );
        Response::created(['conversation_id' => $id]);
    }

    public function messages(Request $request): void
    {
        $conversation = $this->requireMembership($request);
        $afterId = (int) ($request->queryParam('after_id') ?? 0);
        $messages = $this->chat->messages((int) $conversation['id'], $afterId);
        $this->chat->markRead((int) $conversation['id'], $request->userId());
        Response::json($messages);
    }

    public function send(Request $request): void
    {
        $conversation = $this->requireMembership($request);

        $type = (string) $request->input('type', 'text');
        $message = [
            'conversation_id' => (int) $conversation['id'],
            'sender_id'       => $request->userId(),
            'type'            => $type,
        ];

        switch ($type) {
            case 'text':
                $data = Validator::validate($request->body, ['body' => 'required|min:1|max:2000']);
                $message['body'] = Text::clean($data['body']);
                break;
            case 'location':
                $data = Validator::validate($request->body, [
                    'lat' => 'required|numeric',
                    'lng' => 'required|numeric',
                ]);
                $message['lat'] = (float) $data['lat'];
                $message['lng'] = (float) $data['lng'];
                break;
            case 'image':
            case 'file':
                if (!isset($request->files['file'])) {
                    throw new HttpException('Falta el archivo "file"', 422);
                }
                $message['file_url'] = (new UploadService())->store($request->files['file']);
                $message['body'] = Text::clean((string) $request->input('body', ''));
                break;
            default:
                throw new HttpException('Tipo de mensaje inválido', 422);
        }

        $id = $this->chat->addMessage($message);
        Response::created(array_merge(['id' => $id], $message, ['created_at' => date('Y-m-d H:i:s')]));
    }

    private function requireMembership(Request $request): array
    {
        $conversation = $this->chat->findConversation((int) $request->param('id'));
        if ($conversation === null) {
            throw new HttpException('Conversación no encontrada', 404);
        }
        $uid = $request->userId();
        if ((int) $conversation['user_one'] !== $uid && (int) $conversation['user_two'] !== $uid) {
            throw new HttpException('No formás parte de esta conversación', 403);
        }
        return $conversation;
    }
}
