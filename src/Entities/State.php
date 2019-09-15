<?php

namespace RenanBritz\DoctrineUtils\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="states")
 */
class State
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
    protected $name;

    /**
     * @ORM\Column(name="ibge_code", type="string")
     * 
     * @var string
     */
    protected $ibgeCode;

    /**
     * @ORM\OneToMany(targetEntity="City", mappedBy="state")
     *
     * @var Collection
     */
    protected $cities;

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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getIbgeCode()
    {
        return $this->ibgeCode;
    }

    /**
     * @param string $ibgeCode
     */
    public function setIbgeCode($ibgeCode)
    {
        $this->ibgeCode = $ibgeCode;
    }

    /**
     * @return Collection
     */
    public function getCities()
    {
        return $this->cities;
    }

    /**
     * @param Collection $cities
     */
    public function setCities($cities)
    {
        $this->cities = $cities;
    }
}