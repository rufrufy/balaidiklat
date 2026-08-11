<?php

namespace App\Observers;

use App\Models\ChatbotRule;
use App\Models\ChatbotState;

class ChatbotRuleObserver
{
    public function saved(ChatbotRule $rule): void
    {
        $this->ensureStateExists($rule->state);
        $this->ensureStateExists($rule->next_state);
    }

    private function ensureStateExists(?string $stateKey): void
    {
        if (! $stateKey) {
            return;
        }

        ChatbotState::firstOrCreate(
            ['state_key' => $stateKey],
            [
                'label' => ucfirst(str_replace('_', ' ', $stateKey)),
                'sort_order' => ChatbotState::max('sort_order') + 1 ?? 0,
                'is_entry_point' => $stateKey === 'main_menu',
            ]
        );
    }
}
