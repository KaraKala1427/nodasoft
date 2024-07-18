<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;

abstract class ReferencesOperation
{

    /**
     * Do operation
     *
     * @return array
     */
    abstract public function doOperation(): array;

    /**
     * Get request
     *
     * @param string $pName Parameter name
     *
     * @throws Exception
     * @return array
     */
    public function getRequest(string $pName): array
    {
        if (false === isset($_REQUEST[$pName])) {
            throw new \RuntimeException('Parameter not found', 404);
        }

        return $_REQUEST[$pName];
    }

}