# Doctrine-Utils
## Introduction
This project aims to provide a simple interface to query and persist data with Doctrine ORM. It will allow you to focus on domain logic rather than persistence logic, resulting in more readable and dry controllers.

### Persistence
By following a few simple conventions you'll be able to reduce the amount of code needed to store and update data to your database when using Doctrine ORM. See examples below.

### Querying (WIP)
Using IGDB's [Apicalypse](https://apicalypse.io/) syntax (with a few improvements) you'll be able to query your database using a simpler and more readable syntax. It'll significantly reduce the amount of DQL and repository code needed while providing more flexibility to your front-end.

## Examples
### Persisting Data (Create and Update Person)

```php
<?php

use App\Entities\Person;
use RenanBritz\DoctrineUtils\Persistence;

class UserController extends AbstractController
{
  private $em;
  
  private $persistence;
  
  public function __construct()
  {
    $this->em = $this->getDoctrine()->getEntityManager();
    $this->persistence = new Persistence($this->em);
  }

  /** Create a new user. */
  public function store(Request $request)
  {
    $data = $request->all();
    // Validation logic...
    
    $this->persistence->persist(Person::class, $data);
    
    // Domain/Business logic...
  }
  
  /** Update existing user. */
  public function update(Request $request, int $userId)
  {
    $user = $this->em->getRepository(Person::class)->findOneById($userId);
    
    if (!$user) {
      // Return 404 error.
    }
    
    $data = $request->all();
    // Validation logic...
    
    $this->persistence->persist($user, $data);
    
    // Domain/Business logic...
  }
}
```

#### Person POST Data Example
```javascript
// Person
{
    "name": "John Doe",
    "gender": 1,
    "contacts": [
      {
        "value": "john@mail.com",
        "type": 1,
        "method": 1
      },
      {
        "value": "99999999",
        "type": 1,
        "method": 2
      }
    ],
    "role": {
      "name": "Admin",
      "permissions": [
        {
          "title": "UPDATE_PERSON"
        }
      ]
    },
    "addresses": [
      {
        "street": "Street Name",
        "zipcode": "999999999",
        "number": "1234",
        "type": 1,
        "city": {
          "id": 1
        }
      }
    ]
}
```

#### Response Data Example
```javascript
// Person
{
  "id": 2,
  "role": {
    "id": 3,
    "permissions": [
      2
    ]
  },
  "addresses": [
    2
  ],
  "contacts": [
    11,
    13
  ]
}
```

#### How it works
The persistence class will recursively persist data to the entity and its associations using the Class Metadata definitions.

#### Conventions
* The entity identifier must be named `id`.
* All entity fields and associations must have getter and setter methods formatted in `camelCase`. e.g: `getName()`. Setter method for `id` is not required.
* When updating Collection Associations (One to Many or Many to Many), you must provide at least the id of elements that you want to keep. Otherwise they will be deleted.
