<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ExpenseBudget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'category_id', 'created_by',
        'budget_name', 'description', 'budget_period', 'budget_amount',
        'spent_amount', 'remaining_amount', 'budget_year', 'budget_month',
        'budget_quarter', 'start_date', 'end_date', 'status',
        'alert_threshold', 'send_alerts', 'alert_settings', 'metadata'
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'alert_threshold' => 'decimal:2',
        'send_alerts' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'alert_settings' => 'json',
        'metadata' => 'json',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCurrentPeriod(Builder $query): Builder
    {
        $now = now();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
    }

    // Accessors
    public function getSpentPercentageAttribute(): float
    {
        if ($this->budget_amount <= 0) {
            return 0;
        }
        
        return round(($this->spent_amount / $this->budget_amount) * 100, 2);
    }

    public function getFormattedBudgetAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->budget_amount, 2);
    }

    // Methods
    public function updateSpentAmount(): bool
    {
        $spentAmount = Expense::where('category_id', $this->category_id)
                             ->where('company_id', $this->company_id)
                             ->when($this->branch_id, fn($q) => $q->where('branch_id', $this->branch_id))
                             ->whereIn('status', ['approved', 'paid'])
                             ->whereBetween('expense_date', [$this->start_date, $this->end_date])
                             ->sum('amount');

        return $this->update([
            'spent_amount' => $spentAmount,
            'remaining_amount' => max(0, $this->budget_amount - $spentAmount),
        ]);
    }
}