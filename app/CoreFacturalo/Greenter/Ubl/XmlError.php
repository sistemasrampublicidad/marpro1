<?php
/**
 * Created by PhpStorm.
 * User: Soporte
 * Date: 25/10/2018
 * Time: 15:45
 */

namespace App\CoreFacturalo\Greenter\Ubl;

/**
 * Class XmlError
 */
class XmlError
{
    /**
     * @var int
     */
    public $level;
    /**
     * @var int
     */
    public $code;
    /**
     * @var int
     */
    public $column;
    /**
     * @var string
     */
    public $message;
    /**
     * @var string
     */
    public $file;
    /**
     * @var int
     */
    public $line;

    public function __toString()
    {
        return "Code: {$this->code}, Line: {$this->line}, Column: {$this->column}, Message: {$this->message}";
    }
}