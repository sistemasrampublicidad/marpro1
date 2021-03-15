<?php
/**
 * Created by PhpStorm.
 * User: Giansalex
 * Date: 05/10/2017
 * Time: 08:20
 */

namespace App\CoreFacturalo\Greenter\Xml\Parser;

use App\CoreFacturalo\Greenter\Model\Client\Client;
use App\CoreFacturalo\Greenter\Model\Company\Address;
use App\CoreFacturalo\Greenter\Model\Company\Company;
use App\CoreFacturalo\Greenter\Model\DocumentInterface;
use App\CoreFacturalo\Greenter\Model\Sale\Document;
use App\CoreFacturalo\Greenter\Model\Sale\Legend;
use App\CoreFacturalo\Greenter\Model\Sale\Note;
use App\CoreFacturalo\Greenter\Model\Sale\SaleDetail;
use App\CoreFacturalo\Greenter\Model\Sale\SalePerception;
use App\CoreFacturalo\Greenter\Parser\DocumentParserInterface;
use App\CoreFacturalo\Greenter\Xml\XmlReader;

/**
 * Class NoteParser
 * @package Greenter\Xml\Parser
 */
class NoteParser implements DocumentParserInterface
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
        $isNcr = $root->nodeName == 'CreditNote';

        $note = new Note();
        $idNum = explode('-', $xml->getValue('cbc:ID', $root));
        $this->loadDocAfectado($note);
        $note->setSerie($idNum[0])
            ->setCorrelativo($idNum[1])
            ->setTipoDoc($isNcr ? '07' : '08')
            ->setTipoMoneda($xml->getValue('cbc:DocumentCurrencyCode', $root))
            ->setFechaEmision(new \DateTime($xml->getValue('cbc:IssueDate', $root)))
            ->setCompany($this->getCompany())
            ->setClient($this->getClient());

        $extensions = $xml->getNode('ext:UBLExtensions', $root);
        $additional = $xml->getNode('//sac:AdditionalInformation', $extensions);
        $this->loadTotals($note, $additional);
        $this->loadTributos($note);
        $monetaryTotal = $xml->getNode($isNcr ? 'cac:LegalMonetaryTotal': 'cac:RequestedMonetaryTotal', $root);
        $note->setMtoOtrosTributos(floatval($xml->getValue('cbc:ChargeTotalAmount', $monetaryTotal, 0)))
            ->setMtoImpVenta(floatval($xml->getValue('cbc:PayableAmount', $monetaryTotal, 0)))
            ->setDetails(iterator_to_array($this->getDetails($isNcr)))
            ->setLegends(iterator_to_array($this->getLegends($additional)))
            ->setGuias(iterator_to_array($this->getGuias($root)));

        return $note;
    }

    private function loadTotals(Note $inv, \DOMNode $node = null)
    {
        if (empty($node)) {
            return;
        }

        $xml = $this->reader;
        $totals = $xml->getNodes('sac:AdditionalMonetaryTotal', $node);
        foreach ($totals as $total) {
            /**@var $total \DOMElement*/
            $nodeId = $xml->getNode('cbc:ID', $total);
            $id = trim($nodeId->nodeValue);
            $val = floatval($xml->getValue('cbc:PayableAmount', $total, 0));
            switch ($id) {
                case '1001':
                    $inv->setMtoOperGravadas($val);
                    break;
                case '1002':
                    $inv->setMtoOperInafectas($val);
                    break;
                case '1003':
                    $inv->setMtoOperExoneradas($val);
                    break;
                case '1004':
                    $inv->setMtoOperGratuitas($val);
                    break;
                case '2001':
                    $inv->setPerception((new SalePerception())
                        ->setCodReg($nodeId->getAttribute('schemeID'))
                        ->setMto($val)
                        ->setMtoBase(floatval($xml->getValue('sac:ReferenceAmount', $total,0)))
                        ->setMtoTotal(floatval($xml->getValue('sac:TotalAmount', $total,0))));
                    break;
            }
        }
    }

    private function loadTributos(Note $inv)
    {
        $xml = $this->reader;
        $taxs = $xml->getNodes('cac:TaxTotal', $this->rootNode);
        foreach ($taxs as $tax) {
            $name = $xml->getValue('cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/cbc:Name', $tax);
            $name = trim($name);
            $val = floatval($xml->getValue('cbc:TaxAmount', $tax, 0));
            switch ($name) {
                case 'IGV':
                    $inv->setMtoIGV($val);
                    break;
                case 'ISC':
                    $inv->setMtoISC($val);
                    break;
                case 'OTROS':
                    $inv->setSumOtrosCargos($val);
                    break;
            }
        }
    }

    private function getLegends(\DOMNode $node = null)
    {
        if (empty($node)) {
            return;
        }

        $xml = $this->reader;
        $legends = $xml->getNodes('sac:AdditionalProperty', $node);
        foreach ($legends as $legend) {
            /**@var $legend \DOMElement*/
            $leg = (new Legend())
                ->setCode($xml->getValue('cbc:ID', $legend))
                ->setValue($xml->getValue('cbc:Value', $legend));

            yield $leg;
        }
    }

    private function loadDocAfectado(Note $note)
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:DiscrepancyResponse', $this->rootNode);
        $note->setCodMotivo($xml->getValue('cbc:ResponseCode', $node))
            ->setDesMotivo($xml->getValue('cbc:Description', $node))
            ->setNumDocfectado($xml->getValue('cbc:ReferenceID', $node))
            ->setTipDocAfectado($xml->getValue('cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode'));
    }

    private function getClient()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:AccountingCustomerParty', $this->rootNode);

        $cl = new Client();
        $cl->setNumDoc($xml->getValue('cbc:CustomerAssignedAccountID', $node))
            ->setTipoDoc($xml->getValue('cbc:AdditionalAccountID', $node))
            ->setRznSocial($xml->getValue('cac:Party/cac:PartyLegalEntity/cbc:RegistrationName', $node))
            ->setAddress($this->getAddress($node));

        return $cl;
    }

    private function getCompany()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:AccountingSupplierParty',$this->rootNode);

        $cl = new Company();
        $cl->setRuc($xml->getValue('cbc:CustomerAssignedAccountID', $node))
            ->setNombreComercial($xml->getValue('cac:Party/cac:PartyName/cbc:Name', $node))
            ->setRazonSocial($xml->getValue('cac:Party/cac:PartyLegalEntity/cbc:RegistrationName', $node))
            ->setAddress($this->getAddress($node));

        return $cl;
    }

    private function getGuias($node)
    {
        $xml = $this->reader;
        $guias = $xml->getNodes('cac:DespatchDocumentReference', $node);
        if ($guias->length == 0) {
            return;
        }

        foreach ($guias as $guia) {
            $item = new Document();
            $item->setTipoDoc($xml->getValue('cbc:DocumentTypeCode', $guia));
            $item->setNroDoc($xml->getValue('cbc:ID', $guia));

            yield $item;
        }
    }

    private function getAddress($node)
    {
        $xml = $this->reader;

        $address = $xml->getNode('cac:Party/cac:PostalAddress', $node);
        if ($address) {

            return (new Address())
                ->setDireccion($xml->getValue('cbc:StreetName', $address))
                ->setDepartamento($xml->getValue('cbc:CityName', $address))
                ->setProvincia($xml->getValue('cbc:CountrySubentity', $address))
                ->setDistrito($xml->getValue('cbc:District', $address))
                ->setUbigueo($xml->getValue('cbc:ID', $address));
        }

        return null;
    }

    private function getDetails($isNcr)
    {
        $xml = $this->reader;
        $nodes = $xml->getNodes('cac:' . ($isNcr ? 'CreditNoteLine': 'DebitNoteLine'), $this->rootNode);
        $nameQuant = $isNcr ? 'CreditedQuantity' : 'DebitedQuantity';
        foreach ($nodes as $node) {
            $quant = $xml->getNode('cbc:'.$nameQuant, $node);
            $det = new SaleDetail();
            $det->setCantidad($quant->nodeValue)
                ->setUnidad($quant->getAttribute('unitCode'))
                ->setMtoValorVenta($xml->getValue('cbc:LineExtensionAmount', $node))
                ->setMtoValorUnitario($xml->getValue('cac:Price/cbc:PriceAmount',  $node))
                ->setDescripcion($xml->getValue('cac:Item/cbc:Description', $node))
                ->setCodProducto($xml->getValue('cac:Item/cac:SellersItemIdentification/cbc:ID', $node))
                ->setCodProdSunat($xml->getValue('cac:Item/cac:CommodityClassification/cbc:ItemClassificationCode', $node));

            $taxs = $xml->getNodes('cac:TaxTotal', $node);
            foreach ($taxs as $tax) {
                $name = $xml->getValue('cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/cbc:Name', $tax);
                $name = trim($name);
                $val = floatval($xml->getValue('cbc:TaxAmount', $tax, 0));
                switch ($name) {
                    case 'IGV':
                        $det->setIgv($val);
                        $det->setTipAfeIgv($xml->getValue('cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode', $tax));
                        break;
                    case 'ISC':
                        $det->setIsc($val);
                        $det->setTipSisIsc($xml->getValue('cac:TaxSubtotal/cac:TaxCategory/cbc:TierRange', $tax));
                        break;
                }
            }

            // Descuento
            $descs = $xml->getNodes('cac:AllowanceCharge', $node);
            foreach ($descs as $desc) {
                $charge = $xml->getValue('cbc:ChargeIndicator', $desc);
                $charge = trim($charge);
                if ($charge == 'false') {
                    $val = floatval($xml->getValue('cbc:Amount', $desc, 0));
                    $det->setDescuento($val);
                }
            }

            $prices = $xml->getNodes('cac:PricingReference', $node);
            foreach ($prices as $price) {
                $code = $xml->getValue('cac:AlternativeConditionPrice/cbc:PriceTypeCode', $price);
                $code = trim($code);
                $value = floatval($xml->getValue('cac:AlternativeConditionPrice/cbc:PriceAmount', $price, 0));

                switch ($code) {
                    case '01':
                        $det->setMtoPrecioUnitario($value);
                        break;
                    case '02':
                        $det->setMtoValorGratuito($value);
                        break;
                }
            }

            yield $det;
        }
    }
}