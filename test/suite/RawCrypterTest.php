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

use Eloquent\Liberator\Liberator;
use Eloquent\Lockbox\Cipher\Parameters\EncryptParameters;
use Eloquent\Lockbox\Key\Key;
use PHPUnit_Framework_TestCase;

/**
 * @covers \Eloquent\Lockbox\RawCrypter
 * @covers \Eloquent\Lockbox\AbstractCrypter
 * @covers \Eloquent\Lockbox\RawEncrypter
 * @covers \Eloquent\Lockbox\AbstractRawEncrypter
 * @covers \Eloquent\Lockbox\RawDecrypter
 * @covers \Eloquent\Lockbox\AbstractRawDecrypter
 */
class RawCrypterTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->encrypter = new RawEncrypter;
        $this->decrypter = new RawDecrypter;
        $this->crypter = new RawCrypter($this->encrypter, $this->decrypter);

        $this->decryptParameters = new Key('1234567890123456', '1234567890123456789012345678');
        $this->version = $this->type = chr(1);
        $this->iv = '1234567890123456';
    }

    public function testConstructor()
    {
        $this->assertSame($this->encrypter, $this->crypter->encrypter());
        $this->assertSame($this->decrypter, $this->crypter->decrypter());
    }

    public function testConstructorDefaults()
    {
        $this->crypter = new RawCrypter;

        $this->assertSame(RawEncrypter::instance(), $this->crypter->encrypter());
        $this->assertSame(RawDecrypter::instance(), $this->crypter->decrypter());
    }

    public function encryptionData()
    {
        $data = array();
        foreach (array(16, 24, 32) as $encryptSecretBytes) {
            foreach (array(28, 32, 48, 64) as $authSecretBytes) {
                foreach (array(0, 1, 1024) as $dataSize) {
                    $label = sprintf(
                        '%d byte(s), %dbit encryption, %dbit authentication',
                        $dataSize,
                        $encryptSecretBytes * 8,
                        $authSecretBytes * 8
                    );

                    $data[$label] = array(
                        $dataSize,
                        str_pad('', $encryptSecretBytes, '1234567890'),
                        str_pad('', $authSecretBytes, '1234567890'),
                    );
                }
            }
        }

        return $data;
    }

    /**
     * @dataProvider encryptionData
     */
    public function testEncryptDecrypt($dataSize, $encryptSecret, $authSecret)
    {
        $this->decrypter = new RawDecrypter;
        $this->crypter = new RawCrypter($this->encrypter, $this->decrypter);
        $data = str_repeat('A', $dataSize);
        $this->decryptParameters = new Key($encryptSecret, $authSecret);
        $this->encryptParameters = new EncryptParameters($this->decryptParameters, $this->iv);
        $encrypted = $this->crypter->encrypt($this->encryptParameters, $data);
        $decryptionResult = $this->crypter->decrypt($this->decryptParameters, $encrypted);

        $this->assertSame('SUCCESS',$decryptionResult->type()->key());
        $this->assertSame($data, $decryptionResult->data());
    }

    /**
     * @dataProvider encryptionData
     */
    public function testEncryptDecryptStreaming($dataSize, $encryptSecret, $authSecret)
    {
        $this->decrypter = new RawDecrypter;
        $this->crypter = new RawCrypter($this->encrypter, $this->decrypter);
        $this->decryptParameters = new Key($encryptSecret, $authSecret);
        $this->encryptParameters = new EncryptParameters($this->decryptParameters, $this->iv);
        $encryptStream = $this->crypter->createEncryptStream($this->encryptParameters);
        $decryptStream = $this->crypter->createDecryptStream($this->decryptParameters);
        $encryptStream->pipe($decryptStream);
        $decrypted = '';
        $decryptStream->on(
            'data',
            function ($data, $stream) use (&$decrypted) {
                $decrypted .= $data;
            }
        );
        $data = '';
        for ($i = 0; $i < $dataSize; $i ++) {
            $data .= 'A';
            $encryptStream->write('A');
        }
        $encryptStream->end();

        $this->assertSame($data, $decrypted);
    }

    public function testDecryptFailureEmptyVersion()
    {
        $data = '';
        $data .= $this->authenticate($data);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_SIZE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureUnsupportedVersion()
    {
        $header = chr(111) . str_pad('', 17, '1234567890');
        $block = str_pad('', 16, '1234567890');
        $data = $header . $block . $this->authenticate($block, 2) . $this->authenticate($header . $block);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('UNSUPPORTED_VERSION', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureEmptyType()
    {
        $data = $this->version;
        $data .= $this->authenticate($data);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_SIZE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptUnsupportedType()
    {
        $header = $this->version . chr(111) . str_pad('', 16, '1234567890');
        $block = str_pad('', 16, '1234567890');
        $data = $header . $block . $this->authenticate($block, 2) . $this->authenticate($header . $block);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('UNSUPPORTED_TYPE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureEmptyIv()
    {
        $data = $this->version . $this->type;
        $data .= $this->authenticate($data);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_SIZE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureInvalidSize()
    {
        $data = $this->version . $this->type . $this->iv . '789012345678901234567890123';
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_SIZE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureEmptyCiphertext()
    {
        $data = $this->version . $this->type . $this->iv . '7890123456789012345678901234';
        $data .= $this->authenticate($data);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_SIZE', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureBadBlockMac()
    {
        $header = $this->version . $this->type . $this->iv;
        $block = 'foobarbazquxdoom';
        $data = $header . $block . '12' . $this->authenticate($header . $block);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_MAC', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureBadMac()
    {
        $header = $this->version . $this->type . $this->iv;
        $block = 'foobarbazquxdoom';
        $data = $header . $block . $this->authenticate($block, 2) . '1234567890123456789012345678';
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_MAC', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureBadAesData()
    {
        $header = $this->version . $this->type . $this->iv;
        $block = 'foobarbazquxdoom';
        $data = $header . $block . $this->authenticate($block, 2) . $this->authenticate($header . $block);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_PADDING', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testDecryptFailureBadPadding()
    {
        $header = $this->version . $this->type . $this->iv;
        $block = mcrypt_encrypt(
            MCRYPT_RIJNDAEL_128,
            $this->decryptParameters->encryptSecret(),
            'foobar',
            MCRYPT_MODE_CBC,
            $this->iv
        );
        $data = $header . $block . $this->authenticate($block, 2) . $this->authenticate($header . $block);
        $result = $this->crypter->decrypt($this->decryptParameters, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('INVALID_PADDING', $result->type()->key());
        $this->assertNull($result->data());
    }

    public function testInstance()
    {
        $className = get_class($this->crypter);
        Liberator::liberateClass($className)->instance = null;
        $instance = $className::instance();

        $this->assertInstanceOf($className, $instance);
        $this->assertSame($instance, $className::instance());
    }

    protected function pad($data)
    {
        $padSize = intval(16 - (strlen($data) % 16));

        return $data . str_repeat(chr($padSize), $padSize);
    }

    protected function authenticate($data, $size = null)
    {
        $mac = hash_hmac(
            'sha' . $this->decryptParameters->authSecretBits(),
            $data,
            $this->decryptParameters->authSecret(),
            true
        );

        if (null !== $size) {
            $mac = substr($mac, 0, $size);
        }

        return $mac;
    }
}
