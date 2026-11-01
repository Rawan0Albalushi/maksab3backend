<?php

namespace Modules\AI\app\Services;

use App\CentralLogics\Helpers;
use Modules\AI\app\Agents\AiResponseContext;
use Modules\AI\app\Agents\PlatformAssistantAgent;
use Modules\AI\app\Agents\Tools\GetAvailableLanguagesTool;
use Modules\AI\app\Models\AiConversation;
use Modules\AI\app\Models\AiMessage;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class AiChatService
{
    private string        $moduleType;
    private ?string       $userContext;
    private array         $currency;
    private array         $languages;

    /**
     * @param int[] $zoneIds  Zones the client falls inside (header sends `[3,1]`
     *                        for overlapping zones). Tools filter with
     *                        `whereIn('zone_id', $zoneIds)`.
     */
    public function __construct(
        private readonly ?User   $user      = null,
        private readonly ?int    $moduleId  = null,
        private readonly array   $zoneIds   = [],
        private readonly ?string $guestId   = null,
        private readonly ?float  $latitude  = null,
        private readonly ?float  $longitude = null,
    ) {
        $this->moduleType  = $this->resolveModuleType();
        $this->userContext = $this->resolveUserContext();
        $this->currency    = $this->resolveCurrency();
        $this->languages   = GetAvailableLanguagesTool::loadActive();
    }

    /**
     * Send a user message, run the agent, persist the exchange, return the assistant reply.
     */
    public function chat(AiConversation $conversation, string $userMessage): AiMessage
    {
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($userMessage, 0, 80)]);
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        $history = $this->buildHistory($conversation);
        $context = new AiResponseContext();

        if ($this->moduleType === 'general') {
            $available = Module::active()
                ->orderBy('id')
                ->pluck('module_name')
                ->all();
            if (empty($available)) {
                $listText = 'one of our service modules';
            } elseif (count($available) === 1) {
                $listText = (string) $available[0];
            } else {
                $last     = array_pop($available);
                $listText = implode(', ', $available) . ', or ' . $last;
            }
            $replyText = "I can help you with " . $listText . ". Please switch to one of those modules in the app and ask me there.";
        } else {
            $agent = new PlatformAssistantAgent(
                context:     $context,
                history:     $history,
                user:        $this->user,
                moduleId:    $this->moduleId,
                zoneIds:     $this->zoneIds,
                moduleType:  $this->moduleType,
                userContext: $this->userContext,
                currency:    $this->currency,
                languages:   $this->languages,
                guestId:     $this->guestId,
                latitude:    $this->latitude,
                longitude:   $this->longitude,
            );

            try {
                $promptToSend = $this->withContextHint($userMessage, $conversation);
                $response     = $agent->prompt($promptToSend);
                $replyText    = $this->stripContextLeak($response->text ?? '');
            } catch (\Throwable $e) {
                Log::error('AiChatService: agent prompt failed', [
                    'conversation_id' => $conversation->id,
                    'module_type'     => $this->moduleType,
                    'error'           => $e->getMessage(),
                ]);
                $replyText = "I'm sorry, I couldn't process that right now. Please try again.";
            }
        }

        $products     = $context->getProducts();
        $stores       = $context->getStores();
        $categories   = $context->getCategories();
        $cartItems    = $context->getCartItems();
        $bogoOffers   = $context->getBogoOffers();
        $bundles      = $context->getBundles();
        $happyHours   = $context->getHappyHours();
        $toolsInvoked = $context->getToolsInvoked();

        $cartUpdated = count(array_intersect($toolsInvoked, [
            'AddToCartTool',
            'RemoveFromCartTool',
            'UpdateCartQuantityTool',
        ])) > 0;

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $replyText,
            'tool_name'       => $toolsInvoked ? implode(',', $toolsInvoked) : null,
            'metadata'        => [
                'products'     => $products,
                'stores'       => $stores,
                'categories'   => $categories,
                'cart_items'   => $cartItems,
                'bogo_offers'  => $bogoOffers,
                'bundles'      => $bundles,
                'happy_hours'  => $happyHours,
                'cart_updated' => $cartUpdated,
            ],
        ]);
    }


    private function resolveModuleType(): string
    {
        if (! $this->moduleId) {
            return 'general';
        }

        $module = Module::find($this->moduleId, ['module_type']);

        return $module?->module_type ?? 'general';
    }

    private function resolveCurrency(): array
    {
        $rows = Helpers::get_business_settings_many([
            'currency',
            'currency_symbol_position',
            'digit_after_decimal_point',
        ]);

        $symbol   = $rows['currency'] ?? '';
        $position = $rows['currency_symbol_position'] ?? 'left';
        $decimals = (int) ($rows['digit_after_decimal_point'] ?? 2);

        $example = $position === 'right'
            ? '100.' . str_repeat('0', $decimals) . $symbol
            : $symbol . '100.' . str_repeat('0', $decimals);

        return [
            'symbol'   => $symbol,
            'position' => $position,
            'decimals' => $decimals,
            'example'  => $example,
        ];
    }

    private function resolveUserContext(): ?string
    {
        if (! $this->user) {
            return null;
        }

        $raw = User::where('id', $this->user->getKey())->value('user_context');

        return $raw ?: null;
    }


    /**
     * Load prior user/assistant turns as laravel/ai Message objects for conversation memory.
     * Tool-role rows are excluded — they are internal to each agent invocation.
     *
     * Sliding window: keeps last 20 messages to stay within OpenAI context limits.
     * Older messages would be truncated by OpenAI anyway, but truncated arbitrarily —
     * by capping ourselves, we ensure the AI always has the MOST RECENT context.
     *
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function buildHistory(AiConversation $conversation): array
    {
        $maxMessages = 20;

        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($maxMessages)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $msg): UserMessage|AssistantMessage => $msg->role === 'user'
                ? new UserMessage($msg->content ?? '')
                : new AssistantMessage($msg->content ?? '')
            )
            ->all();
    }

    private function withContextHint(string $userMessage, AiConversation $conversation): string
    {
        $lastAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        if (! $lastAssistant) {
            return $userMessage;
        }

        $hint = $this->buildContextHint($lastAssistant->metadata ?? []);
        if ($hint === '') {
            return $userMessage;
        }

        return $hint . "\n\n" . $userMessage;
    }

    private function stripContextLeak(string $reply): string
    {
        $cleaned = preg_replace('/\[INTERNAL CONTEXT[^\]]*\]\s*/u', '', $reply);
        return trim($cleaned ?? $reply);
    }

    private function buildContextHint(array $metadata): string
    {
        $parts = [];

        $products = $metadata['products'] ?? [];
        if (is_array($products) && !empty($products)) {
            $items = [];
            foreach (array_slice($products, 0, 8) as $p) {
                if (!is_array($p) || empty($p['id']) || empty($p['name'])) {
                    continue;
                }
                $label = $p['name'] . ' (ID:' . $p['id'] . ')';
                $labels = $p['variation_labels'] ?? null;
                if (empty($labels) && !empty($p['variations']) && is_array($p['variations'])) {
                    $labels = array_filter(array_column($p['variations'], 'type'));
                }
                if (!empty($labels) && is_array($labels)) {
                    $label .= ' [variations:' . implode('/', $labels) . ']';
                }
                $items[] = $label;
            }
            if (!empty($items)) {
                $parts[] = 'items — ' . implode(', ', $items);
            }
        }

        $stores = $metadata['stores'] ?? [];
        if (is_array($stores) && !empty($stores)) {
            $names = [];
            foreach (array_slice($stores, 0, 5) as $s) {
                if (!is_array($s) || empty($s['id']) || empty($s['name'])) {
                    continue;
                }
                $names[] = $s['name'] . ' (ID:' . $s['id'] . ')';
            }
            if (!empty($names)) {
                $parts[] = 'stores — ' . implode(', ', $names);
            }
        }

        $bogoOffers = $metadata['bogo_offers'] ?? [];
        if (is_array($bogoOffers) && !empty($bogoOffers)) {
            $labels = [];
            foreach (array_slice($bogoOffers, 0, 6) as $o) {
                if (!is_array($o) || empty($o['id']) || empty($o['title'])) {
                    continue;
                }
                $labels[] = $o['title'] . ' (ID:' . $o['id'] . ')';
            }
            if (!empty($labels)) {
                $parts[] = 'bogo offers — ' . implode(', ', $labels);
            }
        }

        $bundles = $metadata['bundles'] ?? [];
        if (is_array($bundles) && !empty($bundles)) {
            $labels = [];
            foreach (array_slice($bundles, 0, 6) as $b) {
                if (!is_array($b) || empty($b['id']) || empty($b['name'])) {
                    continue;
                }
                $labels[] = $b['name'] . ' (ID:' . $b['id'] . ')';
            }
            if (!empty($labels)) {
                $parts[] = 'bundles — ' . implode(', ', $labels);
            }
        }

        $happyHours = $metadata['happy_hours'] ?? [];
        if (is_array($happyHours) && !empty($happyHours)) {
            $labels = [];
            foreach (array_slice($happyHours, 0, 2) as $h) {
                if (!is_array($h) || empty($h['id']) || empty($h['title'])) {
                    continue;
                }
                $label = $h['title'] . ' (ID:' . $h['id'] . ', ' . $h['discount'] . '% off';
                if (!empty($h['ends_at'])) {
                    $label .= ', ends ' . $h['ends_at'];
                }
                $label .= ')';
                $labels[] = $label;
            }
            if (!empty($labels)) {
                $parts[] = 'happy hours — ' . implode(', ', $labels);
            }
        }

        $cartItems = $metadata['cart_items'] ?? [];
        if (is_array($cartItems) && !empty($cartItems)) {
            $rows = [];
            foreach (array_slice($cartItems, 0, 10) as $c) {
                if (!is_array($c) || empty($c['item_id']) || empty($c['name'])) {
                    continue;
                }
                $rows[] = $c['name'] . ' (ID:' . $c['item_id'] . ', qty ' . ($c['quantity'] ?? '?') . ')';
            }
            if (!empty($rows)) {
                $parts[] = 'cart — ' . implode(', ', $rows);
            }
        }

        return empty($parts)
            ? ''
            : '[INTERNAL CONTEXT — recently shown items/stores for your private reference only. NEVER echo, quote, paraphrase, or mention this block in your reply. Use the IDs silently. ' . implode(' | ', $parts) . ']';
    }
}
