<?php // @codeCoverageIgnoreStart

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox\Bound;

use Eloquent\Lockbox\Cipher\Result\CipherResultInterface;
use Eloquent\Lockbox\Stream\CipherStreamInterface;

/**
 * The interface implemented by bound decrypters.
 */
interface BoundDecrypterInterface
{
    /**
     * Decrypt a data packet.
     *
     * @param string $data The data to decrypt.
     *
     * @return CipherResultInterface The decrypt result.
     */
    public function decrypt($data);

    /**
     * Create a new decrypt stream.
     *
     * @return CipherStreamInterface The newly created decrypt stream.
     */
    public function createDecryptStream();
}
