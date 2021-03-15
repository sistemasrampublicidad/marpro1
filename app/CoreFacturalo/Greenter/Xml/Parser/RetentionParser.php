<?php
/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 29/01/2018
 * Time: 01:25 PM
 */

namespace App\CoreFacturalo\Greenter\Xml\Parser;


use App\CoreFacturalo\Greenter\Model\Client\Client;
use App\CoreFacturalo\Greenter\Model\Company\Address;
use App\CoreFacturalo\Greenter\Model\Company\Company;
use App\CoreFacturalo\Greenter\Model\DocumentInterface;
use App\CoreFacturalo\Greenter\Model\Retention\Exchange;
use App\CoreFacturalo\Greenter\Model\Retention\Payment;
use App\CoreFacturalo\Greenter\Model\Retention\Retention;
use App\CoreFacturalo\Greenter\Model\Retention\RetentionDetail;
use App\CoreFacturalo\Greenter\Xml\XmlReader;

class RetentionParser
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

        $idNum = explode('-', $xml->getValue('cbc:ID'));
        $retention = new Retention();
        $retention->setSerie($idNum[0])
            ->setCorrelativo($idNum[1])
            ->setFechaEmision(new \DateTime($xml->getValue('cbc:IssueDate')))
            ->setCompany($this->getCompany())
            ->setProveedor($this->getClient())
            ->setRegimen($xml->getValue('sac:SUNATRetentionSystemCode'))
            ->setTasa(floatval($xml->getValue('sac:SUNATRetentionPercent', $root, 0)))
            ->setObservacion($xml->getValue('cbc:Note'))
            ->setImpRetenido($xml->getValue('cbc:TotalInvoiceAmount', $root, 0))
            ->setImpPagado(floatval($xml->getValue('sac:SUNATTotalPaid', $root, 0)))
            ->setDetails(iterator_to_array($this->getDetails()));

        return $retention;
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
        $node = $xml->getNode('cac:AgentParty',$this->rootNode);

        $cl = new Company();
        $cl->setRuc($xml->getValue('cac:PartyIdentification/cbc:ID', $node))
            ->setNombreComercial($xml->getValue('cac:PartyName/cbc:Name', $node))
            ->setRazonSocial($xml->getValue('cac:PartyLegalEntity/cbc:RegistrationName', $node))
            ->setAddress($this->getAddress($node));

        return $cl;
    }

    private function getClient()
    {
        $xml = $this->reader;
        $node = $xml->getNode('cac:ReceiverParty', $this->rootNode);

        $ident = $xml->getNode('cac:PartyIdentification/cbc:ID', $node);
        $client = new Client();
        $client->setNumDoc($ident->nodeValue)
            ->setTipoDoc($ident->getAttribute('schemeID'))
            ->setRznSocial($xml->getValue('cac:PartyLegalEntity/cbc:RegistrationName', $node))
            ->setAddress($this->getAddress($node));

        return $client;
    }

    private function getAddress($node)
    {
        $xml = $this->reader;

        $address = $xml->getNode('cac:PostalAddress', $node);
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

    private function getDetails()
    {
        $xml = $this->reader;
        $nodes = $xml->getNodes('sac:SUNATRetentionDocumentReference', $this->rootNode);

        foreach ($nodes as $node) {
            $temp = $xml->getNode('cbc:ID', $node);
            $mount = $xml->getNode('cbc:TotalInvoiceAmount', $node);

            $det = new RetentionDetail();
            $det->setTipoDoc($temp->getAttribute('schemeID'))
                ->setNumDoc($temp->nodeValue)
                ->setFechaEmision(new \DateTime($xml->getValue('cbc:IssueDate', $node)))
                ->setImpTotal(floatval($mount->nodeValue))
                ->setMoneda($mount->getAttribute('currencyID'))
                ->setPagos(iterator_to_array($this->getPayments($node)));

            $temp = $xml->getNode('sac:SUNATRetentionInformation', $node);
            if (empty($temp)) {
                $det->setImpRetenido(0.00)
                    ->setImpPagar(0.00)
                    ->setFechaRetencion(new \DateTime());

                yield $det;
                continue;
            }

            $det
                ->setImpRetenido(floatval($xml->getValue('sac:SUNATRetentionAmount', $temp)))
                ->setFechaRetencion(new \DateTime($xml->getValue('sac:SUNATRetentionDate', $temp)))
                ->setImpPagar(floatval($xml->getValue('sac:SUNATNetTotalPaid', $temp)));

            $cambio = $xml->getNode('cac:ExchangeRate', $temp);
            if ($cambio) {
                $exc = new Exchange();
                $exc->setMonedaRef($xml->getValue('cbc:SourceCurrencyCode', $cambio))
                    ->setMonedaObj($xml->getValue('cbc:TargetCurrencyCode', $cambio))
                    ->setFactor(floatval($xml->getValue('cbc:CalculationRate', $cambio, 0)))
                    ->setFecha(new \DateTime($xml->getValue('cbc:Date', $cambio)));
                $det->setTipoCambio($exc);
            }

            yield $det;
        }
    }

    private function getPayments($node)
    {
        $xml = $this->reader;

        $pays = $xml->getNodes('cac:Payment', $node);
        foreach ($pays as $pay) {
            $temp = $xml->getNode('cbc:PaidAmount', $pay);
            $payment = new Payment();
            $payment->setMoneda($temp->getAttribute('currencyID'))
                ->setImporte(floatval($temp->nodeValue))
                ->setFecha(new \DateTime($xml->getValue('cbc:PaidDate')));

            yield $payment;
        }
    }
}