<?php

namespace App\Http\Controllers;

use App\Models\ChatbotTemplate;
use App\Services\ChatbotTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminChatbotTemplateController extends Controller
{
    public function __construct(
        private readonly ChatbotTemplateService $templateService,
    ) {}

    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = $request->query('search');

        $templates = ChatbotTemplate::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%");
            }))
            ->orderBy('category')
            ->orderBy('label')
            ->paginate(20);

        $categories = ChatbotTemplate::select('category')->distinct()->pluck('category');
        $placeholders = $this->templateService->availablePlaceholders();

        return view('admin.chatbot-templates', compact('templates', 'categories', 'placeholders'));
    }

    public function edit(ChatbotTemplate $template): View
    {
        $placeholders = $this->templateService->availablePlaceholders();

        return view('admin.chatbot-template-edit', compact('template', 'placeholders'));
    }

    public function update(Request $request, ChatbotTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $template->update(array_merge($data, ['updated_by' => auth()->id() ?? 1]));

        return redirect()->route('admin.chatbot.templates.index')
            ->with('status', 'Template "'.$template->label.'" berhasil diperbarui.');
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string'],
            'vars' => ['nullable', 'array'],
        ]);

        $content = $data['content'];
        $vars = $data['vars'] ?? [];

        foreach ($vars as $k => $v) {
            $content = str_replace("{{{$k}}}", (string) $v, $content);
        }

        return response()->json(['preview' => $content]);
    }
}
