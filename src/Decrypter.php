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

use Eloquent\Confetti\TransformStreamInterface;
use Eloquent\Endec\Base64\Base64Url;
use Eloquent\Endec\DecoderInterface;
use Eloquent\Endec\Exception\EncodingExceptionInterface;
use Eloquent\Lockbox\Cipher\Factory\CipherFactoryInterface;
use Eloquent\Lockbox\Cipher\Factory\DecryptCipherFactory;
use Eloquent\Lockbox\Cipher\Result\CipherResult;
use Eloquent\Lockbox\Cipher\Result\CipherResultInterface;
use Eloquent\Lockbox\Cipher\Result\CipherResultType;
use Eloquent\Lockbox\Stream\CipherStream;
use Eloquent\Lockbox\Stream\CompositePreCipherStream;

/**
 * Decrypts encoded data using keys.
 */
class Decrypter implements DecrypterInterface
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
     * Construct a new decrypter.
     *
     * @param CipherFactoryInterface|null $cipherFactory The cipher factory to use.
     * @param DecoderInterface|null       $decoder       The decoder to use.
     */
    public function __construct(
        CipherFactoryInterface $cipherFactory = null,
        DecoderInterface $decoder = null
    ) {
        if (null === $cipherFactory) {
            $cipherFactory = DecryptCipherFactory::instance();
        }
        if (null === $decoder) {
            $decoder = Base64Url::instance();
        }

        $this->cipherFactory = $cipherFactory;
        $this->decoder = $decoder;
    }

    /**
     * Get the cipher factory.
     *
     * @return CipherFactoryInterface The cipher factory.
     */
    public function cipherFactory()
    {
        return $this->cipherFactory;
    }

    /**
     * Get the decoder.
     *
     * @return DecoderInterface The decoder.
     */
    public function decoder()
    {
        return $this->decoder;
    }

    /**
     * Decrypt a data packet.
     *
     * @param Key\KeyInterface $key  The key to decrypt with.
     * @param string           $data The data to decrypt.
     *
     * @return CipherResultInterface The decryption result.
     */
    public function decrypt(Key\KeyInterface $key, $data)
    {
        try {
            $data = $this->decoder()->decode($data);
        } catch (EncodingExceptionInterface $e) {
            return new CipherResult(CipherResultType::INVALID_ENCODING());
        }

        $cipher = $this->cipherFactory()->createCipher();
        $cipher->initialize($key);

        $data = $cipher->finalize($data);

        $result = $cipher->result();
        if ($result->isSuccessful()) {
            $result->setData($data);
        }

        return $result;
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
        $decodeStream = $this->decoder()->createDecodeStream();

        $cipher = $this->cipherFactory()->createCipher();
        $cipher->initialize($key);
        $cipherStream = new CipherStream($cipher);

        $decodeStream->pipe($cipherStream);

        return new CompositePreCipherStream($cipherStream, $decodeStream);
    }

    private static $instance;
    private $cipherFactory;
    private $decoder;
}
