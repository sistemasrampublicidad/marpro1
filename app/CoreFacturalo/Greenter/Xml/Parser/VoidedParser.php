<?php
/**
 * Created by PhpStorm.
 * User: Giansalex
 * Date: 08/11/2017
 * Time: 21:29
 */

namespace App\CoreFacturalo\Greenter\Xml\Parser;

use App\CoreFacturalo\Greenter\Model\Company\Company;
use App\CoreFacturalo\Greenter\Model\DocumentInterface;
use App\CoreFacturalo\Greenter\Model\Voided\Reversion;
use App\CoreFacturalo\Greenter\Model\Voided\Voided;
use App\CoreFacturalo\Greenter\Model\Voided\VoidedDetail;
use App\CoreFacturalo\Greenter\Parser\DocumentParserInterface;
use App\CoreFacturalo\Greenter\Xml\XmlReader;

/**
 * Class VoidedParser
 * @package Greenter\Xml\Parser
 */
class VoidedParser implements DocumentParserInterface
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
        $this->load($value);
        $xml = $this->reader;

        $root = $this->rootNode;
        $id = explode('-', $xml->getValue('cbc:ID', $root));

        $voided = $id[0] == 'RA' ? new Voided() : new Reversion();
        $voided->setCorrelativo($id[2])
            ->setFecGeneracion(new \DateTime($xml->getValue('cbc:ReferenceDate', $root)))
            ->setFecComunicacion(new \DateTime($xml->getValue('cbc:IssueDate', $root)))
            ->setCompany($this->getCompany())
            ->setDetails(iterator_to_array($this->getDetails()));

        return $voided;
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
        $nodes = $xml->getNodes('sac:VoidedDocumentsLine', $this->rootNode);

        foreach ($nodes as $node) {
            $det = new VoidedDetail();
            $det->setTipoDoc($xml->getValue('cbc:DocumentTypeCode', $node))
                ->setSerie($xml->getValue('sac:DocumentSerialID', $node))
                ->setCorrelativo($xml->getValue('sac:DocumentNumberID', $node))
                ->setDesMotivoBaja($xml->getValue('sac:VoidReasonDescription', $node));

            yield $det;
        }
    }
}