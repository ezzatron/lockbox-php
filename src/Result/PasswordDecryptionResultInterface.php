<?php

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox\Result;

/**
 * The interface implemented by password decryption results.
 */
interface PasswordDecryptionResultInterface extends DecryptionResultInterface
{
    /**
     * Get the number of hash iterations used.
     *
     * @return integer|null The hash iterations, or null if unsuccessful.
     */
    public function iterations();
}