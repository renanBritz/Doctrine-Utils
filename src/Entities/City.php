<?php

namespace RenanBritz\DoctrineUtils\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="cities")
 */
class City
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
     * @ORM\ManyToOne(targetEntity="State", inversedBy="cities")
     * @ORM\JoinColumn(name="state_id", referencedColumnName="id")
     *
     * @var State
     */
    protected $state;

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return State
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param State $state
     */
    public function setState($state)
    {
        $this->state = $state;
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
}