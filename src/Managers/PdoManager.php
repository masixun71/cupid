<?php
declare(strict_types=1);

namespace Jue\Cupid\Managers;


class PdoManager
{
    private $dsn;

    private $user;

    private $password;

    private $pdo;

    public function __construct($dsn, $user, $password)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;

        $this->pdo = new \PDO($dsn, $user, $password, array(\PDO::ATTR_PERSISTENT => true));
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function connect() {
        $this->pdo = new \PDO($this->dsn, $this->user, $this->password, array(\PDO::ATTR_PERSISTENT => true));
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function getPdo() {
        return $this->pdo;
    }
}