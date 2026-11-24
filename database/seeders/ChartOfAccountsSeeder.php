<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

/**
 * Starter chart of accounts.
 *
 * In production this is replaced by the chart exported from QuickBooks; these
 * codes are the fallback the posting engine resolves against.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['1000', 'Bank – Current', Account::TYPE_ASSET],
            ['1100', 'Accounts Receivable', Account::TYPE_ASSET],
            ['1200', 'VAT Input', Account::TYPE_ASSET],
            ['1500', 'Fixed Assets', Account::TYPE_ASSET],
            ['2000', 'Accounts Payable', Account::TYPE_LIABILITY],
            ['2100', 'VAT Output', Account::TYPE_LIABILITY],
            ['3000', 'Share Capital', Account::TYPE_EQUITY],
            ['3100', 'Retained Earnings', Account::TYPE_EQUITY],
            ['4000', 'Service Income', Account::TYPE_INCOME],
            ['5000', 'Cost of Sales', Account::TYPE_EXPENSE],
            ['6000', 'Salaries', Account::TYPE_EXPENSE],
            ['6100', 'Rent', Account::TYPE_EXPENSE],
            ['6200', 'Utilities', Account::TYPE_EXPENSE],
            ['6300', 'Travel', Account::TYPE_EXPENSE],
            ['6400', 'Professional Fees', Account::TYPE_EXPENSE],
            ['6500', 'Office & Sundry', Account::TYPE_EXPENSE],
        ];

        $byCode = [];

        foreach ($accounts as [$code, $name, $type]) {
            $byCode[$code] = Account::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => Account::normalBalanceFor($type),
                    'is_active' => true,
                ],
            );
        }

        // VAT is fixed at 15% (ADR-014).
        TaxCode::query()->updateOrCreate(
            ['code' => 'STD15'],
            [
                'name' => 'Standard rated 15% (sales)',
                'rate' => TaxCode::STANDARD_RATE,
                'direction' => TaxCode::DIRECTION_OUTPUT,
                'account_id' => $byCode['2100']->id,
                'is_active' => true,
            ],
        );

        TaxCode::query()->updateOrCreate(
            ['code' => 'STD15-IN'],
            [
                'name' => 'Standard rated 15% (purchases)',
                'rate' => TaxCode::STANDARD_RATE,
                'direction' => TaxCode::DIRECTION_INPUT,
                'account_id' => $byCode['1200']->id,
                'is_active' => true,
            ],
        );

        // Explicit mapping so an imported chart can be re-pointed without code changes.
        $mapping = [
            'account_bank_id' => '1000',
            'account_receivable_id' => '1100',
            'account_vat_input_id' => '1200',
            'account_payable_id' => '2000',
            'account_vat_output_id' => '2100',
            'account_sales_id' => '4000',
            'account_expense_id' => '6500',
        ];

        foreach ($mapping as $key => $code) {
            AppSetting::setValue($key, (string) $byCode[$code]->id);
        }
    }
}
