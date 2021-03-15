<?php
/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 18/10/2017
 * Time: 06:12 PM
 */

namespace App\CoreFacturalo\Greenter\Xml\Parser;

use App\CoreFacturalo\Greenter\Model\Client\Client;
use App\CoreFacturalo\Greenter\Model\Company\Address;
use App\CoreFacturalo\Greenter\Model\Company\Company;
use App\CoreFacturalo\Greenter\Model\DocumentInterface;
use App\CoreFacturalo\Greenter\Model\Sale\Receipt;
use App\CoreFacturalo\Greenter\Parser\DocumentParserInterface;
use App\CoreFacturalo\Greenter\Xml\XmlReader;

/**
 * Class ReceiptParser
 * @package Greenter\Xml\Parser
 */
class ReceiptParser implements DocumentParserInterface
{
    /**
     * @var XmlReader
     */
    private $reader;

    /**
     * @var \DOMElement
     */
    private $rootNode;

    /**
     * Parse document.
     *
     * @param $value
     * @return DocumentInterface
     */
    public function parse($value)
    {
        $this->reader = new XmlReader();
        $xml = $this->reader;

        if ($value instanceof \DOMDocument) {
            $this->reader->loadDom($value);
        } else {
            $this->reader->loadXml($value);
        }

        $root = $xml->getXpath()->document->documentElement;
        $this->rootNode = $root;
        $receipt = new Receipt();
        $docFac = explode('-', $xml->getValue('cbc:ID', $root));
        $receipt->setSerie($docFac[0])
            ->setCorrelativo($docFac[1])
            ->setFechaEmision(new \DateTime($xml->getValue('cbc:IssueDate', $root)))
            ->setMontoLetras($xml->getValue('cbc:Note', $root))
            ->setPerson($this->getPerson())
            ->setReceptor($this->getClient());

        $monetaryTotal = $xml->getNode('cac:LegalMonetaryTotal', $root);
        $receipt->setSubTotal(floatval($xml->getValue('cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount', $root, 0)))
            ->setRetencion(floatval($xml->getValue('cbc:TaxExclusiveAmount', $monetaryTotal, 0)))
            ->setTotal(floatval($xml->getValue('cbc:PayableAmount', $monetaryTotal, 0)));

        $this->loadFromDetail($receipt);

        return $receipt;
    }

    private function getClient()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:AccountingCustomerParty', $this->rootNode);

        $cl = new Client();
        $cl->setNumDoc($xml->getValue('cbc:CustomerAssignedAccountID', $node))
            ->setTipoDoc($xml->getValue('cbc:AdditionalAccountID', $node))
            ->setRznSocial($xml->getValue('cac:Party/cac:PartyName/cbc:Name', $node))
            ->setAddress($this->getAddress($node));

        return $cl;
    }

    private function getPerson()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:AccountingSupplierParty',$this->rootNode);

        $cl = new Company();
        $cl->setRuc($xml->getValue('cbc:CustomerAssignedAccountID', $node))
            ->setRazonSocial($xml->getValue('cac:Party/cac:PartyName/cbc:Name', $node))
            ->setAddress($this->getAddress($node));

        return $cl;
    }

    private function getAddress($node)
    {
        $xml = $this->reader;
        $address = new Address();
        $address->setDireccion($xml->getValue('cac:Party/cac:PostalAddress/cbc:StreetName', $node));

        return $address;
    }

    private function loadFromDetail(Receipt $receipt)
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:InvoiceLine', $this->rootNode);

        $receipt
            ->setPorcentaje(floatval($xml->getValue('cac:TaxTotal/cac:TaxSubtotal/cbc:Percent', $node, 0)))
            ->setConcepto($xml->getValue('cac:Item/cbc:Description', $node));
    }
}