<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppInbox\WhatsAppInboxService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Bandeja de WhatsApp: conversaciones con quienes escriben al número de la API (webhook de Meta)
 * y respuestas desde el admin (texto libre dentro de las 24 h, template aprobado fuera de ellas).
 */
class WhatsAppInbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Bandeja WhatsApp';

    protected static ?string $title = 'Bandeja de WhatsApp';

    protected static ?string $slug = 'whatsapp-inbox';

    protected string $view = 'filament.pages.whatsapp-inbox';

    #[Url(as: 'c')]
    public ?int $conversationId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $onlyUnread = false;

    public int $conversationLimit = 50;

    public int $messageLimit = 100;

    public string $reply = '';

    public string $templateKey = '';

    /** @var array<string, string> */
    public array $templateParams = [];

    public static function getNavigationBadge(): ?string
    {
        $unread = WhatsAppConversation::where('unread_count', '>', 0)->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public function mount(): void
    {
        if ($this->conversationId !== null) {
            $this->openConversation($this->conversationId);
        }
    }

    public function openConversation(int $conversationId): void
    {
        $conversation = WhatsAppConversation::find($conversationId);

        if ($conversation === null) {
            $this->conversationId = null;

            return;
        }

        $this->conversationId = $conversation->id;
        $this->messageLimit = 100;
        $this->reset('reply', 'templateKey', 'templateParams');
        $this->resetErrorBag();

        app(WhatsAppInboxService::class)->markAsRead($conversation);
    }

    public function closeConversation(): void
    {
        $this->conversationId = null;
    }

    /**
     * Llamado por wire:poll: marca como leído lo que llega a la conversación abierta.
     */
    public function refreshInbox(): void
    {
        $conversation = $this->selectedConversation();

        if ($conversation !== null && $conversation->unread_count > 0) {
            app(WhatsAppInboxService::class)->markAsRead($conversation);
        }
    }

    public function loadMoreConversations(): void
    {
        $this->conversationLimit += 50;
    }

    public function loadOlderMessages(): void
    {
        $this->messageLimit += 100;
    }

    public function sendReply(): void
    {
        $this->validate(
            ['reply' => ['required', 'string', 'max:4096']],
            [],
            ['reply' => 'mensaje'],
        );

        $conversation = $this->selectedConversation();

        if ($conversation === null) {
            return;
        }

        try {
            $message = app(WhatsAppInboxService::class)->sendText($conversation, $this->reply, auth()->user());
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->notifyResult($message);

        if ($message->status !== 'failed') {
            $this->reset('reply');
        }
    }

    public function updatedTemplateKey(): void
    {
        $template = $this->selectedTemplate();

        $this->templateParams = $template === null
            ? []
            : array_fill_keys($template['variables'], '');
    }

    public function sendTemplate(): void
    {
        $conversation = $this->selectedConversation();
        $template = $this->selectedTemplate();

        if ($conversation === null || $template === null) {
            Notification::make()->title('Elige un template.')->warning()->send();

            return;
        }

        try {
            $message = app(WhatsAppInboxService::class)->sendTemplate(
                $conversation,
                $template['name'],
                $template['language'],
                $this->templateParams,
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->notifyResult($message);

        if ($message->status !== 'failed') {
            $this->reset('templateKey', 'templateParams');
        }
    }

    public function refreshTemplates(): void
    {
        $count = count(app(WhatsAppInboxService::class)->availableTemplates(refresh: true));

        Notification::make()
            ->title($count > 0 ? "Templates actualizados ({$count})." : 'No se encontraron templates aprobados utilizables.')
            ->color($count > 0 ? 'success' : 'warning')
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $conversation = $this->selectedConversation();
        $messages = $conversation !== null ? $this->messagesFor($conversation) : collect();

        return [
            'conversations' => $this->conversations(),
            'hasMoreConversations' => $this->conversationsQuery()->count() > $this->conversationLimit,
            'conversation' => $conversation,
            'messages' => $messages,
            'hasOlderMessages' => $conversation !== null && $conversation->messages()->count() > $this->messageLimit,
            'templates' => $conversation !== null && ! $conversation->isServiceWindowOpen()
                ? app(WhatsAppInboxService::class)->availableTemplates()
                : [],
            'selectedTemplate' => $this->selectedTemplate(),
        ];
    }

    private function selectedConversation(): ?WhatsAppConversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        return WhatsAppConversation::with('user')->find($this->conversationId);
    }

    /**
     * @return array{key: string, name: string, language: string, category: ?string, body: string, variables: array<int, string>, named: bool}|null
     */
    private function selectedTemplate(): ?array
    {
        if ($this->templateKey === '') {
            return null;
        }

        return collect(app(WhatsAppInboxService::class)->availableTemplates())
            ->firstWhere('key', $this->templateKey);
    }

    private function conversationsQuery(): Builder
    {
        $search = trim($this->search);

        return WhatsAppConversation::query()
            ->when($this->onlyUnread, fn (Builder $query) => $query->where('unread_count', '>', 0))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($like, $digits): void {
                    $query->whereRaw('LOWER(profile_name) LIKE ?', [$like])
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(agency) LIKE ?', [$like]));

                    if ($digits !== '') {
                        $query->orWhere('wa_id', 'like', '%'.$digits.'%');
                    }
                });
            });
    }

    /**
     * @return Collection<int, WhatsAppConversation>
     */
    private function conversations(): Collection
    {
        return $this->conversationsQuery()
            ->with('user')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($this->conversationLimit)
            ->get();
    }

    /**
     * Últimos mensajes de la conversación, en orden cronológico.
     *
     * @return Collection<int, WhatsAppMessage>
     */
    private function messagesFor(WhatsAppConversation $conversation): Collection
    {
        return $conversation->messages()
            ->with(['sender', 'notificationLog.propertyListing', 'notificationLog.propertyRequest'])
            ->orderByDesc('id')
            ->limit($this->messageLimit)
            ->get()
            ->reverse()
            ->values();
    }

    private function notifyResult(WhatsAppMessage $message): void
    {
        if ($message->status === 'failed') {
            Notification::make()
                ->title('WhatsApp no aceptó el mensaje')
                ->body($message->error_message)
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Mensaje enviado.')->success()->send();
    }
}
