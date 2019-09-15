<?php

namespace RenanBritz\DoctrineUtils\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity()
 * @ORM\Table(name="people")
 */
class Person
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
     * @ORM\OneToOne(targetEntity="PersonRole", inversedBy="person", orphanRemoval=true)
     *
     * @var PersonRole
     */
    protected $role;

    /**
     * @ORM\Column(type="date", nullable=true)
     *
     * @var DateTime
     */
    protected $birthdate;

    /**
     * @ORM\Column(type="smallint")
     *
     * @var integer
     */
    protected $gender;

    /**
     * @ORM\ManyToMany(targetEntity="Address", orphanRemoval=true)
     * @ORM\JoinTable(name="people_addresses",
     *      joinColumns={@ORM\JoinColumn(name="person_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="address_id", referencedColumnName="id", unique=true)}
     * )
     *
     * @var Collection|null
     */
    protected $addresses;

    /**
     * @ORM\OneToMany(targetEntity="Contact", mappedBy="person")
     *
     * @var Collection|null
     */
    protected $contacts;

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
     * @return PersonRole
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param PersonRole $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * @return DateTime
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }

    /**
     * @param DateTime $birthdate
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;
    }

    /**
     * @return int
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * @param int $gender
     */
    public function setGender($gender)
    {
        $this->gender = $gender;
    }

    /**
     * @return Collection|null
     */
    public function getAddresses()
    {
        return $this->addresses;
    }

    /**
     * @param Collection|null $addresses
     */
    public function setAddresses($addresses)
    {
        $this->addresses = $addresses;
    }

    /**
     * @return Collection|null
     */
    public function getContacts()
    {
        return $this->contacts;
    }

    /**
     * @param Collection|null $contacts
     */
    public function setContacts($contacts)
    {
        $this->contacts = $contacts;
    }
}