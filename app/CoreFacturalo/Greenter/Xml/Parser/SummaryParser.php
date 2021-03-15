<?php
/**
 * Created by PhpStorm.
 * User: Giansalex
 * Date: 28/01/2018
 * Time: 13:04
 */

namespace App\CoreFacturalo\Greenter\Xml\Parser;

use App\CoreFacturalo\Greenter\Model\Company\Company;
use App\CoreFacturalo\Greenter\Model\DocumentInterface;
use App\CoreFacturalo\Greenter\Model\Sale\Document;
use App\CoreFacturalo\Greenter\Model\Summary\Summary;
use App\CoreFacturalo\Greenter\Model\Summary\SummaryDetail;
use App\CoreFacturalo\Greenter\Model\Summary\SummaryPerception;
use App\CoreFacturalo\Greenter\Parser\DocumentParserInterface;
use App\CoreFacturalo\Greenter\Xml\XmlReader;

/**
 * Class SummaryParser
 * @package Greenter\Xml\Parser
 */
class SummaryParser implements DocumentParserInterface
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
     *
     * @return DocumentInterface
     */
    public function parse($value)
    {
        $this->load($value);
        $xml = $this->reader;
        $root = $this->rootNode;

        $id = explode('-', $xml->getValue('cbc:ID', $root));
        $summary = new Summary();
        $summary->setCorrelativo($id[2])
            ->setFecGeneracion(new \DateTime($xml->getValue('cbc:ReferenceDate', $root)))
            ->setFecResumen(new \DateTime($xml->getValue('cbc:IssueDate', $root)))
            ->setCompany($this->getCompany())
            ->setDetails(iterator_to_array($this->getDetails()));

        return $summary;
    }

    private function load($value)
    {
        $this->reader = new XmlReader();

        if ($value instanceof \DOMDocument) {
            $this->reader->loadDom($value);
        } else {
            $this->reader->loadXml($value);
        }

        $this->rootNode = $this->reader->getXpath()->document->documentElement;
    }

    private function getCompany()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:AccountingSupplierParty',$this->rootNode);

        $cl = new Company();
        $cl->setRuc($xml->getValue('cbc:CustomerAssignedAccountID', $node))
            ->setNombreComercial($xml->getValue('cac:Party/cac:PartyName/cbc:Name', $node))
            ->setRazonSocial($xml->getValue('cac:Party/cac:PartyLegalEntity/cbc:RegistrationName', $node));

        return $cl;
    }

    private function getDetails()
    {
        $xml = $this->reader;
        $nodes = $xml->getNodes('sac:SummaryDocumentsLine', $this->rootNode);

        foreach ($nodes as $node) {
            $det = new SummaryDetail();
            $det->setTipoDoc($xml->getValue('cbc:DocumentTypeCode', $node))
                ->setSerieNro($xml->getValue('cbc:ID', $node))
                ->setEstado(trim($xml->getValue('cac:Status/cbc:ConditionCode', $node)))
                ->setClienteTipo(trim($xml->getValue('cac:AccountingCustomerParty/cbc:AdditionalAccountID', $node)))
                ->setClienteNro(trim($xml->getValue('cac:AccountingCustomerParty/cbc:CustomerAssignedAccountID', $node)))
                ->setTotal(floatval($xml->getValue('sac:TotalAmount', $node, 0)))
                ->setMtoOtrosCargos(floatval($xml->getValue('cac:AllowanceCharge/cbc:Amount', $node, 0)));

            $ref = $xml->getNode('cac:BillingReference', $node);
            if ($ref) {
                $doc = new Document();
                $doc->setTipoDoc(trim($xml->getValue('cac:InvoiceDocumentReference/cbc:DocumentTypeCode', $ref)))
                    ->setNroDoc(trim($xml->getValue('cac:InvoiceDocumentReference/cbc:ID', $ref)));
                $det->setDocReferencia($doc);
            }

            $ref = $xml->getNode('sac:SUNATPerceptionSummaryDocumentReference', $node);
            if ($ref) {
                $perc = new SummaryPerception();
                $perc->setCodReg(trim($xml->getValue('sac:SUNATPerceptionSystemCode', $ref)))
                    ->setTasa(floatval($xml->getValue('sac:SUNATPerceptionPercent', $ref, 0)))
                    ->setMto(floatval($xml->getValue('sac:TotalInvoiceAmount', $ref, 0)))
                    ->setMtoTotal(floatval($xml->getValue('sac:SUNATTotalCashed', $ref, 0)))
                    ->setMtoBase(floatval($xml->getValue('sac:TaxableAmount', $ref, 0)));
            }

            $totals = $xml->getNodes('sac:BillingPayment', $node);
            foreach ($totals as $total) {
                /**@var $total \DOMElement*/
                $id = trim($xml->getValue('cbc:InstructionID', $total));
                $val = floatval($xml->getValue('cbc:PaidAmount', $total, 0));
                switch ($id) {
                    case '01':
                        $det->setMtoOperGravadas($val);
                        break;
                    case '02':
                        $det->setMtoOperExoneradas($val);
                        break;
                    case '03':
                        $det->setMtoOperInafectas($val);
                        break;
                    case '05':
                        $det->setMtoOperGratuitas($val);
                        break;
                }
            }
            $taxs = $xml->getNodes('cac:TaxTotal', $node);
            foreach ($taxs as $tax) {
                $name = trim($xml->getValue('cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/cbc:Name', $tax));
                $val = floatval($xml->getValue('cbc:TaxAmount', $tax,0));
                switch ($name) {
                    case 'IGV':
                        $det->setMtoIGV($val);
                        break;
                    case 'ISC':
                        $det->setMtoISC($val);
                        break;
                    case 'OTROS':
                        $det->setMtoOtrosTributos($val);
                        break;
                }
            }


            yield $det;
        }
    }
}