<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Invoice;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * A signed UBL invoice, credit note or debit note ready to be submitted to ZATCA.
 *
 *     $invoice = SignedInvoice::fromXml($signedXml);             // UUID and hash are read / computed
 *     $invoice = new SignedInvoice($signedXml, $hash, $uuid);    // or given explicitly
 */
final class SignedInvoice
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private string $xml;

    private string $hash;

    private string $uuid;

    /**
     * @param string $xml  the signed invoice XML
     * @param string $hash base64-encoded SHA-256 invoice hash (as returned by the signer)
     * @param string $uuid value of cbc:UUID
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $xml, string $hash, string $uuid)
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('The invoice XML must not be empty.');
        }

        $hash = trim($hash);
        $decodedHash = base64_decode($hash, true);

        if ($decodedHash === false || strlen($decodedHash) !== 32) {
            throw new InvalidArgumentException('The invoice hash must be a base64-encoded SHA-256 digest.');
        }

        $uuid = trim($uuid);

        if (preg_match(self::UUID_PATTERN, $uuid) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid invoice UUID "%s".', $uuid));
        }

        $this->xml = $xml;
        $this->hash = $hash;
        $this->uuid = $uuid;
    }

    /**
     * Reads cbc:UUID from the XML and computes the invoice hash the same way ZATCA does.
     *
     * @throws InvalidArgumentException when the XML is invalid or has no UUID
     */
    public static function fromXml(string $xml): self
    {
        return new self($xml, InvoiceXml::hash($xml), InvoiceXml::uuid($xml));
    }

    /**
     * Computes the ZATCA invoice hash of a signed invoice XML.
     *
     * @throws InvalidArgumentException when the XML is invalid
     */
    public static function computeHash(string $xml): string
    {
        return InvoiceXml::hash($xml);
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Request body of the compliance, reporting and clearance endpoints.
     *
     * @return array{invoiceHash: string, uuid: string, invoice: string}
     */
    public function toPayload(): array
    {
        return [
            'invoiceHash' => $this->hash,
            'uuid' => $this->uuid,
            'invoice' => base64_encode($this->xml),
        ];
    }
}
