<?php

namespace RenanBritz\DoctrineUtils\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="contacts")
 */
class Contact
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="contacts")
     * @ORM\JoinColumn(name="person_id", referencedColumnName="id")
     *
     * @var Person
     */
    protected $person;

    /**
     * @ORM\Column(type="smallint")
     *
     * @var integer
     */
    protected $type;

    /**
     * @ORM\Column(type="smallint")
     *
     * @var integer
     */
    protected $method;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     */
    protected $value;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Person
     */
    public function getPerson()
    {
        return $this->person;
    }

    /**
     * @param Person $person
     */
    public function setPerson($person)
    {
        $this->person = $person;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param int $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}