<?php // @codeCoverageIgnoreStart

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox\Padding;

/**
 * The interface implemented by padders.
 */
interface PadderInterface
{
    /**
     * Pad a data packet to a specific block size.
     *
     * @param string $data The data to pad.
     *
     * @return string The padded data.
     */
    public function pad($data);
}
