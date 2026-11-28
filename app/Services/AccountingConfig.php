<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppSetting;

/**
 * Resolves the chart-of-accounts entries the posting engine needs.
 *
 * The mapping is stored in app_settings so an imported QuickBooks chart can be
 * remapped without code changes; the seeded codes are only a fallback.
 */
class AccountingConfig
{
    public const DEFAULT_CODES = [
        'bank' => '1000',
        'receivable' => '1100',
        'vat_input' => '1200',
        'payable' => '2000',
        'vat_output' => '2100',
        'sales' => '4000',
        'expense' => '6500',
    ];

    public function isConfigured(): bool
    {
        return Account::query()->exists();
    }

    public function bank(): ?Account
    {
        return $this->resolve('bank');
    }

    public function receivable(): ?Account
    {
        return $this->resolve('receivable');
    }

    public function payable(): ?Account
    {
        return $this->resolve('payable');
    }

    public function vatInput(): ?Account
    {
        return $this->resolve('vat_input');
    }

    public function vatOutput(): ?Account
    {
        return $this->resolve('vat_output');
    }

    public function sales(): ?Account
    {
        return $this->resolve('sales');
    }

    public function defaultExpense(): ?Account
    {
        return $this->resolve('expense');
    }

    private function resolve(string $key): ?Account
    {
        $id = AppSetting::valueFor("account_{$key}_id");

        if ($id !== null && ($account = Account::query()->find((int) $id)) !== null) {
            return $account;
        }

        $code = self::DEFAULT_CODES[$key] ?? null;

        return $code === null ? null : Account::query()->where('code', $code)->first();
    }
}
