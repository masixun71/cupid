<?php
declare(strict_types=1);

namespace Jue\Cupid;


class SqlEvent
{
    const INSERT = 1;
    const UPDATE = 2;

    private $type;

    private $srcColumn;

    private $callbackUrl;

    private $retriesTime;


    public function __construct($type,array $column,string $callbackUrl)
    {
        $this->type = $type;
        $this->srcColumn = $column;
        $this->callbackUrl = $callbackUrl;
        $this->retriesTime = 0;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getSrcColumn(): array
    {
        return $this->srcColumn;
    }

    /**
     * @return string
     */
    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }


    public function toJson() {

        return json_encode([
            'type' => $this->type,
            'srcColumn' => $this->srcColumn,
            'callbackUrl' => $this->callbackUrl
        ]);

    }

    public function toArray() {
        return [
            'type' => $this->type,
            'srcColumn' => $this->srcColumn,
            'callbackUrl' => $this->callbackUrl,
            'retriesTime'=> $this->retriesTime
        ];
    }


    public function incrRetriesTime() {
        $this->retriesTime++;
    }

    /**
     * @return mixed
     */
    public function getRetriesTime()
    {
        return $this->retriesTime;
    }



}