<?php

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox;

use Eloquent\Confetti\TransformStream;
use Eloquent\Confetti\TransformStreamInterface;
use Eloquent\Lockbox\Comparator\SlowStringComparator;
use Eloquent\Lockbox\Transform\Factory\DecryptTransformFactory;
use Eloquent\Lockbox\Transform\Factory\KeyTransformFactoryInterface;

/**
 * Decrypts raw data using keys.
 */
class RawDecrypter implements DecrypterInterface
{
    /**
     * Get the static instance of this decrypter.
     *
     * @return DecrypterInterface The static decrypter.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Construct a new raw decrypter.
     *
     * @param KeyTransformFactoryInterface|null $transformFactory The transform factory to use.
     */
    public function __construct(
        KeyTransformFactoryInterface $transformFactory = null
    ) {
        if (null === $transformFactory) {
            $transformFactory = DecryptTransformFactory::instance();
        }

        $this->transformFactory = $transformFactory;
    }

    /**
     * Get the transform factory.
     *
     * @return KeyTransformFactoryInterface The transform factory.
     */
    public function transformFactory()
    {
        return $this->transformFactory;
    }

    /**
     * Decrypt a data packet.
     *
     * @param Key\KeyInterface $key  The key to decrypt with.
     * @param string           $data The data to decrypt.
     *
     * @return string                              The decrypted data.
     * @throws Exception\DecryptionFailedException If the decryption failed.
     */
    public function decrypt(Key\KeyInterface $key, $data)
    {
        $size = strlen($data);
        $hash = hash_hmac(
            'sha' . $key->authenticationSecretBits(),
            substr($data, 0, $size - $key->authenticationSecretBytes()),
            $key->authenticationSecret(),
            true
        );

        if (
            !SlowStringComparator::isEqual(
                substr($data, $size - $key->authenticationSecretBytes()),
                $hash
            )
        ) {
            throw new Exception\DecryptionFailedException($key);
        }

        list($data) = $this->transformFactory()
            ->createTransform($key)
            ->transform($data, $context, true);

        return $data;
    }

    /**
     * Create a new decrypt stream.
     *
     * @param Key\KeyInterface $key The key to decrypt with.
     *
     * @return TransformStreamInterface The newly created decrypt stream.
     */
    public function createDecryptStream(Key\KeyInterface $key)
    {
        return new TransformStream(
            $this->transformFactory()->createTransform($key)
        );
    }

    private static $instance;
    private $transformFactory;
}
