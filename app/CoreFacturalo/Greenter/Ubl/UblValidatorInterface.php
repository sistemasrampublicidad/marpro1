<?php
/**
 * Created by PhpStorm.
 * User: Soporte
 * Date: 25/10/2018
 * Time: 12:42
 */

namespace App\CoreFacturalo\Greenter\Ubl;

/**
 * Interface UblValidatorInterface
 */
interface UblValidatorInterface
{
    /**
     * Get last message error or warning.
     *
     * @return string
     */
    public function getError();

    /**
     * @param \DOMDocument|string $value Xml content or DomDocument
     *
     * @return bool
     */
    public function isValid($value);
}