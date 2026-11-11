<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BranchBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time a branch can't take cars. "closed" shuts the shop (a holiday, a half
 * day): no drop-offs, and jobs in progress pause, so their ready-by moves
 * later. "bays" takes some bays (or all) out of service without closing.
 * A block without a branch covers every branch.
 *
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 */
class BranchBlock extends Model
{
    /** @use HasFactory<BranchBlockFactory> */
    use HasFactory;

    public const KINDS = [
        'closed' => 'Closed (no drop-offs, work pauses)',
        'bays' => 'Bays out of service',
    ];

    protected $fillable = ['branch_id', 'kind', 'bays', 'starts_at', 'ends_at', 'title', 'note', 'created_by'];

    protected $attributes = [
        'kind' => 'closed',
    ];

    protected function casts(): array
    {
        return [
            'bays' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeClosures(Builder $query): void
    {
        $query->where('kind', 'closed');
    }

    public function scopeBayBlocks(Builder $query): void
    {
        $query->where('kind', 'bays');
    }

    /**
     * The branch's own blocks, plus the ones for every branch.
     */
    public function scopeForBranch(Builder $query, Branch|int $branch): void
    {
        $id = $branch instanceof Branch ? $branch->getKey() : $branch;

        $query->where(fn (Builder $scope) => $scope->where('branch_id', $id)->orWhereNull('branch_id'));
    }

    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function isEveryBranch(): bool
    {
        return $this->branch_id === null;
    }

    /**
     * "Mon, Nov 30 (all day)", "Dec 24, 12:00 PM – Dec 26, 8:00 AM".
     */
    public function dateLabel(): string
    {
        $start = $this->starts_at;
        $end = $this->ends_at;

        if ($start->isStartOfDay() && $end->isStartOfDay()) {
            $last = $end->subDay();

            return $last->isSameDay($start)
                ? $start->format('D, M j').' (all day)'
                : $start->format('M j').' – '.$last->format('M j').' (all day)';
        }

        return $start->isSameDay($end)
            ? $start->format('D, M j, g:i A').' – '.$end->format('g:i A')
            : $start->format('M j, g:i A').' – '.$end->format('M j, g:i A');
    }
}
