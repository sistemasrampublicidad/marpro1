<?php

/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 25/01/2018
 * Time: 05:26 PM.
 */

namespace App\CoreFacturalo\Greenter\Ubl;

use Illuminate\Support\Facades\Log;

/**
 * Class SchemaValidator.
 */
class SchemaValidator implements SchemaValidatorInterface
{
    /**
     * @var \Generator
     */
    private $errors;

    /**
     * Get errors list.
     *
     * @return XmlError[]
     */
    public function getErrors()
    {
        return iterator_to_array($this->errors);
    }

    /**
     * @param \DOMDocument $document
     * @param string $xsdPath XSD full path
     *
     * @return bool
     */
    public function validate(\DOMDocument $document, $xsdPath)
    {
        $state = libxml_use_internal_errors(true);

        $result = $document->schemaValidate($xsdPath);

        $this->errors = $this->extractErrors();

        libxml_use_internal_errors($state);

        return $result;
    }

    /**
     * Get errors list.
     *
     * @return \Generator
     */
    public function extractErrors()
    {
        $xmlErrors = libxml_get_errors();
        $errors = $this->mapToErrors($xmlErrors);

        libxml_clear_errors();

        return $errors;
    }

    /**
     * @param \LibXMLError[] $xmlErrors
     * @return \Generator
     */
    private function mapToErrors($xmlErrors)
    {
        foreach ($xmlErrors as $error) {
            $item = new XmlError();
            $item->level = $error->level;
            $item->code = $error->code;
            $item->column = $error->column;
            $item->message = $error->message;
            $item->line = $error->line;
            $exist_text = $this->existText($item->message);
            if (!$exist_text) {
                yield $item;
            }
        }

        // $err = json_encode($xmlErrors, true);
        // Log::info("map to errors: " . $err);
    }

    public function existText($message)
    {
        $exist = false;
        $findme   = 'languageID';
        $pos = strpos($message, $findme);
        if ($pos !== false) {
            $exist =  true;
        } else {
            $exist = false;
        }
        return $exist;
    }
}
