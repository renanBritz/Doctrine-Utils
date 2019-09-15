<?php

namespace RenanBritz\DoctrineUtils\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="person_roles")
 */
class PersonRole
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
     * @ORM\OneToOne(targetEntity="Person", mappedBy="role")
     *
     * @var Person
     */
    protected $person;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     */
    protected $name;

    /**
     * @ORM\ManyToMany(targetEntity="Permission")
     * @ORM\JoinTable(name="person_roles_permissions",
     *      joinColumns={@ORM\JoinColumn(name="role_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="permission_id", referencedColumnName="id", unique=true)}
     * )
     *
     * @var Collection|null
     */
    protected $permissions;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return Collection|null
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param Collection|null $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }
}