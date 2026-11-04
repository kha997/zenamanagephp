<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryDocumentAttachment as Attachment;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GAP-106 (Gate 2 S-2, E-1, M-1, D-1) — evidence files of Treasury money documents.
 *
 * S-2: own table, private disk, read only through Treasury routes. E-1: any money document may carry evidence;
 * "missing evidence" counts posted, non-reversed expenses without a file. M-1: tenant switch (tenants.settings,
 * default off) that blocks submitting an expense without evidence; only owner-level users change it. D-1: the
 * uploader removes a file only before the document is posted. Evidence never changes amounts or statuses.
 */
class TreasuryEvidenceService
{
    public const EVIDENCE_TYPES = [
        'bank_transfer' => 'Ảnh / xác nhận chuyển khoản',
        'bank_statement' => 'Sao kê ngân hàng',
        'invoice' => 'Hoá đơn',
        'receipt' => 'Phiếu thu / biên nhận',
        'payment_voucher' => 'Phiếu chi',
        'cash_acknowledgement' => 'Xác nhận nhận tiền mặt',
        'labour_confirmation' => 'Xác nhận tổ đội',
        'contract_or_po' => 'Hợp đồng / đơn hàng',
        'other' => 'Khác',
    ];

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'docx', 'doc'];

    public const MAX_KILOBYTES = 10240;

    public const SETTING_KEY = 'treasury_evidence_required_for_expenses';

    /** Document statuses before posting (D-1: evidence may still be removed). */
    public const REMOVABLE_STATUSES = [Document::STATUS_DRAFT, Document::STATUS_SUBMITTED, Document::STATUS_REJECTED];

    private const POSTED = [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED];

    public function canAttach(User $actor, Project $project, Document $document): bool
    {
        return (string) $document->created_by === (string) $actor->id
            || Gate::forUser($actor)->allows('treasury.approve-expense', $project)
            || Gate::forUser($actor)->allows('treasury.reconcile', $project);
    }

    public function canRemove(User $actor, Document $document, Attachment $attachment): bool
    {
        return (string) $attachment->uploaded_by === (string) $actor->id && in_array($document->status, self::REMOVABLE_STATUSES, true);
    }

    public function attach(Project $project, User $actor, Document $document, UploadedFile $file, string $evidenceType, ?string $note = null): Attachment
    {
        $this->requireDocumentOf($project, $document);
        if ($document->document_type === Document::TYPE_REVERSAL) {
            throw new TreasuryRuleViolation('Không đính chứng từ vào bút toán đảo; đính vào chứng từ gốc.', 'file');
        }
        if (!$this->canAttach($actor, $project, $document)) {
            throw new TreasuryRuleViolation('Bạn không được đính chứng từ vào khoản này.', 'file');
        }
        if (!array_key_exists($evidenceType, self::EVIDENCE_TYPES)) {
            throw new TreasuryRuleViolation('Loại chứng từ không hợp lệ.', 'evidence_type');
        }
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $guessed = strtolower((string) $file->guessExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true) || !in_array($guessed === 'jpeg' ? 'jpg' : $guessed, array_merge(self::ALLOWED_EXTENSIONS, ['jpg']), true)) {
            throw new TreasuryRuleViolation('Chỉ nhận PDF, ảnh (JPG, PNG, WEBP) hoặc Word / Excel.', 'file');
        }
        if ((int) $file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw new TreasuryRuleViolation('File vượt quá 10 MB.', 'file');
        }
        $hash = (string) hash_file('sha256', (string) $file->getRealPath());
        $duplicate = Attachment::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('financial_document_id', (string) $document->id)
            ->where('file_hash', $hash)
            ->exists();
        if ($duplicate) {
            throw new TreasuryRuleViolation('File này đã được đính vào khoản này.', 'file');
        }

        $directory = sprintf('treasury/%s/%s/%s', $project->tenant_id, $project->id, $document->id);
        $stored = Storage::disk($this->disk())->putFileAs($directory, $file, (string) Str::ulid() . '.' . $extension);
        if ($stored === false) {
            throw new TreasuryRuleViolation('Không lưu được file, vui lòng thử lại.', 'file');
        }

        try {
            return DB::transaction(function () use ($project, $actor, $document, $file, $evidenceType, $note, $hash, $stored): Attachment {
                $attachment = Attachment::query()->create([
                    'tenant_id' => (string) $project->tenant_id,
                    'project_id' => (string) $project->id,
                    'financial_document_id' => (string) $document->id,
                    'evidence_type' => $evidenceType,
                    'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                    'file_path' => $stored,
                    'mime_type' => (string) $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                    'file_hash' => $hash,
                    'note' => $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 500),
                    'uploaded_by' => (string) $actor->id,
                ]);
                $this->audit($actor, $project, 'treasury.evidence.attached', (string) $attachment->id, [
                    'financial_document_id' => (string) $document->id,
                    'evidence_type' => $evidenceType,
                    'file_hash' => $hash,
                ]);

                return $attachment;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk($this->disk())->delete($stored);

            throw new TreasuryRuleViolation('File này đã được đính vào khoản này.', 'file');
        }
    }

    public function remove(Project $project, User $actor, Document $document, Attachment $attachment): void
    {
        $this->requireDocumentOf($project, $document);
        $this->requireAttachmentOf($document, $attachment);
        if (!$this->canRemove($actor, $document, $attachment)) {
            throw new TreasuryRuleViolation('Chỉ người tải lên gỡ được, và chỉ khi khoản chưa ghi sổ.', 'file');
        }

        DB::transaction(function () use ($project, $actor, $document, $attachment): void {
            $attachment->delete();
            $this->audit($actor, $project, 'treasury.evidence.removed', (string) $attachment->id, [
                'financial_document_id' => (string) $document->id,
                'file_hash' => $attachment->file_hash,
            ]);
        });
        Storage::disk($this->disk())->delete($attachment->file_path);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(Project $project, Document $document): array
    {
        $this->requireDocumentOf($project, $document);

        /** @var list<Attachment> $rows */
        $rows = Attachment::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('financial_document_id', (string) $document->id)
            ->orderBy('created_at')
            ->get()
            ->values()
            ->all();

        return $rows;
    }

    public function attachmentOf(Project $project, Document $document, string $id): ?Attachment
    {
        /** @var Attachment|null $attachment */
        $attachment = Attachment::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('financial_document_id', (string) $document->id)
            ->whereKey($id)
            ->first();

        return $attachment;
    }

    public function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    /**
     * Evidence count per document, one query.
     *
     * @param list<string> $documentIds
     * @return array<string, int>
     */
    public function counts(Project $project, array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        return DB::table('treasury_document_attachments')
            ->where('tenant_id', (string) $project->tenant_id)
            ->whereIn('financial_document_id', $documentIds)
            ->groupBy('financial_document_id')
            ->selectRaw('financial_document_id, COUNT(*) AS file_count')
            ->pluck('file_count', 'financial_document_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Posted, non-reversed expenses with no evidence file (E-1).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function missingEvidenceQuery(Project $project): \Illuminate\Database\Query\Builder
    {
        return DB::table('treasury_financial_documents')
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', Document::TYPE_EXPENSE)
            ->whereIn('status', self::POSTED)
            ->whereNotExists(static fn ($q) => $q->selectRaw('1')->from('treasury_document_attachments')
                ->whereColumn('treasury_document_attachments.financial_document_id', 'treasury_financial_documents.id'));
    }

    public function evidenceRequired(string $tenantId): bool
    {
        $settings = Tenant::query()->whereKey($tenantId)->value('settings');
        $settings = is_string($settings) ? (array) json_decode($settings, true) : (array) $settings;

        return (bool) ($settings[self::SETTING_KEY] ?? false);
    }

    public function canChangePolicy(User $actor): bool
    {
        return $actor->hasPermission('treasury.self_approve_expense');
    }

    public function setEvidenceRequired(User $actor, bool $required): void
    {
        if (!$this->canChangePolicy($actor)) {
            throw new TreasuryRuleViolation('Chỉ chủ doanh nghiệp đổi được quy định chứng từ.', 'evidence_required');
        }

        DB::transaction(function () use ($actor, $required): void {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey((string) $actor->tenant_id)->lockForUpdate()->firstOrFail();
            $settings = (array) ($tenant->settings ?? []);
            $before = (bool) ($settings[self::SETTING_KEY] ?? false);
            $settings[self::SETTING_KEY] = $required;
            $tenant->settings = $settings;
            $tenant->save();
            AuditLog::query()->create([
                'user_id' => (string) $actor->id,
                'tenant_id' => (string) $tenant->id,
                'action' => 'treasury.evidence.policy_changed',
                'entity_type' => 'tenant',
                'entity_id' => (string) $tenant->id,
                'old_data' => [self::SETTING_KEY => $before],
                'new_data' => [self::SETTING_KEY => $required],
            ]);
        });
    }

    /** M-1: called before an expense is submitted. */
    public function requireEvidenceForSubmit(Project $project, Document $expense): void
    {
        if (!$this->evidenceRequired((string) $project->tenant_id)) {
            return;
        }
        $has = Attachment::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('financial_document_id', (string) $expense->id)
            ->exists();
        if (!$has) {
            throw new TreasuryRuleViolation('Khoản chi cần ít nhất một chứng từ trước khi gửi duyệt.', 'evidence');
        }
    }

    private function requireDocumentOf(Project $project, Document $document): void
    {
        if ((string) $document->tenant_id !== (string) $project->tenant_id || (string) $document->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Chứng từ không thuộc dự án này.', 'file');
        }
    }

    private function requireAttachmentOf(Document $document, Attachment $attachment): void
    {
        if ((string) $attachment->financial_document_id !== (string) $document->id || (string) $attachment->tenant_id !== (string) $document->tenant_id) {
            throw new TreasuryRuleViolation('File không thuộc khoản này.', 'file');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function audit(User $actor, Project $project, string $action, string $attachmentId, array $data): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => $action,
            'entity_type' => 'treasury_document_attachment',
            'entity_id' => $attachmentId,
            'new_data' => $data,
        ]);
    }
}
