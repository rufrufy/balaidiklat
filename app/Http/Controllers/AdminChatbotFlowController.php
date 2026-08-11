<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\ChatbotState;
use App\Models\ChatbotTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminChatbotFlowController extends Controller
{
    public function index(): View
    {
        return view('admin.chatbot-flow');
    }

    public function flowData(): JsonResponse
    {
        $states = ChatbotState::orderBy('sort_order')->get();
        $rules = ChatbotRule::where('is_active', true)->orderBy('priority')->get();
        $templates = ChatbotTemplate::orderBy('category')->orderBy('label')->get(['id', 'key', 'label', 'category', 'content']);
        $actionOptions = AdminChatbotRuleController::actionOptions();

        $nodes = [];
        foreach ($states as $state) {
            $nodes[] = [
                'id' => $state->state_key,
                'label' => $state->label,
                'color' => $state->color,
                'is_entry' => $state->is_entry_point,
                'description' => $state->description,
                'sort_order' => $state->sort_order,
                'rule_count' => $rules->where('state', $state->state_key)->count(),
            ];
        }

        $edges = [];
        foreach ($rules as $rule) {
            if ($rule->state && $rule->next_state) {
                $edges[] = [
                    'id' => (string) $rule->id,
                    'from' => $rule->state,
                    'to' => $rule->next_state,
                    'label' => ($rule->keyword ?: 'any') . ($rule->action ? ' | ' . $rule->action : ''),
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->nama,
                    'keyword' => $rule->keyword,
                    'match_type' => $rule->match_type,
                    'action' => $rule->action,
                    'reply_text' => $rule->reply_text,
                    'priority' => $rule->priority,
                    'is_active' => $rule->is_active,
                ];
            }
        }

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
            'templates' => $templates,
            'action_options' => $actionOptions,
        ]);
    }

    public function moveRule(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'rule_id' => ['required', 'exists:chatbot_rules,id'],
            'new_state' => ['required', 'string'],
            'new_sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        ChatbotRule::where('id', $data['rule_id'])->update([
            'state' => $data['new_state'],
            'sort_order' => $data['new_sort_order'] ?? 0,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'state' => $data['new_state']]);
        }

        return redirect()->route('admin.chatbot.flow')->with('status', 'Rule dipindahkan ke state: '.$data['new_state']);
    }

    public function reorderRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.id' => ['required', 'exists:chatbot_rules,id'],
            'rules.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['rules'] as $rule) {
            ChatbotRule::where('id', $rule['id'])->update(['sort_order' => $rule['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    public function storeState(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'state_key' => ['required', 'string', 'unique:chatbot_states,state_key', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'is_entry_point' => ['nullable', 'boolean'],
        ]);

        $data['color'] = $data['color'] ?? '#072C2C';
        $data['sort_order'] = ChatbotState::max('sort_order') + 1;
        $data['is_entry_point'] = $request->boolean('is_entry_point');

        ChatbotState::create($data);

        return redirect()->route('admin.chatbot.flow')->with('status', 'State baru ditambahkan.');
    }

    public function updateState(Request $request, ChatbotState $state): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_entry_point' => ['nullable', 'boolean'],
        ]);

        $data['is_entry_point'] = $request->boolean('is_entry_point');
        $state->update($data);

        return redirect()->route('admin.chatbot.flow')->with('status', 'State diperbarui.');
    }

    public function destroyState(ChatbotState $state): RedirectResponse
    {
        $count = ChatbotRule::where('state', $state->state_key)
            ->orWhere('next_state', $state->state_key)
            ->count();

        if ($count > 0) {
            return redirect()->back()->withErrors([
                'state' => "Tidak bisa hapus state. Masih ada {$count} rule yang memakai state ini.",
            ]);
        }

        $state->delete();

        return redirect()->route('admin.chatbot.flow')->with('status', 'State dihapus.');
    }

    private function ensureStatesExist($rules, $states): void
    {
        $existing = $states->pluck('state_key')->toArray();
        $allStates = $rules->keys()
            ->merge($rules->flatten(1)->pluck('next_state')->filter())
            ->unique()
            ->filter(fn ($s) => ! empty($s) && ! in_array($s, $existing))
            ->values();

        $nextSort = ChatbotState::max('sort_order') ?? 0;
        foreach ($allStates as $stateKey) {
            $nextSort++;
            ChatbotState::create([
                'state_key' => $stateKey,
                'label' => ucfirst(str_replace('_', ' ', $stateKey)),
                'sort_order' => $nextSort,
                'is_entry_point' => $stateKey === 'main_menu',
            ]);
        }
    }
}
