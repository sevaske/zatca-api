<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Invoice;

use Sevaske\ZatcaApi\Exception\InvalidArgumentException;

/**
 * Reads the UUID of a UBL invoice and computes its ZATCA invoice hash.
 *
 * @internal use {@see SignedInvoice::fromXml()}
 */
final class InvoiceXml
{
    private const NAMESPACES = [
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    /**
     * Elements excluded from the invoice hash, as in the ds:Transforms of the ZATCA XAdES signature.
     */
    private const EXCLUDED_FROM_HASH = [
        '/*/ext:UBLExtensions',
        '/*/cac:Signature',
        "/*/cac:AdditionalDocumentReference[cbc:ID = 'QR']",
    ];

    private function __construct() {}

    /**
     * Value of cbc:UUID.
     *
     * @throws InvalidArgumentException when the XML is invalid or has no UUID
     */
    public static function uuid(string $xml): string
    {
        $nodes = self::xpath(self::load($xml))->query('/*/cbc:UUID');
        $node = $nodes !== false ? $nodes->item(0) : null;
        $uuid = $node instanceof \DOMElement ? trim($node->textContent) : '';

        if ($uuid === '') {
            throw new InvalidArgumentException('The invoice XML has no cbc:UUID element.');
        }

        return $uuid;
    }

    /**
     * base64(sha256(C14N(invoice without UBLExtensions, Signature and the QR document reference))).
     *
     * @throws InvalidArgumentException when the XML is invalid
     */
    public static function hash(string $xml): string
    {
        $document = self::load($xml);
        $xpath = self::xpath($document);

        foreach (self::EXCLUDED_FROM_HASH as $expression) {
            $nodes = $xpath->query($expression);

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                if ($node instanceof \DOMElement && $node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $root = $document->documentElement;
        $canonical = $root !== null ? $root->C14N(false, false) : false;

        if ($canonical === false) {
            throw new InvalidArgumentException('Failed to canonicalize the invoice XML.');
        }

        return base64_encode(hash('sha256', $canonical, true));
    }

    private static function load(string $xml): \DOMDocument
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('The invoice XML must not be empty.');
        }

        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET);
        $error = libxml_get_last_error();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false || $document->documentElement === null) {
            $reason = $error !== false ? trim($error->message) : 'unknown error';

            throw new InvalidArgumentException('The invoice XML is not well-formed: ' . $reason);
        }

        return $document;
    }

    private static function xpath(\DOMDocument $document): \DOMXPath
    {
        $xpath = new \DOMXPath($document);

        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }
}
