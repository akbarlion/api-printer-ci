<?php

namespace Firebase\JWT;

class Key
{
    /** @var string */
    private $keyMaterial;
    
    /** @var string */
    private $algorithm;

    /**
     * @param string $keyMaterial
     * @param string $algorithm
     */
    public function __construct($keyMaterial, $algorithm)
    {
        $this->keyMaterial = $keyMaterial;
        $this->algorithm = $algorithm;
    }

    /**
     * @return string
     */
    public function getKeyMaterial()
    {
        return $this->keyMaterial;
    }

    /**
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->algorithm;
    }
}