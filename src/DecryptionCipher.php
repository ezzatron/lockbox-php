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

use Eloquent\Endec\Base64\Base64Url;
use Eloquent\Endec\DecoderInterface;
use Eloquent\Endec\Transform\Exception\TransformExceptionInterface;

/**
 * The standard Lockbox decryption cipher.
 */
class DecryptionCipher implements DecryptionCipherInterface
{
    /**
     * Get the static instance of this cipher.
     *
     * @return DecryptionCipherInterface The static cipher.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Construct a new decryption cipher.
     *
     * @param DecoderInterface|null $base64UrlDecoder The base64url encoder to use.
     */
    public function __construct(DecoderInterface $base64UrlDecoder = null)
    {
        if (null === $base64UrlDecoder) {
            $base64UrlDecoder = Base64Url::instance();
        }

        $this->base64UrlDecoder = $base64UrlDecoder;
    }

    /**
     * Get the base64url encoder.
     *
     * @return DecoderInterface The base64url encoder.
     */
    public function base64UrlDecoder()
    {
        return $this->base64UrlDecoder;
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
        try {
            $data = $this->base64UrlDecoder()->decode($data);
        } catch (TransformExceptionInterface $e) {
            throw new Exception\DecryptionFailedException($key, $e);
        }

        $length = strlen($data);
        if ($length < 48) {
            throw new Exception\DecryptionFailedException($key);
        }

        $iv = substr($data, 0, 16);
        $authenticationCode = substr($data, $length - 32);
        $data = substr($data, 16, $length - 48);

        if (!$data) {
            throw new Exception\DecryptionFailedException($key);
        }

        if (
            $this->authenticationCode($key, $iv . $data) !==
            $authenticationCode
        ) {
            throw new Exception\DecryptionFailedException($key);
        }

        return $this->decryptAes($key, $iv, $data);
    }

    /**
     * Create a message authentication code for the supplied ciphertext using
     * HMAC-SHA-256.
     *
     * @link https://tools.ietf.org/html/rfc6234
     *
     * @param KeyInterface $key        The key to authenticate with.
     * @param string       $ciphertext The ciphertext.
     *
     * @return string The message authentication code.
     */
    protected function authenticationCode(Key\KeyInterface $key, $ciphertext)
    {
        return hash_hmac(
            'sha256',
            $ciphertext,
            $key->authenticationSecret(),
            true
        );
    }

    /**
     * Decrypt some data with AES and PKCS #7 padding.
     *
     * @param Key\KeyInterface $key  The key to decrypt with.
     * @param string           $iv   The initialization vector to use.
     * @param string           $data The data to decrypt.
     *
     * @return string                              The decrypted data.
     * @throws Exception\DecryptionFailedException If the decryption failed.
     */
    protected function decryptAes(Key\KeyInterface $key, $iv, $data)
    {
        $data = mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $key->encryptionSecret(),
            $data,
            MCRYPT_MODE_CBC,
            $iv
        );

        try {
            $data = $this->unpad($data);
        } catch (Exception\InvalidPaddingException $e) {
            throw new Exception\DecryptionFailedException($key, $e);
        }

        return $data;
    }

    /**
     * Remove PKCS #7 (RFC 2315) padding from a string.
     *
     * @link http://tools.ietf.org/html/rfc2315
     *
     * @param string $data The padded data.
     *
     * @return string                            The data with padding removed.
     * @throws Exception\InvalidPaddingException If the padding is invalid.
     */
    protected function unpad($data)
    {
        $padSize = ord(substr($data, -1));
        $padding = substr($data, -$padSize);
        if (str_repeat(chr($padSize), $padSize) !== $padding) {
            throw new Exception\InvalidPaddingException;
        }

        return substr($data, 0, -$padSize);
    }

    private static $instance;
    private $base64UrlDecoder;
}
