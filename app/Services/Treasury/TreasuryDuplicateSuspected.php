<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Treasury\TreasuryFinancialDocument;
use RuntimeException;

/**
 * GAP-064 (Owner Gate 1 answer 1, PR #245 §5.2.6): a posted, non-reversed
 * document with the same project, type, amount, date, source, destination
 * and reference already exists. The caller must confirm to post anyway.
 */
class TreasuryDuplicateSuspected extends RuntimeException
{
    public function __construct(public readonly TreasuryFinancialDocument $existing)
    {
        parent::__construct('Có thể bạn đang khai trùng: đã có chứng từ giống hệt (cùng số tiền, ngày, nguồn, đích và tham chiếu).');
    }
}
