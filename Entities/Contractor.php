<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;

    public function __construct(int $id)
    {
        $this->id   = $id;
        $this->type = self::TYPE_CUSTOMER;
        $this->name = 'Yernar Yerboluly';
    }

    public static function getById(int $id): ?self
    {
        if ($id === 123) {  // Simulates fetching from a database
            return new self(123);
        }

        return null;
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}
