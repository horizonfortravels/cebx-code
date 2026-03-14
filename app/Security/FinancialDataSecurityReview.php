<?php

namespace App\Security;

/**
 * FR-IAM-012 — Security Review Notes
 *
 * This file documents the security design decisions and review for the
 * financial data masking implementation.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * MASKING PRINCIPLES
 * ═══════════════════════════════════════════════════════════════════════
 *
 * 1. DEFENSE IN DEPTH: Masking occurs at the service/presentation layer.
 *    Raw financial data NEVER leaves the backend without permission check.
 *
 * 2. FAIL-SAFE: If masking fails or user is null, ALL financial fields
 *    are masked (fail-closed, not fail-open).
 *
 * 3. CARD NUMBERS: Full card numbers should NEVER be stored in the system.
 *    Only the last 4 digits and a payment gateway token should be stored.
 *    The masking service handles cases where raw numbers are passed through.
 *
 * 4. AUDIT TRAIL: Every financial data access is logged in the audit log.
 *    The audit log itself sanitizes card numbers and passwords.
 *
 * 5. PERMISSION HIERARCHY:
 *    - Owner: ALL financial data visible (implicit)
 *    - financial:profit.view: Net, Retail, Profit, Pricing Breakdown
 *    - financial:view: Totals, Tax, COD, Wallet Balance
 *    - financial:cards.view: Unmasked card/IBAN data
 *    - No permission: ALL financial fields masked
 *
 * ═══════════════════════════════════════════════════════════════════════
 * THREAT MODEL
 * ═══════════════════════════════════════════════════════════════════════
 *
 * T1: Unauthorized profit viewing by warehouse/print staff
 *     → Mitigation: financial:profit.view permission gate
 *     → Test: DataMaskingTest::printer_sees_no_financial_data
 *
 * T2: Card number leakage in logs/audit trails
 *     → Mitigation: sanitizeForAuditLog() always masks card numbers
 *     → Test: DataMaskingTest::it_sanitizes_card_numbers_in_audit
 *
 * T3: Password leakage in audit log values
 *     → Mitigation: sanitizeForAuditLog() redacts password/token fields
 *     → Test: DataMaskingTest::it_redacts_passwords_in_audit
 *
 * T4: Masking failure exposes full card number
 *     → Mitigation: Fail-safe returns fully masked or null on error
 *     → Test: FinancialDataApiTest::masking_failure_returns_safe_default
 *
 * T5: Short/unusual card numbers not properly masked
 *     → Mitigation: Handles 1-19 digit cards, masks entirely if < 4 digits
 *     → Test: DataMaskingTest::it_handles_short_card_number
 *
 * T6: API bypass — direct database query exposes data
 *     → Mitigation: All API resources MUST use DataMaskingService::filterFinancialData()
 *     → Note: Database-level encryption is recommended for production (at-rest encryption)
 *
 * ═══════════════════════════════════════════════════════════════════════
 * RECOMMENDATIONS FOR PRODUCTION
 * ═══════════════════════════════════════════════════════════════════════
 *
 * 1. Store card numbers ONLY as tokens from payment gateway (Stripe/etc.)
 * 2. Enable database-level encryption at rest (AES-256)
 * 3. Add rate limiting on financial data access endpoints
 * 4. Consider field-level encryption for IBAN/bank account columns
 * 5. Implement PCI DSS compliance checks in CI/CD pipeline
 * 6. Add anomaly detection for unusual financial data access patterns
 * 7. Regular rotation of encryption keys
 */
class FinancialDataSecurityReview
{
    // This is a documentation class — no runtime code.
}
