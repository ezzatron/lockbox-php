<?php

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox\Cipher\Parameters;

use Eloquent\Lockbox\Key\KeyInterface;

/**
 * Cipher parameters for encrypting data with a key.
 */
class EncryptParameters implements EncryptParametersInterface
{
    /**
     * Construct a new encrypt parameters instance.
     *
     * @param KeyInterface $key The key to use.
     * @param string|null  $iv  The initialization vector to use, or null to generate one.
     */
    public function __construct(KeyInterface $key, $iv = null)
    {
        $this->key = $key;
        $this->iv = $iv;
    }

    /**
     * Get the key.
     *
     * @return KeyInterface The key.
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Get the initialization vector.
     *
     * @return string|null The initialization vector, or null if none was specified.
     */
    public function iv()
    {
        return $this->iv;
    }

    /**
     * Erase these parameters, removing any sensitive data.
     */
    public function erase()
    {
        $this->key()->erase();

        unset($this->iv);
        $this->iv = null;
    }

    private $key;
    private $iv;
}
