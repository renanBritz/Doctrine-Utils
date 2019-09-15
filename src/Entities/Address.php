<?php

namespace RenanBritz\DoctrineUtils\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="addresses")
 */
class Address
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
     * @ORM\Column(type="string")
     *
     * @var string
     */
    protected $street;

    /**
     * @ORM\Column(type="string", length=10)
     *
     * @var string
     */
    protected $number;

    /**
     * @ORM\Column(type="smallint")
     *
     * @var integer
     */
    protected $type;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     */
    protected $zipcode;

    /**
     * @ORM\ManyToOne(targetEntity="City")
     * @ORM\JoinColumn(name="city_id", referencedColumnName="id")
     *
     * @var City
     */
    protected $city;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @param string $street
     */
    public function setStreet($street)
    {
        $this->street = $street;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param string $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
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
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @param string $zipcode
     */
    public function setZipcode($zipcode)
    {
        $this->zipcode = $zipcode;
    }

    /**
     * @return City
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param City $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }
}