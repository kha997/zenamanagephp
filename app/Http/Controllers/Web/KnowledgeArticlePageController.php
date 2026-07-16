<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KnowledgeArticlePageController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = KnowledgeArticle::query()->where('tenant_id', $tenantId);

        if (!auth()->user()?->hasPermission('knowledge.manage') || $request->query('status') !== 'draft') {
            $query->where('status', KnowledgeArticle::STATUS_PUBLISHED);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->query('q')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        $articles = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('knowledge.index', [
            'articles' => $articles,
            'types' => KnowledgeArticle::VALID_TYPES,
            'filters' => $request->only(['type', 'category', 'q', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('knowledge.form', [
            'article' => null,
            'types' => KnowledgeArticle::VALID_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $tenantId = (string) auth()->user()?->tenant_id;
        $userId = (string) auth()->id();

        $article = KnowledgeArticle::query()->create([
            'tenant_id' => $tenantId,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'body' => $validated['body'] ?? null,
            'checklist_items' => $validated['checklist_items'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('operator.knowledge.show', $article->id)->with('success', 'Đã tạo bản nháp.');
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()
            ->where('tenant_id', $tenantId)
            ->with(['project:id,tenant_id,name', 'creator:id,name'])
            ->findOrFail($id);

        return view('knowledge.show', ['article' => $article]);
    }

    public function edit(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($article->status !== KnowledgeArticle::STATUS_DRAFT) {
            abort(404);
        }

        return view('knowledge.form', [
            'article' => $article,
            'types' => KnowledgeArticle::VALID_TYPES,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($article->status !== KnowledgeArticle::STATUS_DRAFT) {
            return back()->with('error', 'Chỉ có thể sửa bản nháp.');
        }

        $validated = $this->validatePayload($request);

        $article->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'body' => $validated['body'] ?? null,
            'checklist_items' => $validated['checklist_items'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'updated_by' => (string) auth()->id(),
        ]);

        return redirect()->route('operator.knowledge.show', $article->id)->with('success', 'Đã lưu.');
    }

    public function publish(string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if (!KnowledgeArticle::canTransition($article->status, KnowledgeArticle::STATUS_PUBLISHED)) {
            return back()->with('error', 'Không thể xuất bản.');
        }

        if ($article->type === KnowledgeArticle::TYPE_CHECKLIST) {
            if (empty($article->checklist_items)) {
                return back()->with('error', 'Checklist cần ít nhất một mục trước khi xuất bản.');
            }
        } elseif (empty($article->body)) {
            return back()->with('error', 'Nội dung không được để trống trước khi xuất bản.');
        }

        $article->update([
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'published_at' => $article->published_at ?? now(),
        ]);

        return back()->with('success', 'Đã xuất bản.');
    }

    public function unpublish(string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if (!KnowledgeArticle::canTransition($article->status, KnowledgeArticle::STATUS_DRAFT)) {
            return back()->with('error', 'Không thể gỡ xuất bản.');
        }

        $article->update(['status' => KnowledgeArticle::STATUS_DRAFT]);

        return back()->with('success', 'Đã gỡ xuất bản, chuyển về bản nháp.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $article = KnowledgeArticle::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($article->status !== KnowledgeArticle::STATUS_DRAFT) {
            return back()->with('error', 'Chỉ có thể xóa bản nháp.');
        }

        $article->delete();

        return redirect()->route('operator.knowledge.index')->with('success', 'Đã xóa.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:' . implode(',', KnowledgeArticle::VALID_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'body' => ['nullable', 'string'],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*.text' => ['required_with:checklist_items', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'project_id' => ['nullable', 'string'],
        ]);

        if (!empty($validated['checklist_items'])) {
            $validated['checklist_items'] = array_map(
                static fn (array $item): array => ['text' => $item['text'], 'done' => (bool) ($item['done'] ?? false)],
                $validated['checklist_items']
            );
        }

        return $validated;
    }
}
