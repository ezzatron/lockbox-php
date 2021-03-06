<?php

/*
 * This file is part of the Lockbox package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Lockbox\Key\Persistence;

use Eloquent\Endec\Base64\Base64Url;
use Eloquent\Endec\EncoderInterface;
use Eloquent\Lockbox\EncrypterInterface;
use Eloquent\Lockbox\Key\KeyInterface;
use Eloquent\Lockbox\Key\Persistence\Exception\KeyWriteException;
use Eloquent\Lockbox\Password\Cipher\Parameters\PasswordEncryptParametersInterface;
use Eloquent\Lockbox\Password\Password;
use Eloquent\Lockbox\Password\PasswordEncrypter;
use Icecave\Isolator\Isolator;

/**
 * Writes encryption keys to files and streams.
 */
class KeyWriter implements EncryptedKeyWriterInterface
{
    /**
     * Get the static instance of this writer.
     *
     * @return EncryptedKeyWriterInterface The static writer.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Construct a new key reader.
     *
     * @param EncrypterInterface    $encrypter The encrypter to use.
     * @param EncoderInterface|null $encoder   The encoder to use.
     * @param Isolator|null         $isolator  The isolator to use.
     */
    public function __construct(
        EncrypterInterface $encrypter = null,
        EncoderInterface $encoder = null,
        Isolator $isolator = null
    ) {
        if (null === $encrypter) {
            $encrypter = PasswordEncrypter::instance();
        }
        if (null === $encoder) {
            $encoder = Base64Url::instance();
        }

        $this->encrypter = $encrypter;
        $this->encoder = $encoder;
        $this->isolator = Isolator::get($isolator);
    }

    /**
     * Get the encrypter.
     *
     * @return EncrypterInterface The encrypter.
     */
    public function encrypter()
    {
        return $this->encrypter;
    }

    /**
     * Get the encoder.
     *
     * @return EncoderInterface The encoder.
     */
    public function encoder()
    {
        return $this->encoder;
    }

    /**
     * Write a key to the supplied path.
     *
     * @param KeyInterface $key  The key.
     * @param string       $path The path to write to.
     *
     * @throws KeyWriteException If the key cannot be written.
     */
    public function writeFile(KeyInterface $key, $path)
    {
        $keyString = $this->writeString($key);

        if (@!$this->isolator()->file_put_contents($path, $keyString)) {
            throw new KeyWriteException($path);
        }
    }

    /**
     * Write a key, encrypted with a password, to the supplied path.
     *
     * @param KeyInterface                       $key        The key.
     * @param PasswordEncryptParametersInterface $parameters The encrypt parameters.
     * @param string                             $path       The path to write to.
     *
     * @throws KeyWriteException If the key cannot be written.
     */
    public function writeFileWithPassword(
        KeyInterface $key,
        PasswordEncryptParametersInterface $parameters,
        $path
    ) {
        $keyString = $this->writeStringWithPassword($key, $parameters);

        if (@!$this->isolator()->file_put_contents($path, $keyString)) {
            throw new KeyWriteException($path);
        }
    }

    /**
     * Write a key to the supplied stream.
     *
     * @param KeyInterface $key    The key.
     * @param stream       $stream The stream to write to.
     * @param string|null  $path   The path, if known.
     *
     * @throws KeyWriteException If the key cannot be written.
     */
    public function writeStream(KeyInterface $key, $stream, $path = null)
    {
        $result = @fwrite($stream, $this->writeString($key));
        if (!$result) {
            throw new KeyWriteException($path);
        }
    }

    /**
     * Write a key, encrypted with a password, to the supplied stream.
     *
     * @param KeyInterface                       $key        The key.
     * @param PasswordEncryptParametersInterface $parameters The encrypt parameters.
     * @param stream                             $stream     The stream to write to.
     * @param string|null                        $path       The path, if known.
     *
     * @throws KeyWriteException If the key cannot be written.
     */
    public function writeStreamWithPassword(
        KeyInterface $key,
        PasswordEncryptParametersInterface $parameters,
        $stream,
        $path = null
    ) {
        $result =
            @fwrite($stream, $this->writeStringWithPassword($key, $parameters));
        if (!$result) {
            throw new KeyWriteException($path);
        }
    }

    /**
     * Write a key to a string.
     *
     * @param KeyInterface $key The key.
     *
     * @return string The key string.
     */
    public function writeString(KeyInterface $key)
    {
        $data = "{\n";

        if (null !== $key->name()) {
            $data .= '    "name": ' . json_encode($key->name()) . ",\n";
        }
        if (null !== $key->description()) {
            $data .= '    "description": ' . json_encode($key->description()) .
                ",\n";
        }

        $data .= "    \"type\": \"lockbox-key\",\n    \"version\": 1,\n";

        $data .= '    "encryptSecret": ' .
            json_encode($this->encoder()->encode($key->encryptSecret())) .
            ",\n";
        $data .= '    "authSecret": ' .
            json_encode($this->encoder()->encode($key->authSecret())) . "\n";

        return $data . "}\n";
    }

    /**
     * Write a key, encrypted with a password, to a string.
     *
     * @param KeyInterface                       $key        The key.
     * @param PasswordEncryptParametersInterface $parameters The encrypt parameters.
     *
     * @return string The key string.
     */
    public function writeStringWithPassword(
        KeyInterface $key,
        PasswordEncryptParametersInterface $parameters
    ) {
        return chunk_split(
            $this->encrypter()
                ->encrypt($parameters, $this->writeString($key)),
            64,
            "\n"
        );
    }

    /**
     * Get the isolator.
     *
     * @return Isolator The isolator.
     */
    protected function isolator()
    {
        return $this->isolator;
    }

    private static $instance;
    private $encrypter;
    private $encoder;
    private $isolator;
}
