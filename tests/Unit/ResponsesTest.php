<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\ZatcaApi\Environment;
use Sevaske\ZatcaApi\Response\ClearanceResult;
use Sevaske\ZatcaApi\Response\ReportingResult;
use Sevaske\ZatcaApi\Response\ValidationMessage;

final class ResponsesTest extends TestCase
{
    public function testInvalidClearedInvoiceIsNotDecoded(): void
    {
        $result = ClearanceResult::fromArray(['clearanceStatus' => 'CLEARED', 'clearedInvoice' => 'not base64!']);

        self::assertNull($result->getClearedInvoice());
        self::assertSame('not base64!', $result->getClearedInvoiceBase64());
    }

    public function testResultsCanBeCreatedForFakes(): void
    {
        $reported = ReportingResult::fromArray(['reportingStatus' => 'REPORTED']);

        self::assertTrue($reported->isReported());
        self::assertSame(200, $reported->getHttpStatusCode());
        self::assertTrue($reported->getValidationResults()->isPassed());
        self::assertSame([], $reported->getValidationResults()->getErrorCodes());
    }

    public function testValidationMessageStringRepresentation(): void
    {
        self::assertSame('[BR-KSA-37] Building number', (string) ValidationMessage::fromArray(['code' => 'BR-KSA-37', 'message' => 'Building number']));
        self::assertSame('Invoice was already Reported successfully earlier.', (string) ValidationMessage::fromArray(['code' => null, 'message' => 'Invoice was already Reported successfully earlier.']));
        self::assertSame(ValidationMessage::TYPE_WARNING, ValidationMessage::fromArray([], ValidationMessage::TYPE_WARNING)->getType());
    }

    public function testEnvironments(): void
    {
        self::assertSame([Environment::SANDBOX, Environment::SIMULATION, Environment::PRODUCTION], Environment::all());
        self::assertTrue(Environment::isValid('production'));
        self::assertFalse(Environment::isValid('core'));
    }
}
