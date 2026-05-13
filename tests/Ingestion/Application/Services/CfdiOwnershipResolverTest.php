<?php

declare(strict_types=1);

namespace Tests\Ingestion\Application\Services;

use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Services\CfdiOwnershipResolver;
use App\Ingestion\Application\Services\Rules\IncomeIssuedRule;
use App\Ingestion\Application\Services\Rules\IncomeReceivedRule;
use App\Ingestion\Application\Services\Rules\PaymentIssuedRule;
use App\Ingestion\Application\Services\Rules\PaymentReceivedRule;
use App\Ingestion\Application\Services\Rules\PayrollIssuedRule;
use App\Ingestion\Application\Services\Rules\PayrollReceivedRule;
use App\Ingestion\Application\Services\Rules\SelfInvoiceRule;
use App\Ingestion\Application\Services\Rules\ThirdPartyFallbackRule;
use App\Ingestion\Application\Services\Rules\TransferRule;
use PHPUnit\Framework\TestCase;

class CfdiOwnershipResolverTest extends TestCase
{
    private CfdiOwnershipResolver $sut;

    protected function setUp(): void
    {
        // Inject all rules — resolver sorts by priority internally
        $this->sut = new CfdiOwnershipResolver(
            new SelfInvoiceRule(),
            new PayrollIssuedRule(),
            new PayrollReceivedRule(),
            new PaymentIssuedRule(),
            new PaymentReceivedRule(),
            new TransferRule(),
            new IncomeIssuedRule(),
            new IncomeReceivedRule(),
            new ThirdPartyFallbackRule(),
        );
    }

    // --- The 9 canonical categories ---

    public function test_case_1_self_invoice_when_emisor_and_receptor_are_tenant(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'AAA010101AAA', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::SELF_INVOICE, $result);
    }

    public function test_case_2_payroll_issued_when_tenant_is_emisor_and_type_is_payroll(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'BBB020202BBB', SatDocumentType::PAYROLL);
        $this->assertSame(CfdiOwnershipCategory::PAYROLL_ISSUED, $result);
    }

    public function test_case_3_payroll_received_when_tenant_is_receptor_and_type_is_payroll(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'BBB020202BBB', 'AAA010101AAA', SatDocumentType::PAYROLL);
        $this->assertSame(CfdiOwnershipCategory::PAYROLL_RECEIVED, $result);
    }

    public function test_case_4_payment_complement_issued_when_tenant_is_emisor_and_type_is_payment(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'BBB020202BBB', SatDocumentType::PAYMENT);
        $this->assertSame(CfdiOwnershipCategory::PAYMENT_COMPLEMENT_ISSUED, $result);
    }

    public function test_case_5_payment_complement_received_when_tenant_is_receptor_and_type_is_payment(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'BBB020202BBB', 'AAA010101AAA', SatDocumentType::PAYMENT);
        $this->assertSame(CfdiOwnershipCategory::PAYMENT_COMPLEMENT_RECEIVED, $result);
    }

    public function test_case_6_transfer_when_document_type_is_transfer(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'BBB020202BBB', SatDocumentType::TRANSFER);
        $this->assertSame(CfdiOwnershipCategory::TRANSFER, $result);
    }

    public function test_case_7_income_issued_when_tenant_is_emisor_and_type_is_invoice(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'BBB020202BBB', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::INCOME_ISSUED, $result);
    }

    public function test_case_8_income_received_when_tenant_is_receptor_and_type_is_invoice(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'BBB020202BBB', 'AAA010101AAA', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::INCOME_RECEIVED, $result);
    }

    public function test_case_9_third_party_when_tenant_matches_neither_emisor_nor_receptor(): void
    {
        $result = $this->sut->resolve('AAA010101AAA', 'BBB020202BBB', 'CCC030303CCC', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::THIRD_PARTY, $result);
    }

    // --- Priority ordering protection ---

    public function test_case_10_self_invoice_takes_priority_over_income_issued(): void
    {
        // Both SelfInvoiceRule (100) and IncomeIssuedRule (60) would match this context.
        // SELF_INVOICE must win because it has higher priority.
        $result = $this->sut->resolve('AAA010101AAA', 'AAA010101AAA', 'AAA010101AAA', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::SELF_INVOICE, $result);
        $this->assertNotSame(CfdiOwnershipCategory::INCOME_ISSUED, $result);
    }

    public function test_third_party_only_matches_as_last_resort(): void
    {
        // THIRD_PARTY must not appear when a more specific rule matches
        $incomeCases = [
            ['AAA010101AAA', 'AAA010101AAA', 'BBB020202BBB', SatDocumentType::INVOICE],
            ['AAA010101AAA', 'BBB020202BBB', 'AAA010101AAA', SatDocumentType::INVOICE],
        ];

        foreach ($incomeCases as [$tenant, $emisor, $receptor, $type]) {
            $result = $this->sut->resolve($tenant, $emisor, $receptor, $type);
            $this->assertNotSame(CfdiOwnershipCategory::THIRD_PARTY, $result);
        }
    }

    public function test_resolver_works_with_only_fallback_rule(): void
    {
        // Even without specific rules, the fallback guarantees a result
        $minimalResolver = new CfdiOwnershipResolver(new ThirdPartyFallbackRule());
        $result = $minimalResolver->resolve('AAA010101AAA', 'BBB020202BBB', 'CCC030303CCC', SatDocumentType::INVOICE);
        $this->assertSame(CfdiOwnershipCategory::THIRD_PARTY, $result);
    }
}
