<?php


namespace App\Exceptions\Domain;

use RuntimeException;

abstract class RegraDeNegocioException extends RuntimeException
{
    abstract public function codigo(): string;
}