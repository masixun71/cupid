<?php
declare(strict_types=1);

namespace Jue\Cupid\Managers;


class IdManager
{
    private $currentId;

    private $maxId;

    /**
     * @return mixed
     */
    public function getCurrentId()
    {
        return $this->currentId;
    }

    /**
     * @param mixed $currentId
     * @return $this
     */
    public function setCurrentId($currentId)
    {
        $this->currentId = $currentId;
        return $this;
    }

    public function incrCurrentId() {
        $this->currentId++;
    }


    /**
     * @return mixed
     */
    public function getMaxId()
    {
        return $this->maxId;
    }

    /**
     * @param mixed $maxId
     * @return $this
     */
    public function setMaxId($maxId)
    {
        $this->maxId = $maxId;
        return $this;
    }


    public function hasNewId() {
        return $this->currentId <= $this->maxId;
    }

}